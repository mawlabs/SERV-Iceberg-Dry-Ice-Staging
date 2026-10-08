<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerPricing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\RecurringOrder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Imports customers, per-customer pricing, account-holder orders and recurring
 * schedules from the live (legacy PHP) database into this system.
 *
 * Reads only, via the `old_mysql` connection. Writes happen in ONE transaction
 * (--dry-run rolls it back). Idempotent: rows already carrying a legacy_id are
 * left untouched. Public credit-card orders (legacy cc_orders) are NOT imported.
 *
 * See Docs/LEGACY_DATA_MIGRATION_PLAN.md for the mapping rules and rationale.
 */
class ImportLegacyData extends Command
{
    protected $signature = 'legacy:import
        {--since=2025-01-01 : Import deliveries dated on/after this day (UTC calendar date)}
        {--cutover= : Cutover day, default today. Earlier = history (completed), later = open}
        {--billable-from= : Orders dated before this day are locked as paid so the invoice generator skips them. Default: the cutover day}
        {--default-supplier= : supply_location id for customers whose legacy supply_location is 0/unset (default: leave unset and flag)}
        {--map-block5=block : Product for legacy Block 5 lb (block|pellets)}
        {--map-rice=pellets : Product for legacy Rice (block|pellets)}
        {--dry-run : Do everything, then roll back}';

    protected $description = 'Import customers, pricing, orders and recurring schedules from the live legacy database';

    /** Legacy customer ids that are never real customers: 0, 1 (maintenance bot), 2 (copied card orders). Admins are added from login.admin. */
    private array $excludedLegacyCustomers = [0, 1, 2];

    private Carbon $since;
    private Carbon $cutover;
    private Carbon $billableFrom;
    private ?int $defaultSupplier = null;

    private ?Product $pellets = null;
    private ?Product $block = null;

    /** legacy customerID => Customer */
    private array $customers = [];
    /** legacy customerID => CustomerPricing|null */
    private array $pricing = [];
    /** legacy parent orderID => Order */
    private array $parents = [];
    /** every legacy orders row, keyed by orderID */
    private $legacyOrders;

    private array $flags = [];
    private array $stats = [
        'customers_created' => 0, 'customers_adopted' => 0, 'customers_existing' => 0,
        'pricing_created' => 0,
        'orders_created' => 0, 'orders_existing' => 0,
        'parents_created' => 0,
        'instances_created' => 0, 'instances_standalone' => 0,
        'skipped' => 0,
    ];
    /** [customerLegacyId][Y-m] => [legacy_orders, new_orders, legacy_lbs, new_lbs] */
    private array $recon = [];

    public function handle(): int
    {
        $this->since = Carbon::parse($this->option('since'), 'UTC')->startOfDay();
        $this->cutover = ($this->option('cutover') ? Carbon::parse($this->option('cutover'), 'UTC') : Carbon::now('UTC'))->startOfDay();
        $this->billableFrom = ($this->option('billable-from') ? Carbon::parse($this->option('billable-from'), 'UTC') : $this->cutover->copy())->startOfDay();
        $this->defaultSupplier = $this->option('default-supplier') !== null ? (int) $this->option('default-supplier') : null;
        $dry = (bool) $this->option('dry-run');

        $this->pellets = Product::where('product_name', 'like', '%pellet%')->first() ?? Product::find(1);
        $this->block = Product::where('product_name', 'like', '%block%')->first() ?? Product::find(2);
        if (!$this->pellets || !$this->block) {
            $this->error('Could not resolve the pellets and block products. Create them first.');
            return 1;
        }

        $this->info(($dry ? '[DRY RUN] ' : '') . "since={$this->since->toDateString()} cutover={$this->cutover->toDateString()} billable-from={$this->billableFrom->toDateString()}");
        $this->line("Products: pellets=#{$this->pellets->id} \"{$this->pellets->product_name}\", block=#{$this->block->id} \"{$this->block->product_name}\"");

        DB::beginTransaction();
        try {
            $this->doImport();
            $dry ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Import failed, nothing was written: ' . $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
            return 1;
        }

        $dir = $this->writeReports($dry);
        $this->printSummary($dry, $dir);

        return 0;
    }

