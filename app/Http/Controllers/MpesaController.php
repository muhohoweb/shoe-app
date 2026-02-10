<?php

namespace App\Http\Controllers;

use App\Models\MpesaTransaction;
use Iankumu\Mpesa\Facades\Mpesa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use function Pest\Laravel\json;

class MpesaController extends Controller
{
    /**
     * Show M-Pesa settings page
     */
    public function settings()
    {
        return Inertia::render('settings/Mpesa', [
            'balance' => session('mpesa_balance'),
            'balanceError' => session('mpesa_balance_error'),
        ]);
    }

    /**
     * Query account balance
     */
    public function queryBalance()
    {
        try {

            $shortcode = env('MPESA_BUSINESS_SHORTCODE','');
            $identiertype = 4;
            $remarks = "ONLINE CHECK BALANCE";
            $result_url = env('MPESA_BALANCE_TIMEOUT_URL');
            $timeout_url = env('MPESA_BALANCE_TIMEOUT_URL');
            $shortCodeType = "C2B";



            $response = Mpesa::accountbalance(
                $shortcode,
                $identiertype,
                $remarks,
                $result_url,
                $timeout_url,
                $shortCodeType
            );
            $result = $response->json();

            Log::info('M-Pesa Balance Query Response', $result);

            if (isset($result['ResponseCode']) && $result['ResponseCode'] === '0') {
                // Balance is returned via callback, so we return success message
                return back()->with('mpesa_balance', 'Balance request sent. Check back shortly.');
            }

            return back()->with('mpesa_balance_error', $result['ResponseDescription'] ?? 'Failed to query balance');

        } catch (\Exception $e) {
            Log::error('M-Pesa Balance Query Error: ' . $e->getMessage());
            return back()->with('mpesa_balance_error', 'Failed to connect to M-Pesa');
        }
    }

    public function transactionStatusResponse(Request $request){
        Log::info(json_encode($request));
    }

    /**
     * Handle balance callback from Safaricom
     */
    public function balanceCallback(Request $request)
    {
        Log::info('=== M-PESA BALANCE CALLBACK ===');
        Log::info('Raw content: ' . $request->getContent());

        // Store balance in cache or database for later retrieval
        $result = $request->input('Result');

        if ($result && isset($result['ResultCode']) && $result['ResultCode'] == 0) {
            $balanceData = $result['ResultParameters']['ResultParameter'] ?? [];

            $balanceInfo = [];
            foreach ($balanceData as $param) {
                $balanceInfo[$param['Key']] = $param['Value'] ?? null;
            }

            // Store in cache for 5 minutes
            cache()->put('mpesa_account_balance', $balanceInfo, now()->addMinutes(5));

            Log::info('Balance stored', $balanceInfo);
        }

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    /**
     * Get cached balance (API endpoint for polling)
     */
    public function getBalance()
    {
        $balance = cache()->get('mpesa_account_balance');

        if (!$balance) {
            return response()->json([
                'success' => false,
                'message' => 'No balance data available',
            ]);
        }

        return response()->json([
            'success' => true,
            'balance' => $balance,
        ]);
    }

    /**
     * Handle STK Push callback from Safaricom
     */
    public function callback(Request $request)
    {
        Log::info('=== M-PESA CALLBACK RECEIVED ===');
        Log::info('Raw content: ' . $request->getContent());

        $callback = $request->input('Body.stkCallback');

        if (!$callback) {
            Log::warning('Invalid callback - no stkCallback found');
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Invalid']);
        }

        $checkoutRequestId = $callback['CheckoutRequestID'];
        $resultCode = $callback['ResultCode'];
        $resultDesc = $callback['ResultDesc'];

        Log::info('Callback details', [
            'checkout_request_id' => $checkoutRequestId,
            'result_code' => $resultCode,
            'result_desc' => $resultDesc,
        ]);

        $transaction = MpesaTransaction::where('checkout_request_id', $checkoutRequestId)->first();

        if (!$transaction) {
            Log::warning('Transaction not found: ' . $checkoutRequestId);
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        }

        $metadata = [];
        foreach ($callback['CallbackMetadata']['Item'] ?? [] as $item) {
            $metadata[$item['Name']] = $item['Value'] ?? null;
        }

        Log::info('Callback metadata', $metadata);

        $transaction->update([
            'result_code' => $resultCode,
            'result_desc' => $resultDesc,
            'mpesa_receipt_number' => $metadata['MpesaReceiptNumber'] ?? null,
            'status' => $resultCode == 0 ? 'completed' : 'failed',
            'callback_data' => $callback,
        ]);

        if ($resultCode == 0 && $transaction->order) {
            $transaction->order->update([
                'payment_status' => 'paid',
                'mpesa_code' => $metadata['MpesaReceiptNumber'] ?? null,
            ]);
            Log::info('Order updated to paid: ' . $transaction->order->id);
        }

        Log::info('=== M-PESA CALLBACK COMPLETE ===');

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }

    /**
     * Check payment status
     */
    public function checkStatus(string $identifier)
    {
//        $transaction = MpesaTransaction::query()->where('checkout_request_id', $identifier)
//            ->orWhere('merchant_request_id', $identifier)
//            ->orWhere('mpesa_receipt_number', $identifier)
//            ->first();
//
//        if (!$transaction) {
//            return response()->json(['status' => 'not_found'], 404);
//        }
        $shortcode = config('mpesa.shortcode');
        $identiertype = 4;
        $remarks = "ONLINE CHECK BALANCE";

     $response =    Mpesa::transactionStatus(
            $shortcode,
            $identifier,
            $identiertype,
            $remarks,
         config('mpesa.callbacks.status_result_url'),
         config('mpesa.callbacks.balance_timeout_url'),
         4,
         '',
        );


        Log::info(json_encode($response));
        return json_encode($response);
        return response()->json([
            'status' => 'ok',
            'mpesa_receipt' => 'done'//$transaction->mpesa_receipt_number,
        ]);
    }
}