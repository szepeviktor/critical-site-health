# Critical site health

Check critical values in your WordPress installation with this WP-CLI command.

```shell
wp site-health check critical-site-health.yml
```

## Installation

```shell
wp package install https://github.com/szepeviktor/critical-site-health.git
```

## Configuration

There are 5 kinds of checks.

- options
- constants
- static class methods
- PHP expressions

```yaml
---
# I should be self-sufficient.
option:
    "home": "https://example.com"
    "blog_public": "1"
    "blog_charset": "UTF-8"
    "WPLANG": "en_US"
    "users_can_register": "0"
    "admin_email": "admin@szepe.net"
    "wp_mailfrom_ii_email": "webmaster@szepe.net"
    "woocommerce_shop_page_id": "5372"
    "woocommerce_cart_page_id": "5362"
    "woocommerce_checkout_page_id": "5363"
    "woocommerce_myaccount_page_id": "15"
    "woocommerce_refund_returns_page_id": "5364"
    "woocommerce_terms_page_id": "74"
    "woocommerce_coming_soon": "no"
    "woocommerce_logs_logging_enabled": "yes"
    "woocommerce_logs_level_threshold": "info"
constant:
    "WP_DEBUG": false
    "SCRIPT_DEBUG": false
    "DISALLOW_FILE_EDIT": true
    "DISABLE_WP_CRON": true
    "WP_CACHE_KEY_SALT": "prefix:"
    # Namespaced constant
    "Company\Theme\VERSION": "0.0.0"
    # Class constant
    "Company\Theme::VERSION": "0.0.0"
class_method:
    "Company::version": "1.0.0"
# Should return true
eval:
    # Check file owner
    - |
        exec('find /home/PROJECT/website/code/ -not -user $USER', $output, $exit_status) === '' && $exit_status === 0
    # Check git working tree status
    - |
        exec('git -C /home/PROJECT/website/code status -s -uno', $output, $exit) === '' && $exit === 0
    # IP address of WordPress home URL equals server's primary IP address
    - |
        gethostbyname(parse_url(get_bloginfo('url'), PHP_URL_HOST)) === trim(shell_exec('hostname -i'))
    # This is a production environment
    - |
        wp_get_environment_type() === 'production'
    # Core files are unchanged
    - |
        WP_CLI::runcommand('core verify-checksums --quiet', ['return' => 'return_code']) === 0
    # Plugin files are unchanged
    - |
        WP_CLI::runcommand('plugin verify-checksums --quiet --all', ['return' => 'return_code']) === 0
    # Database is up-to-date
    - |
        WP_CLI::runcommand('core update-db --quiet --dry-run', ['return' => 'return_code']) === 0
    # All active plugins are compatible with core
    - |
        array_reduce(get_option('active_plugins'), function ($c,$p) {return $c && version_compare(get_plugin_data(WP_PLUGIN_DIR.'/'.$p)['RequiresWP'],get_bloginfo('version'),'<=');},true)
    # Auto updated plugins exist
    - |
        array_reduce(get_option('auto_update_plugins',[]), function($e,$p) {return $e && file_exists(WP_PLUGIN_DIR.'/'.$p);},true)
    # No update failed
    - |
        file_exists(WP_CONTENT_DIR.'/upgrade-temp-backup') === false && count(scandir(WP_CONTENT_DIR.'/upgrade')) === 2
    # The current theme is custom-child-theme
    - |
        wp_get_theme()->get_stylesheet() === 'custom-child-theme'
    # Custom CSS is unchanged
    - |
        md5(wp_get_custom_css()) === 'd41d8cd98f00b204e9800998ecf8427e'
    # There is 1 administrator
    - |
        WP_CLI::runcommand('user list --role=administrator --format=count', ['return' => true]) === '1'
    # WP-Cron is running
    - |
        ($c=_get_cron_array()) && array_key_first(ksort($c, SORT_NUMERIC) ? $c : []) > time() - HOUR_IN_SECONDS
    # Redis extension is installed
    - |
        in_array('redis', get_loaded_extensions())
    # wp-redis: WP Redis plugin is installed
    - |
        get_plugins()['wp-redis/wp-redis.php']['Name'] === 'WP Redis'
    # wp-redis: WP Redis is in use
    - |
        WP_CLI::runcommand('cache type', ['return' => true]) === 'Redis'
    # webp-uploads: WebP uploading is enabled
    - |
        function_exists('perflab_get_module_settings') && perflab_get_module_settings()['images/webp-uploads']['enabled'] === '1'
    # woocommerce: HPOS
    - |
        Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()
    # woocommerce: Using same payment gateways
    - |
        array_keys(WC_Payment_Gateways::instance()->get_available_payment_gateways()) === ['paypal']
    # woocommerce: REST API keys are unchanged
    - |
        trim(WP_CLI::runcommand('db query "CHECKSUM TABLE wp_woocommerce_api_keys EXTENDED" --skip-column-names', ['return' => true])) === "wpdb.wp_woocommerce_api_keys\t1111111111"
    # robots.txt is generated
    - |
        wp_remote_retrieve_response_code(wp_remote_get(home_url('/robots.txt'))) === 200
    # Tracking code is included in the homepage
    - >
        strpos(wp_remote_retrieve_body(wp_remote_get(home_url())),
            '<script async src="https://www.googletagmanager.com/gtag/js?id=G-1111111111"></script>') > 10000
    # Pinging of https://healthchecks.io/ was successful
    - |
        wp_remote_retrieve_response_code(wp_remote_get('https://hc-ping.com/YOUR-HC-UUID')) === 200
```