    // ------------------------------------------------------------------ main

    private function doImport(): void
    {
        $legacy = DB::connection('old_mysql');

        $logins = $legacy->table('login')->get()->keyBy('customerID');
        foreach ($logins as $l) {
            if ((int) $l->admin === 1) {
                $this->excludedLegacyCustomers[] = (int) $l->customerID;
            }
        }
        $addresses = $legacy->table('customer_addresses')->orderBy('id')->get()->groupBy('customer_id');

        // orderID 1 and 2 are sentinel rows ("last maintenance run", sublimation), not orders.
        $all = $legacy->table('orders')->where('orderID', '>', 2)->get()->keyBy('orderID');
        $this->legacyOrders = $all;

        $oneTime = []; $instances = []; $parentRows = [];
        foreach ($all as $r) {
            if (in_array((int) $r->customerID, $this->excludedLegacyCustomers, true)) {
                continue;
            }
            if (!$logins->has($r->customerID)) {
                $this->flag('order_customer_missing', $r->orderID, $r->customerID, 'Order references a customer id with no login row; not imported');
                $this->stats['skipped']++;
                continue;
            }
            if ((int) $r->recurring === 1) {
                $parentRows[$r->orderID] = $r;            // may itself carry an "Instance of" note (chained) - still a parent
            } elseif ($this->legacyDate($r)->gte($this->since)) {
                if ($this->parentIdFromNotes($r)) {
                    $instances[] = $r;
                } else {
                    if ((int) $r->recurringInstance === 1) {
                        $this->flag('instance_without_parent_note', $r->orderID, $r->customerID, 'Flagged as instance but no "Instance of Order#" note; imported as a one-time order');
                    }
                    $oneTime[] = $r;
                }
            }
        }

        // Parents needed: every active parent + any (even cancelled) parent referenced by an imported instance.
        $neededParents = [];
        foreach ($parentRows as $id => $p) {
            if ((int) $p->cancelled === 0) {
                $neededParents[$id] = $p;
            }
        }
        foreach ($instances as $i) {
            $pid = $this->parentIdFromNotes($i);
            if (isset($parentRows[$pid])) {
                $neededParents[$pid] = $parentRows[$pid];
            }
        }

        // Customers that need to exist
        $needed = [];
        foreach (array_merge($oneTime, $instances, array_values($neededParents)) as $r) {
            $needed[(int) $r->customerID] = true;
        }
        $this->importCustomers(array_keys($needed), $logins, $addresses, array_merge($oneTime, $instances, array_values($neededParents)));

        $this->importParents($neededParents, $instances);
        $this->importInstances($instances);
        $this->importOneTime($oneTime);
    }

    // ------------------------------------------------------------- customers

