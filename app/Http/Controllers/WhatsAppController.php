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
            return response()->json(['status' => 'ok']);
        }

        Log::info('FlareSend webhook received', $request->all());

        $event = $request->input('event');

        if ($event !== 'message_received') {
            return response()->json(['status' => 'ignored']);
        }

        $from    = $request->input('data.from');
        $message = $request->input('data.message.conversation');

        if (!$from || !$message) {
            return response()->json(['status' => 'missing_data'], 400);
        }

        $phone = preg_replace('/@(s\.whatsapp\.net|lid)$/', '', $from);

        // Fetch dental services
        $services = Http::get('https://drmorch.medicareers.co.ke/dental/services')->json();

        // Ask Claude to process the order
        $claude = Http::withHeaders([
            'x-api-key'         => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
            'Content-Type'      => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model'      => 'claude-haiku-4-5-20251001',
            'max_tokens' => 500,
            'messages'   => [[
                'role'    => 'user',
                'content' => "You are a dental lab assistant. A client sent this WhatsApp message: \"{$message}\"\n\nAvailable services: " . json_encode($services) . "\n\nRespond ONLY with a valid JSON object, no extra text:\n{\"service_name\":\"\",\"tooth_number\":null,\"shade\":null,\"estimated_days\":0,\"price\":0,\"notes\":\"\"}",
            ]],
        ]);

        $rawText = $claude->json()['content'][0]['text'] ?? '{}';
        $cleaned = preg_replace('/```json|```/', '', $rawText);
        $order = json_decode(trim($cleaned), true) ?? [];

        if (empty($order) || !isset($order['service_name'])) {
            Log::error('Claude returned invalid order', ['raw' => $rawText]);
            return response()->json(['status' => 'received']);
        }
        $order['client_phone'] = $phone;

        Log::info('WhatsApp order processed', $order);

        // Save order
        Http::post('https://drmorch.medicareers.co.ke/dental/services/order', $order);

        // Reply to client
        $this->sendWhatsAppMessage(new Request([
            'phone'   => $phone,
            'message' => "Thank you! Your order for *{$order['service_name']}* has been received.\nPrice: $" . $order['price'] . "\nEstimated delivery: {$order['estimated_days']} days.",
        ]));

        return response()->json(['status' => 'received']);
    }

    /** @deprecated Use sendWhatsAppMessage() with FlareSend instead */
    public function sendOldWhatsAppMessage(Request $request): bool {
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
        $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string',
        ]);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.flaresend.key'),
            'Content-Type' => 'application/json',
        ])->post('https://api.flaresend.com/send-message', [
            'recipients' => [$request->phone],
            'type' => 'text',
            'text' => $request->message,
        ]);

        Log::info('FlareSend response', [
            'status' => $response->status(),
            'body' => $response->json(),
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
