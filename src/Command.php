<?php

declare(strict_types=1);

namespace SzepeViktor\WP_CLI\SiteHealth;

use Mustangostang\Spyc;
use WP_CLI;
use WP_CLI_Command;

use function get_option;

// phpcs:disable SlevomatCodingStandard.ControlStructures.EarlyExit.EarlyExitNotUsed

class Command extends WP_CLI_Command
{
    protected bool $hadWarning;

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
            WP_CLI::error(sprintf('YAML file not found at: %s', $yamlPath));
        }

        WP_CLI::debug('Using configuration file: ' . $yamlPath, 'site-health');

        try {
            $checks = Spyc::YAMLLoad($yamlPath);
        } catch (\Throwable $e) {
            WP_CLI::error(sprintf('Failed to parse YAML: %s', $e->getMessage()));
        }

        $this->hadWarning = false;

        // Option values
        if (isset($checks['option']) && is_array($checks['option'])) {
            foreach ($checks['option'] as $option => $expected) {
                WP_CLI::debug('Checking option: ' . $option, 'site-health');

                $actual = get_option($option);
                if ($actual !== $expected) {
                    $this->emitWarning('Option %s: expected "%s", got "%s"', $option, $expected, $actual);
                }
            }
        }

        // Constants
        if (isset($checks['global_constant']) && is_array($checks['global_constant'])) {
            $checks['constant'] = array_merge($checks['global_constant'], $checks['constant']);
        }
        if (isset($checks['class_constant']) && is_array($checks['class_constant'])) {
            $checks['constant'] = array_merge($checks['class_constant'], $checks['constant']);
        }
        if (isset($checks['constant']) && is_array($checks['constant'])) {
            foreach ($checks['constant'] as $name => $expected) {
                if (! defined($name)) {
                    $this->emitWarning('Constant "%s" is not defined.', $name);
                    continue;
                }

                WP_CLI::debug('Checking constant: ' . $name, 'site-health');

                $actual = constant($name);
                if ($actual !== $expected) {
                    $this->emitWarning(
                        'Constant %s: expected "%s", got "%s"',
                        $name,
                        var_export($expected, true),
                        var_export($actual, true)
                    );
                }
            }
        }

        // Class methods
        if (isset($checks['class_method']) && is_array($checks['class_method'])) {
            foreach ($checks['class_method'] as $callable => $expected) {
                if (! is_callable($callable)) {
                    $this->emitWarning('Method "%s" is not callable.', $callable);
                    continue;
                }

                WP_CLI::debug('Checking method: ' . $callable, 'site-health');

                try {
                    // phpcs:disable NeutronStandard.Functions.DisallowCallUserFunc.CallUserFunc
                    $actual = call_user_func($callable);
                    if ($actual !== $expected) {
                        $this->emitWarning('Method %s: expected "%s", got "%s"', $callable, $expected, $actual);
                    }
                } catch (\Throwable $e) {
                    $this->emitWarning('Method %s failed: %s', $callable, $e->getMessage());
                }
            }
        }

        // Eval expressions
        if (isset($checks['eval']) && is_array($checks['eval'])) {
            foreach ($checks['eval'] as $expr) {
                WP_CLI::debug('Running: ' . $expr, 'site-health');

                try {
                    // phpcs:disable Generic.PHP.ForbiddenFunctions.Found,Squiz.PHP.Eval.Discouraged
                    $result = eval(sprintf('return %s;', $expr));
                    if ($result !== true) {
                        $this->emitWarning('Eval failed: %s returned "%s"', $expr, var_export($result, true));
                    }
                } catch (\ParseError $e) {
                    $this->emitWarning('Eval error: %s', $e->getMessage());
                }
            }
        }

        if ($this->hadWarning) {
            WP_CLI::error('Health check completed with warnings.');
        }

        WP_CLI::success('Critical site health checks passed.');
    }

    protected function emitWarning(string $format, ...$args): void
    {
        WP_CLI::error(sprintf($format, ...$args), false);
        $this->hadWarning = true;
    }
}
