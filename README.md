# Critical site health

Check critical values in your WordPress installation with this WP-CLI command.

```shell
wp site-health check critical-site-health.yml
```

## Checks

- options
- global constants
- class constants
- static class methods
- PHP expressions

```yaml
---
# I should be self-sufficient.
option:
    "blog_public": "0"
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
    - |
        gethostbyname(parse_url(get_bloginfo('url'), PHP_URL_HOST)) === trim(shell_exec('hostname -i'))
    - |
        WP_CLI::runcommand('core verify-checksums --quiet', ['return' => 'return_code']) === 0
    - |
        WP_CLI::runcommand('plugin verify-checksums --quiet --all', ['return' => 'return_code']) === 0
    - |
        get_plugins()["wp-redis/wp-redis.php"]["Name"] === 'WP Redis'
    - |
        WP_CLI::runcommand('cache type', ['return' => true]) === 'Redis'
    - |
        WP_CLI::runcommand('user list --role=administrator --format=count', ['return' => true]) === '1'
    - |
        wp_get_theme()->get_stylesheet() === 'custom-child-theme'
    - |
        function_exists('perflab_get_module_settings') && perflab_get_module_settings()['images/webp-uploads']['enabled'] === '1'
    - |
        array_key_first(@ksort($c=_get_cron_array(), SORT_NUMERIC) ? $c : []) > time() - HOUR_IN_SECONDS
    - |
        wp_remote_retrieve_response_code(wp_remote_get('https://hc-ping.com/YOUR-HC-UUID')) === 200
```
