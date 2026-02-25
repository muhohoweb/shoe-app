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

        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        return $uploadDir;
    }

    public function webhook(Request $request)
    {
        // Log everything for debugging
        Log::info('========== WEBHOOK RECEIVED ==========');
        Log::info('Full payload:', $request->all());

        if ($request->isMethod('get')) {
            return response()->json(['status' => 'webhook_active']);
        }

        $event = $request->input('event');
        $data = $request->input('data', []);

        // Extract ANY content from the message regardless of structure
        $extractedData = $this->extractAnyContent($data);

        Log::info('Extracted data:', $extractedData);

        // If we have any text content, process it
        if (!empty($extractedData['text'])) {
            $text = $extractedData['text'];

            // STEP 1: Check if it's a job posting (using enhanced detection)
            if ($this->isJobPosting($text)) {
                Log::info('✓ Message identified as JOB POSTING');

                // Extract phone from message if present
                $contactPhone = null;
                preg_match('/(?:\+?254|0)[7-9][0-9]{8}/', $text, $phoneMatches);
                if (!empty($phoneMatches)) {
                    $contactPhone = $phoneMatches[0];
                    Log::info('Found contact phone in message: ' . $contactPhone);
                }

                // Create a message structure for handleChannelJob
                $jobMessage = [
                    'conversation' => $text,
                    'id' => $extractedData['message_id'] ?? uniqid()
                ];

                // Use the contact phone if found, otherwise use 'channel'
                $sourcePhone = $contactPhone ?: 'whatsapp_channel';

                $this->handleChannelJob($jobMessage, $sourcePhone);
                return response()->json(['status' => 'job_processed']);
            }

            // STEP 2: If not a job, but has sender, process as regular message
            if (!empty($extractedData['sender'])) {
                Log::info('Processing as regular message with sender');
                $this->processRegularMessage($extractedData);
            } else {
                // STEP 3: No sender and not a job - save for review but also try to parse as job anyway
                Log::info('No sender and not clearly a job, but might be job-related - sending to Claude for analysis');

                // Let Claude analyze it to be sure
                $this->analyzeWithClaude($text, $extractedData);
            }
        }

        return response()->json(['status' => 'processed']);
    }

    /**
     * Enhanced job posting detection
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
            'adventist hospital', 'knh', 'kenyatta', 'mbagathi'
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

        // Lower threshold to catch more job posts
        return $score >= 3;
    }

    /**
     * Analyze ambiguous messages with Claude
     */
    private function analyzeWithClaude(string $text, array $extractedData): void
    {
        try {
            $claude = Http::withHeaders([
                'x-api-key' => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-3-haiku-20240307',
                'max_tokens' => 500,
                'system' => "Analyze this message and determine if it's a job posting or job-related opportunity. 
Return JSON with:
{
  \"is_job\": true/false,
  \"confidence\": 0-100,
  \"reason\": \"brief explanation\",
  \"extracted_info\": {
    \"title\": \"any job title mentioned\",
    \"organization\": \"any organization mentioned\",
    \"location\": \"any location mentioned\",
    \"contact\": \"any contact info\"
  }
}",
                'messages' => [
                    ['role' => 'user', 'content' => $text],
                ],
            ]);

            if ($claude->successful()) {
                $reply = $claude->json()['content'][0]['text'] ?? null;
                if ($reply) {
                    $reply = preg_replace('/```json\s*|\s*```/', '', $reply);
                    $analysis = json_decode(trim($reply), true);

                    if ($analysis && ($analysis['is_job'] ?? false) && ($analysis['confidence'] ?? 0) > 60) {
                        Log::info('Claude confirms this is a job posting', $analysis);

                        // Process as job
                        $jobMessage = ['conversation' => $text, 'id' => $extractedData['message_id'] ?? uniqid()];
                        $this->handleChannelJob($jobMessage, 'claude_identified');
                        return;
                    }
                }
            }

            // If not a job or Claude unsure, save for review
            $this->saveDebugMessage($text, 'unknown', 'needs_review');

        } catch (\Exception $e) {
            Log::error('Error in analyzeWithClaude: ' . $e->getMessage());
            $this->saveDebugMessage($text, 'unknown', 'analysis_error');
        }
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

    /**
     * Recursively search for text content in any nested structure
     */
    private function findTextContent($data, $depth = 0): ?string
    {
        if ($depth > 10) return null;

        if (is_string($data)) {
            if (strlen($data) > 15 && !preg_match('/^[0-9]+$/', $data)) {
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
                    if (is_string($data[$field]) && strlen($data[$field]) > 10) {
                        return $data[$field];
                    }
                    if (is_array($data[$field])) {
                        $found = $this->findTextContent($data[$field], $depth + 1);
                        if ($found) return $found;
                    }
                }
            }

            foreach ($data as $key => $value) {
                if (in_array($key, ['id', 'timestamp', 'instanceId', 'event'])) {
                    continue;
                }
                $found = $this->findTextContent($value, $depth + 1);
                if ($found) return $found;
            }
        }

        return null;
    }

    /**
     * Check if text is likely a job posting
     */
    private function isJobPosting(string $text): bool
    {
        $jobIndicators = [
            '/\b(physiotherapist|radiologist|doctor|nurse|officer|manager|assistant|technician|specialist|position|vacancy|opportunity|hiring|recruitment|job|career)\b/i',
            '/\b(apply|application|cv|resume|cover letter|how to apply|requirements?|qualifications?|experience|diploma|degree|certificate|registered)\b/i',
            '/\b(p\.?o\.? box|email:|@|\.ke|nairobi|kenya|university|hospital|institution|ministry|authority|commission)\b/i'
        ];

        $score = 0;
        foreach ($jobIndicators as $pattern) {
            if (preg_match($pattern, $text)) {
                $score++;
            }
        }

        return $score >= 2;
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
            'model' => 'claude-3-haiku-20240307',
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

    // ========== YOUR EXISTING METHODS (KEPT INTACT) ==========

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

            // Extract all contact information
            $contactInfo = [];

            // Extract phone numbers
            preg_match_all('/(?:\+?254|0)[7-9][0-9]{8}/', $text, $phoneMatches);
            if (!empty($phoneMatches[0])) {
                $contactInfo['phones'] = $phoneMatches[0];
            }

            // Extract emails
            preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $emailMatches);
            if (!empty($emailMatches[0])) {
                $contactInfo['emails'] = $emailMatches[0];
            }

            // Extract organization/hospital names
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

            // Send to Claude for parsing with enhanced prompt
            $claude = Http::withHeaders([
                'x-api-key' => config('services.anthropic.key'),
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-3-haiku-20240307',
                'max_tokens' => 1000,
                'system' => "You are a Kenyan job posting parser. Extract ALL job details from the text.
Return a JSON object with these fields (use null if not found):
{
  \"title\": \"job title/position\",
  \"organization\": \"employer/hospital/clinic name\",
  \"location\": \"work location\",
  \"job_type\": \"full-time/part-time/internship/contract\",
  \"description\": \"full job description\",
  \"requirements\": [\"array of requirements\"],
  \"qualifications\": [\"array of qualifications\"],
  \"experience\": \"experience needed\",
  \"gender_preference\": \"any gender preference mentioned\",
  \"skills\": [\"specific skills required\"],
  \"contact_info\": {
    \"phone\": \"contact phone numbers\",
    \"email\": \"contact email\",
    \"address\": \"physical address\",
    \"how_to_apply\": \"application instructions\"
  },
  \"salary\": \"any salary information\",
  \"deadline\": \"application deadline\"
}",
                'messages' => [
                    ['role' => 'user', 'content' => $text],
                ],
            ]);

            if (!$claude->successful()) {
                Log::error('Claude API error');
                // Save raw message anyway
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

            // Add metadata and extracted info
            $job['source_phone'] = $phone;
            $job['raw_text'] = $text;
            $job['parsed_at'] = now()->toDateTimeString();
            $job['message_id'] = $message['id'] ?? uniqid();
            $job['extracted_contacts'] = $contactInfo;
            $job['organizations_mentioned'] = array_unique($organizations) ?? [];

            $this->saveJobToFile($job);

        } catch (\Exception $e) {
            Log::error('Error in handleChannelJob: ' . $e->getMessage());
            // Save raw message on error
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

        if (!empty($job['salary'])) {
            $entry .= "SALARY: " . $job['salary'] . "\n";
        }

        if (!empty($job['application_deadline'])) {
            $entry .= "DEADLINE: " . $job['application_deadline'] . "\n";
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

        if (!empty($job['how_to_apply'])) {
            $entry .= "\nHOW TO APPLY:\n" . $job['how_to_apply'] . "\n";
        }

        if (!empty($job['contact_info'])) {
            $entry .= "\nCONTACT INFORMATION:\n";
            foreach ($job['contact_info'] as $key => $value) {
                if ($value) {
                    $entry .= strtoupper($key) . ": " . $value . "\n";
                }
            }
        }

        if (!empty($job['extracted_phone'])) {
            $entry .= "CONTACT PHONE: " . $job['extracted_phone'] . "\n";
        }
        if (!empty($job['extracted_email'])) {
            $entry .= "CONTACT EMAIL: " . $job['extracted_email'] . "\n";
        }

        $entry .= str_repeat('-', 80) . "\n";
        $entry .= "SOURCE PHONE: " . ($job['source_phone'] ?? 'N/A') . "\n";
        $entry .= "RAW TEXT:\n" . ($job['raw_text'] ?? 'N/A') . "\n";
        $entry .= str_repeat('=', 80) . "\n\n";

        file_put_contents($filePath, $entry, FILE_APPEND | LOCK_EX);
        file_put_contents($jsonPath, json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n", FILE_APPEND | LOCK_EX);

        Log::info('✓ Job saved', ['title' => $job['title'] ?? 'Unknown']);
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