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
        if ($provider === 'gemini') {
            return $this->call_gemini($key, $model, $system, $user_msg, $extra_messages, $max_tokens);
        }
        return $this->call_claude($key, $model, $system, $user_msg, $extra_messages, $max_tokens);
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
            $wait = $code === 529 ? 20 : 10;
            sleep($wait);
            $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
                'timeout' => 45, 'headers' => $headers, 'body' => $body,
            ]);
            if (is_wp_error($response)) throw new \RuntimeException( 'HTTP error after retry: ' . $response->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
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
        $url     = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}";
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
        // Gemini uses 429 for quota exceeded — retry once.
        if (wp_remote_retrieve_response_code($response) === 429) {
            sleep(10);
            $response = wp_remote_post($url, [
                'timeout' => 45, 'headers' => ['Content-Type' => 'application/json'], 'body' => $body,
            ]);
            if (is_wp_error($response)) throw new \RuntimeException( 'HTTP error after retry: ' . $response->get_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
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

    /**
     * Single AI call that generates meta description, fixes the SEO title, and
     * writes ALT text for any images — all in one request.
     *
     * Returns an array:
     *   description  string
     *   title        string|null   null = already in range, leave as-is
     *   title_was    string|null
     *   title_chars  int
     *   title_status 'in_range'|'fixed'|'fixed_imperfect'
     *   alts_saved   int
     */
}
