<?php
/**
 * POST /checkout
 * Body: { email, site_url, type: "subscription"|"boost", license_key? }
 * Returns: { checkout_url, session_token }
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/payfast.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON body']);
    exit;
}

$email       = filter_var(trim($body['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$site_url    = filter_var(trim($body['site_url'] ?? ''), FILTER_VALIDATE_URL) ?: '';
$type        = in_array($body['type'] ?? '', ['subscription', 'boost'], true) ? $body['type'] : 'subscription';
$license_key = preg_replace('/[^a-f0-9]/', '', $body['license_key'] ?? '');

if (!$email) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid email required']);
    exit;
}

$session_token = bin2hex(random_bytes(16));
$db = db();

if ($type === 'subscription') {
    // Create pending license row
    $db->prepare("
        INSERT INTO licenses (email, site_url, session_token, status, usage_reset_date, created_at)
        VALUES (?, ?, ?, 'pending', ?, datetime('now'))
    ")->execute([$email, $site_url, $session_token, pf_current_month_start()]);

    $params = [
        'merchant_id'     => PAYFAST_MERCHANT_ID,
        'merchant_key'    => PAYFAST_MERCHANT_KEY,
        'return_url'      => PROXY_BASE_URL . '/manage?session=' . urlencode($session_token) . '&activated=1',
        'cancel_url'      => $site_url ?: PROXY_BASE_URL,
        'notify_url'      => PROXY_BASE_URL . '/webhook',
        'email_address'   => $email,
        'amount'          => '5.00',
        'item_name'       => 'CloudScale SEO AI - Managed API',
        'item_description'=> '200 AI requests per month',
        'm_payment_id'    => $session_token,
        'custom_str1'     => $session_token,
        'subscription_type' => '1',
        'billing_date'    => date('Y-m-d'),
        'recurring_amount'=> '5.00',
        'frequency'       => '3',   // monthly
        'cycles'          => '0',   // indefinite
    ];
} else {
    // Boost: one-time +200 requests payment
    if (!$license_key) {
        http_response_code(400);
        echo json_encode(['error' => 'license_key required for boost']);
        exit;
    }
    $params = [
        'merchant_id'     => PAYFAST_MERCHANT_ID,
        'merchant_key'    => PAYFAST_MERCHANT_KEY,
        'return_url'      => $site_url ?: PROXY_BASE_URL,
        'cancel_url'      => $site_url ?: PROXY_BASE_URL,
        'notify_url'      => PROXY_BASE_URL . '/webhook',
        'email_address'   => $email,
        'amount'          => '5.00',
        'item_name'       => 'CloudScale SEO AI - Request Boost (+200)',
        'm_payment_id'    => $session_token,
        'custom_str1'     => $session_token,
        'custom_str2'     => $license_key,  // used by webhook to identify account
        'custom_str3'     => 'boost',
    ];
}

$checkout_url = pf_checkout_url($params);

echo json_encode(['checkout_url' => $checkout_url, 'session_token' => $session_token]);
