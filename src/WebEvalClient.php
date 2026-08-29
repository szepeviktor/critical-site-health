<?php
/**
 * Signed REST client for web/PHP-FPM eval checks.
 * PHP version 7.4 or later.
 *
 * @category WordPress
 * @package  SiteHealthCommand
 * @author   Viktor Szépe <viktor@szepe.net>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     https://github.com/szepeviktor/critical-site-health
 */

namespace SzepeViktor\WP_CLI\SiteHealth;

use RuntimeException;

/**
 * Calls the authenticated web eval REST bridge.
 *
 * @category WordPress
 * @package  SiteHealthCommand
 * @author   Viktor Szépe <viktor@szepe.net>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     https://github.com/szepeviktor/critical-site-health
 */
final class WebEvalClient
{
    private const PRIVATE_KEY_FILENAME = 'web-eval-ed25519';
    private const MAX_BODY_BYTES = 65536;

    private string $_privateKey;

    /**
     * Load and validate the raw Ed25519 private key.
     *
     * @param string $keyDirectory Package root containing the private key.
     */
    public function __construct(string $keyDirectory)
    {
        $path = $keyDirectory . DIRECTORY_SEPARATOR . self::PRIVATE_KEY_FILENAME;
        $this->_privateKey = (string) @file_get_contents($path);
        $permissions = @fileperms($path);

        if (! function_exists('sodium_crypto_sign_detached')
            || strlen($this->_privateKey) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES
            || ($permissions !== false && ($permissions & 0077) !== 0)
        ) {
            throw new RuntimeException('Invalid web_eval private key.');
        }
    }

    /**
     * Evaluate one expression through the web server runtime.
     *
     * @param string $expression PHP expression expected to return true.
     *
     * @return bool
     */
    public function evaluate(string $expression): bool
    {
        $endpoint = rest_url('critical-site-health/v1/eval');
        $body = json_encode(['expression' => $expression]);

        if (! is_string($body)
            || strlen($body) > self::MAX_BODY_BYTES
            || stripos($endpoint, 'https://') !== 0
        ) {
            throw new RuntimeException('Invalid web_eval request.');
        }

        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $message = implode("\n", [$timestamp, $nonce, hash('sha256', $body)]);
        $response = wp_remote_post(
            $endpoint,
            [
                'body' => $body,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-CSH-Timestamp' => $timestamp,
                    'X-CSH-Nonce' => $nonce,
                    'X-CSH-Signature' => base64_encode(
                        sodium_crypto_sign_detached($message, $this->_privateKey)
                    ),
                ],
                'redirection' => 0,
                'timeout' => 30,
            ]
        );

        if (is_wp_error($response)
            || wp_remote_retrieve_response_code($response) !== 200
        ) {
            throw new RuntimeException('web_eval request failed.');
        }

        $payload = json_decode(wp_remote_retrieve_body($response), true);
        if (! is_array($payload)
            || ! isset($payload['nonce'], $payload['ok'])
            || ! is_string($payload['nonce'])
            || ! is_bool($payload['ok'])
            || ! hash_equals($nonce, $payload['nonce'])
        ) {
            throw new RuntimeException('Invalid web_eval response.');
        }

        return $payload['ok'];
    }
}
