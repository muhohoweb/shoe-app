<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    private function isEventPosting(string $text): bool
    {
        $text = strtolower($text);

        $eventIndicators = [
            'webinar', 'seminar', 'conference', 'workshop', 'training',
            'cme', 'grand round', 'symposium', 'forum', 'summit',
            'zoom', 'register', 'registration', 'join', 'attend',
            'date:', 'time:', 'venue:', 'topic:', 'speaker:',
            'certificate', 'free', 'online', 'virtual', 'meeting id'
        ];

        $score = 0;
        foreach ($eventIndicators as $indicator) {
            if (str_contains($text, $indicator)) {
                $score++;
            }
        }

        Log::info('Event posting score: ' . $score . ' for text: ' . substr($text, 0, 50));
        return $score >= 3;
    }
    private function getUploadDir(): string
    {
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? base_path('public');
        $uploadDir = rtrim($docRoot, '/') . '/uploads/';

        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        return $uploadDir;
    }

    public function webhook(Request $request)
    {
        Log::info('========== WEBHOOK RECEIVED ==========');
        Log::info('Full payload:', $request->all());

        if ($request->isMethod('get')) {
            return response()->json(['status' => 'webhook_active']);
        }

        $data = $request->input('data', []);

        if (isset($data['message']['protocolMessage'])) {
            return response()->json(['status' => 'protocol_ignored']);
        }

        // Handle image without caption — send to Claude vision for job detection
        $imageMessage = $data['message']['imageMessage'] ?? null;
        if ($imageMessage && empty($imageMessage['caption'])) {
            Log::info('Image without caption received — sending to Claude vision');
            $this->handleImageMessage($imageMessage, $data['id'] ?? uniqid());
            return response()->json(['status' => 'image_processed']);
        }

        $extractedData = $this->extractAnyContent($data);
        Log::info('Extracted data:', $extractedData);

        if (!empty($extractedData['text'])) {
            $text = $extractedData['text'];

            if ($this->isJobPosting($text)) {
                Log::info('✓ Message identified as JOB POSTING');
                preg_match('/(?:\+?254|0)[7-9][0-9]{8}/', $text, $phoneMatches);
                $contactPhone = $phoneMatches[0] ?? null;
                if ($contactPhone) Log::info('Found contact phone in message: ' . $contactPhone);
                $jobMessage = [
                    'conversation' => $text,
                    'id'           => $extractedData['message_id'] ?? uniqid(),
                ];
                $this->handleChannelJob($jobMessage, $contactPhone ?: 'whatsapp_channel');
                return response()->json(['status' => 'job_processed']);
            }

            if ($this->isEventPosting($text)) {
                Log::info('✓ Message identified as EVENT');
                $imageData = $data['message']['imageMessage'] ?? null;
                $this->handleChannelEvent($text, $imageData, $extractedData['message_id'] ?? uniqid());
                return response()->json(['status' => 'event_processed']);
            }

            if (!empty($extractedData['sender'])) {
                Log::info('Processing as regular message with sender');
                $this->processRegularMessage($extractedData);
            } else {
                Log::info('No sender and not clearly a job or event - saving for review');
                $this->saveDebugMessage($text, 'unknown', 'needs_review');
            }
        }

        return response()->json(['status' => 'processed']);
    }

    public function handleChannelEvent(string $text, ?array $imageData, string $messageId): void
    {
        try {
            Log::info('========== HANDLE CHANNEL EVENT ==========');

            // Parse event details with Claude
            $claude = Http::withHeaders([
                'x-api-key'         => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model'      => 'claude-haiku-4-5-20251001',
                'max_tokens' => 800,
                'system'     => 'You are an event details parser. Extract event details from the text.
            Return ONLY a JSON object with these exact keys:
            {
              "title": "event title",
              "description": "full description as plain text",
              "start_date": "YYYY-MM-DD HH:MM:SS or null",
              "end_date": "YYYY-MM-DD HH:MM:SS or null",
              "link": "registration or zoom link or null"
            }
            For dates, infer the year from context if not explicitly stated.
            Return null for any field you cannot determine. No markdown, no extra text.',
                'messages' => [
                    ['role' => 'user', 'content' => $text],
                ],
            ]);

            $reply = $claude->json()['content'][0]['text'] ?? null;

            if (!$reply) {
                Log::error('Claude returned empty response for event');
                return;
            }

            $event = json_decode(trim(preg_replace('/```json|```/', '', $reply)), true);

            if (!$event || !isset($event['title'])) {
                Log::error('Failed to parse event from Claude response', ['raw' => $reply]);
                return;
            }

            Log::info('Event parsed', ['title' => $event['title'], 'start' => $event['start_date']]);

            // Attach image keys if present so medicareers can decrypt and store the image
            $payload = $event;
            if ($imageData) {
                $payload['image_url'] = $imageData['url']      ?? null;
                $payload['media_key'] = $imageData['mediaKey'] ?? null;
            }

            $response = Http::post('https://medicareers.co.ke/whats-app-events', $payload);

            Log::info('Posted event to medicareers', [
                'title'  => $event['title'],
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

        } catch (\Exception $e) {
            Log::error('handleChannelEvent error: ' . $e->getMessage());
        }
    }

    /**
     * Enhanced job posting detection with scoring
     */
    private function isJobPosting(string $text): bool
    {
        $text = strtolower($text);

        // Strong indicators (definitely a job)
        $strongIndicators = [
            'job', 'vacancy', 'hiring', 'position', 'career', 'opportunity',
            'recruitment', 'apply', 'application', 'cv', 'resume', 'salary',
            'experience', 'qualifications', 'requirements', 'candidate'
        ];

        // Medical/healthcare specific
        $medicalIndicators = [
            'doctor', 'nurse', 'clinical', 'medical', 'hospital', 'clinic',
            'pharmacist', 'radiologist', 'physiotherapist', 'intern', 'pre intern',
            'dispensing', 'drugs', 'procedures', 'clerking', 'labs', 'interpretation',
            'adventist hospital', 'knh', 'kenyatta', 'mbagathi,pre intern', 'internship', 'dispensing', 'clerking', 'labs', 'eliox', 'medical clinic'
        ];

        // Application related
        $applicationIndicators = [
            'call', 'contact', 'send', 'email', 'apply', 'interview',
            '074', '071', '072', '073', '075', '079', '070'  // Kenyan phone prefixes
        ];

        $score = 0;

        // Check strong indicators (2 points each)
        foreach ($strongIndicators as $indicator) {
            if (str_contains($text, $indicator)) {
                $score += 2;
            }
        }

        // Check medical indicators (1 point each)
        foreach ($medicalIndicators as $indicator) {
            if (str_contains($text, $indicator)) {
                $score += 1;
            }
        }

        // Check application indicators (1 point each)
        foreach ($applicationIndicators as $indicator) {
            if (str_contains($text, $indicator)) {
                $score += 1;
            }
        }

        // Check for phone numbers
        if (preg_match('/(?:\+?254|0)[7-9][0-9]{8}/', $text)) {
            $score += 2;
        }

        // Check for email addresses
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text)) {
            $score += 2;
        }

        Log::info('Job posting score: ' . $score . ' for text: ' . substr($text, 0, 50));

        // Threshold to catch job posts
        return $score >= 3;
    }

    /**
     * Extract ANY content from ANY webhook structure
     */
    private function extractAnyContent(array $data): array
    {
        $result = [
            'text' => null,
            'sender' => null,
            'timestamp' => null,
            'message_id' => null,
            'media' => null,
            'raw' => $data
        ];

        // Try to find sender information anywhere
        $possibleSenderFields = ['from', 'sender', 'phone', 'wa_id', 'user', 'author'];
        foreach ($possibleSenderFields as $field) {
            if (isset($data[$field])) {
                $result['sender'] = $data[$field];
                break;
            }
        }

        // Try to find timestamp
        $possibleTimeFields = ['timestamp', 'time', 'created_at', 'date'];
        foreach ($possibleTimeFields as $field) {
            if (isset($data[$field])) {
                $result['timestamp'] = $data[$field];
                break;
            }
        }

        // Try to find message ID
        $possibleIdFields = ['id', 'message_id', 'msg_id', 'uuid'];
        foreach ($possibleIdFields as $field) {
            if (isset($data[$field])) {
                $result['message_id'] = $data[$field];
                break;
            }
        }

        // RECURSIVELY search for any text content anywhere in the payload
        $result['text'] = $this->findTextContent($data);

        return $result;
    }

    public function handleImageMessage(array $imageMessage, string $messageId): void
    {
        try {
            Log::info('========== HANDLE IMAGE MESSAGE ==========');

            $url      = $imageMessage['url']      ?? null;
            $mediaKey = $imageMessage['mediaKey'] ?? null;

            if (!$url) {
                Log::error('Image missing url');
                return;
            }

            // If no mediaKey, try downloading directly (unencrypted channel images)
            if (!$mediaKey) {
                Log::info('No mediaKey — attempting direct download');
                $response = Http::timeout(30)->get($url);

                if (!$response->successful() || empty($response->body())) {
                    Log::error('Direct image download failed', ['status' => $response->status()]);
                    return;
                }

                $imageData = $response->body();
            } else {
                $mediaKeyBytes = base64_decode($mediaKey);
                $expanded      = hash_hkdf('sha256', $mediaKeyBytes, 112, 'WhatsApp Image Keys');
                $iv            = substr($expanded, 0, 16);
                $cipherKey     = substr($expanded, 16, 32);

                $encrypted = Http::timeout(30)->get($url)->body();

                if (empty($encrypted)) {
                    Log::error('Empty image download');
                    return;
                }

                $imageData = openssl_decrypt(
                    substr($encrypted, 0, -10),
                    'aes-256-cbc',
                    $cipherKey,
                    OPENSSL_RAW_DATA,
                    $iv
                );

                if ($imageData === false) {
                    Log::error('Image decryption failed');
                    return;
                }
            }

            $base64Image = base64_encode($imageData);

            $claude = Http::withHeaders([
                'x-api-key'         => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model'      => 'claude-haiku-4-5-20251001',
                'max_tokens' => 1500,
                'system'     => 'You are a job posting image analyzer for a Kenyan health jobs platform.
Look at the image and determine if it contains a job posting.
If it is a job posting, extract all details and return this JSON:
{
  "is_job": true,
  "title": "job title",
  "organization": "employer name",
  "location": "work location",
  "job_type": "full-time/part-time/internship/contract",
  "contract_duration": "duration or null",
  "description": "job summary 2-3 sentences",
  "responsibilities": ["array or empty array"],
  "requirements": ["array of requirements"],
  "skills": ["array or empty array"],
  "contact_info": {
    "phone": "phone or null",
    "email": "email or null",
    "address": "address or null",
    "how_to_apply": "application instructions or null"
  },
  "salary": "salary info or null",
  "deadline": "YYYY-MM-DD or null"
}
If it is NOT a job posting, return: {"is_job": false}
No markdown. Return only the JSON object.',
                'messages' => [
                    [
                        'role'    => 'user',
                        'content' => [
                            [
                                'type'   => 'image',
                                'source' => [
                                    'type'       => 'base64',
                                    'media_type' => 'image/jpeg',
                                    'data'       => $base64Image,
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => 'Analyze this image. Is it a job posting? Extract all details if yes.',
                            ],
                        ],
                    ],
                ],
            ]);

            $reply = $claude->json()['content'][0]['text'] ?? null;

            if (!$reply) {
                Log::error('Claude returned empty response for image');
                return;
            }

            $result = json_decode(trim(preg_replace('/```json|```/', '', $reply)), true);

            if (!$result || empty($result['is_job'])) {
                Log::info('Image is not a job posting');
                return;
            }

            Log::info('✓ Image identified as JOB POSTING', ['title' => $result['title']]);

            $parsedContact           = $result['contact_info'] ?? [];
            $result['contact_phone'] = $parsedContact['phone']        ?? null;
            $result['contact_email'] = $parsedContact['email']        ?? null;
            $result['how_to_apply']  = $parsedContact['how_to_apply'] ?? null;
            $result['contact_address'] = $parsedContact['address']    ?? null;
            $result['source_phone']  = 'whatsapp_image';
            $result['message_id']    = $messageId;
            $result['parsed_at']     = now()->toDateTimeString();

            $this->saveJobToFile($result);

            $response = Http::post('https://medicareers.co.ke/whats-app-jobs', $result);
            Log::info('Posted image job to medicareers', [
                'title'  => $result['title'],
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

        } catch (\Exception $e) {
            Log::error('handleImageMessage error: ' . $e->getMessage());
        }
    }

    /**
     * Recursively search for text content in any nested structure
     */
    private function findTextContent($data, $depth = 0): ?string
    {
        if ($depth > 10) return null;

        if (is_string($data)) {
            // Lower threshold to 3 chars, but skip URLs and base64
            if (strlen($data) > 3
                && !preg_match('/^[0-9]+$/', $data)
                && !preg_match('/^https?:\/\//', $data)
                && !preg_match('/^\/9j\//', $data)  // skip base64 jpeg
                && strlen($data) < 5000) {          // skip huge strings
                return $data;
            }
        }

        if (is_array($data)) {
            $textFields = [
                'conversation', 'text', 'body', 'message', 'content',
                'caption', 'description', 'text_body', 'msg_text',
                'extendedTextMessage', 'textMessage', 'plain_text'
            ];

            foreach ($textFields as $field) {
                if (isset($data[$field])) {
                    if (is_string($data[$field]) && strlen($data[$field]) > 3) {
                        return $data[$field];
                    }
                    if (is_array($data[$field])) {
                        $found = $this->findTextContent($data[$field], $depth + 1);
                        if ($found) return $found;
                    }
                }
            }

            foreach ($data as $key => $value) {
                if (in_array($key, ['id', 'timestamp', 'instanceId', 'event', 'url',
                    'directPath', 'jpegThumbnail', 'thumbnailDirectPath',
                    'fileSha256', 'thumbnailSha256', 'mediaKey'])) {
                    continue;
                }
                $found = $this->findTextContent($value, $depth + 1);
                if ($found) return $found;
            }
        }

        return null;
    }

    /**
     * Process regular messages (dental orders, etc.)
     */
    private function processRegularMessage(array $extractedData): void
    {
        $text = $extractedData['text'];
        $sender = $extractedData['sender'] ?? null;

        if (!$sender) {
            Log::info('No sender for regular message, saving for review');
            $this->saveDebugMessage($text, 'unknown', 'regular_message_no_sender');
            return;
        }

        $phone = preg_replace('/@(s\.whatsapp\.net|lid)$/', '', $sender);

        // Your existing dental order handling code
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
    }

    // ========== JOB HANDLING METHODS ==========

    public function testJobPost(Request $request)
    {
        Log::info('========== TEST JOB POST ==========');
        $testMessage = $request->input('message', "We're hiring! Position: Senior Developer at Tech Company Kenya. Location: Nairobi. Requirements: 5+ years experience with PHP, Laravel. Send CV to hr@example.com");

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

    private function isForwardedMessage(array $message): bool
    {
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

        if (isset($message['extendedTextMessage']['contextInfo']['forwardOrigin']) ||
            isset($message['contextInfo']['forwardOrigin'])) {
            Log::info('Found forwardOrigin in message');
            return true;
        }

        return false;
    }

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

            $text = $this->extractMessageText($message);

            if (!$text || strlen(trim($text)) < 10) {
                Log::info('Message too short or empty, skipping');
                return;
            }

            $contactInfo = [];

            preg_match_all('/(?:\+?254|0)[7-9][0-9]{8}/', $text, $phoneMatches);
            if (!empty($phoneMatches[0])) {
                $contactInfo['phones'] = $phoneMatches[0];
            }

            preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $emailMatches);
            if (!empty($emailMatches[0])) {
                $contactInfo['emails'] = $emailMatches[0];
            }

            $orgPatterns = [
                '/([A-Z][a-z]+ (?:Hospital|Clinic|Medical Centre|Nursing Home))/',
                '/([A-Z][a-z]+ University)/',
                '/at\s+([A-Z][a-zA-Z\s]+)/',
                '/([A-Z][a-zA-Z\s]+(?:Hospital|Clinic|Medical))/'
            ];

            $organizations = [];
            foreach ($orgPatterns as $pattern) {
                if (preg_match_all($pattern, $text, $orgMatches)) {
                    $organizations = array_merge($organizations, $orgMatches[1]);
                }
            }

            Log::info('Extracted contact info:', $contactInfo);

            $claude = Http::withHeaders([
                'x-api-key'         => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model'      => 'claude-haiku-4-5-20251001',
                'max_tokens' => 1500,
                'system'     => 'You are a Kenyan job posting parser. Extract ALL job details from the text.
If the text contains a URL that looks like a careers/jobs/vacancy portal link, treat it as the application link in contact_info.how_to_apply.
Return a JSON object with these fields (use null if not found):
{
  "title": "job title/position",
  "organization": "employer/hospital/clinic name",
  "location": "work location",
  "job_type": "full-time/part-time/internship/contract",
  "contract_duration": "e.g. one-year renewable, permanent, 6-month contract or null",
  "description": "job summary only, 2-3 sentences max",
  "responsibilities": ["array of key responsibilities or empty array"],
  "requirements": ["array of requirements and qualifications combined"],
  "skills": ["specific skills required or empty array"],
  "contact_info": {
    "phone": "contact phone number or null",
    "email": "contact email address or null",
    "address": "physical address or null",
    "how_to_apply": "full application instructions including any portal URLs, email, or links"
  },
  "salary": "any salary information or null",
  "deadline": "application deadline in YYYY-MM-DD format or null"
}
No markdown. Return only the JSON object.',
                'messages' => [
                    ['role' => 'user', 'content' => $text],
                ],
            ]);

            if (!$claude->successful()) {
                Log::error('Claude API error');
                $this->saveRawJob($text, $contactInfo, $phone);
                return;
            }

            $reply = $claude->json()['content'][0]['text'] ?? null;

            if (!$reply) {
                Log::error('Claude returned empty response');
                $this->saveRawJob($text, $contactInfo, $phone);
                return;
            }

            $reply = preg_replace('/```json\s*|\s*```/', '', $reply);
            $reply = trim($reply);

            $job = json_decode($reply, true);

            if (!$job || json_last_error() !== JSON_ERROR_NONE) {
                Log::error('Failed to parse Claude response');
                $this->saveRawJob($text, $contactInfo, $phone);
                return;
            }

            // Flatten contact_info to top-level fields for the API
            $parsedContact        = $job['contact_info'] ?? [];
            $job['contact_phone'] = $parsedContact['phone']        ?? ($contactInfo['phones'][0] ?? null);
            $job['contact_email'] = $parsedContact['email']        ?? ($contactInfo['emails'][0] ?? null);
            $job['how_to_apply']  = $parsedContact['how_to_apply'] ?? null;
            $job['contact_address'] = $parsedContact['address']    ?? null;

            // Metadata
            $job['source_phone']            = $phone;
            $job['raw_text']                = $text;
            $job['parsed_at']               = now()->toDateTimeString();
            $job['message_id']              = $message['id'] ?? uniqid();
            $job['extracted_contacts']      = $contactInfo;
            $job['organizations_mentioned'] = array_unique($organizations);

            $this->saveJobToFile($job);

            $response = Http::post('https://medicareers.co.ke/whats-app-jobs', $job);
            Log::info('Posted to medicareers', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);

        } catch (\Exception $e) {
            Log::error('Error in handleChannelJob: ' . $e->getMessage());
            if (isset($text)) {
                $this->saveRawJob($text, [], $phone ?? 'unknown');
            }
        }
    }

    /**
     * Save raw job when parsing fails
     */
    private function saveRawJob(string $text, array $contactInfo, string $source): void
    {
        $rawJob = [
            'raw_text' => $text,
            'contact_info' => $contactInfo,
            'source' => $source,
            'received_at' => now()->toDateTimeString(),
            'status' => 'unparsed'
        ];

        $filePath = $this->getUploadDir() . 'unparsed_jobs.json';
        file_put_contents($filePath, json_encode($rawJob, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND | LOCK_EX);

        Log::info('Saved unparsed job for review');
    }

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

    private function saveJobToFile(array $job): void
    {
        $filePath = $this->getUploadDir() . 'health_jobs.txt';
        $jsonPath = $this->getUploadDir() . 'health_jobs.json';

        $entry = str_repeat('=', 80) . "\n";
        $entry .= "DATE: " . now()->format('Y-m-d H:i:s') . "\n";
        $entry .= str_repeat('-', 80) . "\n";
        $entry .= "TITLE: " . ($job['title'] ?? 'N/A') . "\n";
        $entry .= "ORGANIZATION: " . ($job['organization'] ?? 'N/A') . "\n";
        $entry .= "LOCATION: " . ($job['location'] ?? 'N/A') . "\n";
        $entry .= "JOB TYPE: " . ($job['job_type'] ?? 'N/A') . "\n";

        if (!empty($job['experience'])) {
            $entry .= "EXPERIENCE: " . $job['experience'] . "\n";
        }

        if (!empty($job['gender_preference'])) {
            $entry .= "GENDER PREFERENCE: " . $job['gender_preference'] . "\n";
        }

        if (!empty($job['salary'])) {
            $entry .= "SALARY: " . $job['salary'] . "\n";
        }

        if (!empty($job['deadline'])) {
            $entry .= "DEADLINE: " . $job['deadline'] . "\n";
        }

        $entry .= str_repeat('-', 80) . "\n";
        $entry .= "DESCRIPTION:\n" . ($job['description'] ?? 'N/A') . "\n";

        if (!empty($job['requirements']) && is_array($job['requirements'])) {
            $entry .= "\nREQUIREMENTS:\n";
            foreach ($job['requirements'] as $req) {
                $entry .= "• " . $req . "\n";
            }
        }

        if (!empty($job['qualifications']) && is_array($job['qualifications'])) {
            $entry .= "\nQUALIFICATIONS:\n";
            foreach ($job['qualifications'] as $qual) {
                $entry .= "• " . $qual . "\n";
            }
        }

        if (!empty($job['skills']) && is_array($job['skills'])) {
            $entry .= "\nSKILLS:\n";
            foreach ($job['skills'] as $skill) {
                $entry .= "• " . $skill . "\n";
            }
        }

        if (!empty($job['contact_info'])) {
            $entry .= "\nCONTACT INFORMATION:\n";
            foreach ($job['contact_info'] as $key => $value) {
                if ($value) {
                    if (is_array($value)) {
                        $entry .= strtoupper($key) . ": " . implode(', ', $value) . "\n";
                    } else {
                        $entry .= strtoupper($key) . ": " . $value . "\n";
                    }
                }
            }
        }

        $entry .= str_repeat('-', 80) . "\n";
        $entry .= "SOURCE PHONE: " . ($job['source_phone'] ?? 'N/A') . "\n";
        $entry .= "MESSAGE ID: " . ($job['message_id'] ?? 'N/A') . "\n";
        $entry .= "PARSED AT: " . ($job['parsed_at'] ?? 'N/A') . "\n";
        $entry .= str_repeat('=', 80) . "\n\n";

        file_put_contents($filePath, $entry, FILE_APPEND | LOCK_EX);
        file_put_contents($jsonPath, json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n", FILE_APPEND | LOCK_EX);

        Log::info('✓ Job saved', ['title' => $job['title'] ?? 'Unknown']);
    }

    // ========== MEDIA HANDLING METHODS ==========

    private function downloadAndSaveMedia(array $media, string $type): ?string
    {
        try {
            $url = $media['url'] ?? null;
            $mediaKey = isset($media['mediaKey']) ? base64_decode($media['mediaKey']) : null;

            if (!$url || !$mediaKey) {
                Log::error('Missing url or mediaKey', ['type' => $type]);
                return null;
            }

            $mediaTypeLabel = match (true) {
                str_contains($type, 'image') => 'WhatsApp Image Keys',
                str_contains($type, 'video') => 'WhatsApp Video Keys',
                str_contains($type, 'audio') => 'WhatsApp Audio Keys',
                default => 'WhatsApp Document Keys',
            };

            $expanded = hash_hkdf('sha256', $mediaKey, 112, $mediaTypeLabel);
            $iv = substr($expanded, 0, 16);
            $cipherKey = substr($expanded, 16, 32);

            $encrypted = Http::timeout(30)->get($url)->body();

            if (empty($encrypted)) {
                Log::error('Empty response when downloading media', ['url' => $url]);
                return null;
            }

            $ciphertext = substr($encrypted, 0, -10);
            $decrypted = openssl_decrypt($ciphertext, 'aes-256-cbc', $cipherKey, OPENSSL_RAW_DATA, $iv);

            if ($decrypted === false) {
                Log::error('Media decryption failed', ['type' => $type]);
                return null;
            }

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

    // ========== WHATSAPP MESSAGE SENDING METHODS ==========

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
            ========================================
            ");

            return $response->successful();

        } catch (\Exception $e) {
            Log::error("WHATSAPP MESSAGE ERROR: " . $e->getMessage());
            return false;
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