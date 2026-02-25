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
        // Log ALL incoming data for debugging
        Log::info('========== WEBHOOK RECEIVED ==========');
        Log::info('Method: ' . $request->method());
        Log::info('Full payload:', $request->all());

        if ($request->isMethod('get')) {
            return response()->json(['status' => 'webhook_active']);
        }

        $event = $request->input('event');
        $data = $request->input('data', []);

        Log::info('Event type: ' . ($event ?? 'null'));
        Log::info('Data structure:', $data);

        // Log all possible message locations
        if (isset($data['message'])) {
            $message = $data['message'];
            Log::info('Message keys:', array_keys($message));

            if (isset($message['protocolMessage'])) {
                Log::info('Protocol message type: ' . ($message['protocolMessage']['type'] ?? 'unknown'));
            }

            if (isset($message['conversation'])) {
                Log::info('Conversation text: ' . $message['conversation']);
            }

            if (isset($message['extendedTextMessage'])) {
                Log::info('Extended text message: ' . json_encode($message['extendedTextMessage']));
            }
        }

        // Check if this is a forwarded message
        $isForwarded = $this->isForwardedMessage($data['message'] ?? []);
        Log::info('Is forwarded message: ' . ($isForwarded ? 'YES' : 'NO'));

        if ($event !== 'message_received') {
            Log::info('Ignoring non-message event');
            return response()->json(['status' => 'ignored']);
        }

        $from    = $data['from'] ?? null;
        $message = $data['message'] ?? [];

        if (!$from) {
            Log::warning('No sender information in webhook');
            return response()->json(['status' => 'missing_data'], 400);
        }

        $phone = preg_replace('/@(s\.whatsapp\.net|lid)$/', '', $from);
        Log::info('Processing message from: ' . $phone);

        // Check for forwarded messages first
        if ($isForwarded) {
            Log::info('✓ Forwarded message detected', ['phone' => $phone]);
            $this->handleChannelJob($message, $phone);
            return response()->json(['status' => 'forwarded_processed']);
        }

        // Ignore internal WhatsApp protocol messages
        if (isset($message['protocolMessage'])) {
            Log::info('Ignoring protocol message: ' . ($message['protocolMessage']['type'] ?? 'unknown'));
            return response()->json(['status' => 'protocol_ignored']);
        }

        // Handle media messages
        $mediaTypes = ['imageMessage', 'videoMessage', 'audioMessage', 'documentMessage'];
        foreach ($mediaTypes as $type) {
            if (isset($message[$type])) {
                Log::info('Media message detected', ['type' => $type]);
                $savedPath = $this->downloadAndSaveMedia($message[$type], $type);
                Log::info('Media saved', ['path' => $savedPath, 'phone' => $phone]);
                return response()->json(['status' => 'media_saved', 'file' => basename($savedPath ?? '')]);
            }
        }

        // Regular text message handling
        $text = $message['conversation'] ?? $message['extendedTextMessage']['text'] ?? null;

        if (!$text) {
            Log::info('No text content in message');
            return response()->json(['status' => 'no_text']);
        }

        Log::info('Processing text message: ' . substr($text, 0, 100));

        // Your existing chat handling code here...
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
            'model'      => 'claude-3-haiku-20240307',
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
     * Test endpoint to simulate receiving a job post
     */
    public function testJobPost(Request $request)
    {
        Log::info('========== TEST JOB POST ==========');

        $testMessage = $request->input('message', "We're hiring! Position: Senior Developer at Tech Company Kenya. Location: Nairobi. Requirements: 5+ years experience with PHP, Laravel. Send CV to hr@example.com");

        // Create a mock forwarded message
        $mockMessage = [
            'extendedTextMessage' => [
                'text' => $testMessage,
                'contextInfo' => [
                    'isForwarded' => true,
                    'forwardingScore' => 1,
                    'forwardOrigin' => ['type' => 'channel']
                ]
            ],
            'id' => uniqid()
        ];

        $this->handleChannelJob($mockMessage, '254700000000');

        return response()->json(['status' => 'test_completed', 'message' => 'Check logs and uploads/health_jobs.txt']);
    }

    /**
     * Check if a message is forwarded
     */
    private function isForwardedMessage(array $message): bool
    {
        // Check all possible locations for forwarded flag
        $forwardedLocations = [
            'extendedTextMessage.contextInfo.isForwarded',
            'contextInfo.isForwarded',
            'imageMessage.contextInfo.isForwarded',
            'videoMessage.contextInfo.isForwarded',
            'documentMessage.contextInfo.isForwarded',
            'audioMessage.contextInfo.isForwarded'
        ];

        foreach ($forwardedLocations as $location) {
            $keys = explode('.', $location);
            $value = $message;

            foreach ($keys as $key) {
                if (!isset($value[$key])) {
                    $value = null;
                    break;
                }
                $value = $value[$key];
            }

            if ($value === true || $value === 1 || $value === 'true') {
                Log::info('Found forwarded flag at: ' . $location);
                return true;
            }
        }

        // Also check for forwardOrigin which indicates forwarded message
        if (isset($message['extendedTextMessage']['contextInfo']['forwardOrigin']) ||
            isset($message['contextInfo']['forwardOrigin'])) {
            Log::info('Found forwardOrigin in message');
            return true;
        }

        return false;
    }

    /**
     * Extract text from message regardless of format
     */
    private function extractMessageText(array $message): ?string
    {
        $textLocations = [
            'conversation',
            'extendedTextMessage.text',
            'imageMessage.caption',
            'videoMessage.caption',
            'documentMessage.caption',
            'audioMessage.caption'
        ];

        foreach ($textLocations as $location) {
            $keys = explode('.', $location);
            $value = $message;

            foreach ($keys as $key) {
                if (!isset($value[$key])) {
                    $value = null;
                    break;
                }
                $value = $value[$key];
            }

            if ($value && is_string($value) && strlen(trim($value)) > 0) {
                Log::info('Found text at: ' . $location);
                return trim($value);
            }
        }

        return null;
    }

    public function handleChannelJob(array $message, string $phone): void
    {
        try {
            Log::info('========== HANDLE CHANNEL JOB ==========');
            Log::info('Phone: ' . $phone);
            Log::info('Full message structure:', $message);

            // Extract text from message
            $text = $this->extractMessageText($message);

            Log::info('Extracted text: ' . ($text ? substr($text, 0, 200) : 'NO TEXT FOUND'));

            if (!$text || strlen(trim($text)) < 20) {
                Log::info('Message too short or empty, skipping', ['length' => strlen(trim($text ?? ''))]);
                return;
            }

            // Check if this looks like a job posting
            $jobKeywords = ['job', 'vacancy', 'hiring', 'position', 'career', 'opportunity',
                'recruitment', 'work', 'employment', 'staff', 'need', 'looking for',
                'we are hiring', 'job opening', 'job alert'];

            $lowerText = strtolower($text);
            $looksLikeJob = false;
            $matchedKeywords = [];

            foreach ($jobKeywords as $keyword) {
                if (str_contains($lowerText, $keyword)) {
                    $looksLikeJob = true;
                    $matchedKeywords[] = $keyword;
                }
            }

            Log::info('Job keywords found: ' . json_encode($matchedKeywords));

            if (!$looksLikeJob) {
                Log::info('Message does not appear to be a job posting');

                // Save non-job messages for debugging
                $this->saveDebugMessage($text, $phone, 'non_job');
                return;
            }

            Log::info('✓ Message identified as potential job posting', ['phone' => $phone]);

            // Send to Claude to parse
            Log::info('Sending to Claude for parsing');

            $claude = Http::withHeaders([
                'x-api-key' => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-3-haiku-20240307',
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
            Log::info('Claude response received', ['response' => substr($reply ?? 'null', 0, 200)]);

            if (!$reply) {
                Log::error('Claude returned empty response for job parsing');
                return;
            }

            // Clean the response
            $reply = preg_replace('/```json\s*|\s*```/', '', $reply);
            $reply = trim($reply);

            $job = json_decode($reply, true);

            if (!$job || json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to parse Claude response as JSON', [
                    'response' => $reply,
                    'json_error' => json_last_error_msg()
                ]);

                // Save raw response for debugging
                $this->saveDebugMessage($reply, $phone, 'claude_error');
                return;
            }

            // Add metadata
            $job['source_phone'] = $phone;
            $job['raw_text'] = $text;
            $job['parsed_at'] = now()->toDateTimeString();
            $job['message_id'] = $message['id'] ?? uniqid();

            Log::info('✓ Job parsed successfully', [
                'title' => $job['title'] ?? 'Unknown',
                'location' => $job['location'] ?? 'Unknown',
                'company' => $job['company'] ?? 'Unknown'
            ]);

            // Save to files
            $this->saveJobToFile($job);

        } catch (\Exception $e) {
            Log::error('Error in handleChannelJob: ' . $e->getMessage(), [
                'phone' => $phone,
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Save debug messages for analysis
     */
    private function saveDebugMessage(string $content, string $phone, string $type): void
    {
        $debugPath = $this->getUploadDir() . 'debug_messages.txt';
        $entry = "=== " . now()->format('Y-m-d H:i:s') . " ===\n";
        $entry .= "Type: {$type}\n";
        $entry .= "Phone: {$phone}\n";
        $entry .= "Content:\n{$content}\n";
        $entry .= str_repeat('=', 50) . "\n\n";

        file_put_contents($debugPath, $entry, FILE_APPEND | LOCK_EX);
        Log::info("Debug message saved: {$type}");
    }

    /**
     * Save job data to file with proper formatting
     */
    private function saveJobToFile(array $job): void
    {
        $filePath = $this->getUploadDir() . 'health_jobs.txt';
        $jsonPath = $this->getUploadDir() . 'health_jobs.json';

        Log::info('Saving job to: ' . $filePath);

        // Create formatted entry
        $entry = str_repeat('=', 80) . "\n";
        $entry .= "DATE: " . now()->format('Y-m-d H:i:s') . "\n";
        $entry .= "MESSAGE ID: " . ($job['message_id'] ?? 'N/A') . "\n";
        $entry .= str_repeat('-', 80) . "\n";
        $entry .= "TITLE: " . ($job['title'] ?? 'N/A') . "\n";
        $entry .= "COMPANY: " . ($job['company'] ?? 'N/A') . "\n";
        $entry .= "LOCATION: " . ($job['location'] ?? 'N/A') . "\n";
        $entry .= "JOB TYPE: " . ($job['job_type'] ?? 'N/A') . "\n";
        $entry .= "EXPERIENCE: " . ($job['experience_level'] ?? 'N/A') . "\n";

        if (isset($job['salary_min']) && isset($job['salary_max']) && $job['salary_min'] && $job['salary_max']) {
            $entry .= "SALARY: {$job['salary_min']} - {$job['salary_max']}\n";
        }

        if (!empty($job['contact_info'])) {
            $entry .= "CONTACT: {$job['contact_info']}\n";
        }

        if (!empty($job['application_deadline'])) {
            $entry .= "DEADLINE: {$job['application_deadline']}\n";
        }

        $entry .= str_repeat('-', 80) . "\n";
        $entry .= "DESCRIPTION:\n" . ($job['description'] ?? 'N/A') . "\n";

        if (!empty($job['qualifications']) && is_array($job['qualifications'])) {
            $entry .= str_repeat('-', 80) . "\n";
            $entry .= "QUALIFICATIONS:\n";
            foreach ($job['qualifications'] as $qual) {
                $entry .= "• {$qual}\n";
            }
        }

        if (!empty($job['requirements']) && is_array($job['requirements'])) {
            $entry .= str_repeat('-', 80) . "\n";
            $entry .= "REQUIREMENTS:\n";
            foreach ($job['requirements'] as $req) {
                $entry .= "• {$req}\n";
            }
        }

        $entry .= str_repeat('-', 80) . "\n";
        $entry .= "SOURCE PHONE: " . ($job['source_phone'] ?? 'N/A') . "\n";
        $entry .= "RAW TEXT:\n" . ($job['raw_text'] ?? 'N/A') . "\n";
        $entry .= str_repeat('=', 80) . "\n\n";

        // Append to text file
        file_put_contents($filePath, $entry, FILE_APPEND | LOCK_EX);

        // Also save as JSON
        $jsonEntry = json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
        file_put_contents($jsonPath, $jsonEntry, FILE_APPEND | LOCK_EX);

        Log::info('✓ Job saved successfully', [
            'text_file' => $filePath,
            'json_file' => $jsonPath,
            'file_exists' => file_exists($filePath) ? 'YES' : 'NO',
            'file_size' => file_exists($filePath) ? filesize($filePath) : 0
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