    private function importCustomers(array $legacyIds, $logins, $addresses, array $orderRows): void
    {
        sort($legacyIds);
        $emailUse = [];
        foreach ($legacyIds as $id) {
            $e = $this->cleanEmail($logins[$id]->email);
            if ($e) {
                $emailUse[$e] = ($emailUse[$e] ?? 0) + 1;
            }
        }

        // customers whose imported orders are all pickups never need a delivery charge
        $hasDelivery = [];
        foreach ($orderRows as $r) {
            $hasDelivery[(int) $r->customerID] = ($hasDelivery[(int) $r->customerID] ?? false) || (int) $r->delivery === 1;
        }

        foreach ($legacyIds as $id) {
            $l = $logins[$id];
            $addr = $addresses->get($id)?->first();
            $email = $this->cleanEmail($l->email);
            $name = trim($l->login);

            $existing = Customer::where('legacy_id', $id)->first();
            if ($existing) {
                $this->customers[$id] = $existing;
                $this->pricing[$id] = CustomerPricing::where('customer_id', $existing->id)->first();
                $this->stats['customers_existing']++;
                continue;
            }

            $adopt = $email ? Customer::where('email', $email)->whereNull('legacy_id')->first() : null;
            if ($adopt) {
                $adopt->legacy_id = $id;
                $adopt->save();
                $this->customers[$id] = $adopt;
                $this->pricing[$id] = CustomerPricing::where('customer_id', $adopt->id)->first();
                $this->stats['customers_adopted']++;
                $this->flag('customer_adopted', $id, $name, "Matched an existing customer #{$adopt->id} by email; its data and password were left unchanged");
                $this->importPricing($id, $adopt, $l, $hasDelivery[$id] ?? false);
                continue;
            }

            // Password: keep the legacy login working by hashing the plaintext, but only when the
            // email is usable as a unique login identifier.
            $password = null;
            if (!$email) {
                $this->flag('customer_no_email', $id, $name, 'Blank email: cannot log in until an email is added and a reset link is sent');
            } elseif ($emailUse[$email] > 1) {
                $this->flag('customer_shared_email', $id, $name, "Email {$email} is shared by {$emailUse[$email]} legacy accounts: no password set, needs separate emails");
            } elseif (Customer::where('email', $email)->exists()) {
                $this->flag('customer_email_taken', $id, $name, 'Email already used by another customer in the new system: no password set');
            } elseif ($l->password !== null && $l->password !== '') {
                $password = Hash::make($l->password);
            }

            $province = $this->province($addr->province ?? null);
            if (!$addr) {
                $this->flag('customer_no_address', $id, $name, 'No row in customer_addresses: address/phone left blank, fill in before the first Novex push or invoice');
            }
            if (!$province && $addr) {
                $this->flag('customer_province', $id, $name, "Unrecognised province \"{$addr->province}\"");
            }
            $supply = (int) $l->supply_location > 0 ? (int) $l->supply_location : $this->defaultSupplier;
            if (!$supply) {
                $this->flag('customer_no_supplier', $id, $name, 'No supply location: set one in the customer record or Novex pushes will fail');
            }
            if ((int) $l->suspended === 1) {
                $this->flag('customer_suspended', $id, $name, 'Marked suspended/inactive on the live site');
            }

            $c = new Customer([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'phone' => $addr ? trim($addr->phone) ?: null : null,
                'address' => $addr ? trim($addr->address) : '',
                'city' => $addr ? trim($addr->city) : '',
                'postal_code' => $addr ? trim($addr->postal) : '',
                'province' => $province,
                'supply_location_id' => $supply,
            ]);
            $c->legacy_id = $id;
            $c->save();

            if ($addr && trim($addr->address) !== '' && $province) {
                CustomerAddress::create([
                    'customer_id' => $c->id,
                    'address_label' => 'Main',
                    'address' => trim($addr->address),
                    'unit' => ($addr->unit && $addr->unit !== '0') ? $addr->unit : null,
                    'city' => trim($addr->city),
                    'postal_code' => trim($addr->postal),
                    'province' => $province,
                    'country' => 'Canada',
                    'is_default' => 1,
                    'is_active' => 1,
                ]);
            }

            $this->customers[$id] = $c;
            $this->stats['customers_created']++;
            $this->importPricing($id, $c, $l, $hasDelivery[$id] ?? false);
        }
    }

