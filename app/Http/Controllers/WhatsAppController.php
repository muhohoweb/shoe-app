<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    public function webhook(Request $request)
    {
        // Verification (GET request from Meta)
        if ($request->isMethod('get')) {
            $verifyToken = env('WHATSAPP_VERIFY_TOKEN'); // Set this in .env

            if ($request->query('hub_verify_token') === $verifyToken) {
                return response($request->query('hub_challenge'), 200);
            }

            return response('Invalid token', 403);
        }

        // Incoming webhook (POST request)
        Log::info('WhatsApp Webhook:', $request->all());

        return response('OK', 200);
    }
}
