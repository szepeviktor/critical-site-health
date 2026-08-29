<?php

/**
 * Plugin Name: Critical Site Health Web Eval Bridge
 * Description: Authenticated PHP-FPM expression evaluator for Critical Site Health.
 * Version: 1.0.0
 * PHP version 7.4 or later.
 *
 * @category WordPress
 * @package  CriticalSiteHealth
 * @author   Viktor Szépe <viktor@szepe.net>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     https://github.com/szepeviktor/critical-site-health
 */

declare(strict_types=1);

namespace SzepeViktor\CriticalSiteHealth\WebBridge;

use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if (! defined('ABSPATH')) {
    return;
}

// phpcs:disable PSR1.Files.SideEffects.FoundWithSymbols -- MU-plugin entrypoint.

/**
 * Authenticated REST endpoint for PHP-FPM expression checks.
 *
 * @category WordPress
 * @package  CriticalSiteHealth
 * @author   Viktor Szépe <viktor@szepe.net>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     https://github.com/szepeviktor/critical-site-health
 */
final class Endpoint
{
    private const NONCE_PREFIX = '_csh_web_eval_nonce_';
    private const MAX_BODY_BYTES = 65536;
    private const MAX_EXPRESSION_BYTES = 8192;
    private const MAX_CLOCK_SKEW = 60;

    /**
     * Register the eval route.
     *
     * @return void
     */
    public static function register(): void
    {
        register_rest_route(
            'critical-site-health/v1',
            '/eval',
            [
                'methods' => 'POST',
                'callback' => [self::class, 'evaluate'],
                'permission_callback' => [self::class, 'authorize'],
            ]
        );
    }

    /**
     * Authorize a signed eval request.
     *
     * @param WP_REST_Request $request REST request.
     *
     * @return true|WP_Error
     */
    public static function authorize(WP_REST_Request $request)
    {
        $body = $request->get_body();
        $timestamp = $request->get_header('X-CSH-Timestamp');
        $nonce = $request->get_header('X-CSH-Nonce');
        $signature = base64_decode($request->get_header('X-CSH-Signature'), true);
        $publicKeyPath = defined('CRITICAL_SITE_HEALTH_WEB_EVAL_PUBLIC_KEY_FILE')
            ? constant('CRITICAL_SITE_HEALTH_WEB_EVAL_PUBLIC_KEY_FILE')
            : null;
        $publicKey = is_string($publicKeyPath)
            ? (string) @file_get_contents($publicKeyPath)
            : '';

        if (! function_exists('sodium_crypto_sign_verify_detached')
            || ! is_ssl()
            || ! $request->is_json_content_type()
            || $body === ''
            || strlen($body) > self::MAX_BODY_BYTES
            || preg_match('/^[0-9]{10}$/', $timestamp) !== 1
            || abs(time() - (int) $timestamp) > self::MAX_CLOCK_SKEW
            || preg_match('/^[a-f0-9]{32}$/', $nonce) !== 1
            || $signature === false
            || strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES
            || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES
            || ! sodium_crypto_sign_verify_detached(
                $signature,
                implode("\n", [$timestamp, $nonce, hash('sha256', $body)]),
                $publicKey
            )
        ) {
            return new WP_Error(
                'csh_unauthorized',
                'Authentication failed.',
                ['status' => 401]
            );
        }

        $nonceAdded = add_option(
            self::NONCE_PREFIX . hash('sha256', $nonce),
            time(),
            '',
            false
        );
        if (! $nonceAdded) {
            return new WP_Error(
                'csh_replay',
                'Authentication failed.',
                ['status' => 409]
            );
        }

        return true;
    }

    /**
     * Evaluate a validated PHP expression.
     *
     * @param WP_REST_Request $request REST request.
     *
     * @return WP_REST_Response
     */
    public static function evaluate(WP_REST_Request $request): WP_REST_Response
    {
        $payload = json_decode($request->get_body(), true);
        $expression = is_array($payload) && array_keys($payload) === ['expression']
            ? $payload['expression']
            : null;

        if (! is_string($expression)
            || $expression === ''
            || strlen($expression) > self::MAX_EXPRESSION_BYTES
        ) {
            return new WP_REST_Response(['message' => 'Invalid request.'], 400);
        }

        try {
            // phpcs:disable Generic.PHP.ForbiddenFunctions.Found,Squiz.PHP.Eval.Discouraged
            $ok = eval('return (' . $expression . ');') === true;
        } catch (Throwable $throwable) {
            $ok = false;
        }

        return new WP_REST_Response(
            [
                'nonce' => $request->get_header('X-CSH-Nonce'),
                'ok' => $ok,
            ]
        );
    }

    /**
     * Delete stale nonce records.
     *
     * @return void
     */
    private static function _deleteExpiredNonces(): void
    {
        global $wpdb;

        $maximumAge = time() - (self::MAX_CLOCK_SKEW * 2);

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                    WHERE option_name LIKE %s AND option_value < %d",
                $wpdb->esc_like(self::NONCE_PREFIX) . '%',
                $maximumAge
            )
        );
    }
}

add_action('rest_api_init', [Endpoint::class, 'register']);
