<?php
/**
 * GET /billing-portal?key=<license_key>
 * Returns a 302 redirect to the subscription management page.
 * Called by the plugin's ajax_proxy_billing_portal handler.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$key = preg_replace('/[^a-f0-9]/', '', $_GET['key'] ?? '');

if (!$key) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'key required']);
    exit;
}

$db   = db();
$stmt = $db->prepare("SELECT id FROM licenses WHERE license_key = ? LIMIT 1");
$stmt->execute([$key]);
$row  = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'License not found']);
    exit;
}

$manage_url = PROXY_BASE_URL . '/manage?key=' . urlencode($key);
header('Location: ' . $manage_url, true, 302);
exit;
