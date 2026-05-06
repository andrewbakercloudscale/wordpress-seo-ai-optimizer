<?php
/**
 * POST /v1/messages
 * Validates license key, enforces rate limits, proxies to Anthropic API.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, X-License-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST')    { http_response_code(405); echo json_encode(['error' => 'POST required']); exit; }

// Enforce max body size (64 KB)
$raw = file_get_contents('php://input', false, null, 0, 65537);
if (strlen($raw) > 65536) {
    http_response_code(413); echo json_encode(['error' => 'Request too large']); exit;
}

$key = trim($_SERVER['HTTP_X_LICENSE_KEY'] ?? '');
if (!$key) {
    http_response_code(400); echo json_encode(['error' => 'X-License-Key header required']); exit;
}

$db   = db();
$stmt = $db->prepare("SELECT * FROM licenses WHERE license_key = ? LIMIT 1");
$stmt->execute([$key]);
$row  = $stmt->fetch();

if (!$row) {
    http_response_code(403); echo json_encode(['error' => 'License not found']); exit;
}
if ($row['status'] !== 'active') {
    http_response_code(403);
    echo json_encode(['error' => 'License ' . $row['status'], 'status' => $row['status']]);
    exit;
}

// Auto-reset usage at start of new billing month
if ($row['usage_reset_date'] < date('Y-m-01')) {
    $db->prepare("UPDATE licenses SET monthly_requests = 0, usage_reset_date = ? WHERE id = ?")
       ->execute([date('Y-m-01'), $row['id']]);
    $row['monthly_requests'] = 0;
}

if ((int)$row['monthly_requests'] >= (int)$row['monthly_limit']) {
    http_response_code(429);
    echo json_encode(['error' => 'Monthly request limit reached (' . $row['monthly_limit'] . ')']);
    exit;
}

// Parse and sanitise the request body
$body = json_decode($raw, true);
if (!$body || !isset($body['messages'])) {
    http_response_code(400); echo json_encode(['error' => 'Invalid request body']); exit;
}

$allowed = ['model', 'messages', 'system', 'max_tokens', 'temperature', 'stream'];
$payload = array_intersect_key($body, array_flip($allowed));
$payload['model'] = $payload['model'] ?? 'claude-sonnet-4-6';

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'x-api-key: ' . ANTHROPIC_API_KEY,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_SSL_VERIFYPEER => true,
]);
$resp = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($err) {
    http_response_code(502); echo json_encode(['error' => 'Upstream error: ' . $err]); exit;
}

// Increment usage on success
if ($code === 200) {
    $db->prepare("UPDATE licenses SET monthly_requests = monthly_requests + 1, last_used_at = datetime('now') WHERE id = ?")
       ->execute([$row['id']]);
}

http_response_code($code);
echo $resp;
