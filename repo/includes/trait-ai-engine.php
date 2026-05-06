<?php
/**
 * AI engine — central dispatcher for Anthropic Claude and Google Gemini API calls.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_AI_Engine {

    /**
     * Verifies capability and nonce — kept for traits outside this file that still call it.
     * New handlers should call check_ajax_referer() and current_user_can() directly so
     * PHPCS NonceVerification can trace the check in the handler scope.
     *
     * @since 4.0.0
     * @return void
     */
    private function ajax_check(): void {
        if (!current_user_can('manage_options')) wp_send_json_error('Forbidden', 403);
        if (!check_ajax_referer('cs_seo_nonce', 'nonce', false)) wp_send_json_error('Bad nonce', 403);
    }

    /**
     * Resolves the stored model setting to an actual model ID.
     * '_auto' (or empty string) maps to the recommended model for the provider.
     *
     * @since 4.19.45
     * @param string $model    Stored model setting value.
     * @param string $provider 'anthropic' or 'gemini'.
     * @return string Resolved model ID.
     */
    private function resolve_model(string $model, string $provider): string {
        if ($model === '_auto' || $model === '') {
            return self::recommended_model($provider);
        }
        return $model;
    }

    /**
     * Central AI dispatcher — routes to Anthropic or Gemini and returns the response text.
     *
     * @since 4.0.0
     * @param string     $provider       'anthropic' or 'gemini'.
     * @param string     $key            API key for the selected provider.
     * @param string     $model          Model ID string.
     * @param string     $system         System prompt.
     * @param string     $user_msg       Initial user message.
     * @param array|null $extra_messages Additional turns to append after the initial user message (multi-turn correction).
     * @param int        $max_tokens     Maximum tokens to generate.
     * @return string Response text from the AI.
     */
    private function dispatch_ai(string $provider, string $key, string $model, string $system, string $user_msg, ?array $extra_messages, int $max_tokens): string {
        if ($provider !== 'gemini' && (int)($this->ai_opts['proxy_enabled'] ?? 0) && !empty($this->ai_opts['proxy_license_key'])) {
            return $this->dispatch_via_proxy($model, $system, $user_msg, $extra_messages, $max_tokens);
        }
        if ($provider === 'gemini') {
            return $this->call_gemini($key, $model, $system, $user_msg, $extra_messages, $max_tokens);
        }
        return $this->call_claude($key, $model, $system, $user_msg, $extra_messages, $max_tokens);
    }

    /**
     * Forward an Anthropic request to our managed proxy using the stored license key.
     */
    private function dispatch_via_proxy(string $model, string $system, string $user_msg, ?array $extra_messages, int $max_tokens): string {
        $license_key = (string)($this->ai_opts['proxy_license_key'] ?? '');
        $messages    = [['role' => 'user', 'content' => $user_msg]];
        if ($extra_messages) {
            $messages = array_merge($messages, $extra_messages);
        }
        $payload = [
            'model'      => $this->resolve_model($model, 'anthropic'),
            'max_tokens' => $max_tokens,
            'system'     => $system,
            'messages'   => $messages,
        ];
        $body = wp_json_encode($payload);
        if ($body === false) {
            throw new \RuntimeException('Failed to encode proxy request as JSON.');
        }
        $response = wp_remote_post('https://api.andrewbaker.ninja/v1/messages', [
            'timeout' => 45,
            'headers' => [
                'Content-Type'  => 'application/json',
                'X-License-Key' => $license_key,
            ],
            'body' => $body,
        ]);
        if (is_wp_error($response)) {
            throw new \RuntimeException('Proxy HTTP error: ' . $response->get_error_message()); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $code      = wp_remote_retrieve_response_code($response);
        $resp_body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 429) {
            $msg = $resp_body['error'] ?? 'Monthly request limit reached';
            throw new \RuntimeException('Managed API: ' . $msg); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ($code === 403) {
            $msg    = $resp_body['error'] ?? 'License inactive';
            $status = $resp_body['status'] ?? '';
            // Update cached proxy status so UI reflects the new state immediately
            $this->ai_opts['proxy_status'] = $status ?: 'inactive';
            update_option('cs_seo_ai_opts', $this->ai_opts);
            throw new \RuntimeException('Managed API: ' . $msg); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ($code !== 200) {
            $detail = $resp_body['error']['message'] ?? ($resp_body['error'] ?? '');
            throw new \RuntimeException("Managed API HTTP {$code}" . ($detail ? ": {$detail}" : '')); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return trim($resp_body['content'][0]['text'] ?? '');
    }

    /**
     * Make an Anthropic Claude API call, with 429 retry.
     */
    private function call_claude(string $key, string $model, string $system, string $user_msg, ?array $extra_messages, int $max_tokens): string {
        $messages = [['role' => 'user', 'content' => $user_msg]];
        if ($extra_messages) {
            $messages = array_merge($messages, $extra_messages);
        }
        $payload = [
            'model'      => $model,
            'max_tokens' => $max_tokens,
            'system'     => $system,
            'messages'   => $messages,
        ];
        $headers = [
            'Content-Type'      => 'application/json',
            'x-api-key'         => $key,
            'anthropic-version' => '2023-06-01',
        ];
        $body = wp_json_encode($payload);
        if ($body === false) {
            throw new \RuntimeException('Failed to encode API request as JSON — post content may contain invalid UTF-8.'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'timeout' => 45,
            'headers' => $headers,
            'body'    => $body,
        ]);
        if (is_wp_error($response)) throw new \RuntimeException( 'HTTP error: ' . $response->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        $code = wp_remote_retrieve_response_code($response);
        if ($code === 429 || $code === 529) {
            // Do not sleep() inside an AJAX handler — return immediately so the
            // JS caller can back off and retry. The error label includes the code
            // so the client can distinguish rate-limit retries from real errors.
            $label = $code === 529 ? '529 - Service Overloaded' : '429 - Rate Limited';
            throw new \RuntimeException( "Response: {$label} — retry after a short delay" ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON in Anthropic response: ' . json_last_error_msg()); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ($code !== 200) {
            $label = match($code) {
                429 => '429 - Rate Limited',
                529 => '529 - Service Overloaded',
                500 => '500 - Anthropic Server Error',
                401 => '401 - Invalid API Key',
                403 => '403 - Forbidden',
                default => "HTTP {$code}",
            };
            $detail = $body['error']['message'] ?? '';
            $msg    = "Response: {$label}" . ($detail ? " — {$detail}" : '');
            throw new \RuntimeException( $msg ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return trim($body['content'][0]['text'] ?? '');
    }

    /**
     * Make a Google Gemini API call.
     * Gemini uses a flat contents array with role:user/model alternating.
     * The system prompt is injected as the first user turn since Gemini
     * supports a systemInstruction field in the v1beta API.
     */
    private function call_gemini(string $key, string $model, string $system, string $user_msg, ?array $extra_messages, int $max_tokens): string {
        // Convert Anthropic-style messages to Gemini contents array.
        $contents = [
            ['role' => 'user', 'parts' => [['text' => $user_msg]]],
        ];
        if ($extra_messages) {
            foreach ($extra_messages as $m) {
                $gemini_role = $m['role'] === 'assistant' ? 'model' : 'user';
                $contents[]  = ['role' => $gemini_role, 'parts' => [['text' => $m['content']]]];
            }
        }
        $url     = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $key );
        $payload = [
            'systemInstruction' => ['parts' => [['text' => $system]]],
            'contents'          => $contents,
            'generationConfig'  => ['maxOutputTokens' => $max_tokens],
        ];
        $body = wp_json_encode($payload);
        if ($body === false) {
            throw new \RuntimeException('Failed to encode API request as JSON — post content may contain invalid UTF-8.'); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $response = wp_remote_post($url, [
            'timeout' => 45,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => $body,
        ]);
        if (is_wp_error($response)) throw new \RuntimeException( 'HTTP error: ' . $response->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        // Gemini uses 429 for quota exceeded — return immediately so the JS caller
        // can back off and retry rather than blocking the PHP worker with sleep().
        if (wp_remote_retrieve_response_code($response) === 429) {
            throw new \RuntimeException( 'Response: 429 - Rate Limited — retry after a short delay' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON in Gemini response: ' . json_last_error_msg()); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if ($code !== 200) {
            $label = match($code) {
                429 => '429 - Rate Limited',
                500 => '500 - Gemini Server Error',
                401 => '401 - Invalid API Key',
                403 => '403 - Forbidden',
                default => "HTTP {$code}",
            };
            $detail = $body['error']['message'] ?? '';
            $msg    = "Response: {$label}" . ($detail ? " — {$detail}" : '');
            throw new \RuntimeException( $msg ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return trim($body['candidates'][0]['content']['parts'][0]['text'] ?? '');
    }

    // =========================================================================
    // Managed API proxy AJAX handlers
    // =========================================================================

    /**
     * Initiates a managed-API checkout session via the proxy service.
     *
     * @since 4.21.52
     * @return void
     */
    public function ajax_proxy_checkout(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );
        $email    = sanitize_email( (string) ( $_POST['email']    ?? '' ) );    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by check_ajax_referer() above
        $site_url = esc_url_raw( (string) ( $_POST['site_url']   ?? '' ) );     // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (!is_email($email)) { wp_send_json_error(['message' => 'Invalid email address']); }

        $resp = wp_remote_post('https://api.andrewbaker.ninja/checkout', [
            'timeout' => 20,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode(['email' => $email, 'site_url' => $site_url, 'type' => 'subscription']),
        ]);
        if (is_wp_error($resp)) { wp_send_json_error(['message' => $resp->get_error_message()]); }

        $data = json_decode(wp_remote_retrieve_body($resp), true);
        if (empty($data['checkout_url'])) {
            wp_send_json_error(['message' => $data['error'] ?? 'Checkout failed']);
        }

        if (!empty($data['session_token'])) {
            $this->ai_opts['proxy_session_id'] = $data['session_token'];
            $this->ai_opts['proxy_status']     = 'pending';
            $this->ai_opts['proxy_email']      = $email;
            update_option(self::AI_OPT, $this->ai_opts);
        }

        wp_send_json_success(['checkout_url' => $data['checkout_url']]);
    }

    /**
     * Initiates a boost top-up checkout session for an existing subscriber.
     *
     * @since 4.21.52
     * @return void
     */
    public function ajax_proxy_boost_checkout(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );
        $key = (string)($this->ai_opts['proxy_license_key'] ?? '');
        if (!$key) { wp_send_json_error(['message' => 'No active license found']); }

        $email = (string)($this->ai_opts['proxy_email'] ?? '');
        if (!$email) {
            // Fallback: use current user email
            $email = wp_get_current_user()->user_email ?? '';
        }

        $resp = wp_remote_post('https://api.andrewbaker.ninja/checkout', [
            'timeout' => 20,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode(['email' => $email, 'license_key' => $key, 'type' => 'boost']),
        ]);
        if (is_wp_error($resp)) { wp_send_json_error(['message' => $resp->get_error_message()]); }

        $data = json_decode(wp_remote_retrieve_body($resp), true);
        if (empty($data['checkout_url'])) {
            wp_send_json_error(['message' => $data['error'] ?? 'Boost checkout failed']);
        }
        wp_send_json_success(['checkout_url' => $data['checkout_url']]);
    }

    /**
     * Cancels the active managed-API subscription via the proxy service.
     *
     * @since 4.21.52
     * @return void
     */
    public function ajax_proxy_cancel(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );
        $key = (string)($this->ai_opts['proxy_license_key'] ?? '');
        if (!$key) { wp_send_json_error(['message' => 'No active license found']); }

        $resp = wp_remote_post('https://api.andrewbaker.ninja/cancel', [
            'timeout' => 20,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode(['key' => $key, 'confirm' => 'yes']),
        ]);
        if (is_wp_error($resp)) { wp_send_json_error(['message' => $resp->get_error_message()]); }

        $data = json_decode(wp_remote_retrieve_body($resp), true);
        if (!empty($data['ok'])) {
            $this->ai_opts['proxy_status'] = 'cancelled';
            update_option(self::AI_OPT, $this->ai_opts);
            $email    = (string)($this->ai_opts['proxy_email'] ?? '');
            $site_url = home_url('/');
            if ($email) {
                $this->send_cancellation_email($email, $site_url);
            }
            wp_send_json_success(['message' => 'Subscription cancelled']);
        } else {
            wp_send_json_error(['message' => $data['error'] ?? 'Cancellation failed. Please try again']);
        }
    }

    private function send_cancellation_email(string $to, string $site_url): void {
        $site_label = wp_parse_url($site_url, PHP_URL_HOST) ?: get_bloginfo('name');

        $subject = 'Your CloudScale SEO subscription has been cancelled';

        $body = '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:40px 0">
  <tr><td align="center">
    <table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08)">

      <tr>
        <td style="background:linear-gradient(135deg,#4338ca 0%,#6366f1 100%);padding:32px 40px;text-align:center">
          <p style="margin:0;font-size:13px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.7)">CloudScale SEO AI Optimizer</p>
          <h1 style="margin:8px 0 0;font-size:24px;font-weight:700;color:#fff">Until next time 👋</h1>
        </td>
      </tr>

      <tr>
        <td style="padding:36px 40px">
          <p style="margin:0 0 16px;font-size:16px;color:#1d2327;line-height:1.6">
            Your subscription for <strong>' . esc_html($site_label) . '</strong> has been cancelled.
            You&rsquo;ll keep full AI access until the end of your current billing period. We won&rsquo;t cut you off early.
          </p>

          <p style="margin:0 0 16px;font-size:15px;color:#374151;line-height:1.6">
            We&rsquo;re genuinely sorry it didn&rsquo;t work out. If something wasn&rsquo;t right (a missing feature, a bug, or just not the right fit), we&rsquo;d love to hear about it. We read every reply and use the feedback to make the plugin better for everyone.
          </p>

          <p style="margin:0 0 24px;font-size:15px;color:#374151;line-height:1.6">
            You were an awesome client and we appreciate you giving us a shot. The plugin remains completely free. All non-AI features stay active forever. And whenever you want the AI features back, you can resubscribe in seconds from the Get&nbsp;Started tab.
          </p>

          <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px">
            <tr>
              <td style="background:#f5f3ff;border:1px solid #e0e7ff;border-left:4px solid #6366f1;border-radius:8px;padding:16px 20px">
                <p style="margin:0;font-size:12px;font-weight:700;color:#4338ca;text-transform:uppercase;letter-spacing:.05em">What stays active forever (free)</p>
                <ul style="margin:8px 0 0;padding-left:18px;font-size:13px;color:#374151;line-height:2">
                  <li>Full site audit &amp; SEO score</li>
                  <li>XML Sitemap + llms.txt</li>
                  <li>Schema, OG &amp; Meta tags</li>
                  <li>Broken link checker</li>
                  <li>HTTPS fixer &amp; redirects</li>
                  <li>Category analysis &amp; performance tools</li>
                </ul>
              </td>
            </tr>
          </table>

          <p style="margin:0 0 8px;font-size:14px;color:#6b7280;line-height:1.6">
            Questions or feedback? Just reply to this email. We are a small team and a real person will get back to you.
          </p>

          <p style="margin:0;font-size:14px;color:#6b7280">
            With gratitude,<br>
            <strong style="color:#374151">Andrew &amp; the CloudScale team</strong>
          </p>
        </td>
      </tr>

      <tr>
        <td style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:20px 40px;text-align:center">
          <p style="margin:0;font-size:11px;color:#9ca3af;line-height:1.6">
            CloudScale SEO AI Optimizer. Open source, always free to install.<br>
            <a href="https://andrewbaker.ninja/" style="color:#6366f1;text-decoration:none">andrewbaker.ninja</a>
          </p>
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>';

        add_filter('wp_mail_content_type', static function() { return 'text/html'; });
        wp_mail(
            $to,
            $subject,
            $body,
            ['From: CloudScale SEO <noreply@andrewbaker.ninja>', 'Reply-To: support@andrewbaker.ninja']
        );
        remove_all_filters('wp_mail_content_type');
    }

    /**
     * Toggles the managed-API proxy on or off without changing other settings.
     *
     * @since 4.21.52
     * @return void
     */
    public function ajax_proxy_set_enabled(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );
        $enabled = (int) (bool) ( $_POST['enabled'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by check_ajax_referer() above
        $this->ai_opts['proxy_enabled'] = $enabled;
        update_option(self::AI_OPT, $this->ai_opts);
        wp_send_json_success();
    }

    /**
     * Fetches the latest subscription status from the proxy service and persists it.
     *
     * @since 4.21.52
     * @return void
     */
    public function ajax_proxy_refresh_status(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );
        $key = (string)($this->ai_opts['proxy_license_key'] ?? '');
        if (!$key) { wp_send_json_error(['message' => 'No license key stored']); }

        $resp = wp_remote_get('https://api.andrewbaker.ninja/status?key=' . urlencode($key), ['timeout' => 10]);
        if (is_wp_error($resp)) { wp_send_json_error(['message' => $resp->get_error_message()]); }

        $data = json_decode(wp_remote_retrieve_body($resp), true);
        if (!$data) { wp_send_json_error(['message' => 'Invalid response from proxy']); }

        $this->ai_opts['proxy_status']     = $data['status']           ?? $this->ai_opts['proxy_status'];
        $this->ai_opts['proxy_usage']      = (int)($data['monthly_requests'] ?? 0);
        $this->ai_opts['proxy_limit']      = (int)($data['monthly_limit']    ?? 200);
        $this->ai_opts['proxy_reset_date'] = $data['reset_date']        ?? '';
        $this->ai_opts['proxy_status_ts']  = time();
        update_option(self::AI_OPT, $this->ai_opts);
        wp_send_json_success($data);
    }

    /**
     * Polls the proxy service for session completion after a PayFast checkout redirect.
     *
     * @since 4.21.52
     * @return void
     */
    public function ajax_proxy_poll_session(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified by check_ajax_referer() above
        $session_id = sanitize_text_field( (string) ( $_POST['session_token'] ?? $_POST['session_id'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if (!$session_id) { wp_send_json_error(['message' => 'No session_id']); }

        $resp = wp_remote_get('https://api.andrewbaker.ninja/status?session_token=' . urlencode($session_id), ['timeout' => 10]);
        if (is_wp_error($resp)) { wp_send_json_error(['message' => $resp->get_error_message()]); }

        $data = json_decode(wp_remote_retrieve_body($resp), true);
        if (!$data) { wp_send_json_error(['message' => 'Invalid response']); }

        if (($data['status'] ?? '') === 'active' && !empty($data['license_key'])) {
            $this->ai_opts['proxy_status']     = 'active';
            $this->ai_opts['proxy_license_key']= $data['license_key'];
            $this->ai_opts['proxy_usage']      = (int)($data['monthly_requests'] ?? 0);
            $this->ai_opts['proxy_limit']      = (int)($data['monthly_limit']    ?? 200);
            $this->ai_opts['proxy_reset_date'] = $data['reset_date']        ?? '';
            $this->ai_opts['proxy_session_id'] = '';
            $this->ai_opts['proxy_status_ts']  = time();
            update_option(self::AI_OPT, $this->ai_opts);
        }

        wp_send_json_success($data);
    }

    /**
     * Returns the PayFast billing-portal URL for the subscriber's license key.
     *
     * @since 4.21.52
     * @return void
     */
    public function ajax_proxy_billing_portal(): void {
        check_ajax_referer( 'cs_seo_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden', 403 );
        $key = (string)($this->ai_opts['proxy_license_key'] ?? '');
        if (!$key) { wp_send_json_error(['message' => 'No license key stored']); }

        $resp = wp_remote_get('https://api.andrewbaker.ninja/billing-portal?key=' . urlencode($key), [
            'timeout'     => 15,
            'redirection' => 0,
        ]);
        if (is_wp_error($resp)) { wp_send_json_error(['message' => $resp->get_error_message()]); }

        $code = wp_remote_retrieve_response_code($resp);
        // billing-portal.php returns a 302 redirect to the PayFast billing management URL
        $location = wp_remote_retrieve_header($resp, 'location');
        if ($location) {
            wp_send_json_success(['url' => $location]);
        }
        // If body has JSON error
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        wp_send_json_error(['message' => $data['error'] ?? "HTTP {$code}"]);
    }

}
