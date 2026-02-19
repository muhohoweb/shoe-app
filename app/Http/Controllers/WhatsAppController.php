<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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

    public function sendWhatsAppMessage(Request $request): bool {
        $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string',
        ]);

        $response = Http::post(config('services.whats_app_service.key'), [
            'phone' => $request->phone,
            'message' => $request->message,
        ]);

        return $response->successful();
    }

    public function sendDispatchNotification(Request $request)
    {
        $response = Http::withToken(env('WHATSAPP_ACCESS_TOKEN'))
            ->post('https://graph.facebook.com/v21.0/' . env('WHATSAPP_PHONE_NUMBER_ID') . '/messages', [
                'messaging_product' => 'whatsapp',
                'to' => $request->to,
                'type' => 'template',
                'template' => [
                    'name' => 'dispatch',
                    'language' => ['code' => 'en'],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => $request->name],
                                ['type' => 'text', 'text' => $request->order_id],
                                ['type' => 'text', 'text' => $request->destination],
                                ['type' => 'text', 'text' => $request->delivery_date],
                            ]
                        ]
                    ]
                ]
            ]);

        return $response->json();
    }
}