    /**
     * login.price_* are integer cents. price_ice is per lb and applies to every format, so it
     * is used for both pellets and blocks (this system prices both per lb).
     */
    private function importPricing(int $legacyId, Customer $c, $l, bool $hasDelivery): void
    {
        if (CustomerPricing::where('customer_id', $c->id)->exists()) {
            $this->pricing[$legacyId] = CustomerPricing::where('customer_id', $c->id)->first();
            return;
        }
        $ice = (int) $l->price_ice; $deliv = (int) $l->price_delivery; $haz = (int) $l->price_hazmat; $other = (int) $l->price_other;
        if ($ice <= 0 && $deliv <= 0 && $haz <= 0 && $other <= 0) {
            $this->pricing[$legacyId] = null;   // no custom pricing: invoices fall back to product prices (with the default-pricing warning)
            return;
        }

        // CustomerPricing columns default to 0 and the invoice generator treats 0 as "a price", so the
        // product list price is written explicitly whenever the customer has no per-lb price.
        $icePrice = $ice > 0 ? $ice / 100 : (float) $this->pellets->price;
        // $boxPrice = $ice > 0 ? $ice / 100 : (float) $this->block->price;  // one ice_price now applies to all formats
        if ($ice > 0 && ($ice < 50 || $ice > 500)) {
            $this->flag('pricing_suspicious', $legacyId, $c->name, "price_ice={$ice} cents/lb looks unusual: verify");
        }
        if ($other > 0) {
            $this->flag('pricing_other_charge', $legacyId, $c->name, "price_other={$other} cents was never charged on the live site; imported as other_charges, confirm");
        }

        $mode = $deliv > 0 ? CustomerPricing::DELIVERY_FIXED : ($hasDelivery ? CustomerPricing::DELIVERY_NOVEX : CustomerPricing::DELIVERY_NONE);

        $p = CustomerPricing::create([
            'customer_id' => $c->id,
            'ice_price' => $icePrice,
            // 'box_price' => $boxPrice,
            'hazmat_fee' => $haz / 100,
            'delivery_fee' => $deliv / 100,
            'delivery_mode' => $mode,
            'pickup_fee' => 0,
            'other_charges' => $other / 100,
        ]);
        $this->pricing[$legacyId] = $p;
        $this->stats['pricing_created']++;
    }

    // ---------------------------------------------------------------- orders

