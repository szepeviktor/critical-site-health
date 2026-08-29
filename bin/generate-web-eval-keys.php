#!/usr/bin/env php
<?php

/**
 * Generate raw Ed25519 key files for web_eval checks.
 *
 * @category WordPress
 * @package  SiteHealthCommand
 * @author   Viktor Szépe <viktor@szepe.net>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     https://github.com/szepeviktor/critical-site-health
 */

declare(strict_types=1);

$keyPair = sodium_crypto_sign_keypair();
$privateKeyPath = dirname(__DIR__) . '/web-eval-ed25519';
$publicKeyPath = dirname(__DIR__) . '/web-eval-ed25519.pub';

file_put_contents($privateKeyPath, sodium_crypto_sign_secretkey($keyPair));
file_put_contents($publicKeyPath, sodium_crypto_sign_publickey($keyPair));

chmod($privateKeyPath, 0600);
chmod($publicKeyPath, 0644);
