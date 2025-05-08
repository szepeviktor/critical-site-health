`critical-site-health.yml`
```yaml
---
# I should be self-sufficient.
option:
    "blog_public": "0"
global_constant:
    "WP_CACHE_KEY_SALT": "prefix:"
class_constant:
    "Company\\THEME_VERSION": "0.0.0"
class_method:
    "Company::version": "1.0.0"
eval:
    "is_string($variable);": true
```
