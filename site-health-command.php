<?php

declare(strict_types=1);

if (! class_exists('WP_CLI')) {
    return;
}

require_once __DIR__ . '/vendor/autoload.php';

WP_CLI::add_command('site-health', 'SzepeViktor\\WP_CLI\\SiteHealth\\Command');
