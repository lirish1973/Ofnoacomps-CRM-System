<?php
/**
 * Elementor Lead Webhook Handler
 * קובץ זה משדר לידים מטופס Elementor לשרת ה-CRM
 * 
 * Setup: הוסף את URL הזה לטופס Elementor בהגדרות "Custom Webhook" או "Form Action"
 * URL: https://www.tryit.co.il/wp-content/plugins/ofnoacomps-crm/elementor-webhook.php
 * 
 * או בשרת מקומי: http://localhost:3001/api/elementor/leads
 */

// Allow cross-origin requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    $data = $_POST;
}

// Clean data
$lead = [];
foreach ($data as $key => $value) {
    $cleanKey = sanitize_text_field($key);
    $cleanValue = is_array($value) ? implode(', ', array_map('sanitize_text_field', $value)) : sanitize_text_field($value);
    $lead[$cleanKey] = $cleanValue;
}

// Add metadata
$lead['IP Address'] = sanitize_text_field($_SERVER['REMOTE_ADDR']);
$lead['User Agent'] = sanitize_text_field($_SERVER['HTTP_USER_AGENT']);
$lead['Timestamp'] = gmdate('Y-m-d H:i:s', time() + (3 * 3600)); // Israel Time

// Option 1: Send to local CRM server (recommended for production)
$crm_endpoint = 'http://localhost:3001/api/elementor/leads';

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => $crm_endpoint,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode($lead),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 10
]);

$response = curl_exec($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curl_error = curl_error($curl);
curl_close($curl);

// Log the request
$log_file = __DIR__ . '/../../_leads_log.txt';
$log_entry = "[" . date('Y-m-d H:i:s') . "] Lead from Elementor | HTTP:" . $http_code . " | Phone: " . ($lead['phone'] ?? $lead['Phone'] ?? 'N/A') . "\n";
error_log($log_entry, 3, $log_file);

// Return response
if ($http_code === 200) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'הליד התקבל בהצלחה וישלח אליך בקרוב',
        'timestamp' => $lead['Timestamp']
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'שגיאה בשליחת הליד',
        'error' => $curl_error ?? 'Unknown error'
    ]);
}

function sanitize_text_field($text) {
    return htmlspecialchars(trim($text), ENT_QUOTES, 'UTF-8');
}
?>
