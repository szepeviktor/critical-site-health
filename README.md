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

There are six kinds of checks.

- options
- active plugins
- constants
- static class methods
- CLI PHP expressions
- web/PHP-FPM PHP expressions

```yaml
---
# I should be self-sufficient.
option:
    "home": "https://example.com"
    "permalink_structure": "/%postname%/"
    "blog_public": "1"
    "blog_charset": "UTF-8"
    "WPLANG": "en_US"
    "users_can_register": "0"
    "admin_email": "admin@szepe.net"
    "wp_mailfrom_ii_email": "webmaster@szepe.net"
    "link_manager_enabled": "0"
    "elementor_safe_mode": ""
    "woocommerce_shop_page_id": "101"
    "woocommerce_cart_page_id": "102"
    "woocommerce_checkout_page_id": "103"
    "woocommerce_myaccount_page_id": "104"
    "woocommerce_refund_returns_page_id": "105"
    "woocommerce_terms_page_id": "106"
    "woocommerce_coming_soon": "no"
    "woocommerce_logs_logging_enabled": "yes"
    "woocommerce_logs_level_threshold": "info"
plugin_active:
    - "woocommerce/woocommerce.php"
constant:
    "WP_DEBUG": false
    "SCRIPT_DEBUG": false
    "DISALLOW_FILE_EDIT": true
    "DISABLE_WP_CRON": true
    "WP_CACHE_KEY_SALT": "prefix:"
    # Namespaced constant
    "Company\Theme\VERSION": "1.0.0"
    # Class constant
    "Company\Theme::VERSION": "1.0.0"
class_method:
    "Company::version": "1.0.0"
# Should return true
eval:
    # PHP minor version is not changed
    - |
        PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 1
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
        WP_CLI::runcommand('core verify-checksums --quiet', ['return' => 'return_code', 'exit_error' => false]) === 0
    # Plugin files are unchanged
    - |
        WP_CLI::runcommand('plugin verify-checksums --quiet --all', ['return' => 'return_code', 'exit_error' => false]) === 0
    # Database is up-to-date
    - |
        WP_CLI::runcommand('core update-db --quiet --dry-run', ['return' => 'return_code', 'exit_error' => false]) === 0
    # All active plugins are compatible with core
    - |
        array_reduce(get_option('active_plugins'), function ($c,$p) {return $c && version_compare(get_plugin_data(WP_PLUGIN_DIR.'/'.$p)['RequiresWP'],get_bloginfo('version'),'<=');},true)
    # The active parent and child theme are compatible with core
    - |
        version_compare(wp_get_theme()['RequiresWP'],get_bloginfo('version'),'<=') && version_compare(wp_get_theme(wp_get_theme()->get_template())['RequiresWP'],get_bloginfo('version'),'<=')
    # Auto updated plugins exist
    - |
        array_reduce(get_option('auto_update_plugins',[]), function($e,$p) {return $e && file_exists(WP_PLUGIN_DIR.'/'.$p);},true)
    # No update failed
    - |
        array_filter(list_files(WP_CONTENT_DIR.'/upgrade-temp-backup',100,[],true), 'is_file') === [] && count(scandir(WP_CONTENT_DIR.'/upgrade')) === 2
    # The current theme is custom-child-theme
    - |
        wp_get_theme()->get_stylesheet() === 'custom-child-theme'
    # Custom CSS is unchanged
    - |
        md5(wp_get_custom_css()) === 'd41d8cd98f00b204e9800998ecf8427e'
    # There is 1 administrator
    - |
        WP_CLI::runcommand('user list --role=administrator --format=count', ['return' => true, 'exit_error' => false]) === '1'
    # WP-Cron is running
    - |
        ($c=_get_cron_array()) && array_key_first(ksort($c, SORT_NUMERIC) ? $c : []) > time() - HOUR_IN_SECONDS
    # No tag-category collision
    - |
        (fn($s) => count($s) === count(array_unique($s)))(array_map(fn($t) => $t->slug,get_terms(['taxonomy'=>['category','post_tag'],'hide_empty'=>false])))
    # Redis extension is installed
    - |
        in_array('redis', get_loaded_extensions())
    # wp-redis: WP Redis plugin is installed
    - |
        get_plugins()['wp-redis/wp-redis.php']['Name'] === 'WP Redis'
    # wp-redis: WP Redis is in use
    - |
        WP_CLI::runcommand('cache type', ['return' => true, 'exit_error' => false]) === 'Redis'
    # wp-redis: No transients in the database
    - |
        WP_CLI::runcommand('transient list --quiet --format=count', ['return' => true, 'exit_error' => false]) === '0'
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
        trim(WP_CLI::runcommand('db query "SELECT BIT_XOR(CAST(CRC32(CONCAT_WS(CHAR(35),key_id,permissions,consumer_key)) AS UNSIGNED)) FROM wp_woocommerce_api_keys;" --skip-column-names', ['return' => true, 'exit_error' => false])) === "123456789"
    # woocommerce: No product tag-category collision
    - |
        (fn($s) => count($s) === count(array_unique($s)))(array_map(fn($t) => $t->slug,get_terms(['taxonomy'=>['product_cat','product_tag'],'hide_empty'=>false])))
    # woocommerce: a REST API key was used recently
    - |
        strtotime(WP_CLI::runcommand('db query "SELECT last_access FROM wp_woocommerce_api_keys WHERE key_id = 11" --skip-column-names', ['return' => true, 'exit_error' => false])) > time() - HOUR_IN_SECONDS
    # events-calendar-pro: License is valid
    # wp option list --search=pue_key_status_*
    - |
        array_filter(get_option('tribe_pue_key_notices')) === []
    # Divi: License is valid
    - |
        get_site_option('et_account_status') === 'active'
    # robots.txt is generated
    - |
        wp_remote_retrieve_response_code(wp_remote_get(home_url('/robots.txt'))) === 200
    # Tracking code is included in the homepage
    - >
        strpos(wp_remote_retrieve_body(wp_remote_get(home_url())),
            '<script async src="https://www.googletagmanager.com/gtag/js?id=G-1234567890"></script>') > 10000
    # Pinging of https://healthchecks.io/ was successful
    - |
        wp_remote_retrieve_response_code(wp_remote_get('https://hc-ping.com/YOUR-HC-UUID')) === 200
# These expressions run through HTTPS in the PHP-FPM web environment.
web_eval_private_key: "/secure/path/web-eval-ed25519"
web_eval:
    # This is the PHP-FPM runtime
    - |
        PHP_SAPI === 'fpm-fcgi'
    # HTTP host is unchanged
    - |
        $_SERVER['HTTP_HOST'] === 'example.com'
    # This is a production environment
    - |
        wp_get_environment_type() === 'production'
    # PHP minor version is not changed
    - |
        PHP_MAJOR_VERSION === 8 && PHP_MINOR_VERSION === 1
    # PHP error display is disabled
    - |
        ini_get('display_errors') === ''
    # PHP version exposure is disabled
    - |
        ini_get('expose_php') === ''
    # .user.ini files are disabled
    - |
        ini_get('user_ini.filename') === ''
    # PHP timezone is UTC
    - |
        ini_get('date.timezone') === 'UTC'
    # PHP memory limit is unchanged
    - |
        ini_get('memory_limit') === '128M'
    # PHP input variables limit is unchanged
    - |
        ini_get('max_input_vars') === '1000'
    # PHP upload size is unchanged
    - |
        ini_get('upload_max_filesize') === '4M'
    # PHP POST size is unchanged
    - |
        ini_get('post_max_size') === '4M'
    # PHP file upload count is unchanged
    - |
        ini_get('max_file_uploads') === '20'
    # OPcache is installed
    - |
        extension_loaded('Zend OPcache')
    # OPcache memory size is unchanged
    - |
        ini_get('opcache.memory_consumption') === '256'
    # OPcache strings buffer size is unchanged
    - |
        ini_get('opcache.interned_strings_buffer') === '16'
    # OPcache file count is unchanged
    - |
        ini_get('opcache.max_accelerated_files') === '10000'
    # cURL extension is installed
    - |
        extension_loaded('curl')
    # Intl extension is installed
    - |
        extension_loaded('intl')
    # Mbstring extension is installed
    - |
        extension_loaded('mbstring')
    # MySQLi extension is installed
    - |
        extension_loaded('mysqli')
    # OpenSSL extension is installed
    - |
        extension_loaded('openssl')
    # Sodium extension is installed
    - |
        extension_loaded('sodium')
    # XML extensions are installed
    - |
        extension_loaded('xml') && extension_loaded('SimpleXML') && extension_loaded('xmlreader') && extension_loaded('dom')
    # Image processing is available
    - |
        extension_loaded('gd') || extension_loaded('imagick')
    # Deprecated mcrypt extension is disabled
    - |
        ! extension_loaded('mcrypt')
    # Deprecated mysql extension is disabled
    - |
        ! extension_loaded('mysql')
    # PHP upload temporary directory is writable
    - |
        ($d = ini_get('upload_tmp_dir')) === '' || (file_exists($d) && is_writable($d))
    # PHP system temporary directory is writable
    - |
        ($d = ini_get('sys_temp_dir')) === '' || (file_exists($d) && is_writable($d))
    # PHP session directory is writable
    - |
        ($d = ini_get('session.save_path')) === '' || (file_exists($d) && is_writable($d))
```

