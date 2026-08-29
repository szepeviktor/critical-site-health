<?php
/**
 * Smoke test for the signed web eval bridge.
 * PHP version 7.4 or later.
 *
 * @category WordPress
 * @package  SiteHealthCommand
 * @author   Viktor Szépe <viktor@szepe.net>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     https://github.com/szepeviktor/critical-site-health
 */

declare(strict_types=1);

// phpcs:disable -- WordPress API stubs intentionally use global snake_case names.

use SzepeViktor\CriticalSiteHealth\WebBridge\Endpoint;

final class WP_Error
{
    public string $code;

    /**
     * @var array<string, int>
     */
    public array $data;

    /**
     * @param array<string, int> $data
     */
    public function __construct(string $code, string $message, array $data)
    {
        $this->code = $code;
        $this->data = $data;
    }
}

final class WP_REST_Request
{
    private string $body;

    /**
     * @var array<string, string>
     */
    private array $headers;

    /**
     * @param array<string, string> $headers
     */
    public function __construct(string $body, array $headers)
    {
        $this->body = $body;
        $this->headers = $headers;
    }

    public function get_method(): string
    {
        return 'POST';
    }

    public function get_body(): string
    {
        return $this->body;
    }

    public function is_json_content_type(): bool
    {
        return true;
    }

    public function get_header(string $name): string
    {
        return isset($this->headers[$name]) ? $this->headers[$name] : '';
    }
}

final class WP_REST_Response
{
    /**
     * @var array<string, mixed>
     */
    public array $data;

    public int $status;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(array $data, int $status = 200)
    {
        $this->data = $data;
        $this->status = $status;
    }
}

/**
 * @var array<string, int>
 */
$nonceOptions = [];

function is_ssl(): bool
{
    return true;
}

function add_action(string $hook, array $callback): void
{
}

function rest_url(string $path): string
{
    return 'https://example.com/wp-json/' . $path;
}

function expect(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

/**
 * @param mixed $value
 * @param mixed $deprecated
 */
function add_option(string $name, $value, $deprecated = '', bool $autoload = false): bool
{
    global $nonceOptions;

    if (array_key_exists($name, $nonceOptions)) {
        return false;
    }

    $nonceOptions[$name] = (int) $value;

    return true;
}

/**
 * @param array<string, mixed> $arguments
 *
 * @return array<string, mixed>
 */
function wp_remote_post(string $url, array $arguments): array
{
    global $testPublicKey;

    expect($url === 'https://example.com/wp-json/critical-site-health/v1/eval', 'Unexpected client URL.');
    $headers = $arguments['headers'];
    $message = implode(
        "\n",
        [
            $headers['X-CSH-Timestamp'],
            $headers['X-CSH-Nonce'],
            hash('sha256', $arguments['body']),
        ]
    );
    expect(
        sodium_crypto_sign_verify_detached(
            base64_decode($headers['X-CSH-Signature'], true),
            $message,
            $testPublicKey
        ),
        'The client signature is invalid.'
    );
    $payload = json_decode($arguments['body'], true);
    expect($payload === ['expression' => 'true'], 'The client did not send one expression.');

    return [
        'response' => ['code' => 200],
        'body' => json_encode(
            [
                'nonce' => $headers['X-CSH-Nonce'],
                'ok' => true,
            ]
        ),
    ];
}

/**
 * @param mixed $response
 */
function is_wp_error($response): bool
{
    return false;
}

/**
 * @param array<string, mixed> $response
 */
function wp_remote_retrieve_response_code(array $response): int
{
    return $response['response']['code'];
}

/**
 * @param array<string, mixed> $response
 */
function wp_remote_retrieve_body(array $response): string
{
    return $response['body'];
}

$wpdb = new class () {
    public string $options = 'wp_options';

    public function esc_like(string $value): string
    {
        return $value;
    }

    /**
     * @param mixed ...$values
     */
    public function prepare(string $query, ...$values): string
    {
        return $query;
    }

    public function query(string $query): int
    {
        return 0;
    }
};

$keyPair = sodium_crypto_sign_keypair();
$secretKey = sodium_crypto_sign_secretkey($keyPair);
$publicKey = sodium_crypto_sign_publickey($keyPair);
$testPublicKey = $publicKey;
$keyDirectory = sys_get_temp_dir() . '/csh-web-eval-' . bin2hex(random_bytes(8));
if (! mkdir($keyDirectory, 0700) || file_put_contents($keyDirectory . '/web-eval-ed25519.pub', $publicKey) === false) {
    throw new RuntimeException('Failed to create temporary key files.');
}

define('CRITICAL_SITE_HEALTH_WEB_EVAL_PUBLIC_KEY_FILE', $keyDirectory . '/web-eval-ed25519.pub');
define('ABSPATH', dirname(__DIR__) . '/');
require dirname(__DIR__) . '/mu-plugin/critical-site-health-web-eval.php';

$body = json_encode(
    ['expression' => 'true'],
    JSON_UNESCAPED_SLASHES
);
if (! is_string($body)) {
    throw new RuntimeException('Failed to encode test request.');
}

$timestamp = (string) time();
$nonce = bin2hex(random_bytes(16));
$message = implode("\n", [$timestamp, $nonce, hash('sha256', $body)]);
$request = new WP_REST_Request(
    $body,
    [
        'X-CSH-Timestamp' => $timestamp,
        'X-CSH-Nonce' => $nonce,
        'X-CSH-Signature' => base64_encode(sodium_crypto_sign_detached($message, $secretKey)),
    ]
);

expect(Endpoint::authorize($request) === true, 'A valid signed request was rejected.');
$response = Endpoint::evaluate($request);
expect($response->status === 200, 'The endpoint did not return HTTP 200.');
expect($response->data['nonce'] === $nonce, 'The response nonce does not match.');
expect($response->data['ok'] === true, 'The true expression failed.');

$replay = Endpoint::authorize($request);
expect($replay instanceof WP_Error, 'A replayed nonce was accepted.');
expect($replay->data['status'] === 409, 'A replay did not return HTTP 409.');

$tampered = new WP_REST_Request(
    str_replace('"true"', '"false"', $body),
    [
        'X-CSH-Timestamp' => $timestamp,
        'X-CSH-Nonce' => bin2hex(random_bytes(16)),
        'X-CSH-Signature' => base64_encode(sodium_crypto_sign_detached($message, $secretKey)),
    ]
);
$rejected = Endpoint::authorize($tampered);
expect($rejected instanceof WP_Error, 'A request with a modified body was accepted.');
expect($rejected->data['status'] === 401, 'A bad signature did not return HTTP 401.');

$privateKeyPath = $keyDirectory . '/web-eval-ed25519';
if (file_put_contents($privateKeyPath, $secretKey) === false) {
    throw new RuntimeException('Failed to create a temporary private key.');
}
chmod($privateKeyPath, 0600);

require dirname(__DIR__) . '/src/WebEvalClient.php';
$client = new SzepeViktor\WP_CLI\SiteHealth\WebEvalClient($keyDirectory);
expect($client->evaluate('true') === true, 'The signed client request failed.');

unlink($privateKeyPath);
unlink($keyDirectory . '/web-eval-ed25519.pub');
rmdir($keyDirectory);

echo "Web bridge smoke test passed.\n";
