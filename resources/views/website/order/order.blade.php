@extends('website.layouts.main')
<script>
    window.isLoggedIn = @json(Auth::guard('web')->check() || Auth::guard('customer')->check());

    window.customerData = @json(
        Auth::guard('web')->check()
            ? Auth::guard('web')->user()
            : (Auth::guard('customer')->check() ? Auth::guard('customer')->user() : null)
    );
</script>



@section('content')
    <style>

            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 20px;
                background-color: #f5f5f5;
            }

            th {
                background-color: #006;
                color: #FFF;
                font-weight: bold;
            }

            td {
                padding: 1px;
            }

            table {
                border-collapse: separate;
                margin: 0px auto;
            }



            .is_button {
                padding: 10px 20px;
                font-size: 16px;
                cursor: pointer;
                background-color: #f0f0f0;
            }

            .tab-content {
                display: none;
            }

            .tab-content.active {
                display: block;
            }

            .error {
                color: red;
                font-weight: bold;
            }

            .valid {
                color: green;
                font-weight: bold;
            }



            hr {
                margin: 10px 0;
            }

            /* Modal Styles */
            .modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.5);
            }

            .modal-content {
                background-color: #fefefe;
                margin: 15% auto;
                padding: 20px;
                border: 2px solid #006;
                border-radius: 10px;
                width: 400px;
                max-width: 90%;
                text-align: center;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
            }

            .modal-header {
                background-color: #006;
                color: white;
                padding: 15px;
                margin: -20px -20px 20px -20px;
                border-radius: 8px 8px 0 0;
                font-size: 18px;
                font-weight: bold;
            }

            .modal-buttons {
                margin-top: 20px;
            }

            .modal-button {
                padding: 12px 25px;
                margin: 0 10px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 16px;
                font-weight: bold;
                min-width: 120px;
            }

            .signup-btn {
                background-color: #006;
                color: white;
                margin-bottom: 8px;
            }

            .signup-btn:hover {
                background-color: #004;
            }

            .guest-btn {
                background-color: #28a745;
                color: white;
            }

            .guest-btn:hover {
                background-color: #218838;
            }

            .modal-text {
                font-size: 16px;
                line-height: 1.5;
                margin: 15px 0;
            }

    </style>


    <body onload="document.getElementById('weight').focus();">

    <br>




    <center>
        <!-- Tab Navigation -->
        <table width='90%'>
            <tr>
                <td class='forsale_menu text-uppercase selected' id='tab-order' onclick='showTab("order")'>{{ __('order_tab_order') }}</td>
                <td class='forsale_menu text-uppercase' id='tab-location' onclick='showTab("location")'>{{ __('order_tab_location') }}</td>
                <td class='forsale_menu text-uppercase' id='tab-review' onclick='showTab("review")'>{{ __('order_tab_review') }}</td>
            </tr>
        </table>

        <br>

        <!-- ORDER TAB -->
        <div id="order-content" class="tab-content active">
            <form id='order_form'>
                <table>
                    <tr>
                        <th class="text-uppercase" colspan='4'>{{ __('order_tab_order') }}</th>
                    </tr>

                    @foreach ($products as $product)
                        @php
                            $safeId = 'product_' . $product->id;
                            $costId = 'product_cost_' . $product->id;
                            $notesId = 'notes_' . $product->id;
                            $priceFormatted = number_format($product->price, 2);
                        @endphp
                        <tr>
                            <td>{{ $product->product_name }} ({{ $product->unit }})</td>

                            <td>
                                <input
                                    class="textbox_data"
                                    style="text-align: center;"
                                    size="10"
                                    type="text"
                                    name="product[{{ $product->id }}][quantity]"
                                    id="{{ $safeId }}"
                                    value="0"
                                    data-unit-price="{{ $product->price }}"
                                    data-unit="{{ $product->unit }}"
                                    onblur="calculateProductCost({{ $product->id }}, {{ $product->price }})"
                                >
                            </td>

                            <td width="130px" align="right">
                                $ <input
                                    class="textbox_data"
                                    style="text-align: right;"
                                    readonly
                                    size="7"
                                    type="text"
                                    name="product[{{ $product->id }}][cost]"
                                    id="{{ $costId }}"
                                    value="0.00"
                                >
                            </td>

                            <td align="right" width="300px">
                                <div id="{{ $notesId }}">
                                    <i>
                                        ${{ $priceFormatted }} / {{ $product->unit }}
                                        @if ($product->id == 1)
                                            , <span style="color:red">{{ __('order_minimum_weight') }}</span>
                                        @endif
                                    </i>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    <tr>
                        <td colspan="4" id="anyproducterror">

                        </td>
                    </tr>
                </table>

                <br>
                <input style='width: 100px; text-align: center; color: #090; border: outset 2px #090;' class='is_button' type='button' value='{{ __('order_next') }}' onclick='nextFromOrder();'>
            </form>
        </div>

        <!-- Signup/Guest Modal -->
        <div id="signupModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    {{ __('order_signup_welcome') }}
                </div>
                <div class="modal-text">
                    <p>{{ __('order_signup_question') }}</p>
                </div>
                <div class="modal-buttons">
                    <button class="modal-button signup-btn" onclick="redirectToLoginOrSignup()">
                        {{ __('order_login_signup') }}
                    </button>
                    <br>
                    <button class="modal-button guest-btn" onclick="proceedAsGuest()">
                        {{ __('order_continue_guest') }}
                    </button>
                </div>
            </div>
        </div>


        <!-- LOCATION TAB -->
        <div id="location-content" class="tab-content">
            <form id='location_form'>
                <table width="95%">
                    <tr>
                        <th colspan='4'>{{ __('order_delivery_location') }}</th>
                    </tr>
                    <tr style="visibility: hidden">
                        <td>{{ __('order_pickup_delivery') }}</td>
                        <td></td>
                        <td>
                            <select class='textbox_data' style='width: 215px;' id='delivery_type'>
                                <option value=''>{{ __('order_select') }}</option>
                                <option value='pickup'>{{ __('order_pickup') }}</option>
                                <option selected value='delivery'>{{ __('order_delivery') }}</option>
                            </select>
                        </td>
                        <td>
                            <div id='delivery_notes'></div>
                        </td>
                    </tr>
                    <tr>
                        <td>{{ __('order_province') }}</td>
                        <td></td>
                        <td>
                            <select name='province' id='province' style='width: 210px;' class='textbox_data'>
                                <option value='BC'>British Columbia</option>
                                <option value='AB'>Alberta</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td>{{ __('order_city') }}</td>
                        <td></td>
                        <td><input class='textbox_data' size='22' type='text' id='city' name='city'></td>
                    </tr>
                    <tr>
                        <td>{{ __('order_address') }}</td>
                        <td></td>
                        <td><input class='textbox_data' size='22' type='text' id='address' name='address'></td>
                        <td rowspan='4'>
                            <div id='address_notes'></div>
                        </td>
                    </tr>
                    <tr>
                        <td>{{ __('order_postal') }}</td>
                        <td></td>
                        <td><input class='textbox_data' size='22' type='text' id='postal' name='postal_code'></td>
                    </tr>
                    <tr>
                        <td colspan='4'><br></td>
                    </tr>
                    <tr>
                        <th colspan='4'>{{ __('order_delivery_date') }}</th>
                    </tr>
                    <tr>
                        <?php
// Define target time zones
                        $timeZones = [
                            'America/Vancouver',    // GMT-7 (British Columbia)
                            'America/Edmonton',     // GMT-6 (Alberta)
                            'America/Toronto'       // GMT-4 (Quebec)
                        ];

                        $isAfter830AM = false;

