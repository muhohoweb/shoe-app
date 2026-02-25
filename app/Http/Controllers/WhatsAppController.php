<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    // Add this method:
    private function getUploadDir(): string
    {
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? base_path('public');
        return rtrim($docRoot, '/') . '/uploads/';
    }

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
        $message = $request->input('data.message');

        if (!$from) {
            return response()->json(['status' => 'missing_data'], 400);
        }

        $phone = preg_replace('/@(s\.whatsapp\.net|lid)$/', '', $from);

        // Detect forwarded channel messages (job adverts)
        $isForwarded = isset($message['extendedTextMessage']['contextInfo']['isForwarded']);
        $hasChannelInfo = isset($message['extendedTextMessage']['contextInfo']['forwardOrigin']);

        if ($isForwarded && $hasChannelInfo) {
            $this->handleChannelJob($message, $phone);
            return response()->json(['status' => 'job_processed']);
        }

        // Handle media messages
        $mediaTypes = ['imageMessage', 'videoMessage', 'audioMessage', 'documentMessage'];
        foreach ($mediaTypes as $type) {
            if (isset($message[$type])) {
                $savedPath = $this->downloadAndSaveMedia($message[$type], $type);
                Log::info('Media saved', ['path' => $savedPath, 'phone' => $phone]);
                return response()->json(['status' => 'media_saved', 'file' => basename($savedPath ?? '')]);
            }
        }

        // Text message handling
        $text = $message['conversation'] ?? null;

        if (!$text) {
            return response()->json(['status' => 'no_text']);
        }

        $cacheKey = "whatsapp_chat_{$phone}";
        $history = cache()->get($cacheKey, []);
        $history[] = ['role' => 'user', 'content' => $text];

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

        $history[] = ['role' => 'assistant', 'content' => $reply];
        cache()->put($cacheKey, $history, now()->addMinutes(30));

        $cleaned = preg_replace('/```json|```/', '', $reply);
        $order = json_decode(trim($cleaned), true);

        if (isset($order['order_ready']) && $order['order_ready'] === true) {
            $order['client_phone'] = $phone;
            Http::post('https://drmorch.medicareers.co.ke/dental/services/order', $order);
            cache()->forget($cacheKey);
            $replyMessage = "Order confirmed!\n{$order['service_name']}\nPrice: Ksh " . ($order['price'] * 130) . "\nEstimated delivery: {$order['estimated_days']} days.\nWe will notify you when it is ready!";
        } else {
            $replyMessage = $reply;
        }

        $this->sendWhatsAppMessage(new Request([
            'phone' => $phone,
            'message' => $replyMessage,
        ]));

        return response()->json(['status' => 'received']);
    }

    public function handleChannelJob(array $message, string $phone): void
    {
        // Extract text from channel/forwarded message
        $text = $message['extendedTextMessage']['text']
            ?? $message['conversation']
            ?? null;

        if (!$text) {
            Log::info('Channel message has no text, skipping');
            return;
        }

        // Send to Claude to parse into structured job data
        $claude = Http::withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-haiku-4-5-20251001',
            'max_tokens' => 800,
            'system' => 'You are a job listing parser. Extract job details from the provided text and return ONLY a JSON object with these exact keys:
            {
              "title": "job title or null",
              "description": "full job description as plain text or null",
              "location": "city or region or null",
              "job_type": "full-time or part-time or contract or null",
              "salary_min": numeric value only or null,
              "salary_max": numeric value only or null,
              "experience_level": "entry or mid or senior or null",
              "qualifications": ["array", "of", "qualifications"] or null,
              "requirements": ["array", "of", "requirements"] or null
            }
            Return null for any field you cannot determine. No markdown, no extra text.',
            'messages' => [
                ['role' => 'user', 'content' => $text],
            ],
        ]);

        $reply = $claude->json()['content'][0]['text'] ?? null;

        if (!$reply) {
            Log::error('Claude returned empty response for job parsing');
            return;
        }

        $job = json_decode(trim($reply), true);

        if (!$job || !isset($job['title'])) {
            Log::info('Could not parse job from message', ['raw' => $text]);
            return;
        }

        // Add metadata
        $job['source_phone'] = $phone;
        $job['raw_text'] = $text;
        $job['parsed_at'] = now()->toDateTimeString();

        // Save to text file (one JSON per line for easy reading later)
        $filePath = $this->getUploadDir() . 'health_jobs.txt';
        $line = json_encode($job, JSON_PRETTY_PRINT) . "\n---\n";
        file_put_contents($filePath, $line, FILE_APPEND | LOCK_EX);

        Log::info('Job saved to file', ['title' => $job['title'], 'location' => $job['location']]);
    }

    private function downloadAndSaveMedia(array $media, string $type): ?string
    {
        try {
            $url = $media['url'] ?? null;
            $mediaKey = isset($media['mediaKey']) ? base64_decode($media['mediaKey']) : null;

            if (!$url || !$mediaKey) {
                Log::error('Missing url or mediaKey', ['type' => $type]);
                return null;
            }

            // HKDF expand: derive 112 bytes from mediaKey
            $mediaTypeLabel = match (true) {
                str_contains($type, 'image') => 'WhatsApp Image Keys',
                str_contains($type, 'video') => 'WhatsApp Video Keys',
                str_contains($type, 'audio') => 'WhatsApp Audio Keys',
                default => 'WhatsApp Document Keys',
            };

            $expanded = hash_hkdf('sha256', $mediaKey, 112, $mediaTypeLabel);
            $iv = substr($expanded, 0, 16);
            $cipherKey = substr($expanded, 16, 32);

            // Download encrypted file
            $encrypted = Http::timeout(30)->get($url)->body();

            if (empty($encrypted)) {
                Log::error('Empty response when downloading media', ['url' => $url]);
                return null;
            }

            // Strip last 10 bytes (MAC tag) then decrypt
            $ciphertext = substr($encrypted, 0, -10);
            $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $cipherKey, OPENSSL_RAW_DATA, $iv);

            if ($decrypted === false) {
                Log::error('Media decryption failed', ['type' => $type]);
                return null;
            }

            // Derive file extension from mimetype
            // With this:
            $mime = $media['mimetype'] ?? 'application/octet-stream';
            $ext = match (true) {
                str_contains($mime, 'jpeg') => 'jpg',
                str_contains($mime, 'png') => 'png',
                str_contains($mime, 'gif') => 'gif',
                str_contains($mime, 'webp') => 'webp',
                str_contains($mime, 'mp4') => 'mp4',
                str_contains($mime, 'mpeg') => 'mp3',
                str_contains($mime, 'ogg') => 'ogg',
                str_contains($mime, 'webm') => 'webm',
                str_contains($mime, 'pdf') => 'pdf',
                str_contains($mime, 'msword') => 'doc',
                str_contains($mime, 'wordprocessingml') => 'docx',
                str_contains($mime, 'spreadsheetml') => 'xlsx',
                str_contains($mime, 'presentationml') => 'pptx',
                str_contains($mime, 'zip') => 'zip',
                str_contains($mime, 'stl') => 'stl',
                str_contains($mime, 'sla') => 'stl', // some tools send this mime
                default => last(explode('/', $mime)) // fallback to whatever is after '/'
            };

            $filename = uniqid('wa_', true) . '.' . $ext;
            $fullPath = $this->getUploadDir() . $filename;

            file_put_contents($fullPath, $decrypted);

            Log::info('Media file saved', ['file' => $filename, 'size' => strlen($decrypted)]);

            return $fullPath;

        } catch (\Exception $e) {
            Log::error('downloadAndSaveMedia exception: ' . $e->getMessage());
            return null;
        }
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