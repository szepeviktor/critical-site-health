<?php

declare(strict_types=1);

if (! class_exists('WP_CLI')) {
    return;
}

if (is_readable(__DIR__ . '/vendor/autoload.php')) {
    include_once __DIR__ . '/vendor/autoload.php';
}

WP_CLI::add_command('site-health', 'SzepeViktor\\WP_CLI\\SiteHealth\\Command');
