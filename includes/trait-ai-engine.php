<?php
/**
 * AI engine — central dispatcher for Anthropic Claude and Google Gemini API calls.
 *
 * Also provides the shared ajax_check() guard used by all AJAX handlers.
 *
 * @package CloudScale_SEO_AI_Optimizer
 * @since   4.0.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound
trait CS_SEO_AI_Engine {

    /**
     * Verifies capability and nonce for every AJAX handler — call first in every handler.
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

    public function ajax_proxy_checkout(): void {
        $this->ajax_check();
        $email    = sanitize_email((string)($_POST['email']    ?? ''));
        $site_url = esc_url_raw((string)($_POST['site_url']   ?? ''));
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

        if (!empty($data['session_id'])) {
            $this->ai_opts['proxy_session_id'] = $data['session_id'];
            $this->ai_opts['proxy_status']     = 'pending';
            update_option(self::AI_OPT, $this->ai_opts);
        }

        wp_send_json_success(['checkout_url' => $data['checkout_url']]);
    }

    public function ajax_proxy_boost_checkout(): void {
        $this->ajax_check();
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

    public function ajax_proxy_set_enabled(): void {
        $this->ajax_check();
        $enabled = (int)(bool)($_POST['enabled'] ?? 0);
        $this->ai_opts['proxy_enabled'] = $enabled;
        update_option(self::AI_OPT, $this->ai_opts);
        wp_send_json_success();
    }

    public function ajax_proxy_refresh_status(): void {
        $this->ajax_check();
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

    public function ajax_proxy_poll_session(): void {
        $this->ajax_check();
        $session_id = sanitize_text_field((string)($_POST['session_id'] ?? ''));
        if (!$session_id) { wp_send_json_error(['message' => 'No session_id']); }

        $resp = wp_remote_get('https://api.andrewbaker.ninja/status?session=' . urlencode($session_id), ['timeout' => 10]);
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

    public function ajax_proxy_billing_portal(): void {
        $this->ajax_check();
        $key = (string)($this->ai_opts['proxy_license_key'] ?? '');
        if (!$key) { wp_send_json_error(['message' => 'No license key stored']); }

        $resp = wp_remote_get('https://api.andrewbaker.ninja/billing-portal?key=' . urlencode($key), [
            'timeout'     => 15,
            'redirection' => 0,
        ]);
        if (is_wp_error($resp)) { wp_send_json_error(['message' => $resp->get_error_message()]); }

        $code = wp_remote_retrieve_response_code($resp);
        // billing-portal.php returns a 302 redirect to the Stripe portal URL
        $location = wp_remote_retrieve_header($resp, 'location');
        if ($location) {
            wp_send_json_success(['url' => $location]);
        }
        // If body has JSON error
        $data = json_decode(wp_remote_retrieve_body($resp), true);
        wp_send_json_error(['message' => $data['error'] ?? "HTTP {$code}"]);
    }

}