    /**
     * A recurring parent is the schedule template. Its delivery_date WEEKDAY drives
     * orders:process-recurring (see ProcessRecurringOrders), and the parent row itself counts as a
     * delivery on that date. Legacy already has the delivery rows for every date up to its horizon,
     * so the parent is dated at the last matching weekday BEFORE the cutover and locked as paid:
     * it keeps the schedule alive without double-booking or double-invoicing a delivery.
     */
    private function importParents(array $parents, array $instances): void
    {
        $lastInstance = [];
        foreach ($instances as $i) {
            $pid = $this->parentIdFromNotes($i);
            $d = $this->legacyDate($i);
            if (!isset($lastInstance[$pid]) || $d->gt($lastInstance[$pid])) {
                $lastInstance[$pid] = $d;
            }
        }

        foreach ($parents as $id => $p) {
            $existing = Order::where('legacy_id', $id)->first();
            if ($existing) {
                $this->parents[$id] = $existing;
                $this->stats['orders_existing']++;
                continue;
            }
            $cust = $this->customers[(int) $p->customerID];
            $active = (int) $p->cancelled === 0;

            $weekday = ucfirst(strtolower(trim((string) $p->orderDay)));
            if (!in_array($weekday, ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'], true)) {
                $this->flag('parent_no_weekday', $id, $cust->name, 'Recurring parent has no valid orderDay: not imported' . ($active ? ' (ACTIVE schedule lost!)' : ''));
                $this->stats['skipped']++;
                continue;
            }
            $date = $this->cutover->copy()->subDay();
            while ($date->englishDayOfWeek !== $weekday) {
                $date->subDay();
            }

            $order = $this->makeOrder($p, $cust, Order::RECURRING, $active ? Order::VALID : Order::CANCELLED, $date, true, 1);
            $this->parents[$id] = $order;
            $this->stats['parents_created']++;
            if ((int) $p->recurSkip === 1) {
                $this->flag('parent_skip_flag', $id, $cust->name, 'Legacy "skip next" flag on the parent was ignored (it only ever skipped one occurrence)');
            }
        }
    }

    private function importInstances(array $instances): void
    {
        usort($instances, fn ($a, $b) => (int) $a->orderDate <=> (int) $b->orderDate);
        $seen = [];

        foreach ($instances as $r) {
            if (RecurringOrder::where('legacy_id', $r->orderID)->exists() || Order::where('legacy_id', $r->orderID)->exists()) {
                $this->stats['orders_existing']++;
                continue;
            }
            $cust = $this->customers[(int) $r->customerID];
            $date = $this->legacyDate($r);
            $pid = $this->parentIdFromNotes($r);
            $parentLegacy = $this->parents[$pid] ?? null;
            $cancelled = (int) $r->cancelled === 1 || (int) $r->recurSkip === 1;
            $past = $date->lt($this->cutover);

            $legacyLbs = $cancelled ? 0 : $this->quantity($r);
            $this->reconAdd($r->customerID, $date, $cancelled ? 0 : 1, 0, $legacyLbs, 0);

            // A recurring_orders row has no items/time/PO of its own: it inherits them from the parent.
            // Only fold the legacy instance into the schedule when it matches its parent.
            $parentRow = $parentLegacy ? $this->legacyOrders->get($pid) : null;
            $differs = $parentRow && $this->differsFromParent($r, $parentRow);

            if (!$parentLegacy) {
                $this->flag('instance_parent_missing', $r->orderID, $cust->name, "Parent order #{$pid} not found/imported; imported as a standalone one-time order");
                $this->createStandalone($r, $cust, $date, $cancelled, $past);
                continue;
            }

            if ($differs && $past) {
                // history keeps its own actual quantity: standalone completed order
                $this->createStandalone($r, $cust, $date, $cancelled, $past);
                $this->stats['instances_standalone']++;
                continue;
            }

            $key = $parentLegacy->id . '|' . $date->toDateString();
            if (isset($seen[$key]) || RecurringOrder::where('order_id', $parentLegacy->id)->whereDate('scheduled_delivery_date', $date->toDateString())->exists()) {
                $this->flag('instance_duplicate', $r->orderID, $cust->name, "Second instance of parent #{$pid} on {$date->toDateString()}; skipped");
                $this->stats['skipped']++;
                continue;
            }
            $seen[$key] = true;

            if ($differs) {   // future/open: cannot hold an override, and a standalone order would be double-booked by the generator
                $this->flag('instance_override_open', $r->orderID, $cust->name, "OPEN instance on {$date->toDateString()} differs from its schedule (qty/time/format/PO): imported with the schedule's values, ADJUST BY HAND");
            }

            $status = $cancelled ? RecurringOrder::CANCELLED : ($past ? RecurringOrder::COMPLETED : RecurringOrder::OPEN);
            $ro = new RecurringOrder([
                'order_id' => $parentLegacy->id,
                'scheduled_delivery_date' => $date,
                'status' => $status,
                'recurring_payment_status' => $date->lt($this->billableFrom) ? 'paid' : null,
            ]);
            $ro->push = (int) $r->pushed_to_novex === 1;
            $ro->legacy_id = $r->orderID;
            $ro->save();

            if ($status === RecurringOrder::OPEN && !$ro->push && $date->lte($this->cutover->copy()->addDay())) {
                $this->flag('push_manually', $r->orderID, $cust->name, "Open instance on {$date->toDateString()} was never pushed to Novex on the live site and is inside the 2-day auto-push window: push by hand");
            }
            $this->stats['instances_created']++;
            $this->reconAdd($r->customerID, $date, 0, $cancelled ? 0 : 1, 0, $cancelled ? 0 : $this->quantityOfOrder($parentLegacy));
        }
    }

    private function importOneTime(array $rows): void
    {
        foreach ($rows as $r) {
            if (Order::where('legacy_id', $r->orderID)->exists()) {
                $this->stats['orders_existing']++;
                continue;
            }
            $cust = $this->customers[(int) $r->customerID];
            $date = $this->legacyDate($r);
            $cancelled = (int) $r->cancelled === 1 || (int) $r->recurSkip === 1;
            $this->reconAdd($r->customerID, $date, $cancelled ? 0 : 1, 0, $cancelled ? 0 : $this->quantity($r), 0);
            $this->createStandalone($r, $cust, $date, $cancelled, $date->lt($this->cutover));
        }
    }

    private function createStandalone($r, Customer $cust, Carbon $date, bool $cancelled, bool $past): Order
    {
        $status = (int) $r->cancelled === 1 ? Order::CANCELLED : ((int) $r->recurSkip === 1 ? Order::SKIP : ($past ? Order::COMPLETED : Order::VALID));
        $order = $this->makeOrder($r, $cust, Order::NON_RECURRING, $status, $date, $date->lt($this->billableFrom), (int) $r->pushed_to_novex);
        $this->stats['orders_created']++;
        $this->reconAdd($r->customerID, $date, 0, $cancelled ? 0 : 1, 0, $cancelled ? 0 : $this->quantity($r));

        if ($status === Order::VALID && !(int) $r->pushed_to_novex && (int) $r->delivery === 1 && $date->lte($this->cutover->copy()->addDay())) {
            $this->flag('push_manually', $r->orderID, $cust->name, "Open delivery on {$date->toDateString()} was never pushed to Novex on the live site: push by hand");
        }
        return $order;
    }

    private function makeOrder($r, Customer $cust, string $recurring, string $status, Carbon $date, bool $locked, int $push): Order
    {
        $qty = $this->quantity($r);
        $product = $this->productFor($r);
        $unit = $this->unitPrice($r, $product);

        $time = max(0, min(23, (int) $r->orderTime));
        $order = new Order([
            'customer_id' => $cust->id,
            'customer_name' => $cust->name,
            'email' => $cust->email,
            'phone' => $cust->phone,
            'amount_of_ice' => $qty,
            'amount_of_boxes' => 0,
            'origin' => 'manual',
            'recurring' => $recurring,
            'location_name' => '',
            'address' => $cust->address,
            'unit' => '',
            'city' => $cust->city,
            'postal_code' => $cust->postal_code,
            'province' => $cust->province,
            'country' => 'Canada',
            'pickup_delivery' => (int) $r->delivery === 1 ? 'delivery' : 'pickup',
            'status' => $status,
            'hazmat' => 0,
            'delivery_date' => $date->copy()->setTime($time, 0),
            'notes' => $r->notes ?? '',
            'payment_status' => $locked ? 'paid' : null,
            'supplier_id' => $cust->supply_location_id,
            'invoice_id' => null,
            'po' => $r->purchaseOrder !== '' ? $r->purchaseOrder : null,
        ]);
        $order->push = $push;
        $order->legacy_id = $r->orderID;
        $order->save();

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'amount_of_items' => $qty,
            'unit_price' => $unit,
            'total_price' => round($qty * $unit, 2),
        ]);

