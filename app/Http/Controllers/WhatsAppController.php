<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    public function webhook(Request $request)
    {
        Log::info('Full webhook payload', $request->all());

        if ($request->isMethod('get')) {
            return response()->json(['status' => 'ok']);
        }

        $event = $request->input('event');
        if ($event !== 'message_received') {
            return response()->json(['status' => 'ignored']);
        }

        $from = $request->input('data.from');
        $message = $request->input('data.message.conversation');

        if (!$from || !$message) {
            return response()->json(['status' => 'missing_data'], 400);
        }

        $phone = preg_replace('/@(s\.whatsapp\.net|lid)$/', '', $from);

        // Load conversation history
        $cacheKey = "whatsapp_chat_{$phone}";
        $history = cache()->get($cacheKey, []);

        // Add client message to history
        $history[] = ['role' => 'user', 'content' => $message];

        $services = (new DentalService())->getServices();

        $systemPrompt = "You are a dental lab assistant for Digital Art Dental Studios. 
        Your job is to collect order details from clients via WhatsApp before placing an order.
        
        Available services: " . json_encode($services) . "
        
        Follow these steps:
        1. Greet the client and identify the service they need
        2. Ask for tooth number if not provided
        3. Ask for shade if relevant (crowns, veneers)
        4. Confirm all details with the client
        5. Once confirmed, respond ONLY with this JSON (no other text):
        {\"order_ready\":true,\"service_name\":\"\",\"tooth_number\":null,\"shade\":null,\"estimated_days\":0,\"price\":0,\"notes\":\"\"}
        
        Currency is Kenyan Shillings (Ksh). Do NOT use markdown formatting like ** or * in your responses. Use plain text only.
        Until you have all details, respond conversationally in plain text.";

        $claude = Http::withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-haiku-4-5-20251001',
            'max_tokens' => 500,
            'system' => $systemPrompt,
            'messages' => $history,
        ]);

        $reply = $claude->json()['content'][0]['text'] ?? '';

        // Add Claude reply to history
        $history[] = ['role' => 'assistant', 'content' => $reply];

        // Save history for 30 minutes
        cache()->put($cacheKey, $history, now()->addMinutes(30));

        // Check if Claude has collected all details
        $cleaned = preg_replace('/```json|```/', '', $reply);
        $order = json_decode(trim($cleaned), true);

        if (isset($order['order_ready']) && $order['order_ready'] === true) {
            // Place the order
            $order['client_phone'] = $phone;
            Http::post('https://drmorch.medicareers.co.ke/dental/services/order', $order);

            // Clear conversation
            cache()->forget($cacheKey);

            $replyMessage = "Order confirmed!\n{$order['service_name']}\nPrice: Ksh " . ($order['price'] * 130) . "\nEstimated delivery: {$order['estimated_days']} days.\nWe will notify you when it is ready!";
        } else {
            // Send Claude's question back to client
            $replyMessage = $reply;
        }

        $this->sendWhatsAppMessage(new Request([
            'phone' => $phone,
            'message' => $replyMessage,
        ]));

        return response()->json(['status' => 'received']);
    }

    /** @deprecated Use sendWhatsAppMessage() with FlareSend instead */
    public function sendOldWhatsAppMessage(Request $request): bool
    {
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

    public function sendWhatsAppMessage(Request $request): bool
    {
        try {
            $response = Http::timeout(5)->withOptions([
                'curl' => [
                    CURLOPT_RESOLVE => ['api.flaresend.com:443:57.128.52.136'],
                ],
            ])->withHeaders([
                'Authorization' => 'Bearer ' . config('services.flaresend.key'),
                'Content-Type' => 'application/json',
            ])->post('https://api.flaresend.com/send-message', [
                'recipients' => [$request->phone],
                'type' => 'text',
                'text' => $request->message,
            ]);

            $status = $response->successful() ? 'SUCCESS' : 'FAILED';

            Log::info("
            ========================================
             WHATSAPP MESSAGE | {$status}
            ========================================
             To      : {$request->phone}
             Message : {$request->message}
             Status  : {$response->status()}
             Response: " . json_encode($response->json(), JSON_PRETTY_PRINT) . "
            ========================================
                    ");

                        return $response->successful();

                    } catch (\Exception $e) {
                        Log::error("
            ========================================
             WHATSAPP MESSAGE | ERROR
            ========================================
             To      : {$request->phone}
             Message : {$request->message}
             Error   : {$e->getMessage()}
            ========================================
        ");
            return false;
        }
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
