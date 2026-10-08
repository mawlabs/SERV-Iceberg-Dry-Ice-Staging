<?php

namespace App\Http\Controllers\Admin;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerPricing;
use App\Models\SupplyLocation;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use App\Http\Requests\CustomerRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Class CustomersCrudController
 * @package App\Http\Controllers\Admin
 */
class CustomersCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    public function setup()
    {
        CRUD::setModel(\App\Models\Customer::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/customers');
        CRUD::setEntityNameStrings('customer', 'customers');
        CRUD::setCreateView('vendor.backpack.crud.customer-create');
    }

    protected function setupListOperation()
    {
        CRUD::column('name');
        CRUD::column('email');
        CRUD::column('phone');
        CRUD::column('address');
        CRUD::column('city');
        CRUD::column('postal_code');
        CRUD::column('province');

        CRUD::addColumn([
            'name'     => 'payment_status',
            'label'    => 'Payment Status',
            'type'     => 'closure',
            'function' => function($entry) {
                return $entry->getPaymentStatusBadge();
            },
            'escaped'  => false,
        ]);

        CRUD::modifyColumn('payment_status', [
            'priority' => 1,
        ]);

        $this->crud->removeAllButtonsFromStack('line');
        $this->crud->addButtonFromView('line', 'view_button', 'view-button', 'beginning');
    }

    public function rules()
    {
        return [
            'name' => 'required|string',
            'email' => 'required|email|unique:customers,email',
            'phone' => 'required|string',
            'address' => 'required|string',
            'city' => 'required|string',
            'postal_code' => 'required|string',
            'province' => 'required|string',
        ];
    }

    protected function setupCreateOperation()
    {
        CRUD::setValidation(CustomerRequest::class);

        CRUD::field('name');
        CRUD::field('email');
        CRUD::field('phone');
        CRUD::field('address');
        CRUD::field('city');
        CRUD::field('postal_code');
        CRUD::field('province')->type('enum')->options(['BC' => 'BC', 'AB' => 'AB']);
        CRUD::field('supply_location_id')
            ->type('select')
            ->entity('supplyLocation')
            ->model(SupplyLocation::class)
            ->attribute('name');
    }

    public function view($id)
    {
        $this->crud->hasAccessOrFail('update');

        $entry = Customer::with(['addresses' => function($query) {
            $query->where('is_active', 1)->orderBy('is_default', 'desc');
        },'invoices'])->findOrFail($id);
        return view('vendor.backpack.crud.customer-edit', [
            'entry' => $entry,
            'crud' => $this->crud,
        ]);
    }

    public function updateFromView($id, Request $request)
    {
        $this->crud->hasAccessOrFail('update');

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:customers,email,' . $id,
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'province' => 'required|in:BC,AB',
        ]);

        try {
            DB::beginTransaction();

            $customer = Customer::findOrFail($id);

            // Update customer inline address fields (for backward compatibility)
            $customer->update([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'address' => $request->address,
                'city' => $request->city,
                'postal_code' => $request->postal_code,
                'province' => $request->province,
                'supply_location_id' => $request->supply_location_id,
            ]);

            // Update or create default address in customer_addresses table
            $defaultAddress = CustomerAddress::where('customer_id', $customer->id)
                ->where('is_default', 1)
                ->where('is_active', 1)
                ->first();

            if ($defaultAddress) {
                // Update existing default address
                $defaultAddress->update([
                    'address' => $request->address,
                    'city' => $request->city,
                    'postal_code' => $request->postal_code,
                    'province' => $request->province,
                ]);
            } else {
                // Create new default address if none exists
                CustomerAddress::create([
                    'customer_id' => $customer->id,
                    'address_label' => 'Primary Address',
                    'address' => $request->address,
                    'city' => $request->city,
                    'postal_code' => $request->postal_code,
                    'province' => $request->province,
                    'country' => 'Canada',
                    'is_default' => 1,
                    'is_active' => 1,
                ]);
            }

            DB::commit();

            \Alert::success('Customer updated successfully.')->flash();
            return redirect()->route('customer.view', $id);

        } catch (\Exception $e) {
            DB::rollBack();
            \Alert::error('Failed to update customer: ' . $e->getMessage())->flash();
            return back()->withInput();
        }
    }

    /**
     * Get all addresses for a customer (AJAX)
     */
    public function getCustomerAddresses($customerId)
    {
        try {
            $addresses = CustomerAddress::where('customer_id', $customerId)
                ->where('is_active', 1)
                ->orderBy('is_default', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'addresses' => $addresses
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load addresses: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add new address for customer (AJAX)
     */
    public function addAddress(Request $request, $customerId)
    {
        $request->validate([
            'address_label' => 'required|string|max:100',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'province' => 'required|in:BC,AB',
            'unit' => 'nullable|string|max:50',
            'location_name' => 'nullable|string|max:255',
            'delivery_instructions' => 'nullable|string',
            'is_default' => 'nullable|boolean',
        ]);

        try {
            DB::beginTransaction();

            // If setting as default, unset other defaults
            if ($request->is_default) {
                CustomerAddress::where('customer_id', $customerId)
                    ->update(['is_default' => 0]);
            }

            $address = CustomerAddress::create([
                'customer_id' => $customerId,
                'address_label' => $request->address_label,
                'location_name' => $request->location_name,
                'address' => $request->address,
                'unit' => $request->unit,
                'city' => $request->city,
                'postal_code' => $request->postal_code,
                'province' => $request->province,
                'country' => 'Canada',
                'delivery_instructions' => $request->delivery_instructions,
                'is_default' => $request->is_default ?? 0,
                'is_active' => 1,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Address added successfully',
                'address' => $address
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to add address: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update existing address (AJAX)
     */
    public function updateAddress(Request $request, $customerId, $addressId)
    {
        $request->validate([
            'address_label' => 'required|string|max:100',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'postal_code' => 'required|string|max:20',
            'province' => 'required|in:BC,AB',
            'unit' => 'nullable|string|max:50',
            'location_name' => 'nullable|string|max:255',
            'delivery_instructions' => 'nullable|string',
            'is_default' => 'nullable|boolean',
        ]);

        try {
            DB::beginTransaction();

            $address = CustomerAddress::where('customer_id', $customerId)
                ->where('id', $addressId)
                ->firstOrFail();

            // If setting as default, unset other defaults
            if ($request->is_default) {
                CustomerAddress::where('customer_id', $customerId)
                    ->where('id', '!=', $addressId)
                    ->update(['is_default' => 0]);
            }

            $address->update([
                'address_label' => $request->address_label,
                'location_name' => $request->location_name,
                'address' => $request->address,
                'unit' => $request->unit,
                'city' => $request->city,
                'postal_code' => $request->postal_code,
                'province' => $request->province,
                'delivery_instructions' => $request->delivery_instructions,
                'is_default' => $request->is_default ?? $address->is_default,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Address updated successfully',
                'address' => $address
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update address: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete address (soft delete - AJAX)
     */
    public function deleteAddress($customerId, $addressId)
    {
        try {
            $address = CustomerAddress::where('customer_id', $customerId)
                ->where('id', $addressId)
                ->firstOrFail();

            // Check if address is used in any active orders
            $activeOrdersCount = \App\Models\Order::where('address_id', $addressId)
                ->whereIn('status', ['valid', 'skip'])
                ->count();

            if ($activeOrdersCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete address. It's used in {$activeOrdersCount} active order(s)."
                ], 422);
            }

            // Soft delete
            $address->update(['is_active' => 0]);

            return response()->json([
                'success' => true,
                'message' => 'Address deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete address: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Set address as default (AJAX)
     */
    public function setDefaultAddress($customerId, $addressId)
    {
        try {
            DB::beginTransaction();

            // Unset all defaults for this customer
            CustomerAddress::where('customer_id', $customerId)
                ->update(['is_default' => 0]);

            // Set new default
            $address = CustomerAddress::where('customer_id', $customerId)
                ->where('id', $addressId)
                ->where('is_active', 1)
                ->firstOrFail();

            $address->update(['is_default' => 1]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Default address updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to set default address: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteFromView($id)
    {
        $this->crud->hasAccessOrFail('delete');

        \App\Models\Customer::findOrFail($id)->delete();

        \Alert::success('Customer Deleted.')->flash();

        return redirect($this->crud->route);
    }

    public function searchCustomers(Request $request)
    {
        $query = $request->get('q', '');
        $page = $request->get('page', 1);
        $perPage = 20;

        $customersQuery = Customer::select([
            'id',
            'name',
            'email',
            'phone',
            'address',
            'city',
            'postal_code',
            'province'
        ]);

        if (!empty($query)) {
            $customersQuery->where(function($q) use ($query) {
                $q->where('email', 'LIKE', "%{$query}%");
            });
        }

        $customers = $customersQuery
            ->orderBy('email')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'customers' => $customers->items(),
            'has_more' => $customers->hasMorePages(),
            'total' => $customers->total()
        ]);
    }

    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }


    public function customerPricing(){
        $customers = Customer::with('pricing')
            ->orderBy('name')
            ->get();

        return view('vendor.backpack.crud.customer-pricing', compact('customers'));
    }

    public function customerPricingUpdate(Request $request, $customerId)
    {
        $validated = $request->validate([
            'ice_price' => 'nullable|numeric|min:0',
            // 'box_price' => 'nullable|numeric|min:0', // one ice_price now applies to all formats
            'hazmat_fee' => 'nullable|numeric|min:0',
            'delivery_fee' => 'nullable|numeric|min:0',
            'delivery_mode' => 'nullable|in:novex,fixed,none',
            'pickup_fee' => 'nullable|numeric|min:0',
            'other_charges' => 'nullable|numeric|min:0',
        ]);

        $icePrice = (float)($validated['ice_price'] ?? 0);
        // $boxPrice = (float)($validated['box_price'] ?? 0);
        $hazmatFee = (float)($validated['hazmat_fee'] ?? 0);
        $deliveryMode = $validated['delivery_mode'] ?? CustomerPricing::DELIVERY_NOVEX;
        $pickupFee = (float)($validated['pickup_fee'] ?? 0);
        $otherCharges = (float)($validated['other_charges'] ?? 0);

        // The fixed amount only applies in fixed mode.
        $deliveryFee = $deliveryMode === CustomerPricing::DELIVERY_FIXED
            ? (float)($validated['delivery_fee'] ?? 0)
            : 0.0;

        if ($deliveryMode === CustomerPricing::DELIVERY_FIXED && $deliveryFee <= 0) {
            return back()->with(
                'error',
                'Enter a delivery amount for Fixed delivery, or choose Novex rate / No delivery.'
            );
        }

        $total =
            $icePrice +
            // $boxPrice +
            $hazmatFee +
            $deliveryFee +
            $pickupFee +
            $otherCharges;

        $pricing = CustomerPricing::where(
            'customer_id',
            $customerId
        )->first();

        // If everything is zero and delivery is the default (Novex), there is
        // nothing to store, so don't create a record.
        if ($total == 0 && $deliveryMode === CustomerPricing::DELIVERY_NOVEX) {

            if ($pricing) {
                $pricing->delete();
            }

            return back()->with(
                'success',
                'Pricing cleared successfully.'
            );
        }

        CustomerPricing::updateOrCreate(
            [
                'customer_id' => $customerId
            ],
            [
                'ice_price' => $icePrice,
                // 'box_price' => $boxPrice,
                'hazmat_fee' => $hazmatFee,
                'delivery_fee' => $deliveryFee,
                'delivery_mode' => $deliveryMode,
                'pickup_fee' => $pickupFee,
                'other_charges' => $otherCharges,
            ]
        );

        return back()->with(
            'success',
            'Customer pricing updated successfully.'
        );
    }

}
