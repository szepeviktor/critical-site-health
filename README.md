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
- global constants
- class constants
- static class methods
- PHP expressions

```yaml
---
# I should be self-sufficient.
option:
    "blog_public": "1"
    "blog_charset": "UTF-8"
    "users_can_register": "0"
    "admin_email": "admin@szepe.net"
    "woocommerce_shop_page_id": "5372"
    "woocommerce_cart_page_id": "5362"
    "woocommerce_checkout_page_id": "5363"
    "woocommerce_myaccount_page_id": "15"
    "woocommerce_refund_returns_page_id": "5364"
    "woocommerce_terms_page_id": "74"
global_constant:
    "WP_DEBUG": false
    "DISALLOW_FILE_EDIT": true
    "DISABLE_WP_CRON": true
    "WP_CACHE_KEY_SALT": "prefix:"
class_constant:
    "Company\\THEME_VERSION": "0.0.0"
class_method:
    "Company::version": "1.0.0"
# Should return true
eval:
    # IP address of WordPress home URL equals server's primary IP address
    - |
        gethostbyname(parse_url(get_bloginfo('url'), PHP_URL_HOST)) === trim(shell_exec('hostname -i'))
    # Core files are unchanged
    - |
        WP_CLI::runcommand('core verify-checksums --quiet', ['return' => 'return_code']) === 0
    # Plugin files are unchanged
    - |
        WP_CLI::runcommand('plugin verify-checksums --quiet --all', ['return' => 'return_code']) === 0
    # WP Redis plugin is installed
    - |
        get_plugins()['wp-redis/wp-redis.php']['Name'] === 'WP Redis'
    # WP Redis is in use
    - |
        WP_CLI::runcommand('cache type', ['return' => true]) === 'Redis'
    # There is 1 administrator
    - |
        WP_CLI::runcommand('user list --role=administrator --format=count', ['return' => true]) === '1'
    # The current theme is custom-child-theme
    - |
        wp_get_theme()->get_stylesheet() === 'custom-child-theme'
    # WebP uploading is enabled
    - |
        function_exists('perflab_get_module_settings') && perflab_get_module_settings()['images/webp-uploads']['enabled'] === '1'
    # WP-Cron is running
    - |
        ($c=_get_cron_array()) && array_key_first(ksort($c, SORT_NUMERIC) ? $c : []) > time() - HOUR_IN_SECONDS
    # Ping https://healthchecks.io/
    - |
        wp_remote_retrieve_response_code(wp_remote_get('https://hc-ping.com/YOUR-HC-UUID')) === 200
```