        return $order;
    }

    // --------------------------------------------------------------- helpers

    private function legacyDate($row): Carbon
    {
        // orderDate is epoch seconds: 07:00 UTC for 2013-2017 rows, 00:00 UTC since 2018. The UTC calendar
        // date is the delivery day for both; converting to America/Vancouver would shift later rows back a day.
        return Carbon::createFromTimestampUTC((int) $row->orderDate)->startOfDay();
    }

    private function parentIdFromNotes($row): ?int
    {
        return preg_match('/Instance of Order#\s*(\d+)/i', (string) $row->notes, $m) ? (int) $m[1] : null;
    }

    private function productFor($r): Product
    {
        return match ((int) $r->ice_format_id) {
            3 => $this->pellets,
            4 => $this->block,
            1 => $this->option('map-block5') === 'pellets' ? $this->pellets : $this->block,
            0 => $this->option('map-rice') === 'block' ? $this->block : $this->pellets,
            default => $this->pellets,
        };
    }

    /** lbs; legacy measure_type 3 = number of 500 lb totes */
    private function quantity($r): float
    {
        return (float) $r->count * ((int) $r->measure_type_id === 3 ? 500 : 1);
    }

    private function quantityOfOrder(Order $o): float
    {
        return (float) $o->items()->sum('amount_of_items');
    }

    private function unitPrice($r, Product $product): float
    {
        $p = $this->pricing[(int) $r->customerID] ?? null;
        if ($p) {
            return (float) $p->ice_price;   // one price per customer for all formats
        }
        return (float) $product->price;
    }

    private function differsFromParent($inst, $parent): bool
    {
        foreach (['count', 'orderTime', 'ice_format_id', 'delivery', 'purchaseOrder', 'measure_type_id'] as $k) {
            if ((string) $inst->$k !== (string) $parent->$k) {
                return true;
            }
        }
        return false;
    }

    private function cleanEmail($e): ?string
    {
        $e = strtolower(trim((string) $e));
        return $e !== '' ? $e : null;
    }

    private function province($p): ?string
    {
        $p = strtolower(trim((string) $p));
        return match (true) {
            in_array($p, ['bc', 'b.c.', 'british columbia'], true) => 'BC',
            in_array($p, ['ab', 'alberta'], true) => 'AB',
            default => null,
        };
    }

    private function flag(string $type, $ref, $who, string $detail): void
    {
        $this->flags[] = [$type, $ref, $who, $detail];
    }

    private function reconAdd($legacyCustomer, Carbon $date, int $legacyOrders, int $newOrders, float $legacyLbs, float $newLbs): void
    {
        $k = $date->format('Y-m');
        $row = &$this->recon[(int) $legacyCustomer][$k];
        $row = $row ?? [0, 0, 0.0, 0.0];
        $row[0] += $legacyOrders; $row[1] += $newOrders; $row[2] += $legacyLbs; $row[3] += $newLbs;
    }

    // --------------------------------------------------------------- reports

    private function writeReports(bool $dry): string
    {
        $dir = 'legacy-import/' . now()->format('Ymd-His') . ($dry ? '-dry-run' : '');
        $csv = function (array $header, array $rows) {
            $f = fopen('php://temp', 'w+');
            fputcsv($f, $header);
            foreach ($rows as $r) {
                fputcsv($f, $r);
            }
            rewind($f);
            return stream_get_contents($f);
        };

        usort($this->flags, fn ($a, $b) => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        Storage::put("$dir/flagged.csv", $csv(['type', 'legacy_ref', 'customer', 'detail'], $this->flags));

        $rows = [];
        ksort($this->recon);
        foreach ($this->recon as $cid => $months) {
            ksort($months);
            foreach ($months as $month => [$lo, $no, $ll, $nl]) {
                $rows[] = [$cid, $this->customers[$cid]->name ?? '', $month, $lo, $no, $ll, $nl, $lo === $no && abs($ll - $nl) < 0.001 ? 'OK' : 'DIFF'];
            }
        }
        Storage::put("$dir/reconciliation.csv", $csv(['legacy_customer', 'name', 'month', 'legacy_orders', 'new_orders', 'legacy_lbs', 'new_lbs', 'result'], $rows));

        return Storage::path($dir);
    }

    private function printSummary(bool $dry, string $dir): void
    {
        $this->newLine();
        $this->table(['Metric', 'Count'], collect($this->stats)->map(fn ($v, $k) => [$k, $v])->values()->all());

        $byType = collect($this->flags)->groupBy(0)->map->count();
        if ($byType->isNotEmpty()) {
            $this->warn('Flags (see flagged.csv):');
            $this->table(['Type', 'Count'], $byType->map(fn ($v, $k) => [$k, $v])->values()->all());
        }

        $diff = 0;
        foreach ($this->recon as $months) {
            foreach ($months as [$lo, $no, $ll, $nl]) {
                if ($lo !== $no || abs($ll - $nl) >= 0.001) {
                    $diff++;
                }
            }
        }
        $diff === 0 ? $this->info('Reconciliation: every customer-month matches (orders and lbs).') : $this->warn("Reconciliation: {$diff} customer-month(s) differ, see reconciliation.csv");
        $this->line("Reports: {$dir}");
        $this->info($dry ? 'Dry run: nothing was written.' : 'Import committed.');
    }
}