// Check each time zone to see if any are past 8:30 AM
                        foreach ($timeZones as $tz) {
                            $zoneTime = new DateTime('now', new DateTimeZone($tz));
                            $hour = (int)$zoneTime->format('H');
                            $minute = (int)$zoneTime->format('i');

                            if (($hour > 8) || ($hour == 8 && $minute > 30)) {
                                $isAfter830AM = true;
                                break; // If any zone is past 8:30 AM, we show next day
                            }
                        }

// Use server local time for date calculations
                        $now = new DateTime();

// Determine starting month, day, and year
                        if ($isAfter830AM) {
                            $tomorrow = clone $now;
                            $tomorrow->modify('+1 day');
                            $startMonth = (int)$tomorrow->format('n');
                            $startDay = (int)$tomorrow->format('j');
                            $startYear = (int)$tomorrow->format('Y');
                        } else {
                            $startMonth = (int)$now->format('n');
                            $startDay = (int)$now->format('j');
                            $startYear = (int)$now->format('Y');
                        }

                        $currentMonth = (int)$now->format('n');
                        $currentYear = (int)$now->format('Y');
                        ?>

                        <td colspan='3'>
                            <!-- Month Selection -->
                            <select name="month" id='month' style='width: 150px;' class='textbox_data' onchange="updateDayOptions()">
                                <?php
                                $months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

                                foreach ($months as $index => $month) {
                                    $monthValue = $index + 1;
                                    $selected = ($monthValue == $startMonth) ? 'selected' : '';
                                    echo "<option value='{$monthValue}' {$selected}>{$month}</option>";
                                }
                                ?>
                            </select>

                            <!-- Day Selection -->
                            <select name="day" id='day' style='width: 80px;' class='textbox_data'>
                                <?php
                                // We'll populate this with JavaScript based on month selection to exclude weekends
                                // But provide initial values excluding weekends
                                $daysInMonth = date('t', mktime(0, 0, 0, $startMonth, 1, $startYear));

                                for ($i = 1; $i <= $daysInMonth; $i++) {
                                    // Check if this day is valid (not in the past and not a weekend)
                                    $dayToCheck = mktime(0, 0, 0, $startMonth, $i, $startYear);
                                    $dayOfWeek = date('w', $dayToCheck); // 0 = Sunday, 6 = Saturday

                                    // Skip weekends
                                    if ($dayOfWeek == 0 || $dayOfWeek == 6) {
                                        continue;
                                    }

                                    // Only show valid days (current day or future)
                                    if ($startMonth == $currentMonth && $startYear == $currentYear) {
                                        if ($i >= $startDay) {
                                            $selected = ($i == $startDay && $dayOfWeek != 0 && $dayOfWeek != 6) ? 'selected' : '';
                                            echo "<option value='{$i}' {$selected}>{$i}</option>";
                                        }
                                    } else {
                                        $selected = ($i == $startDay && $dayOfWeek != 0 && $dayOfWeek != 6) ? 'selected' : '';
                                        echo "<option value='{$i}' {$selected}>{$i}</option>";
                                    }
                                }
                                ?>
                            </select>

                            <!-- Year Selection -->
                            <select name="year" id='year' style='width: 80px;' class='textbox_data' onchange="updateMonthAndDayOptions()">
                                <?php
                                for ($i = $startYear; $i <= $startYear + 3; $i++) {
                                    $selected = ($i == $startYear) ? 'selected' : '';
                                    echo "<option value='{$i}' {$selected}>{$i}</option>";
                                }
                                ?>
                            </select>
                        </td>
                        <td>
                            <br>
                            <div id='date_notes'>{{ __('order_min_hours_notice') }}
                                <br>
                                <br>
                            </div>
                        </td>
                    </tr>

                    <tr>
                        <th colspan='4'>{{ __('order_contact_notes') }}</th>
                    </tr>
                    <tr>
                        <td width='130px'>{{ __('order_your_name') }}</td>
                        <td></td>
                        <td><input class='textbox_data' style='text-align: left;' size='22' type='text' name='name' id='name' value=''></td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>{{ __('order_location_type') }}</td>
                        <td></td>
                        <td>
                            <select class='textbox_data' style='width: 215px;' id='location_type' onchange="set_name();">
                                <option value='2'>{{ __('order_company') }}</option>
                                <option value='1'>{{ __('order_home_residence') }}</option>
                            </select>
                        </td>
                        <td></td>
                    </tr>
                    <tr>
                        <td>{{ __('order_location_name') }}</td>
                        <td></td>
                        <td><input onblur='check_company_name();' class='textbox_data' size='22' type='text' id='company' name='company' value=''></td>
                        <td>
                            <div id='company_notes'></div>
                        </td>
                    </tr>
                    <tr>
                        <td>{{ __('order_phone') }}</td>
                        <td></td>
                        <td><input onblur="check_phone();" class='textbox_data' style='text-align: left;' size='22' type='text' name='phone' id='phone' value=''></td>
                        <td>
                            <div id='phone_notes'></div>
                        </td>
                    </tr>
                    <tr>
                        <td>{{ __('order_email') }}</td>
                        <td></td>
                        <td><input onblur="check_email();" class='textbox_data' style='text-align: left;' size='22' type='text' name='email' id='email' value=''></td>
                        <td>
                            <div id='email_notes'></div>
                        </td>
                    </tr>
                    <tr>
                        <td>{{ __('order_notes') }}</td>
                        <td></td>
                        <td><input class='textbox_data' style='text-align: left;' size='22' type='text' name='notes' id='notes' value=''></td>
                        <td></td>
                    </tr>
                </table>
                <center>
                    <input style='width: 100px; text-align: center; color: #090; border: outset 2px #090;' type="button" onclick="nextFromLocation()" value="NEXT" class="is_button">
                </center>
            </form>
            <center>
                <br><b>{{ __('order_delivery_info') }}<br>
                    {{ __('order_delivery_window') }}</b>
            </center>
        </div>

        <!-- REVIEW TAB -->

        <div id="review-content" class="tab-content">
            <form id="orderForm" action="/order/ajax-create-from-review" method="POST">
                @csrf
                <!-- Hidden fields to store all order data - matching review controller validation -->
                <input type="hidden" id="hidden_customer_name" name="customer_name" value="">
                <input type="hidden" id="hidden_email" name="email" value="">
                <input type="hidden" id="hidden_phone" name="phone" value="">

                <input type="hidden" id="hidden_amount_of_ice" name="amount_of_ice" value="">
                <input type="hidden" id="hidden_amount_of_boxes" name="amount_of_boxes" value="">

                <!-- Dynamic product items will be injected here -->
                <div id="product-hidden-fields"></div>

                <input type="hidden" id="hidden_recurring" name="recurring" value="non-recurring">
                <input type="hidden" id="hidden_location_name" name="location_name" value="">
                <input type="hidden" id="hidden_address" name="address" value="">
                <input type="hidden" id="hidden_unit" name="unit" value="">
                <input type="hidden" id="hidden_city" name="city" value="">
                <input type="hidden" id="hidden_postal_code" name="postal_code" value="">
                <input type="hidden" id="hidden_province" name="province" value="">
                <input type="hidden" id="hidden_country" name="country" value="Canada">
                <input type="hidden" id="hidden_delivery_date" name="delivery_date" value="">
                <input type="hidden" id="hidden_notes" name="notes" value="">
                <input type="hidden" id="hidden_status" name="status" value="valid">
                <input type="hidden" id="hidden_pickup_delivery" name="pickup_delivery" value="">
                <!-- Review-specific cost fields -->
                <input type="hidden" id="hidden_weight_cost" name="weight_cost" value="">
                <input type="hidden" id="hidden_box_cost" name="box_cost" value="">
                <input type="hidden" id="hidden_subtotal" name="sub_total" value="">
                <input type="hidden" id="hidden_hazmat" name="hazmat" value="">
                <input type="hidden" id="hidden_delivery_cost" name="delivery_cost" value="">
                <input type="hidden" id="hidden_tax" name="tax" value="">
                <input type="hidden" id="hidden_total_cost" name="total_cost" value="">
                <input type="hidden" id="hidden_supplier_id" name="supplier_id" value="">
                <input type="hidden" id="hidden_submission_token" name="submission_token" value="">

                <table>
                    <tr>
                        <th colspan='4'>{{ __('order_review_order') }}</th>
                    </tr>
                    <tr>
                        <td width='300px' id='order-summary'>
                            <!-- Order summary will be populated by JavaScript -->
                        </td>
                        <td align='right' id='order-costs'>
                            <!-- Order costs will be populated by JavaScript -->
                        </td>
                    </tr>
                    <tr>
                        <td>{{ __('order_delivery') }}</td>
                        <td align='right' id="delivery-cost"></td>
                    </tr>
                    <tr>
                        <td>{{ __('order_hazmat_fee') }}</td>
                        <td align='right'>$12.00</td>
                    </tr>
                    <tr>
                        <td>
                            {{ __('order_tax') }}<br>
                            <hr>
                            <b>{{ __('order_total') }}</b>
                        </td>
                        <td align='right' id='tax-total'>
                            <!-- Tax and total will be calculated -->
                        </td>
                    </tr>

                    <tr>
                        <th colspan='4'>{{ __('order_location') }}</th>
                    </tr>
                    <tr>
                        <td colspan='4' id='delivery-location'>
                            <!-- Location details will be populated -->
                        </td>
                    </tr>

                    <tr>
                        <th colspan='4'>{{ __('order_date') }}</th>
                    </tr>
                    <tr>
                        <td colspan='4' id='delivery-date'>
                            <!-- Date will be populated -->
                        </td>
                    </tr>

                    <tr>
                        <th colspan='4'>{{ __('order_contact_info') }}</th>
                    </tr>
                    <tr>
                        <td colspan='4' id='contact-info'>
                            <!-- Contact info will be populated -->
                        </td>
                    </tr>

                    <tr>
                        <th colspan='4'>{{ __('order_order_notes') }}</th>
                    </tr>
                    <tr>
                        <td colspan='4' id='order-notes'>
                            <!-- Notes will be populated -->
                        </td>
                    </tr>
                </table>

                <div class="safety-disclaimer" style="text-align: left">
                    <h1>{{ __('order_safety_title') }}</h1>
                    <p>1. {{ __('order_safety_tip1') }}</p>
                    <p>2. {{ __('order_safety_tip2') }}</p>
                    <p>3. {{ __('order_safety_tip3') }}</p>

                    <h1>{{ __('order_disclaimer_title') }}</h1>
                    <p>1. {{ __('order_disclaimer1') }}</p>
                    <p>2. {{ __('order_disclaimer2') }}</p>
                    <p>3. {{ __('order_disclaimer3') }}</p>
                    <p>4. {{ __('order_disclaimer4') }}</p>
                    <p>5. {{ __('order_disclaimer5') }}</p>
                    <p><i>{{ __('order_disclaimer6') }}</i></p>
                    <hr>
                </div>

                <div class="submit-section">
                    <div class="checkbox-section">
                        <b>
                            <input type="checkbox" id="accept" required name="accept">
                            {{ __('order_accept_checkbox') }}
                        </b>
                    </div>
                    <input type="image"
                           src="https://www.paypalobjects.com/en_US/i/btn/btn_buynow_LG.gif"
                           alt="Buy Now with PayPal"
                           class="submit-btn" style="margin-top: 3px;">
                </div>
            </form>
        </div>
    </center>


    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>

        function redirectToLoginOrSignup() {
            saveFormData(); // Add this line
            const currentUrl = window.location.pathname;
            window.location.href = `/login?redirect=${encodeURIComponent(currentUrl)}`;
        }

        function nextFromOrder() {
            // Validate Dry Ice minimum requirement
            if (!validateDryIceMinimum()) {
                return; // Stop if validation fails
            }

            // 🔒 Handle auth
            if (window.isLoggedIn) {
                showTab('location');
            } else {
                document.getElementById('signupModal').style.display = 'block';
            }
        }


        function validateDryIceMinimum() {
            let hasDryIceError = false;
            let allQuantityZero = true;
            document.getElementById('anyproducterror').innerHTML = '';

            // Get all product quantity inputs
            const productInputs = document.querySelectorAll('[name^="product["][name$="[quantity]"]');

            productInputs.forEach(input => {
                const match = input.name.match(/product\[(\d+)\]\[quantity\]/);
                if (!match) return;

                const productId = match[1];
                const quantity = parseFloat(input.value) || 0;

                // Get product name from the table row
                const productRow = input.closest('tr');
                const productNameCell = productRow?.querySelector('td:first-child');
                const productName = productNameCell?.textContent.trim() || '';

                // Check if this is a Dry Ice product and quantity is less than 10
                if (productName.toLowerCase().includes('dry ice') && quantity > 0 && quantity < 10) {
                    // Show error message in the notes field
                    const notesId = 'notes_' + productId;
                    const notesElement = document.getElementById(notesId);
                    if (notesElement) {
                        // Get the original price info
                        const originalContent = notesElement.innerHTML;
                        const priceInfo = originalContent.split(',')[0]; // Get the price part

                        notesElement.innerHTML = '<span style="color:red; font-weight:bold;">Invalid: Please add at least 10 lbs</span>';
                        notesElement.className = 'error';
                    }
                    hasDryIceError = true;
                }
                else if(quantity > 0 && quantity < 10){
                    const notesId = 'notes_' + productId;
                    const notesElement = document.getElementById(notesId);
                    if (notesElement) {
                        // Get the original price info
                        const originalContent = notesElement.innerHTML;
                        const priceInfo = originalContent.split(',')[0]; // Get the price part

                        notesElement.innerHTML = '<span style="color:red; font-weight:bold;">Invalid: Please add at least 10 lbs</span>';
                        notesElement.className = 'error';
                    }
                    hasDryIceError = true;
                }
                else {
                    if(allQuantityZero && quantity > 0){
                        allQuantityZero = false;
                    }
                    // Clear any previous error messages for this product
                    const notesId = 'notes_' + productId;
                    const notesElement = document.getElementById(notesId);
                    if (notesElement && notesElement.innerHTML.includes('Invalid: Please add at least 10 lbs')) {
                        // Restore original content
                        const productRow = input.closest('tr');
                        const priceCell = productRow?.querySelector('td:last-child div');
                        if (priceCell) {
                            // Rebuild the original notes content
                            const unitPrice = input.getAttribute('data-unit-price') || '0.00';
                            const unit = productName.match(/\(([^)]+)\)/)?.[1] || 'unit';
                            let originalContent = `<i>$${parseFloat(unitPrice).toFixed(2)} / ${unit}`;
                            if (productName.toLowerCase().includes('dry ice')) {
                                originalContent += ', <span style="color:red">minimum 10 lbs.</span>';
                            }
                            originalContent += '</i>';
                            notesElement.innerHTML = originalContent;
                            notesElement.className = '';
                        }
                    }
                }
            });
            if(allQuantityZero && !hasDryIceError){
                document.getElementById('anyproducterror').innerHTML = '<span style="color:red">Please select at least one product.</span>';
                return false;
            }else{
                return !hasDryIceError;
            }
        }

        function calculateProductCost(productId, unitPrice) {
            const quantity = parseFloat(document.getElementById(`product_${productId}`).value) || 0;
            const cost = quantity * unitPrice;
            document.getElementById(`product_cost_${productId}`).value = cost.toFixed(2);

            validateDryIceForProduct(productId, quantity);
        }

        function validateDryIceForProduct(productId, quantity) {
            const productRow = document.getElementById(`product_${productId}`).closest('tr');
            const productNameCell = productRow?.querySelector('td:first-child');
            const productName = productNameCell?.textContent.trim() || '';

            const notesId = 'notes_' + productId;
            const notesElement = document.getElementById(notesId);

            if (!notesElement) return;

            if (productName.toLowerCase().includes('dry ice') && quantity > 0 && quantity < 10) {
                // Show error
                const originalContent = notesElement.innerHTML;
                const priceInfo = originalContent.split(',')[0];
                notesElement.innerHTML = '<span style="color:red; font-weight:bold;">Invalid: Please add at least 10 lbs</span>';
                notesElement.className = 'error';
            } else if (notesElement.innerHTML.includes('Invalid: Please add at least 10 lbs')) {
                // Clear error and restore original
                const input = document.getElementById(`product_${productId}`);
                const unitPrice = input.getAttribute('data-unit-price') || '0.00';
                const unit = productName.match(/\(([^)]+)\)/)?.[1] || 'unit';
                let originalContent = `<i>$${parseFloat(unitPrice).toFixed(2)} / ${unit}`;
                if (productName.toLowerCase().includes('dry ice')) {
                    originalContent += ', <span style="color:red">minimum 10 lbs.</span>';
                }
                originalContent += '</i>';
                notesElement.innerHTML = originalContent;
                notesElement.className = '';
            }
        }




        var currentTab = 'order';



        function populateCustomerData() {
            if (window.isLoggedIn && window.customerData) {
                const customer = window.customerData;

                // Populate form fields with customer data
                if (customer.name) document.getElementById('name').value = customer.name;
                if (customer.email) document.getElementById('email').value = customer.email;
                if (customer.phone) document.getElementById('phone').value = customer.phone;
                if (customer.address) document.getElementById('address').value = customer.address;
                if (customer.city) document.getElementById('city').value = customer.city;
                if (customer.postal_code) document.getElementById('postal').value = customer.postal_code;
                if (customer.province) document.getElementById('province').value = customer.province;
            }
        }


        // Modified Tab switching function
        async function showTab(tabName) {
            // Save current form data before switching
            saveFormData();

            // Hide all tab contents
            var contents = document.querySelectorAll('.tab-content');
            contents.forEach(function(content) {
                content.classList.remove('active');
            });

            // Remove selected class from all tabs
            var tabs = document.querySelectorAll('.forsale_menu');
            tabs.forEach(function(tab) {
                tab.classList.remove('selected');
            });

            // Show selected tab content
            var targetContent = document.getElementById(tabName + '-content');
            var targetTab = document.getElementById('tab-' + tabName);

            if (targetContent && targetTab) {
                targetContent.classList.add('active');
                targetTab.classList.add('selected');
                currentTab = tabName;

                // Populate customer data when showing location tab
                if (tabName === 'location') {
                    populateCustomerData();
                }

                // If showing review tab, validate first then populate conditionally
                if (tabName === 'review') {
                    try {
                        // Run all validations including async address check
                        const validationPassed = await runLocationValidations();

                        if (validationPassed) {
                            populateReview();
                        } else {
                            // Validation failed, switch back to location tab and show customer data
                            await showTab('location'); // Switch back to location tab
                            populateCustomerData();
                            return; // Exit early
                        }
                    } catch (error) {
                        console.error('Validation error:', error);
                        // If validation fails due to error, switch back to location
                        await showTab('location');
                        populateCustomerData();
                        return; // Exit early
                    }
                }
            } else {
                console.error('Tab elements not found:', tabName);
            }
        }

        // Helper function to run location validations
        async function runLocationValidations() {
            var checkDate = check_date();
            var companyError = check_company_name();
            var contactError = check_contact();
            var phoneError = check_phone();
            var emailError = check_email();
            var deliveryError = check_delivery();

            // Only run address validation if all other fields are valid (empty string means valid)
            var addressError = '';
            if (checkDate === '' && companyError === '' && contactError === '' && phoneError === '' && emailError === '' && deliveryError === '') {
                addressError = await check_address(); // Async address validation
            } else {
                // Clear any previous address validation message since we're not checking it
                set_msg('address_notes', '', false);
            }

            // Return true if no errors, false if any errors exist
            return (checkDate === '' && addressError === '' && companyError === '' && contactError === '' && phoneError === '' && emailError === '' && deliveryError === '');
        }

        // Modified nextFromLocation function (simplified)
        async function nextFromLocation() {
            var checkDate = check_date();
            var companyError = check_company_name();
            var contactError = check_contact();
            var phoneError = check_phone();
            var emailError = check_email();
            var deliveryError = check_delivery();
            var addressError = '';

            // Run address validation
            if (checkDate === '' && companyError === '' && contactError === '' && phoneError === '' && emailError === '' && deliveryError === '') {
                addressError = await check_address();
            }

            // Only proceed if ALL validations pass (empty string means no error)
            if (checkDate === '' && addressError === '' && companyError === '' && contactError === '' && phoneError === '' && emailError === '' && deliveryError === '') {
                showTab('review');
            } else {
                // Show an alert indicating validation failed
                Swal.fire({
                    icon: 'error',
                    title: 'Validation Error',
                    text: 'Please fix all errors before proceeding to review.'
                });
            }
        }



        function proceedWithSignup() {
            // Hide modal
            document.getElementById('signupModal').style.display = 'none';
            alert('Signup functionality would be implemented here. Proceeding to location for now.');
            showTab('location');
        }

        function proceedAsGuest() {
            // Hide modal and proceed to location tab
            document.getElementById('signupModal').style.display = 'none';
            showTab('location');
        }

        // Close modal if user clicks outside of it
        window.onclick = function(event) {
            var modal = document.getElementById('signupModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }


        async function validateAddress(addressObj) {
            const apiKey = "{{config('services.google.address_api_key')}}";
            const response = await fetch(`https://addressvalidation.googleapis.com/v1:validateAddress?key=${apiKey}`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    address: {
                        regionCode: "CA", // For Canada
                        addressLines: [addressObj.address],
                        locality: addressObj.city,
                        administrativeArea: addressObj.province,
                        postalCode: addressObj.postal
                    }
                })
            });

            const data = await response.json();
            return data;
        }

        // Modified nextFromLocation function to handle async address check
        async function nextFromLocation() {
            var checkDate = check_date();
            var companyError = check_company_name();
            var contactError = check_contact();
            var phoneError = check_phone();
            var emailError = check_email();
            var deliveryError = check_delivery();



            if (checkDate === '' && companyError === '' && contactError === '' && phoneError === '' && emailError === '' && deliveryError === '') {
                addressError = await check_address();
            }

            // Only proceed if all validations pass (empty string means no error)
            if (checkDate === '' && addressError === '' && companyError === '' && contactError === '' && phoneError === '' && emailError === '' && deliveryError === '') {
                showTab('review');
            }
        }

        // Enhanced check_address function - combines existing logic with API validation
        async function check_address() {
            var address = document.getElementById('address').value;
            var city = document.getElementById('city').value;
            var prov = document.getElementById('province').value;
            var postal = document.getElementById('postal').value;
            var error_msg = '';

            // Only run address validation if all other validations pass AND delivery type is "delivery"

            var deliveryType = document.getElementById('delivery_type').value;

            // Your existing basic validation
            if (address === '' || city === '' || prov === '' || postal === '')
                error_msg = 'Please enter all address information.';

            if (postal != '') {
                var no_spaces_postal = postal.replace(/ /g, '');
                no_spaces_postal = no_spaces_postal.toUpperCase();
                document.getElementById('postal').value = no_spaces_postal;
                postal = no_spaces_postal;
                var regex = new RegExp(/^[ABCEGHJKLMNPRSTVXY]\d[ABCEGHJKLMNPRSTVWXYZ]( )?\d[ABCEGHJKLMNPRSTVWXYZ]\d$/i);
                if (!regex.test(postal))
                    error_msg += '<br>Postal code must be in the form A1A1A1.';
            }

            // If basic validation passes, proceed with API validation
            // if (error_msg === '') {
            //     try {
            //         // Call the Google Address Validation API
            //         const result = await validateAddress({
            //             address: address.trim(),
            //             city: city.trim(),
            //             province: prov.trim(),
            //             postal: postal.trim()
            //         });
            //
            //         // Check if address is invalid according to Google API
            //         if (result.result.verdict.possibleNextAction === "FIX" || result.result.verdict.hasUnconfirmedComponents) {
            //             error_msg = 'Address could not be confirmed by validation service. Please verify your address details.';
            //
            //             // Show SweetAlert warning
            //             Swal.fire({
            //                 title: 'Address Validation',
            //                 html: `Address could not be confirmed. Please provide proper address. <br>Street Address, City, Province, Postal`,
            //                 icon: 'warning',
            //                 showCancelButton: false,
            //                 confirmButtonColor: '#d33',
            //             });
            //         }
            //
            //     } catch (error) {
            //         console.error('Address validation API error:', error);
            //         // Don't block submission if API fails, just log the error
            //         console.warn('Address validation service temporarily unavailable, proceeding with basic validation only.');
            //     }
            // }

            // Set the message based on validation results
            if (error_msg === '')
                set_msg('address_notes', '<center><b>VALID</b></center>', false);
            else
                set_msg('address_notes', error_msg, true);

            return error_msg;
        }




        const phpStartMonth = <?php echo $startMonth; ?>;
        const phpStartDay = <?php echo $startDay; ?>;
        const phpStartYear = <?php echo $startYear; ?>;
        const phpCurrentMonth = <?php echo $currentMonth; ?>;
        const phpCurrentYear = <?php echo $currentYear; ?>;

        function updateMonthAndDayOptions() {
            const yearSelect = document.getElementById('year');
            const monthSelect = document.getElementById('month');
            const selectedYear = parseInt(yearSelect.value);

            // Clear and repopulate month options
            monthSelect.innerHTML = '';

            const months = [
                "January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"
            ];

            // Determine which months to show
            let startMonth = 1;
            if (selectedYear === phpCurrentYear) {
                startMonth = phpStartMonth; // Only show current month and future months in current year
            }

            // Add month options
            for (let i = startMonth; i <= 12; i++) {
                const option = document.createElement('option');
                option.value = i;
                option.textContent = months[i - 1];

                // Select the first available month by default
                if (i === startMonth) {
                    option.selected = true;
                }

                monthSelect.appendChild(option);
            }

            // Update day options after month options are set
            updateDayOptions();
        }

        function updateDayOptions() {
            const monthSelect = document.getElementById('month');
            const daySelect = document.getElementById('day');
            const yearSelect = document.getElementById('year');

            const selectedMonth = parseInt(monthSelect.value);
            const selectedYear = parseInt(yearSelect.value);

            // Clear existing options
            daySelect.innerHTML = '';

            // Get number of days in selected month
            const daysInMonth = new Date(selectedYear, selectedMonth, 0).getDate();

            // Determine minimum day based on current selection
            let minDay = 1;

            // Only restrict days if it's the CURRENT month in the CURRENT year
            // For future months or future years, show all days from 1
            if (selectedYear === phpCurrentYear && selectedMonth === phpCurrentMonth) {
                minDay = phpStartDay;
            }

            let firstValidDay = null;

            // Populate day options, excluding weekends
            for (let i = minDay; i <= daysInMonth; i++) {
                // Check if this day is a weekend
                const dateToCheck = new Date(selectedYear, selectedMonth - 1, i);
                const dayOfWeek = dateToCheck.getDay(); // 0 = Sunday, 6 = Saturday

                // Skip weekends
                if (dayOfWeek === 0 || dayOfWeek === 6) {
                    continue;
                }

                const option = document.createElement('option');
                option.value = i;
                option.textContent = i;

                // Track the first valid day
                if (firstValidDay === null) {
                    firstValidDay = i;
                    option.selected = true;
                }

                daySelect.appendChild(option);
            }

            // If no weekdays are available in this month, show a message
            if (daySelect.children.length === 0) {
                const option = document.createElement('option');
                option.value = '';
                option.textContent = 'No weekdays available';
                option.disabled = true;
                option.selected = true;
                daySelect.appendChild(option);
            }
        }

        function check_date() {
            const year = parseInt(document.getElementById('year').value);
            const month = parseInt(document.getElementById('month').value) - 1;
            const day = parseInt(document.getElementById('day').value);
            let error_msg = '';

            const date = new Date(year, month, day);
            const today = new Date();

            // Enhanced time zone aware validation
            const selectedDate = new Date(year, month, day);
            const currentDate = new Date();
            currentDate.setHours(0, 0, 0, 0);

            // Check if selected date is in the past
            if (selectedDate < currentDate) {
                error_msg += 'Cannot select past dates.<br>';
            }

            // Time zone specific validation for same day orders
            // Check multiple time zones: GMT-7 (BC), GMT-6 (Alberta), GMT-4 (Quebec)
            const timeZones = [
                { name: 'Pacific Time (BC)', offset: -7 },
                { name: 'Mountain Time (Alberta)', offset: -6 },
                { name: 'Eastern Time (Quebec)', offset: -4 }
            ];

            let isAfter830InAnyZone = false;

            // Check if it's past 8:30 AM in any of the target time zones
            timeZones.forEach(zone => {
                const utcTime = new Date();
                const zoneTime = new Date(utcTime.getTime() + (zone.offset * 60 * 60 * 1000));
                const hour = zoneTime.getUTCHours();
                const minute = zoneTime.getUTCMinutes();

                if ((hour > 8) || (hour === 8 && minute > 30)) {
                    isAfter830InAnyZone = true;
                }
            });

            // Same day order validation with time zone consideration
            if (
                date.getFullYear() === today.getFullYear() &&
                date.getMonth() === today.getMonth() &&
                date.getDate() === today.getDate()
            ) {
                if (isAfter830InAnyZone) {
                    error_msg += 'Same day orders must be completed before 8:30am in BC/Alberta/Quebec time zones.<br>';
                }
            }

            // Display validation result
            if (error_msg === '') {
                set_msg('date_notes', '<center><b>VALID</b></center>', false);
            } else {
                set_msg('date_notes', error_msg, true);
            }

            return error_msg;
        }

        // Initialize day options on page load
        document.addEventListener('DOMContentLoaded', function() {
            updateMonthAndDayOptions();
        });

        function check_delivery() {
            var error_msg = '';
            var delivery = document.getElementById('delivery_type').value;

            if (delivery === '') {
                error_msg = 'Please select a valid option.<br>';
            }

            if (error_msg === '') {
                set_msg('delivery_notes', '<center><b>VALID</b></center>', false);
            } else {
                set_msg('delivery_notes', error_msg, true);
            }

            return error_msg;
        }


        function check_company_name() {
            var error_msg = '';
            if (document.getElementById('location_type').value == 1) {
                document.getElementById('company').value = 'Residence';
            }
            // else {
            //     if (document.getElementById('company').value === '')
            //         error_msg = "Please enter the company name here.";
            // }
            set_msg('company_notes', error_msg, true);
            return error_msg;
        }

        function check_contact() {
            var error_msg = '';
            var email = document.getElementById('email').value.trim();
            var phone = document.getElementById('phone').value.trim();

            // Require BOTH email AND phone
            if (email === '' || phone === '') {
                error_msg = 'Please enter both email and phone number so we can contact you if there is any trouble with your order.';
            }

            set_msg('phone_notes', error_msg, true);
            return error_msg;
        }

        function check_phone() {
            var error_msg = '';
            if (document.getElementById('phone').value != '') {
                var phoneno = /^\(?([0-9]{3})\)?[-. ]?([0-9]{3})[-. ]?([0-9]{4})$/;
                if (!phoneno.test(document.getElementById('phone').value))
                    error_msg = 'Please enter a valid 10 digit phone number.';
                set_msg('phone_notes', error_msg, true);
            }
            return error_msg;
        }

        function check_email() {
            var error_msg = '';
            if (document.getElementById('email').value != '') {
                var re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
                if (!re.test(document.getElementById('email').value))
                    error_msg = 'Please enter a valid email.';
                set_msg('email_notes', error_msg, true);
            }
            return error_msg;
        }

        function set_name() {
            if (document.getElementById('location_type').value == 1) {
                document.getElementById('company').value = 'Residence';
            } else {
                document.getElementById('company').value = '';
            }
        }

        // Utility functions
        function set_msg(elementId, message, isError) {
            var element = document.getElementById(elementId);
            if (element) {
                element.innerHTML = message;
                if (isError) {
                    element.className = 'error';
                } else {
                    element.className = 'valid';
                }
            }
        }


        function setHiddenValue(name, value) {
            let field = document.querySelector(`input[name="${name}"]`);
            if (!field) {
                field = document.createElement('input');
                field.type = 'hidden';
                field.name = name;
                document.getElementById('orderForm').appendChild(field);
            }
            field.value = value;
        }

        // 1. Fix the populateReview function to properly handle dynamic products
        // One random id per order attempt. Re-sending the same form (retry, double click,
        // lost response) re-sends the same id, so the server returns the order it already
        // made instead of creating a second one. A new id is made each time the review is
        // rebuilt, so an edited order is never mistaken for the earlier one.
        let submissionTokenFallback = null;

        function resetSubmissionToken() {
            submissionTokenFallback = null;
            try { sessionStorage.removeItem('orderSubmissionToken'); } catch (e) { /* storage unavailable */ }
        }

        function getSubmissionToken() {
            try {
                const saved = sessionStorage.getItem('orderSubmissionToken');
                if (saved) return saved;
            } catch (e) { /* storage unavailable */ }

            if (!submissionTokenFallback) {
                submissionTokenFallback = (window.crypto && crypto.randomUUID)
                    ? crypto.randomUUID()
                    : Date.now().toString(36) + Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2);
            }
            try { sessionStorage.setItem('orderSubmissionToken', submissionTokenFallback); } catch (e) { /* storage unavailable */ }
            return submissionTokenFallback;
        }

        // Bumped on every review refresh so a slow, older delivery quote can't overwrite a newer one.
        let reviewRun = 0;

        function setSubmitEnabled(enabled) {
            const btn = document.querySelector('.submit-btn');
            if (btn) btn.disabled = !enabled;
        }

        function populateReview() {
            console.log('🎯 populateReview() started');
            const run = ++reviewRun;
            resetSubmissionToken();

            const getValue = (id) => document.getElementById(id)?.value.trim() || '';

            // ✅ Calculate subtotal from all products
            let subtotal = 0;
            let pstableSubtotal = 0; // portion subject to PST (box-unit products only)
            let summaryHtml = '';
            let costsHtml = '';
            let totalIceAmount = 0;
            let productHiddenFields = '';
            let itemIndex = 0;

            const productInputs = document.querySelectorAll('[name^="product["][name$="[quantity]"]');

            productInputs.forEach(input => {
                const match = input.name.match(/product\[(\d+)\]\[quantity\]/);
                if (!match) return;

                const productId = match[1];
                const quantity = parseFloat(input.value) || 0;

                if (quantity <= 0) return; // Skip zero quantity products

                // Get product name from order tab row
                const productRow = input.closest('tr');
                const productNameCell = productRow?.querySelector('td:first-child');
                const productName = productNameCell?.textContent.trim() || `Product ${productId}`;

                // Get unit price from data attribute
                const unitPrice = parseFloat(input.getAttribute('data-unit-price')) || 0;

                // Get cost from cost input
                const costInput = document.getElementById(`product_cost_${productId}`);
                const cost = parseFloat(costInput?.value) || (quantity * unitPrice);

                subtotal += cost;
                if (input.getAttribute('data-unit') === 'box') {
                    pstableSubtotal += cost;
                }
                totalIceAmount += quantity;

                // Build review display rows
                summaryHtml += `<div>${productName} x ${quantity}</div>`;
                costsHtml += `<div>$${cost.toFixed(2)}</div>`;

                // ✅ Build hidden fields matching backend: items[index][product_id], items[index][amount_of_items], etc.
                productHiddenFields += `
            <input type="hidden" name="items[${itemIndex}][product_id]" value="${productId}">
            <input type="hidden" name="items[${itemIndex}][amount_of_items]" value="${quantity}">
            <input type="hidden" name="items[${itemIndex}][unit_price]" value="${unitPrice.toFixed(2)}">
            <input type="hidden" name="items[${itemIndex}][total_price]" value="${cost.toFixed(2)}">
        `;

                itemIndex++;
            });

            // ✅ Inject into review display
            document.getElementById('order-summary').innerHTML = summaryHtml || '<div>No products selected</div>';
            document.getElementById('order-costs').innerHTML = costsHtml;

            // ✅ Inject product hidden fields into form
            document.getElementById('product-hidden-fields').innerHTML = productHiddenFields;

            // ✅ Set ice amount hidden field
            document.getElementById('hidden_amount_of_ice').value = totalIceAmount;

            console.log('🧮 Subtotal:', subtotal, '| Items:', itemIndex, '| Total Ice:', totalIceAmount);

            // Handle delivery type
            const deliveryType = document.getElementById('delivery_type')?.value;
            const pickupDeliveryInput = document.querySelector('[name="pickup_delivery"]');
            const deliveryCostElement = document.getElementById('delivery-cost');

            let deliveryCost = 0;

            if (deliveryType === 'pickup') {
                if (pickupDeliveryInput) pickupDeliveryInput.value = 'pickup';
                if (deliveryCostElement) deliveryCostElement.textContent = '$0.00';
                calculateTotalsAndFinalize(subtotal, deliveryCost, pstableSubtotal);

            } else if (deliveryType === 'delivery') {
                if (pickupDeliveryInput) pickupDeliveryInput.value = 'delivery';

                // No order can be submitted until a real delivery price is in.
                setSubmitEnabled(false);
                document.getElementById('hidden_delivery_cost').value = '';
                if (deliveryCostElement) deliveryCostElement.textContent = 'Calculating...';

                // ✅ Now set hidden_amount_of_ice BEFORE calling getDeliveryQuoteForReview
                // so it reads the correct value
                getDeliveryQuoteForReview(totalIceAmount)
                    .then(quoteTotal => {
                        if (run !== reviewRun) return;
                        deliveryCost = quoteTotal;
                        if (deliveryCostElement) deliveryCostElement.textContent = `$${deliveryCost.toFixed(2)}`;
                        calculateTotalsAndFinalize(subtotal, deliveryCost, pstableSubtotal);
                    })
                    .catch(error => {
                        if (run !== reviewRun) return;
                        console.error('❌ Error getting delivery quote:', error);
                        // Don't fall back to $0 delivery: leave the order blocked and offer a retry.
                        document.getElementById('hidden_delivery_cost').value = '';
                        document.getElementById('tax-total').innerHTML = '&mdash;';
                        if (deliveryCostElement) {
                            deliveryCostElement.innerHTML = '<span style="color:#c00">Unavailable</span> <a href="#" onclick="populateReview(); return false;">Retry</a>';
                        }
                        setSubmitEnabled(false);
                    });
                return;

            } else {
                deliveryCost = 0.00;
                if (pickupDeliveryInput) pickupDeliveryInput.value = '0.00';
                if (deliveryCostElement) deliveryCostElement.textContent = `$${deliveryCost.toFixed(2)}`;
                calculateTotalsAndFinalize(subtotal, deliveryCost, pstableSubtotal);
            }
        }

        function calculateTotalsAndFinalize(subtotal, delivery, pstableSubtotal = 0) {
            // Delivery, Tax & Total
            // GST 5% applies broadly (products, delivery, hazmat). PST 7% applies
            // only to the Styrofoam box portion of the subtotal. Confirmed by
            // Tyler 2026-08-27.
            const hazmat = 12.00;
            const gst = (subtotal + delivery + hazmat) * 0.05;
            const pst = pstableSubtotal * 0.07;
            const tax = gst + pst;
            const total = subtotal + delivery + tax + hazmat;

            document.getElementById('tax-total').innerHTML =
                `$${tax.toFixed(2)}<br><hr><b>$${total.toFixed(2)}</b>`;

            // Set cost hidden fields
            document.getElementById('hidden_hazmat').value = hazmat.toFixed(2);
            document.getElementById('hidden_subtotal').value = subtotal.toFixed(2);
            document.getElementById('hidden_tax').value = tax.toFixed(2);
            document.getElementById('hidden_total_cost').value = total.toFixed(2);
            document.getElementById('hidden_delivery_cost').value = delivery.toFixed(2);

            setSubmitEnabled(true);

            // Continue with the rest of the original function
            populateLocationAndContactInfo();
        }

        function populateLocationAndContactInfo() {
            const getValue = (id) => document.getElementById(id)?.value.trim() || '';
            // Location
            const company = getValue('company');
            const address = getValue('address');
            const city = getValue('city');
            const province = getValue('province');
            const postal = getValue('postal');
            let locationHtml = '';
            if (company && company !== 'Residence') locationHtml += `<b>${company}</b><br>`;
            if (address) locationHtml += `${address}<br>`;
            if (city || province || postal) {
                locationHtml += city;
                if (province) locationHtml += `, ${province}`;
                if (postal) locationHtml += ` ${postal}`;
            }
            document.getElementById('delivery-location').innerHTML = locationHtml || 'No address provided';

            // Delivery Date
            const month = getValue('month');
            const day = getValue('day');
            const year = getValue('year');
            const monthNames = [
                "January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"
            ];
            const dateHtml = (month && day && year)
                ? `${monthNames[month - 1]} ${day}, ${year}`
                : 'No date selected';

            document.getElementById('delivery-date').innerHTML = dateHtml;
            document.getElementById('hidden_delivery_date').value = (month && day && year)
                ? `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`
                : '';

            // Contact Info
            const name = getValue('name');
            const phone = getValue('phone');
            const email = getValue('email');
            let contactHtml = '';
            if (name) contactHtml += `<b>${name}</b><br>`;
            if (phone) contactHtml += `Phone: ${phone}<br>`;
            if (email) contactHtml += `Email: ${email}`;
            document.getElementById('contact-info').innerHTML = contactHtml || 'No contact info';

            // Notes
            const notes = getValue('notes');
            document.getElementById('order-notes').innerHTML = notes || 'No special notes';

            // Hidden fields for order data
            document.getElementById('hidden_customer_name').value = name;
            document.getElementById('hidden_email').value = email;
            document.getElementById('hidden_phone').value = phone;
            document.getElementById('hidden_location_name').value = company;
            document.getElementById('hidden_address').value = address;
            document.getElementById('hidden_city').value = city;
            document.getElementById('hidden_postal_code').value = postal;
            document.getElementById('hidden_province').value = province;
            document.getElementById('hidden_notes').value = notes;
        }

        // Integrated delivery quote function for the review
        async function getDeliveryQuoteForReview() {
            console.log('🚀 getDeliveryQuoteForReview() started');

            const getValue = (id) => document.getElementById(id)?.value.trim() || '';

            const formData = {
                address: getValue('address'),
                city: getValue('city'),
                province: getValue('province'),
                email: getValue('email'),
                name: getValue('name'),
                phone: getValue('phone'),
                iceAmount: parseFloat(getValue('hidden_amount_of_ice')) || 1,
                postal: getValue('postal'),
                locationName: getValue('company'),
                unit: getValue('unit') || ''
            };

            console.log('📋 Form Data collected:', formData);

            const requiredAddressFields = [formData.address, formData.city, formData.province, formData.postal];
            if (!requiredAddressFields.every(val => val && val.trim())) {
                console.error('❌ Missing required address fields');

                Swal.fire({
                    icon: 'error',
                    title: 'Missing Address Information',
                    text: 'Please provide complete address details including street, city, province, and postal code.',
                    confirmButtonColor: '#d33'
                });

                throw new Error('Missing required address fields');
            }

            console.log('✅ Address fields validated');
            console.log('🔍 Fetching closest supplier...');

            try {
                const supplierResponse = await fetch(`/test-closest-supplier?street=${encodeURIComponent(formData.address)}&city=${encodeURIComponent(formData.city)}&province=${encodeURIComponent(formData.province)}`);

                console.log('📡 Supplier response status:', supplierResponse.status);

                if (!supplierResponse.ok) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Supplier Service Error',
                        text: 'Unable to connect to supplier service. Please try again later.',
                        confirmButtonColor: '#d33'
                    });
                    throw new Error(`Supplier API returned ${supplierResponse.status}`);
                }

                const supplierData = await supplierResponse.json();
                console.log('📦 Supplier Data received:', supplierData);

                // Check if supplier was found
                if (!supplierData.closest_supplier || !supplierData.closest_supplier.id || supplierData.message === 'No supplier found') {
                    console.error('❌ No supplier found in response');

                    Swal.fire({
                        icon: 'error',
                        title: 'Service Area Not Available',
                        html: `We're sorry, but we currently don't deliver to this location:<br><br>
                       <strong>${formData.address}, ${formData.city}, ${formData.province} ${formData.postal}</strong><br><br>
                       Please verify your address or contact us for delivery options.`,
                        confirmButtonColor: '#d33',
                        confirmButtonText: 'Go Back'
                    });

                    throw new Error('No supplier found for this location');
                }

                const supplier = supplierData.closest_supplier;
                console.log('✅ Supplier found:', supplier);

                // Update supplier_id if element exists
                const supplierIdElement = document.getElementById('hidden_supplier_id');
                if (supplierIdElement) {
                    supplierIdElement.value = supplier.id;
                }

                // Get delivery quote
                const quotePayload = {
                    supplier_id: supplier.id,
                    delivery: {
                        name: formData.locationName.trim() || 'N/A',
                        street: formData.address.trim(),
                        unit: formData.unit.trim() || '',
                        city: formData.city.trim(),
                        province: formData.province.trim(),
                        postal_code: formData.postal.trim(),
                        contact: formData.name.trim() || 'N/A',
                        phone: formData.phone.trim() || 'N/A',
                        email: formData.email.trim() || 'N/A'
                    },
                    weight: formData.iceAmount
                };

                console.log('💰 Requesting quote with payload:', quotePayload);

                const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

                const quoteResponse = await fetch('/get-delivery-quote', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(quotePayload)
                });

                console.log('📡 Quote response status:', quoteResponse.status);

                const responseText = await quoteResponse.text();
                console.log('📄 Quote response text:', responseText);

                if (!quoteResponse.ok) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Quote Service Error',
                        text: 'Unable to calculate delivery cost. Please try again.',
                        confirmButtonColor: '#d33'
                    });
                    throw new Error(`Quote API returned ${quoteResponse.status}: ${responseText}`);
                }

                let quoteData;
                try {
                    quoteData = JSON.parse(responseText);
                    console.log('💵 Quote data parsed:', quoteData);
                } catch (e) {
                    console.error('❌ Failed to parse JSON:', e);

                    Swal.fire({
                        icon: 'error',
                        title: 'System Error',
                        text: 'Invalid response from quote service. Please contact support.',
                        confirmButtonColor: '#d33'
                    });

                    throw new Error('Invalid JSON response from quote API');
                }

                if (quoteData.success && quoteData.total) {
                    console.log('✅ Quote successful, total:', quoteData.total);
                    return parseFloat(quoteData.total);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Quote Failed',
                        text: quoteData.error || 'Unable to calculate delivery cost for this location.',
                        confirmButtonColor: '#d33'
                    });
                    throw new Error(quoteData.error || 'Quote failed');
                }

            } catch (error) {
                console.error('💥 Error in getDeliveryQuoteForReview:', error);
                throw error;
            }
        }
        // Handle form submission with AJAX

        document.getElementById('orderForm').addEventListener('submit', function(e) {
            e.preventDefault();

            // Validate required checkbox
            if (!document.getElementById('accept').checked) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Required',
                    text: 'Please accept the safety information and disclaimer before submitting.'
                });
                return;
            }

            // A delivery order needs a real delivery price before it can be placed.
            const isDelivery = document.getElementById('hidden_pickup_delivery').value === 'delivery';
            if (isDelivery && !(parseFloat(document.getElementById('hidden_delivery_cost').value) > 0)) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Delivery cost not available',
                    text: 'We could not calculate the delivery cost yet. Please use Retry next to Delivery, or choose pickup.'
                });
                return;
            }

            document.getElementById('hidden_submission_token').value = getSubmissionToken();

            // Prepare form data
            const formData = new FormData(this);

            // Debug: Log form data before sending
            console.log('Form data being sent:');
            for (let [key, value] of formData.entries()) {
                console.log(key + ': ' + value);
            }

            const submitBtn = document.querySelector('.submit-btn');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Submitting...';
            submitBtn.disabled = true;

            fetch('/order/ajax-create-from-review', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                }
            })
                // Keep the HTTP status with the body; a proxy/server error page isn't JSON.
                .then(response => response.json()
                    .catch(() => ({}))
                    .then(data => ({ status: response.status, data })))
                .then(({ status, data }) => {
                    // console.log('Response data:', data);

                    if (data.success) {
                        // Clear saved data on success
                        clearSavedData();

                        // Redirect to payment loading page with order ID
                        window.location.href = `/payment-redirect/${data.order_id}`;
                    } else {
                        const escapeHtml = (s) => String(s).replace(/[&<>"']/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
                        const swalOptions = { icon: 'error', title: 'Submission Failed' };

                        if (status === 419) {
                            // Session/CSRF expired: the saved order details come back after a refresh.
                            swalOptions.title = 'Session Expired';
                            swalOptions.text = 'Your session expired while you were on this page. Please refresh the page and submit again. Your order details have been saved.';
                        } else if (data.errors && typeof data.errors === 'object') {
                            // Show what is actually wrong, not just "(and 2 more errors)".
                            const problems = [...new Set(Object.values(data.errors).flat())].slice(0, 6);
                            swalOptions.title = 'Please check your order';
                            swalOptions.html = '<ul style="text-align:left;margin:0;padding-left:1.2em">'
                                + problems.map(p => '<li>' + escapeHtml(p) + '</li>').join('') + '</ul>';
                        } else if (data.message) {
                            swalOptions.text = data.message;
                        } else if (status >= 500) {
                            swalOptions.text = 'Our server had a problem. Nothing has been charged. Please try again in a moment.';
                        } else {
                            swalOptions.text = 'Something went wrong. Please try again.';
                        }

                        Swal.fire(swalOptions).then(() => {
                            submitBtn.textContent = originalText;
                            submitBtn.disabled = false;
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Network Error',
                        text: 'A network error occurred. Please try again.'
                    }).then(() => {
                        submitBtn.textContent = originalText;
                        submitBtn.disabled = false;
                    });
                });
        });


        document.addEventListener('DOMContentLoaded', function() {
            restoreFormData();
            setupAutoSave();

            // Populate review if we're on the review tab
            if (currentTab === 'review') {
                populateReview();
            }
        });


        function saveFormData() {
            const formData = {
                // Product data
                products: [],
                // Location data
                location: {
                    province: document.getElementById('province')?.value || '',
                    city: document.getElementById('city')?.value || '',
                    address: document.getElementById('address')?.value || '',
                    postal: document.getElementById('postal')?.value || '',
                    month: document.getElementById('month')?.value || '',
                    day: document.getElementById('day')?.value || '',
                    year: document.getElementById('year')?.value || '',
                    name: document.getElementById('name')?.value || '',
                    location_type: document.getElementById('location_type')?.value || '',
                    company: document.getElementById('company')?.value || '',
                    phone: document.getElementById('phone')?.value || '',
                    email: document.getElementById('email')?.value || '',
                    notes: document.getElementById('notes')?.value || '',
                },
                timestamp: Date.now()
            };

            // Save product quantities and costs
            const productInputs = document.querySelectorAll('[name^="product["][name$="[quantity]"]');
            productInputs.forEach(input => {
                const match = input.name.match(/product\[(\d+)\]\[quantity\]/);
                if (match) {
                    const productId = match[1];
                    const quantity = input.value;
                    const costInput = document.querySelector(`[name="product[${productId}][cost]"]`);
                    const cost = costInput ? costInput.value : '0.00';

                    formData.products.push({
                        product_id: productId,
                        quantity: quantity,
                        cost: cost
                    });
                }
            });

            sessionStorage.setItem('orderFormData', JSON.stringify(formData));
        }

        function restoreFormData() {
            const savedData = sessionStorage.getItem('orderFormData');

            if (savedData) {
                try {
                    const formData = JSON.parse(savedData);

                    // Restore product data
                    if (formData.products && Array.isArray(formData.products)) {
                        formData.products.forEach(item => {
                            const quantityInput = document.querySelector(`[name="product[${item.product_id}][quantity]"]`);
                            const costInput = document.querySelector(`[name="product[${item.product_id}][cost]"]`);

                            if (quantityInput) quantityInput.value = item.quantity;
                            if (costInput) costInput.value = item.cost;
                        });
                    }

                    // Restore location data
                    if (formData.location) {
                        const loc = formData.location;
                        Object.keys(loc).forEach(key => {
                            const element = document.getElementById(key);
                            if (element && loc[key]) {
                                element.value = loc[key];
                            }
                        });
                    }

                } catch (error) {
                    console.error('Error restoring form data:', error);
                    sessionStorage.removeItem('orderFormData');
                }
            }
        }


        // 3. CALL restoreFormData() when the page loads
        document.addEventListener('DOMContentLoaded', function() {
            restoreFormData();
        });

        // 4. ALSO ADD auto-save on form changes (optional but recommended)
        function setupAutoSave() {
            // Auto-save on product quantity changes
            const productInputs = document.querySelectorAll('[name^="product["][name$="[quantity]"]');
            productInputs.forEach(input => {
                input.addEventListener('input', saveFormData);
                input.addEventListener('blur', saveFormData);
            });

            // Auto-save on location form changes
            const locationInputs = ['province', 'city', 'address', 'postal', 'month', 'day', 'year',
                'name', 'location_type', 'company', 'phone', 'email', 'notes'];
            locationInputs.forEach(id => {
                const element = document.getElementById(id);
                if (element) {
                    element.addEventListener('input', saveFormData);
                    element.addEventListener('blur', saveFormData);
                }
            });
        }

        function clearSavedData() {
            sessionStorage.removeItem('orderFormData');
        }


    </script>



    </body>
@endsection
