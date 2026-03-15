# Coming from Fenom

If you have been using pdoTools' Fenom syntax (`{$_modx->...}`, `{$_pls}`, etc.) and are switching to Twig, this guide maps Fenom patterns to their Twig equivalents.

## Key Differences

- **Fenom** is processed by pdoTools during the main MODX parser cycle. It uses `{curly brace}` syntax.
- **Twig** is processed by the Twig extra before MODX tags. It uses `{{ double brace }}` and `{% block %}` syntax.
- Both can coexist on the same site. Twig renders first, then MODX tags, then Fenom last. See [How It Works](./how-it-works.md) for details.

## Syntax Comparison

### Resource fields

| Fenom | Twig |
|-------|------|
| `{$_modx->resource.id}` | `{{ resource.id }}` |
| `{$_modx->resource.pagetitle}` | `{{ resource.pagetitle }}` |
| `{$_modx->resource.longtitle}` | `{{ resource.longtitle }}` |
| `{$_modx->resource.content}` | `{{ resource.content\|raw }}` |
| `{$_modx->resource.parent}` | `{{ resource.parent }}` |
| `{$_modx->resource.template}` | `{{ resource.template }}` |

### Template Variables

Fenom does not provide direct TV access on the resource object. pdoTools uses FastField tags (`{$_modx->resource.tv_name}` does not work for TVs in Fenom).

Twig unifies resource fields and TVs on the `resource` global:

| Fenom | Twig |
|-------|------|
| `{'*tv_name'\|resource}` or manual snippet | `{{ resource.MyTV }}` |
| (no equivalent) | `{{ resource.tvRawValue('MyTV') }}` |

The `resource` global returns processed TV values by default (same as `[[*MyTV]]`). Use `tvRawValue()` when you need the raw stored value before MODX applies output rendering.

### Snippets

| Fenom | Twig |
|-------|------|
| `{$_modx->runSnippet('SnippetName')}` | `{{ snippet('SnippetName') }}` |
| `{$_modx->runSnippet('!SnippetName', ['limit' => 5])}` | `{{ snippet('SnippetName', {'limit': 5}) }}` |

### System settings

| Fenom | Twig |
|-------|------|
| `{$_modx->config.site_name}` | `{{ option('site_name') }}` |
| `{$_modx->config.site_url}` | `{{ option('site_url') }}` |
| `{$_modx->config['emailsender']}` | `{{ option('emailsender') }}` |
| `{$_modx->config.any_system_setting}` | `{{ option('any_system_setting') }}` |

You can also access settings via the modx global: `{{ modx.config.site_name }}` or `{{ modx.config['site_name'] }}`.

### Lexicon

| Fenom | Twig |
|-------|------|
| `{$_modx->lexicon('key')}` | `{{ lexicon('key') }}` |
| `{$_modx->lexicon->load('topic')}` | `{{ modx.lexicon.load('topic') }}` |
| load + translate in one call | `{{ trans('key', 'topic') }}` |

The `trans()` function loads the lexicon topic and translates in a single call, which Fenom requires two steps for.

### Links

| Fenom | Twig |
|-------|------|
| `{$_modx->makeUrl(15)}` | `{{ link(15) }}` |
| `{$_modx->makeUrl($_modx->resource.id)}` | `{{ link(resource.id) }}` |

### Placeholders

| Fenom | Twig |
|-------|------|
| `{$_modx->getPlaceholder('name')}` | `{{ placeholder('name') }}` |
| `{$_pls['name']}` | `{{ placeholders.name }}` |
| `{$_pls['name'] ?: 'Guest'}` | `{{ placeholder('name', 'Guest') }}` |

### Chunks

Fenom does not have a built-in chunk rendering function. You would use a snippet or `$modx->getChunk()` in PHP.

| Fenom | Twig |
|-------|------|
| `{$_modx->getChunk('ChunkName')}` | `{{ chunk('ChunkName') }}` |
| `{$_modx->getChunk('ChunkName', ['key' => 'value'])}` | `{{ chunk('ChunkName', {'key': 'value'}) }}` |

### Context

| Fenom | Twig |
|-------|------|
| `{$_modx->context.key}` | `{{ modx.context.key }}` |

### User and profile

Fenom exposes `$_modx->user` as a flat array that merges the user record with profile fields, so `{$_modx->user.fullname}` works directly.

