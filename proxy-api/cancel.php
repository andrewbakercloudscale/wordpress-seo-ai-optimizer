<?php
/**
 * POST /cancel
 * Body: { key: <license_key>, confirm: "yes" }
 * Calls PayFast API to cancel the subscription, then updates DB.
 * The goodbye email is sent by the WordPress plugin side via wp_mail().
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/payfast.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'POST required']); exit;
}

$body    = json_decode(file_get_contents('php://input'), true) ?: [];
$key     = preg_replace('/[^a-f0-9]/', '', $body['key'] ?? '');
$confirm = $body['confirm'] ?? '';

if (!$key || $confirm !== 'yes') {
    http_response_code(400);
    echo json_encode(['error' => 'key and confirm=yes required']);
    exit;
}

$db   = db();
$stmt = $db->prepare("SELECT * FROM licenses WHERE license_key = ? LIMIT 1");
$stmt->execute([$key]);
$row  = $stmt->fetch();

if (!$row) {
    http_response_code(404); echo json_encode(['error' => 'License not found']); exit;
}
if ($row['status'] === 'cancelled') {
    echo json_encode(['ok' => true, 'message' => 'Already cancelled']); exit;
}

$pf_token = $row['pf_subscription_token'] ?? '';
if (!$pf_token) {
    http_response_code(500);
    echo json_encode(['error' => 'No subscription token on file — contact support']);
    exit;
}

$ok = pf_cancel_subscription($pf_token);

if ($ok) {
    $db->prepare("UPDATE licenses SET status = 'cancelled' WHERE id = ?")
       ->execute([$row['id']]);
    echo json_encode(['ok' => true, 'message' => 'Subscription cancelled']);
} else {
    http_response_code(502);
    echo json_encode(['error' => 'PayFast API call failed — please try again or contact support']);
}
