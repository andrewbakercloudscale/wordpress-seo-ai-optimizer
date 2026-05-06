<?php
/**
 * POST /webhook
 * PayFast ITN (Instant Transaction Notification) handler.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/payfast.php';

// PayFast expects HTTP 200 immediately on receipt
http_response_code(200);
echo 'OK';

if (!pf_verify_itn($_POST)) {
    error_log('PayFast ITN verification failed');
    exit;
}

$status     = $_POST['payment_status']    ?? '';
$token      = $_POST['token']             ?? '';
$session    = $_POST['custom_str1']       ?? '';
$custom2    = $_POST['custom_str2']       ?? '';
$custom3    = $_POST['custom_str3']       ?? '';
$pf_pay_id  = $_POST['pf_payment_id']    ?? '';

$db = db();

// --- BOOST (one-time purchase) ---
if ($custom3 === 'boost' && $status === 'COMPLETE' && $custom2) {
    $db->prepare("
        UPDATE licenses SET monthly_limit = monthly_limit + 200
        WHERE license_key = ? AND status = 'active'
    ")->execute([$custom2]);
    exit;
}

// --- SUBSCRIPTION: find the license row ---
// Renewal: token already stored → look up by token
$row = null;
if ($token) {
    $row = $db->prepare("SELECT * FROM licenses WHERE pf_subscription_token = ? LIMIT 1")
               ->execute([$token]) ? $db->prepare("SELECT * FROM licenses WHERE pf_subscription_token = ? LIMIT 1")->execute([$token]) : null;
    // Re-fetch cleanly
    $stmt = $db->prepare("SELECT * FROM licenses WHERE pf_subscription_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $row = $stmt->fetch() ?: null;
}
// First activation: no token stored yet → look up by session_token
if (!$row && $session) {
    $stmt = $db->prepare("SELECT * FROM licenses WHERE session_token = ? LIMIT 1");
    $stmt->execute([$session]);
    $row = $stmt->fetch() ?: null;
}

if (!$row) {
    error_log("PayFast ITN: no license found for token={$token} session={$session}");
    exit;
}

$id = (int) $row['id'];

switch ($status) {
    case 'COMPLETE':
        // First activation — generate license key and activate
        if (!$row['license_key']) {
            $license_key = bin2hex(random_bytes(16));
            $db->prepare("
                UPDATE licenses
                SET license_key = ?, pf_subscription_token = ?, pf_payment_id = ?,
                    status = 'active', usage_reset_date = ?, session_token = ''
                WHERE id = ?
            ")->execute([$license_key, $token, $pf_pay_id, pf_current_month_start(), $id]);
        } else {
            // Renewal — reset usage for new billing cycle
            $db->prepare("
                UPDATE licenses
                SET pf_subscription_token = ?, pf_payment_id = ?,
                    status = 'active', monthly_requests = 0, usage_reset_date = ?
                WHERE id = ?
            ")->execute([$token, $pf_pay_id, pf_current_month_start(), $id]);
        }
        break;

    case 'FAILED':
        $db->prepare("UPDATE licenses SET status = 'past_due' WHERE id = ?")
           ->execute([$id]);
        break;

    case 'CANCELLED':
        $db->prepare("UPDATE licenses SET status = 'cancelled' WHERE id = ?")
           ->execute([$id]);
        break;
}
