<?php
/**
 * PayFast helpers — checkout URL builder, ITN verifier, subscription API.
 */

function pf_checkout_url(array $params): string {
    $base = PAYFAST_TESTING
        ? 'https://sandbox.payfast.co.za/eng/process'
        : 'https://www.payfast.co.za/eng/process';
    return $base . '?' . pf_sign($params, true);
}

/**
 * Build a signed query string. If $append_to_url is true, the signature is
 * appended as a parameter. If false, returns the plain string for verification.
 */
function pf_sign(array $data, bool $include_sig = true): string {
    // Remove empty values and the signature field
    $clean = [];
    foreach ($data as $k => $v) {
        if ($v !== '' && $k !== 'signature') {
            $clean[$k] = $v;
        }
    }
    ksort($clean);
    $str = http_build_query($clean);
    if (PAYFAST_PASSPHRASE !== '') {
        $str .= '&passphrase=' . urlencode(PAYFAST_PASSPHRASE);
    }
    if (!$include_sig) return $str;
    return http_build_query($clean) . '&signature=' . md5($str);
}

function pf_verify_itn(array $post): bool {
    // 1. Verify source IP
    $valid_ips = [
        '197.97.145.144', '197.97.145.145', '197.97.145.146', '197.97.145.147',
        '41.74.179.194',  '41.74.179.195',  '41.74.179.196',  '41.74.179.197',
    ];
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!PAYFAST_TESTING && !in_array($ip, $valid_ips, true)) {
        error_log("PayFast ITN rejected: invalid IP {$ip}");
        return false;
    }

    // 2. Verify signature
    $received_sig = $post['signature'] ?? '';
    $check_str    = pf_sign($post, false);
    if (md5($check_str) !== $received_sig) {
        error_log('PayFast ITN rejected: signature mismatch');
        return false;
    }

    return true;
}

/**
 * Cancel a PayFast subscription via the Recurring Billing API.
 */
function pf_cancel_subscription(string $token): bool {
    $headers = pf_api_headers();
    $url = 'https://api.payfast.co.za/subscriptions/' . rawurlencode($token) . '/cancel';
    if (PAYFAST_TESTING) $url .= '?testing=true';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => 'PUT',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $resp = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) {
        error_log("PayFast cancel API returned HTTP {$code}: {$resp}");
    }
    return $code === 200;
}

function pf_api_headers(): array {
    $ts = gmdate('Y-m-d\TH:i:s');
    $data = ['merchant-id' => PAYFAST_MERCHANT_ID, 'timestamp' => $ts, 'version' => 'v1'];
    if (PAYFAST_PASSPHRASE !== '') $data['passphrase'] = PAYFAST_PASSPHRASE;
    ksort($data);
    $sig = md5(http_build_query($data));
    return [
        'merchant-id: ' . PAYFAST_MERCHANT_ID,
        'version: v1',
        'timestamp: ' . $ts,
        'signature: ' . $sig,
    ];
}

function pf_current_month_start(): string {
    return date('Y-m-01');
}
