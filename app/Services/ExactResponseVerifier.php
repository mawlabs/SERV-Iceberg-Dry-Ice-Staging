<?php

namespace App\Services;

use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Checks that a payment callback really came from the Exact gateway and
 * matches the invoice, before the callback is allowed to change anything.
 *
 * The gateway returns x_MD5_Hash = MD5(response key + login + x_trans_id +
 * x_amount). The response key is a secret set on the Exact payment page and
 * put in EXACT_RESPONSE_KEY. Without it the hash cannot be checked.
 */
class ExactResponseVerifier
{
    /**
     * @return string|null null when the response is acceptable, otherwise the reason it was rejected
     */
    public function verify(Request $request, Invoice $invoice): ?string
    {
        $responseCode = $request->get('x_response_code');
        $amount       = $request->get('x_amount');
        $success      = (string) $responseCode === '1';

        // The amount the gateway reports must be the amount we asked it to charge.
        if ($amount === null || $amount === '') {
            if ($success) {
                return 'missing amount';
            }
        } elseif (abs((float) $amount - (float) $invoice->total_amount) >= 0.005) {
            return 'amount mismatch (got ' . $amount . ', invoice is ' . $invoice->total_amount . ')';
        }

        if ($success && !$request->filled('x_trans_id')) {
            return 'missing transaction id';
        }

        $responseKey = config('services.exact.response_key');

        if (empty($responseKey)) {
            Log::warning('EXACT_RESPONSE_KEY is not set: payment callback hash was NOT verified.', [
                'invoice_number' => $invoice->invoice_number,
            ]);

            return null;
        }

        $given = strtolower((string) $request->get('x_MD5_Hash'));
        if ($given === '') {
            return 'missing response hash';
        }

        $expected = md5($responseKey . config('services.exact.login') . $request->get('x_trans_id') . $amount);

        return hash_equals($expected, $given) ? null : 'response hash mismatch';
    }
}
