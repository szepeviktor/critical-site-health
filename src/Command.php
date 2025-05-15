<?php

declare(strict_types=1);

namespace SzepeViktor\WP_CLI\SiteHealth;

use Mustangostang\Spyc;
use WP_CLI;
use WP_CLI_Command;

use function get_option;

class Command extends WP_CLI_Command
{
    /**
     * Checks critical site health values from a YAML file.
     *
     * ## OPTIONS
     *
     * <yaml-file>
     * : Path to the critical-site-health YAML file.
     *
     * ## EXAMPLES
     *
     *     wp critical-site-health check /path/to/critical-site-health.yml
     *
     * @when after_wp_load
     */
    public function check($args, $assocArgs)
    {
        $yamlPath = $args[0];

        if (! file_exists($yamlPath)) {
            WP_CLI::error('YAML file not found at: ' . $yamlPath);
        }

        try {
            $checks = Spyc::YAMLLoad($yamlPath);
        } catch (\Throwable $e) {
            WP_CLI::error('Failed to parse YAML: ' . $e->getMessage());
        }

        $hadWarning = false;

        // Option values
        if (isset($checks['option'])) {
            foreach ($checks['option'] as $option => $expected) {
                $actual = get_option($option);
                if ($actual === $expected) {
                    continue;
                }

                WP_CLI::warning("Option '$option': expected '$expected', got '$actual'");
                $hadWarning = true;
            }
        }

        // Global constants
        if (isset($checks['global_constant'])) {
            foreach ($checks['global_constant'] as $name => $expected) {
                if (! defined($name)) {
                    WP_CLI::warning("Constant '$name' is not defined.");
                    $hadWarning = true;
                    continue;
                }

                $actual = constant($name);
                if ($actual === $expected) {
                    continue;
                }

                WP_CLI::warning("Constant '$name': expected '$expected', got '$actual'");
                $hadWarning = true;
            }
        }

        // Class constants
        if (isset($checks['class_constant']) && is_array($checks['class_constant'])) {
            foreach ($checks['class_constant'] as $const => $expected) {
                if (! defined($const)) {
                    WP_CLI::warning("Class constant '$const' is not defined.");
                    $hadWarning = true;
                    continue;
                }

                $actual = constant($const);
                if ($actual === $expected) {
                    continue;
                }

                WP_CLI::warning("Class constant '$const': expected '$expected', got '$actual'");
                $hadWarning = true;
            }
        }

        // Class methods
        if (isset($checks['class_method']) && is_array($checks['class_method'])) {
            foreach ($checks['class_method'] as $callable => $expected) {
                if (! is_callable($callable)) {
                    WP_CLI::warning("Method '$callable' is not callable.");
                    $hadWarning = true;
                    continue;
                }

                try {
                    // phpcs:disable NeutronStandard.Functions.DisallowCallUserFunc.CallUserFunc
                    $actual = call_user_func($callable);
                    if ($actual !== $expected) {
                        WP_CLI::warning("Method '$callable': expected '$expected', got '$actual'");
                        $hadWarning = true;
                    }
                } catch (\Throwable $e) {
                    WP_CLI::warning("Method '$callable' failed: " . $e->getMessage());
                    $hadWarning = true;
                }
            }
        }

        // Eval expressions
        if (isset($checks['eval']) && is_array($checks['eval'])) {
            foreach ($checks['eval'] as $expr) {
                try {
                    // phpcs:disable Generic.PHP.ForbiddenFunctions.Found,Squiz.PHP.Eval.Discouraged
                    $result = eval(sprintf('return %s;', $expr));
                    if ($result !== true) {
                        WP_CLI::warning("Eval failed: '$expr' returned '$result'");
                        $hadWarning = true;
                    }
                } catch (\ParseError $e) {
                    WP_CLI::warning('Eval error: ' . $e->getMessage());
                    $hadWarning = true;
                }
            }
        }

        if ($hadWarning) {
            WP_CLI::error('Health check completed with warnings.');
        }

        WP_CLI::success('Critical site health checks passed.');
    }
}
