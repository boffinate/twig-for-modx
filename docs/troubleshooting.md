# Troubleshooting

## Syntax Errors

A Twig syntax error stops the page from rendering. Depending on your MODX error reporting settings, you will see either a blank page or a PHP error message.

Common causes:

- **Unclosed tag.** `{{ title` instead of `{{ title }}`, or `{% if ... %}` without `{% endif %}`.
- **Mismatched quotes.** `{{ chunk('Name") }}` -- single and double quotes must match.
- **Typo in a tag name.** `{% fro item in list %}` instead of `{% for %}`.
- **JavaScript template syntax.** Vue/Angular/Alpine `{{ }}` expressions that Twig tries to parse. See [verbatim blocks](./usage.md#javascript-frameworks-and-verbatim).

### Finding the error

1. Check the MODX error log (Manager > Reports > Error Log). Twig errors include the line number and a snippet of the template.
2. If the page is completely blank, check your PHP error log. The path depends on your server configuration -- common locations are `/var/log/apache2/error.log`, `/var/log/nginx/error.log`, or the path set in `php.ini` under `error_log`.
3. Narrow it down by removing sections of the template until the error goes away.

### Fixing the error

Fix the template in the MODX manager (or in the file if using static elements), then clear the MODX cache. The Twig compiled cache stores the broken template, so a cache clear is needed even after you fix the source.

## Common Mistakes

### HTML appearing as text instead of rendered markup

**Symptom:** You see `<p>Hello <strong>world</strong></p>` as visible text on the page instead of formatted HTML.

**Cause:** Twig auto-escapes all output by default.

**Fix:** Add the `|raw` filter:

```twig
{# Before (escaped): #}
{{ value }}

{# After (rendered): #}
{{ value|raw }}
```

This is the most common mistake when starting with Twig. Use `|raw` on any variable that contains HTML you want rendered: richtext content, chunk output, snippet output, or resource fields like `content`.

See [HTML Escaping](./usage.md#html-escaping) for details.

### Template changes not appearing

**Symptom:** You edited a chunk, template, or resource but the page still shows the old version.

**Cause:** Twig compiles templates to PHP and caches them. The old compiled version is still being used.

**Fix:** Clear the MODX cache (Site > Clear Cache in the manager). Make sure the TwigCacheClear plugin is enabled -- it clears the Twig compiled cache when you clear the MODX cache.

### Blank page with no error message

**Symptom:** The page is completely white.

**Cause:** Usually a Twig syntax error with PHP error display turned off.

**Fix:**

1. Check the MODX error log (Manager > Reports > Error Log).
2. Check the PHP error log.
3. Temporarily enable error display in your `.htaccess` or MODX system settings to see the message.
4. Fix the syntax error and clear the cache.

### Trying to use MODX tag output inside Twig

**Symptom:** `{% if [[*published]] %}` does not work, or `{{ [[+placeholder]] }}` outputs nothing useful.

**Cause:** Twig runs before MODX processes its tags. By the time MODX resolves `[[*published]]`, Twig has already finished.

**Fix:** Use the Twig helper functions instead of MODX tags inside Twig expressions:

```twig
{# Wrong: #}
{% if [[*published]] %}

{# Right: #}
{% if resource.published %}
{% if field('published') %}
```

### Vue/Angular/Alpine expressions causing errors

**Symptom:** Twig throws a syntax error on a line that contains frontend JavaScript template syntax like `{{ message }}` or `{{ count }}`.

**Cause:** Twig and these frameworks share the `{{ }}` syntax. Twig tries to evaluate the JavaScript expressions as Twig variables.

**Fix:** Wrap the frontend code in a `{% verbatim %}` block:

```twig
{% verbatim %}
<div id="app">{{ message }}</div>
{% endverbatim %}
```

### Snippet output not being processed through Twig

**Symptom:** A snippet returns a string containing `{{ variable }}` but it appears literally on the page.

**Cause:** The snippet is being called with the uncacheable flag `[[!Snippet]]` or the parser has already finished its Twig pass for this depth.

**Fix:** This normally works automatically. If it does not, check that the snippet is returning a string (not echoing it), and that the Twig parser is active (the `twig` namespace is registered and the bootstrap loaded).

### pdoTools / Fenom compatibility

**Symptom:** You want to use pdoTools features (Fenom templates, pdoFetch, FastField tags) alongside Twig.

**Note:** pdoTools is optional. The Twig Extra works without it. If you need pdoTools features, install it through the MODX package manager. Twig decorates the pdoTools parser so both coexist -- Twig renders `{{ }}` first, then pdoTools handles MODX tags and Fenom. See [How It Works](how-it-works.md) for details.

## Coming from MODX Tags

If you are used to MODX tag syntax, this table shows the Twig equivalents.

### Resource fields

| MODX | Twig |
|------|------|
| `[[*pagetitle]]` | `{{ resource.pagetitle }}` |
| `[[*id]]` | `{{ resource.id }}` |
| `[[*content]]` | `{{ resource.content\|raw }}` |
| `[[*parent]]` | `{{ resource.parent }}` |
| `[[*template]]` | `{{ resource.template }}` |

### Template Variables

| MODX | Twig |
|------|------|
| `[[*MyTV]]` | `{{ resource.MyTV }}` |
| `[[*MyTV:default=`fallback`]]` | `{{ resource.MyTV\|default('fallback') }}` |
| `[[*ImageTV]]` (HTML output) | `{{ resource.ImageTV\|raw }}` |

### Placeholders

| MODX | Twig |
|------|------|
| `[[+name]]` | `{{ placeholder('name') }}` or `{{ placeholders.name }}` |
| `[[+name:default=`Guest`]]` | `{{ placeholder('name', 'Guest') }}` |

### Chunks

| MODX | Twig |
|------|------|
| `[[$ChunkName]]` | `{{ chunk('ChunkName') }}` |
| `[[$ChunkName? &key=`value`]]` | `{{ chunk('ChunkName', {'key': 'value'}) }}` |

### Snippets

| MODX | Twig |
|------|------|
| `[[SnippetName]]` | `{{ snippet('SnippetName') }}` |
| `[[!SnippetName? &limit=`5`]]` | `{{ snippet('SnippetName', {'limit': 5}) }}` |

### System settings

| MODX | Twig |
|------|------|
| `[[++site_name]]` | `{{ option('site_name') }}` |
| `[[++site_url]]` | `{{ option('site_url') }}` |

### Links

| MODX | Twig |
|------|------|
| `[[~12]]` | `{{ link(12) }}` |
| `[[~[[*id]]]]` | `{{ link(resource.id) }}` |

### Output modifiers (filters)

| MODX | Twig |
|------|------|
| `[[*pagetitle:ucase]]` | `{{ resource.pagetitle\|upper }}` |
| `[[*pagetitle:lcase]]` | `{{ resource.pagetitle\|lower }}` |
| `[[+value:default=`none`]]` | `{{ value\|default('none') }}` |
| `[[+value:stripTags]]` | `{{ value\|striptags }}` |
| `[[+value:nl2br]]` | `{{ value\|nl2br }}` |
| `[[+value:limit=`100`]]` | `{{ value\|slice(0, 100) }}` |
| `[[+value:ellipsis=`100`]]` | `{{ value\|length > 100 ? value\|slice(0, 100) ~ '...' : value }}` |
| `[[+date:strtotime:date=`%d/%m/%Y`]]` | `{{ date\|date('d/m/Y') }}` |

### Conditionals

| MODX | Twig |
|------|------|
| `[[+value:is=`yes`:then=`Yes!`:else=`No`]]` | `{{ value == 'yes' ? 'Yes!' : 'No' }}` |
| `[[+value:notempty=`Has value`]]` | `{% if value %}Has value{% endif %}` |
| `[[+value:empty=`No value`]]` | `{% if not value %}No value{% endif %}` |
| `[[*published:is=`1`:then=`Live`]]` | `{% if resource.published %}Live{% endif %}` |

### Iteration

MODX does not have a native loop construct. Twig does:

```twig
{# Twig can loop over arrays, which MODX tags cannot do natively #}
{% for item in items %}
    <div>{{ item.title }}</div>
{% endfor %}
```

This is one of the main reasons to use Twig -- anything that required a snippet purely for looping can now be done in the template.
