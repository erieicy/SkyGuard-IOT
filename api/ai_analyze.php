<?php
/**
 * SkyGuard AI - Computer Vision & Weather Analysis Engine
 * Powered by Google Gemini / OpenAI AI Vision API (konfigurasi via file .env)
 * 1. Differentiates Natural Sunlight vs Artificial Indoor Lamp
 * 2. Classifies Weather & Cloud State (Cerah, Berawan, Mendung, Hujan, Malam)
 * 3. Recommends drying durations
 * 4. Automatically secures roof if cloudy (mendung) or rainy in AUTO mode.
 */

require_once __DIR__ . '/db.php';

// If executed via HTTP request
if (isset($_SERVER['REQUEST_METHOD'])) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    $pdo = getDbConnection();
    $uploadDir = __DIR__ . '/../uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $source = $_POST['source'] ?? 'esp32_cam'; // esp32_cam, direct_camera, user_upload
    $savedFilename = '';
    $uploadedFilePath = '';

    // 0. Gunakan file yang SUDAH disimpan oleh esp32.php (upload_cam raw stream)
    //    => mencegah php://input dibaca dua kali (bug raw stream).
    if (isset($GLOBALS['skyguard_uploaded_file']) && file_exists($GLOBALS['skyguard_uploaded_file'])) {
        $uploadedFilePath = $GLOBALS['skyguard_uploaded_file'];
        $savedFilename = basename($uploadedFilePath);
    }
    // 1. Direct File Upload (Multipart from ESP32-CAM or form)
    elseif (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['image']['tmp_name'];
        $fileName = $_FILES['image']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) ?: 'jpg';
        
        $savedFilename = 'esp32cam_' . date('Ymd_His') . '_' . uniqid() . '.' . $fileExtension;
        $uploadedFilePath = $uploadDir . $savedFilename;
        
        if (!move_uploaded_file($fileTmpPath, $uploadedFilePath)) {
            echo json_encode(['success' => false, 'error' => 'Gagal menyimpan file gambar']);
            exit;
        }
    }
    // 2. Base64 Image Upload (Live Camera frame or Stream)
    elseif (isset($_POST['image_base64'])) {
        $base64Data = $_POST['image_base64'];
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
            $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
            $fileExtension = strtolower($type[1]);
        } else {
            $fileExtension = 'jpg';
        }
        
        $decodedData = base64_decode($base64Data);
        if ($decodedData === false) {
            echo json_encode(['success' => false, 'error' => 'Format Base64 gambar tidak valid']);
            exit;
        }
        
        $savedFilename = 'snap_' . date('Ymd_His') . '_' . uniqid() . '.' . $fileExtension;
        $uploadedFilePath = $uploadDir . $savedFilename;
        file_put_contents($uploadedFilePath, $decodedData);
    }
    // 3. Raw Body Image Stream (from ESP32-CAM HTTP POST)
    else {
        $rawStream = file_get_contents('php://input');
        if (!empty($rawStream) && strlen($rawStream) > 500) {
            $savedFilename = 'esp32raw_' . date('Ymd_His') . '_' . uniqid() . '.jpg';
            $uploadedFilePath = $uploadDir . $savedFilename;
            file_put_contents($uploadedFilePath, $rawStream);
        }
    }

    if (empty($uploadedFilePath) || !file_exists($uploadedFilePath)) {
        echo json_encode(['success' => false, 'error' => 'Tidak ada data gambar yang diterima']);
        exit;
    }

    // Ambil konfigurasi AI (provider, api key, model) dari settings
    $aiConfig = getAIConfig($pdo);

    // Perform AI Image Analysis (Gemini / OpenAI Vision API dengan fallback lokal)
    $analysisResult = analyzeSkyWithAI($uploadedFilePath, $aiConfig);

    // Determine verdicts
    $weatherVerdict = $analysisResult['weather'];
    $lightVerdict = $analysisResult['light_verdict'];
    $confidence = $analysisResult['confidence'];
    $dryingMinutes = $analysisResult['recommended_minutes'];
    $recommendation = $analysisResult['recommendation'];
    $aiEngineUsed = $analysisResult['engine'] ?? 'AI Vision Engine';

    $state = $pdo->query("SELECT * FROM device_state WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    $roofAction = 'NO_CHANGE';

    // Jika analisis gagal (file tidak valid / API error), jangan ubah status atap.
    if (!empty($analysisResult['error'])) {
        $newRoofStatus = $state['roof_status'];
        $actionReason = 'ERROR AI: ' . ($analysisResult['recommendation'] ?? 'Analisis gagal.');
    } else {
        $newRoofStatus = $state['roof_status'];
        $actionReason = $state['last_action_reason'];

        if ($state['control_mode'] === 'AUTO') {
        if ($weatherVerdict === 'HUJAN') {
            if ($state['roof_status'] === 'OPEN') {
                $newRoofStatus = 'CLOSED';
                $roofAction = 'CLOSED';
                $actionReason = "AI Vision ({$aiEngineUsed}): Terdeteksi hujan aktif - Atap otomatis ditutup demi melindungi jemuran.";
            }
        } elseif ($weatherVerdict === 'MENDUNG' && $state['auto_close_on_mendung'] == 1) {
            if ($state['roof_status'] === 'OPEN') {
                $newRoofStatus = 'CLOSED';
                $roofAction = 'CLOSED';
                $actionReason = "AI Vision ({$aiEngineUsed}): Terdeteksi awan mendung - Atap otomatis ditutup (Auto-Close Mendung AKTIF).";
            }
        } elseif ($lightVerdict === 'SUNLIGHT' && in_array($weatherVerdict, ['CERAH', 'BERAWAN']) && $state['rain_detected'] == 0) {
            if ($state['roof_status'] === 'CLOSED') {
                $newRoofStatus = 'OPEN';
                $roofAction = 'OPENED';
                $actionReason = "AI Vision ({$aiEngineUsed}): Terdeteksi sinar matahari cerah - Atap otomatis dibuka untuk menjemur pakaian.";
            }
        } elseif ($lightVerdict === 'ARTIFICIAL_LAMP') {
            if ($state['roof_status'] === 'OPEN') {
                $newRoofStatus = 'CLOSED';
                $roofAction = 'CLOSED';
                $actionReason = "AI Vision ({$aiEngineUsed}): Terdeteksi hanya cahaya lampu ruangan (bukan matahari) - Atap diamankan ditutup.";
            }
        } elseif (in_array($weatherVerdict, ['MALAM']) || $lightVerdict === 'DARK') {
            if ($state['roof_status'] === 'OPEN') {
                $newRoofStatus = 'CLOSED';
                $roofAction = 'CLOSED';
                $actionReason = "AI Vision ({$aiEngineUsed}): Kondisi gelap/malam terdeteksi - Atap ditutup untuk keamanan jemuran.";
            }
        }
    }
    }

    // Jika AI memutuskan untuk mengubah posisi atap (AUTO), terapkan sebagai
    // "input" AI Vision melalui fungsi terpusat (termasuk log & proteksi hujan).
    if ($roofAction !== 'NO_CHANGE') {
        applyRoofCommand($pdo, $newRoofStatus, $actionReason, 'AI_VISION');
    }

    // Update device state (tanpa roof_status; dikelola oleh applyRoofCommand di atas)
    $update = $pdo->prepare("
        UPDATE device_state 
        SET ai_light_verdict = ?,
            ai_weather_verdict = ?,
            ai_confidence = ?,
            ai_drying_recommendation = ?,
            recommended_minutes = ?,
            last_action_reason = ?,
            esp32_cam_last_seen = datetime('now', 'localtime'),
            updated_at = datetime('now', 'localtime')
        WHERE id = 1
    ");
    $update->execute([
        $lightVerdict,
        $weatherVerdict,
        $confidence,
        $recommendation,
        $dryingMinutes,
        $actionReason
    ]);

    // Insert camera history record
    $hist = $pdo->prepare("
        INSERT INTO camera_history (timestamp, image_path, source, ai_classification, ai_confidence, light_detected, roof_action, notes)
        VALUES (datetime('now', 'localtime'), ?, ?, ?, ?, ?, ?, ?)
    ");
    $relativePath = 'uploads/' . $savedFilename;
    $hist->execute([
        $relativePath,
        $source,
        $weatherVerdict . ' (' . $lightVerdict . ')',
        $confidence,
        ($lightVerdict === 'SUNLIGHT' || $lightVerdict === 'ARTIFICIAL_LAMP') ? 1 : 0,
        $roofAction,
        $recommendation . ' [' . $aiEngineUsed . ']'
    ]);

    // Create system alert if Mendung / Hujan / Lamp
    if ($weatherVerdict === 'MENDUNG') {
        $alert = $pdo->prepare("
            INSERT INTO alerts (timestamp, alert_type, title, message, severity)
            VALUES (datetime('now', 'localtime'), 'MENDUNG_ALERT', 'Peringatan Awan Mendung!', 'Kamera ESP32 & AI mendeteksi awan mendung gelap. ' || ?, 'warning')
        ");
        $alert->execute([$state['control_mode'] === 'AUTO' ? 'Atap jemuran ditutup otomatis.' : 'Harap segera tutup jemuran secara manual!']);
    } elseif ($weatherVerdict === 'HUJAN') {
        $alert = $pdo->prepare("
            INSERT INTO alerts (timestamp, alert_type, title, message, severity)
            VALUES (datetime('now', 'localtime'), 'RAIN_DETECTED', 'Peringatan Hujan!', 'Kamera AI mendeteksi curah hujan aktif. Atap diamankan tertutup.', 'danger')
        ");
        $alert->execute();
    } elseif ($lightVerdict === 'ARTIFICIAL_LAMP') {
        $alert = $pdo->prepare("
            INSERT INTO alerts (timestamp, alert_type, title, message, severity)
            VALUES (datetime('now', 'localtime'), 'LAMP_DETECTED', 'Deteksi Lampu Ruangan', 'Sensor mendeteksi cahaya, namun AI Vision mengonfirmasi ini adalah cahaya lampu listrik/ruangan, bukan matahari.', 'info')
        ");
        $alert->execute();
    }

    echo json_encode([
        'success' => true,
        'image_url' => $relativePath,
        'analysis' => [
            'weather' => $weatherVerdict,
            'light_verdict' => $lightVerdict,
            'confidence' => $confidence,
            'engine' => $aiEngineUsed,
            'details' => $analysisResult['details'],
            'recommendation' => $recommendation,
            'recommended_minutes' => $dryingMinutes,
            'roof_action' => $roofAction,
            'new_roof_status' => $newRoofStatus
        ]
    ]);
    exit;
}

/**
 * Ambil konfigurasi AI Vision.
 * Prioritas: file .env (AI_PROVIDER / AI_API_KEY / AI_MODEL) -> lalu database settings.
 */
function getAIConfig($pdo) {
    // 1. Coba dari .env (sumber utama, agar key tidak perlu disimpan di DB)
    $envProvider = strtolower(trim((string)($_ENV['AI_PROVIDER'] ?? getenv('AI_PROVIDER') ?? '')));
    $envKey      = trim((string)($_ENV['AI_API_KEY']   ?? getenv('AI_API_KEY')   ?? ''));
    $envModel    = trim((string)($_ENV['AI_MODEL']    ?? getenv('AI_MODEL')    ?? ''));

    if ($envProvider !== '' && $envKey !== '') {
        if ($envProvider === 'gemini') {
            return [
                'provider'   => 'gemini',
                'gemini_key' => $envKey,
                'openai_key' => null,
                'model'      => $envModel ?: 'gemini-1.5-flash',
                'source'     => 'env'
            ];
        } elseif ($envProvider === 'openai') {
            return [
                'provider'   => 'openai',
                'gemini_key' => null,
                'openai_key' => $envKey,
                'model'      => $envModel ?: 'gpt-4o-mini',
                'source'     => 'env'
            ];
        }
    }

    // 2. Fallback ke database settings (jika .env tidak dikonfigurasi)
    try {
        $rows = $pdo->query("SELECT key, value FROM settings WHERE key IN ('ai_provider','gemini_api_key','openai_api_key','ai_model')")->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {
        $rows = [];
    }
    return [
        'provider'    => strtolower($rows['ai_provider'] ?? 'local'),
        'gemini_key'  => !empty($rows['gemini_api_key']) ? trim($rows['gemini_api_key']) : null,
        'openai_key'  => !empty($rows['openai_api_key']) ? trim($rows['openai_api_key']) : null,
        'model'       => !empty($rows['ai_model']) ? trim($rows['ai_model']) : '',
        'source'      => 'db'
    ];
}

/**
 * Cek apakah file gambar valid dan bisa dibaca.
 */
function isValidImageFile($filePath) {
    if (!file_exists($filePath) || filesize($filePath) < 100) return false;
    $imgInfo = @getimagesize($filePath);
    return ($imgInfo !== false);
}

/**
 * Main AI Analyzer: memanggil API AI Vision (Gemini / OpenAI) secara langsung.
 * Jika gagal (tidak ada key, koneksi error, dsb) -> kembalikan status "Gagal mendeteksi".
 * Tidak ada fallback lokal: analisis 100% bergantung pada AI API (.env).
 */
function analyzeSkyWithAI($filePath, $aiConfig = []) {
    // Validasi file gambar terlebih dahulu
    if (!isValidImageFile($filePath)) {
        return [
            'weather' => 'ERROR',
            'light_verdict' => 'UNKNOWN',
            'confidence' => 0.0,
            'recommended_minutes' => 0,
            'recommendation' => 'Gagal membaca gambar: file tidak valid atau rusak.',
            'details' => "ERROR: Cannot read '" . basename($filePath) . "'. Silakan pastikan gambar yang diambil benar.",
            'error' => "File gambar tidak valid atau tidak bisa dibaca.",
            'engine' => 'Invalid'
        ];
    }

    $provider = strtolower($aiConfig['provider'] ?? 'local');
    $lastError = '';

    // 1. Google Gemini Vision API
    if ($provider === 'gemini' && !empty($aiConfig['gemini_key'])) {
        $res = callGeminiVisionAPI($filePath, $aiConfig['gemini_key'], $aiConfig['model'] ?: 'gemini-1.5-flash');
        if ($res !== null) {
            $res['engine'] = 'Google Gemini Vision AI';
            return $res;
        }
        $lastError = 'Gemini API gagal merespons (periksa koneksi / API key .env).';
    }

    // 2. OpenAI Vision API
    if ($provider === 'openai' && !empty($aiConfig['openai_key'])) {
        $res = callOpenAIVisionAPI($filePath, $aiConfig['openai_key'], $aiConfig['model'] ?: 'gpt-4o-mini');
        if ($res !== null) {
            $res['engine'] = 'OpenAI Vision API';
            return $res;
        }
        $lastError = 'OpenAI API gagal merespons (periksa koneksi / API key .env).';
    }

    // 3. Tidak ada provider AI yang valid / API gagal -> laporkan GAGAL MENDETEKSI
    $errMsg = $lastError ?: 'Tidak ada API AI yang dikonfigurasi. Isi file .env (AI_PROVIDER & AI_API_KEY).';
    return [
        'weather' => 'ERROR',
        'light_verdict' => 'UNKNOWN',
        'confidence' => 0.0,
        'recommended_minutes' => 0,
        'recommendation' => 'Gagal mendeteksi kondisi langit. ' . $errMsg,
        'details' => $errMsg,
        'error' => 'Gagal mendeteksi: ' . $errMsg,
        'engine' => 'AI API (gagal)'
    ];
}

/**
 * Prompt standar untuk analisis citra langit (dipakai Gemini & OpenAI)
 */
function skyGuardVisionPrompt() {
    return "You are SkyGuard AI, an expert computer vision model for an automated IoT clothesline.
Analyze this photo taken by the ESP32-CAM module facing upwards/outdoors.
Task:
1. Identify the dominant light source:
   - 'SUNLIGHT' = natural daylight/sun from outdoor sky.
   - 'ARTIFICIAL_LAMP' = indoor/room bulb, lamp, ceiling light, or any man-made electric light (even if the area looks bright). Choose this when you see a lamp/bulb/fixture or indoor room context.
   - 'DARK'/'MALAM' = natural night darkness with moon/stars and NO artificial light source.
   Do NOT confuse a bright artificial lamp with sunlight, and do NOT mistake an indoor lit room for night.
2. Classify the sky condition: 'CERAH' (sunny/clear, blue sky dominant), 'BERAWAN' (partly cloudy, some clouds but sunlight still visible), 'MENDUNG' (dark gray overcast threatening rain), 'HUJAN' (raining), or 'MALAM' (night/dark).
3. Determine recommended roof action: 'OPEN' (if sunlight and good weather) or 'CLOSED' (if overcast mendung, rain, night, or lamp).
4. Suggest drying duration in minutes (integer between 0 and 180).
5. Give a concise explanation in Indonesian.

IMPORTANT for realistic accuracy:
- Do NOT return a fixed/round confidence like 90.0 or 95.0. Estimate confidence precisely from actual image evidence (e.g. 87.3, 91.6, 78.4).
- If the sky is partially cloudy, set 'weather' to 'BERAWAN' and lower confidence accordingly (clouds reduce certainty of good drying).
- If sky is gray and overcast, use 'MENDUNG'.
- The 'confidence' must reflect how certain you are; clearer skies with strong sunlight = higher confidence (90-99), ambiguous/mixed = lower (70-89).

Respond ONLY with a valid JSON object matching this exact schema:
{
  \"weather\": \"CERAH\",
  \"light_verdict\": \"SUNLIGHT\",
  \"confidence\": 94.7,
  \"roof_action\": \"OPEN\",
  \"recommended_minutes\": 45,
  \"recommendation\": \"Sinar matahari cerah optimal. Rekomendasi jemur: 45 menit.\",
  \"details\": \"Langit biru cerah, luminansi matahari tinggi, tidak ada awan signifikan.\"
}";
}

/**
 * Google Gemini Vision API Caller
 */
function callGeminiVisionAPI($filePath, $apiKey, $model = 'gemini-1.5-flash') {
    if (!file_exists($filePath)) return null;

    $imageData = file_get_contents($filePath);
    $base64Image = base64_encode($imageData);
    $mimeType = 'image/jpeg';
    $imgInfo = @getimagesize($filePath);
    if ($imgInfo && isset($imgInfo['mime'])) {
        $mimeType = $imgInfo['mime'];
    }

    $promptText = skyGuardVisionPrompt();

    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $promptText],
                    [
                        'inline_data' => [
                            'mime_type' => $mimeType,
                            'data' => $base64Image
                        ]
                    ]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.2,
            'response_mime_type' => 'application/json'
        ]
    ];

    $apiUrl = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent?key=' . urlencode($apiKey);

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && !empty($response)) {
        $resJson = json_decode($response, true);
        // Cek apakah API mengembalikan error (misal: model tidak support image)
        $errStr = json_encode($resJson);
        if (stripos($errStr, 'does not support image') !== false ||
            stripos($errStr, 'cannot read') !== false ||
            stripos($errStr, '"error"') !== false) {
            return null; // Error -> fallback ke local CV
        }
        if (isset($resJson['candidates'][0]['content']['parts'][0]['text'])) {
            $aiText = trim($resJson['candidates'][0]['content']['parts'][0]['text']);
            $parsed = json_decode($aiText, true);
            if ($parsed && isset($parsed['weather']) && isset($parsed['light_verdict'])) {
                return [
                    'weather' => strtoupper($parsed['weather']),
                    'light_verdict' => strtoupper($parsed['light_verdict']),
                    'confidence' => (float)($parsed['confidence'] ?? 95.0),
                    'recommended_minutes' => (int)($parsed['recommended_minutes'] ?? 45),
                    'recommendation' => $parsed['recommendation'] ?? 'Pencahayaan teranalisis oleh Gemini AI.',
                    'details' => $parsed['details'] ?? 'Hasil klasifikasi Google Gemini Vision API.'
                ];
            }
        }
    }

    return null; // API gagal -> analyzeSkyWithAI akan mengembalikan "Gagal mendeteksi"
}

/**
 * OpenAI Vision API Caller (Chat Completions dengan image_url)
 */
function callOpenAIVisionAPI($filePath, $apiKey, $model = 'gpt-4o-mini') {
    if (!file_exists($filePath)) return null;

    $imageData = file_get_contents($filePath);
    $base64Image = base64_encode($imageData);
    $mimeType = 'image/jpeg';
    $imgInfo = @getimagesize($filePath);
    if ($imgInfo && isset($imgInfo['mime'])) {
        $mimeType = $imgInfo['mime'];
    }

    $dataUri = 'data:' . $mimeType . ';base64,' . $base64Image;
    $promptText = skyGuardVisionPrompt();

    $payload = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are SkyGuard AI, a computer vision assistant for an IoT clothesline. Reply ONLY with JSON.'
            ],
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $promptText],
                    ['type' => 'image_url', 'image_url' => ['url' => $dataUri]]
                ]
            ]
        ],
        'response_format' => ['type' => 'json_object'],
        'temperature' => 0.2
    ];

    $apiUrl = 'https://api.openai.com/v1/chat/completions';

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && !empty($response)) {
        $resJson = json_decode($response, true);
        // Cek apakah API mengembalikan error (misal: model tidak support image)
        $errStr = json_encode($resJson);
        if (stripos($errStr, 'does not support image') !== false ||
            stripos($errStr, 'cannot read') !== false ||
            stripos($errStr, '"error"') !== false) {
            return null; // Error -> fallback ke local CV
        }
        if (isset($resJson['choices'][0]['message']['content'])) {
            $aiText = trim($resJson['choices'][0]['message']['content']);
            $parsed = json_decode($aiText, true);
            if ($parsed && isset($parsed['weather']) && isset($parsed['light_verdict'])) {
                return [
                    'weather' => strtoupper($parsed['weather']),
                    'light_verdict' => strtoupper($parsed['light_verdict']),
                    'confidence' => (float)($parsed['confidence'] ?? 95.0),
                    'recommended_minutes' => (int)($parsed['recommended_minutes'] ?? 45),
                    'recommendation' => $parsed['recommendation'] ?? 'Pencahayaan teranalisis oleh OpenAI Vision.',
                    'details' => $parsed['details'] ?? 'Hasil klasifikasi OpenAI Vision API.'
                ];
            }
        }
    }

    return null; // API gagal -> analyzeSkyWithAI akan mengembalikan "Gagal mendeteksi"
}