In Twig, `modx.user` is the `modUser` object. User-table fields (like `id` and `username`) are accessible directly, but profile fields (like `fullname`, `email`, `photo`) are on the related `Profile` object:

| Fenom | Twig |
|-------|------|
| `{$_modx->user.id}` | `{{ modx.user.id }}` |
| `{$_modx->user.username}` | `{{ modx.user.username }}` |
| `{$_modx->user.fullname}` | `{{ modx.user.Profile.fullname }}` |
| `{$_modx->user.email}` | `{{ modx.user.Profile.email }}` |
| `{$_modx->user.photo}` | `{{ modx.user.Profile.photo }}` |
| `{$_modx->user.extended}` | `{{ modx.user.Profile.extended }}` |

Checking whether a user is logged in:

```twig
{# Fenom #}
{if $_modx->user.id > 0}
    Hello, {$_modx->user.fullname}!
{else}
    You need to log in.
{/if}

{# Twig #}
{% if modx.user.id > 0 %}
    Hello, {{ modx.user.Profile.fullname }}!
{% else %}
    You need to log in.
{% endif %}
```

## Conditionals

Fenom and Twig conditionals are similar, but the syntax differs:

```twig
{# Fenom #}
{if $_modx->resource.parent == 5}
    <nav>{$_modx->runSnippet('SiteNav', ['startId' => 5])}</nav>
{/if}

{# Twig #}
{% if resource.parent == 5 %}
    <nav>{{ snippet('SiteNav', {'startId': 5}) }}</nav>
{% endif %}
```

## Loops

```twig
{# Fenom #}
{foreach $items as $item}
    <div>{$item.title}</div>
{/foreach}

{# Twig #}
{% for item in items %}
    <div>{{ item.title }}</div>
{% endfor %}
```

Twig's `loop` variable provides useful metadata inside loops:

```twig
{% for item in items %}
    {{ loop.index }}. {{ item.title }}
    {% if loop.first %} (first) {% endif %}
    {% if loop.last %} (last) {% endif %}
{% endfor %}
```

## Output Filters

Fenom uses modifiers with `|` (pipe) syntax, just like Twig. Many filter names differ:

| Fenom | Twig |
|-------|------|
| `{$value\|upper}` | `{{ value\|upper }}` |
| `{$value\|lower}` | `{{ value\|lower }}` |
| `{$value\|truncate:100}` | `{{ value\|slice(0, 100) }}` |
| `{$value\|strip}` | `{{ value\|striptags }}` |
| `{$value\|escape}` | `{{ value\|e }}` (auto-escaping is on by default) |
| `{$value\|date_format:'d/m/Y'}` | `{{ value\|date('d/m/Y') }}` |
| `{$value\|nl2br}` | `{{ value\|nl2br }}` |
| `{$value\|default:'none'}` | `{{ value\|default('none') }}` |
| `{$value\|json_encode}` | `{{ value\|json_encode }}` |
| `{$value\|number_format:2:'.':','}` | `{{ value\|number_format(2, '.', ',') }}` |

## What Twig Adds

Features available in Twig that Fenom does not provide:

- **Auto-escaping** -- all output is HTML-escaped by default, preventing XSS. Use `|raw` to output trusted HTML.
- **Template inheritance** -- `{% extends %}` and `{% block %}` for layout composition (when using file-based templates).
- **Macros** -- `{% macro %}` for reusable template functions.
- **Named arguments** -- `{{ chunk('Name', {'key': 'value'}) }}` instead of positional arrays.
- **Unified TV access** -- `{{ resource.MyTV }}` treats TVs and fields the same.
- **Extension API** -- register custom functions, filters, and globals via PHP classes or the `OnTwigInit` event. See the [Developer Guide](./developer-guide.md).

## What Changes

- **Escaping**: Twig auto-escapes output. If you see HTML tags appearing as text, add `|raw`. This is safer by default but requires awareness when outputting HTML content.
- **Variable syntax**: Fenom uses `{$var}`, Twig uses `{{ var }}`. No dollar sign prefix.
- **Method calls**: Fenom uses `{$_modx->method()}`, Twig uses helper functions (`snippet()`, `link()`, etc.) or `{{ modx.method() }}`.
- **Comments**: Fenom uses `{* comment *}`, Twig uses `{# comment #}`.
