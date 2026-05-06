<?php
/**
 * GET /status?key=<license_key>          — usage + status for active subscription
 * GET /status?session_token=<token>      — poll after checkout; returns license_key once active
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$db  = db();
$key = $_GET['key']           ?? '';
$ses = $_GET['session_token'] ?? '';

if ($key) {
    $stmt = $db->prepare("SELECT * FROM licenses WHERE license_key = ? LIMIT 1");
    $stmt->execute([$key]);
    $row = $stmt->fetch();
    if (!$row) { http_response_code(404); echo json_encode(['error' => 'License not found']); exit; }

    // Auto-reset usage if we're in a new billing month
    if ($row['usage_reset_date'] < date('Y-m-01')) {
        $db->prepare("UPDATE licenses SET monthly_requests = 0, usage_reset_date = ? WHERE id = ?")
           ->execute([date('Y-m-01'), $row['id']]);
        $row['monthly_requests'] = 0;
        $row['usage_reset_date'] = date('Y-m-01');
    }

    echo json_encode([
        'status'           => $row['status'],
        'license_key'      => $row['license_key'],
        'monthly_requests' => (int) $row['monthly_requests'],
        'monthly_limit'    => (int) $row['monthly_limit'],
        'reset_date'       => date('Y-m-01', strtotime('+1 month', strtotime($row['usage_reset_date']))),
    ]);
    exit;
}

if ($ses) {
    $stmt = $db->prepare("SELECT * FROM licenses WHERE session_token = ? LIMIT 1");
    $stmt->execute([$ses]);
    $row = $stmt->fetch();
    if (!$row) {
        // Also check if the license was activated and session_token was cleared
        $stmt2 = $db->prepare("SELECT * FROM licenses WHERE pf_payment_id != '' AND status = 'active' ORDER BY id DESC LIMIT 1");
        $stmt2->execute();
        // No reliable way to find without session — return pending
        echo json_encode(['status' => 'pending']); exit;
    }

    $out = ['status' => $row['status']];
    if ($row['status'] === 'active' && $row['license_key']) {
        $out['license_key']      = $row['license_key'];
        $out['monthly_requests'] = (int) $row['monthly_requests'];
        $out['monthly_limit']    = (int) $row['monthly_limit'];
        $out['reset_date']       = date('Y-m-01', strtotime('+1 month', strtotime($row['usage_reset_date'])));
    }
    echo json_encode($out);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'Provide key or session_token parameter']);