## PHP-FPM web bridge

`web_eval` uses an authenticated REST endpoint provided by the bundled MU-plugin. The private
Ed25519 key remains in the WP-CLI environment; the web server receives only its public key.

Generate a raw libsodium Ed25519 key pair outside `vendor/`:

```shell
bin/generate-web-eval-keys.php /secure/path
```

The command writes the two key files to the target directory and replaces an existing pair.

Install the endpoint and both key files outside `vendor/`:

```shell
install -m 0644 mu-plugin/critical-site-health-web-eval.php \
    /path/to/wp-content/mu-plugins/critical-site-health-web-eval.php
install -m 0600 web-eval-ed25519 \
    /secure/path/web-eval-ed25519
install -m 0644 web-eval-ed25519.pub \
    /secure/path/web-eval-ed25519.pub
```

The CLI reads the raw 64-byte private key from `web_eval_private_key` in the YAML file. The
MU-plugin reads the raw 32-byte public key from the path defined in `wp-config.php`:

```php
define(
    'CRITICAL_SITE_HEALTH_WEB_EVAL_PUBLIC_KEY_FILE',
    '/secure/path/web-eval-ed25519.pub'
);
```

The REST URL is generated by WordPress and must use HTTPS.

The endpoint rejects non-HTTPS requests, stale timestamps, reused nonces, invalid signatures,
oversized bodies, and malformed expressions. Every expression uses a separate signed request, so
one failed or timed-out request does not prevent later checks. A valid signing key deliberately
grants remote PHP execution with the PHP-FPM user's permissions.
