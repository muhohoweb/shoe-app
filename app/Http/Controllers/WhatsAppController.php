<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    public function webhook(Request $request)
    {
        if ($request->isMethod('get')) {
            $verifyToken = env('WHATSAPP_VERIFY_TOKEN');
            if ($request->query('hub_verify_token') === $verifyToken) {
                return response($request->query('hub_challenge'), 200);
            }
            return response('Invalid token', 403);
        }

        // Extract incoming message
        $data = $request->all();

        $entry = $data['entry'][0] ?? null;
        $changes = $entry['changes'][0]['value'] ?? null;

        // Check if it's an incoming message (not a status update)
        if (isset($changes['messages'])) {
            $message = $changes['messages'][0];
            $from = $message['from'];           // Customer's phone number
            $text = $message['text']['body'];   // Message content
            $messageId = $message['id'];

            Log::info("Message from {$from}: {$text}");

            // TODO: Store in DB or process as needed
        }

        return response('OK', 200);
    }
}
