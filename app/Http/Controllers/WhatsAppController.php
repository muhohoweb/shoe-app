<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    private function getUploadDir(): string
    {
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? base_path('public');
        $uploadDir = rtrim($docRoot, '/') . '/uploads/';

        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        return $uploadDir;
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

        $from    = $request->input('data.from');
        $message = $request->input('data.message');

        if (!$from) {
            return response()->json(['status' => 'missing_data'], 400);
        }

        $phone = preg_replace('/@(s\.whatsapp\.net|lid)$/', '', $from);

        // Check for forwarded messages (potential job posts)
        $isForwarded = $this->isForwardedMessage($message);

        if ($isForwarded) {
            Log::info('Forwarded message detected', ['phone' => $phone]);
            $this->handleChannelJob($message, $phone);
            return response()->json(['status' => 'forwarded_processed']);
        }

        // Ignore internal WhatsApp protocol messages
        if (isset($message['protocolMessage'])) {
            return response()->json(['status' => 'protocol_ignored']);
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

        // Text message handling (your existing chat logic)
        $text = $message['conversation'] ?? $message['extendedTextMessage']['text'] ?? null;

        if (!$text) {
            return response()->json(['status' => 'no_text']);
        }

        // Rest of your existing chat handling code...
        $cacheKey = "whatsapp_chat_{$phone}";
        $history  = cache()->get($cacheKey, []);
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
            'x-api-key'         => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
            'Content-Type'      => 'application/json',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model'      => 'claude-3-haiku-20240307', // Fixed model name
            'max_tokens' => 500,
            'system'     => $systemPrompt,
            'messages'   => $history,
        ]);

        $reply = $claude->json()['content'][0]['text'] ?? '';

        $history[] = ['role' => 'assistant', 'content' => $reply];
        cache()->put($cacheKey, $history, now()->addMinutes(30));

        $cleaned = preg_replace('/```json|```/', '', $reply);
        $order   = json_decode(trim($cleaned), true);

        if (isset($order['order_ready']) && $order['order_ready'] === true) {
            $order['client_phone'] = $phone;
            Http::post('https://drmorch.medicareers.co.ke/dental/services/order', $order);
            cache()->forget($cacheKey);
            $replyMessage = "Order confirmed!\n{$order['service_name']}\nPrice: Ksh " . ($order['price'] * 130) . "\nEstimated delivery: {$order['estimated_days']} days.\nWe will notify you when it is ready!";
        } else {
            $replyMessage = $reply;
        }

        $this->sendWhatsAppMessage(new Request([
            'phone'   => $phone,
            'message' => $replyMessage,
        ]));

        return response()->json(['status' => 'received']);
    }

    /**
     * Check if a message is forwarded
     */
    private function isForwardedMessage(array $message): bool
    {
        // Check extendedTextMessage
        if (isset($message['extendedTextMessage']['contextInfo']['isForwarded']) &&
            $message['extendedTextMessage']['contextInfo']['isForwarded'] === true) {
            return true;
        }

        // Check conversation level contextInfo
        if (isset($message['contextInfo']['isForwarded']) &&
            $message['contextInfo']['isForwarded'] === true) {
            return true;
        }

        // Check for forwarded flag in other message types
        foreach (['imageMessage', 'videoMessage', 'documentMessage'] as $type) {
            if (isset($message[$type]['contextInfo']['isForwarded']) &&
                $message[$type]['contextInfo']['isForwarded'] === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract text from message regardless of format
     */
    private function extractMessageText(array $message): ?string
    {
        // Try various places where text might be
        if (isset($message['conversation'])) {
            return $message['conversation'];
        }

        if (isset($message['extendedTextMessage']['text'])) {
            return $message['extendedTextMessage']['text'];
        }

        if (isset($message['imageMessage']['caption'])) {
            return $message['imageMessage']['caption'];
        }

        if (isset($message['videoMessage']['caption'])) {
            return $message['videoMessage']['caption'];
        }

        if (isset($message['documentMessage']['caption'])) {
            return $message['documentMessage']['caption'];
        }

        return null;
    }

    public function handleChannelJob(array $message, string $phone): void
    {
        try {
            // Extract text from message
            $text = $this->extractMessageText($message);

            // Log for debugging
            Log::info('Processing channel message', [
                'phone' => $phone,
                'message_type' => array_keys($message)[0] ?? 'unknown',
                'text_preview' => $text ? substr($text, 0, 100) . '...' : 'no text'
            ]);

            if (!$text || strlen(trim($text)) < 20) {
                Log::info('Message too short or empty, skipping', ['phone' => $phone]);
                return;
            }

            // First, check if this looks like a job posting using simple keyword check
            $jobKeywords = ['job', 'vacancy', 'hiring', 'position', 'career', 'opportunity',
                'recruitment', 'work', 'employment', 'staff', 'need', 'looking for'];

            $lowerText = strtolower($text);
            $looksLikeJob = false;

            foreach ($jobKeywords as $keyword) {
                if (str_contains($lowerText, $keyword)) {
                    $looksLikeJob = true;
                    break;
                }
            }

            if (!$looksLikeJob) {
                Log::info('Message does not appear to be a job posting', ['phone' => $phone]);
                return;
            }

            // Send to Claude to parse into structured job data
            Log::info('Sending to Claude for parsing', ['phone' => $phone]);

            $claude = Http::withHeaders([
                'x-api-key' => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-3-haiku-20240307', // Fixed model name
                'max_tokens' => 1000,
                'system' => "You are a job listing parser. Extract job details from the provided text. 
Return ONLY a valid JSON object with these exact keys (use null for unknown values):
{
  \"title\": \"job title\",
  \"description\": \"full job description\",
  \"location\": \"city or region\",
  \"job_type\": \"full-time or part-time or contract\",
  \"salary_min\": 0,
  \"salary_max\": 0,
  \"experience_level\": \"entry or mid or senior\",
  \"qualifications\": [\"array of qualifications\"],
  \"requirements\": [\"array of requirements\"],
  \"company\": \"company name if mentioned\",
  \"application_deadline\": \"date if mentioned\",
  \"contact_info\": \"any contact details mentioned\"
}",
                'messages' => [
                    ['role' => 'user', 'content' => $text],
                ],
            ]);

            if (!$claude->successful()) {
                Log::error('Claude API error', [
                    'status' => $claude->status(),
                    'body' => $claude->body()
                ]);
                return;
            }

            $reply = $claude->json()['content'][0]['text'] ?? null;

            if (!$reply) {
                Log::error('Claude returned empty response for job parsing');
                return;
            }

            // Clean the response (remove markdown code blocks if present)
            $reply = preg_replace('/```json\s*|\s*```/', '', $reply);
            $reply = trim($reply);

            $job = json_decode($reply, true);

            if (!$job || json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to parse Claude response as JSON', [
                    'response' => $reply,
                    'json_error' => json_last_error_msg()
                ]);
                return;
            }

            // Add metadata
            $job['source_phone'] = $phone;
            $job['raw_text'] = $text;
            $job['parsed_at'] = now()->toDateTimeString();
            $job['message_id'] = $message['id'] ?? uniqid();

            // Log the parsed job
            Log::info('Job parsed successfully', [
                'title' => $job['title'] ?? 'Unknown',
                'location' => $job['location'] ?? 'Unknown',
                'company' => $job['company'] ?? 'Unknown'
            ]);

            // Save to text file with better formatting
            $this->saveJobToFile($job);

        } catch (\Exception $e) {
            Log::error('Error in handleChannelJob: ' . $e->getMessage(), [
                'phone' => $phone,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Save job data to file with proper formatting
     */
    private function saveJobToFile(array $job): void
    {
        $filePath = $this->getUploadDir() . 'health_jobs.txt';

        // Create a nicely formatted entry
        $entry = str_repeat('=', 80) . "\n";
        $entry .= "DATE: " . now()->format('Y-m-d H:i:s') . "\n";
        $entry .= str_repeat('-', 80) . "\n";
        $entry .= "TITLE: " . ($job['title'] ?? 'N/A') . "\n";
        $entry .= "COMPANY: " . ($job['company'] ?? 'N/A') . "\n";
        $entry .= "LOCATION: " . ($job['location'] ?? 'N/A') . "\n";
        $entry .= "TYPE: " . ($job['job_type'] ?? 'N/A') . "\n";
        $entry .= "EXPERIENCE: " . ($job['experience_level'] ?? 'N/A') . "\n";

        if (isset($job['salary_min']) && isset($job['salary_max'])) {
            $entry .= "SALARY: {$job['salary_min']} - {$job['salary_max']}\n";
        }

        $entry .= str_repeat('-', 80) . "\n";
        $entry .= "DESCRIPTION:\n" . ($job['description'] ?? 'N/A') . "\n";

        if (!empty($job['qualifications'])) {
            $entry .= str_repeat('-', 80) . "\n";
            $entry .= "QUALIFICATIONS:\n";
            foreach ($job['qualifications'] as $qual) {
                $entry .= "- {$qual}\n";
            }
        }

        if (!empty($job['requirements'])) {
            $entry .= str_repeat('-', 80) . "\n";
            $entry .= "REQUIREMENTS:\n";
            foreach ($job['requirements'] as $req) {
                $entry .= "- {$req}\n";
            }
        }

        $entry .= str_repeat('-', 80) . "\n";
        $entry .= "CONTACT: " . ($job['contact_info'] ?? 'N/A') . "\n";
        $entry .= "DEADLINE: " . ($job['application_deadline'] ?? 'N/A') . "\n";
        $entry .= str_repeat('-', 80) . "\n";
        $entry .= "SOURCE PHONE: " . ($job['source_phone'] ?? 'N/A') . "\n";
        $entry .= str_repeat('=', 80) . "\n\n";

        // Append to file
        file_put_contents($filePath, $entry, FILE_APPEND | LOCK_EX);

        // Also save as JSON for easy processing
        $jsonPath = $this->getUploadDir() . 'health_jobs.json';
        $jsonEntry = json_encode($job, JSON_PRETTY_PRINT) . "\n\n";
        file_put_contents($jsonPath, $jsonEntry, FILE_APPEND | LOCK_EX);

        Log::info('Job saved to files', [
            'text_file' => $filePath,
            'json_file' => $jsonPath
        ]);
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
                str_contains($mime, 'sla') => 'stl',
                default => explode('/', $mime)[1] ?? 'bin'
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