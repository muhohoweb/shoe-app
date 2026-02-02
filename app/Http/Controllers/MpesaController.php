<?php

namespace App\Http\Controllers;

use App\Models\MpesaTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MpesaController extends Controller
{
    /**
     * Handle STK Push callback from Safaricom
     */
    public function callback(Request $request)
    {
        Log::info('M-Pesa Callback', $request->all());

        $callback = $request->input('Body.stkCallback');

        if (!$callback) {
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Invalid']);
        }

        $checkoutRequestId = $callback['CheckoutRequestID'];
        $resultCode = $callback['ResultCode'];
        $resultDesc = $callback['ResultDesc'];

        $transaction = MpesaTransaction::where('checkout_request_id', $checkoutRequestId)->first();

        if (!$transaction) {
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        // Parse metadata
        $metadata = [];
        foreach ($callback['CallbackMetadata']['Item'] ?? [] as $item) {
            $metadata[$item['Name']] = $item['Value'] ?? null;
        }

        $transaction->update([
            'result_code' => $resultCode,
            'result_desc' => $resultDesc,
            'mpesa_receipt_number' => $metadata['MpesaReceiptNumber'] ?? null,
            'status' => $resultCode == 0 ? 'completed' : 'failed',
            'callback_data' => $callback,
        ]);

        // Update order if payment successful
        if ($resultCode == 0 && $transaction->order) {
            $transaction->order->update([
                'payment_status' => 'paid',
                'mpesa_code' => $metadata['MpesaReceiptNumber'] ?? null,
            ]);
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    /**
     * Check payment status (for polling from frontend)
     */
    public function checkStatus(string $checkoutRequestId)
    {
        $transaction = MpesaTransaction::where('checkout_request_id', $checkoutRequestId)->first();

        if (!$transaction) {
            return response()->json(['status' => 'not_found'], 404);
        }

        return response()->json([
            'status' => $transaction->status,
            'mpesa_receipt' => $transaction->mpesa_receipt_number,
        ]);
    }
}