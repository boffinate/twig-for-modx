# Using Twig with ContentBlocks

## How It Works

The Twig Extra includes a plugin called **TwigContentBlocks** that listens to the `ContentBlocks_BeforeParse` event. When ContentBlocks is about to render a field template, the plugin passes it through Twig first. This means you can use Twig syntax in any ContentBlocks field template, layout template, or repeater template.

The plugin receives two values from ContentBlocks:

- `$tpl` -- the template string for the field
- `$phs` -- an array of placeholder values for that field

These placeholders become Twig variables. You access them directly by name.

## Simple Fields

For simple ContentBlocks field types (text, textarea, richtext, image, code, etc.), the template receives the field's placeholders as Twig variables. The most common placeholder is `value`, which holds the field content.

### MODX placeholder syntax (before)

```html
<div class="text-block">[[+value]]</div>
```

### Twig syntax (after)

```twig
<div class="text-block">{{ value }}</div>
```

### Available variables in simple fields

Every ContentBlocks field template receives at least:

| Variable | Description |
|----------|-------------|
| `value` | The field content entered by the editor |
| `idx` | The position index of this field on the page |

Additional variables depend on the field type and its settings. For example, an image field may also provide `url`, `size`, `extension`, or any properties defined in the field settings.

### Examples

**Image field with optional link:**

```twig
{% if url %}
    <figure>
        <img src="{{ url }}" alt="{{ title|default('') }}">
        {% if caption %}
            <figcaption>{{ caption }}</figcaption>
        {% endif %}
    </figure>
{% endif %}
```

**Code field with language label:**

```twig
<pre><code class="language-{{ language|default('text') }}">{{ value }}</code></pre>
```

**Text field with fallback:**

```twig
<p>{{ value|default('No content provided') }}</p>
```

### Using Twig filters on field values

Twig filters are especially useful in ContentBlocks templates because they replace the MODX output modifier syntax, which can be awkward for anything beyond simple cases.

```twig
{{ value|upper }}
{{ value|lower }}
{{ value|striptags }}
{{ value|trim }}
{{ value|default('Fallback text') }}
{{ value|nl2br }}
{{ value|length }}
```

### Conditional rendering

Show or hide parts of a template based on whether values are filled in:

```twig
{% if value %}
    <div class="content">{{ value|raw }}</div>
{% endif %}
```

```twig
{% if url %}
    <a href="{{ url }}">{{ title }}</a>
{% else %}
    <span>{{ title }}</span>
{% endif %}
```

## Repeaters

Repeaters are the ContentBlocks field type that holds a collection of rows, where each row has its own set of sub-fields. This is the area where Twig is most useful, because Twig's loop handling is much more capable than what MODX placeholders alone can do.

### How repeater rendering works

ContentBlocks renders a repeater in two stages:

1. **Row template** -- rendered once per row, with that row's field values as placeholders
2. **Wrapper template** -- rendered once for the whole repeater, receiving the combined output

Without any modification, the wrapper template receives `rows` as a single string of pre-rendered HTML. That means the wrapper cannot access the raw data from individual rows.

### The `row_data` variable

With the [ContentBlocks patch](#required-contentblocks-patch) applied, the wrapper template also receives a `row_data` variable. This is an array of the raw row data, where each entry is a hash of field keys to values.

This is the variable that makes Twig genuinely powerful for repeaters: you can loop over the raw row data with full access to each field in each row, use Twig's `loop` variable, apply filters and conditionals per row, and render everything in one template.

### Row template variables

Each row template receives the sub-field values as individual variables, just like a simple field template. The exact variable names match the keys you defined for the repeater's sub-fields in ContentBlocks.

If you defined a repeater with sub-fields `heading`, `body`, and `image`, the row template receives:

| Variable | Description |
|----------|-------------|
| `heading` | The heading sub-field value |
| `body` | The body sub-field value |
| `image` | The image sub-field value |
| `idx` | The row index (1-based) |

**Row template example:**

```twig
<div class="card">
    <h3>{{ heading }}</h3>
    <p>{{ body }}</p>
    {% if image %}
        <img src="{{ image }}" alt="{{ heading }}">
    {% endif %}
</div>
```

### Wrapper template variables

The wrapper template receives:

| Variable | Description |
|----------|-------------|
| `rows` | Pre-rendered HTML string -- the concatenated output of all row templates |
| `row_data` | Array of raw row data (requires patch) -- each entry is a hash of sub-field keys to values |
| `idx` | The position index of this repeater field on the page |

### Using `rows` (pre-rendered output)

If you only need to wrap the rendered rows in a container, use `rows` with the `raw` filter:

```twig
<div class="card-grid">
    {{ rows|raw }}
</div>
```

This is equivalent to the standard MODX approach of `<div class="card-grid">[[+rows]]</div>`.

### Using `row_data` (raw data)

`row_data` gives you full control. Each entry in the array is a hash with the sub-field keys as keys and their values as values.

**Basic loop:**

```twig
<div class="card-grid">
{% for row in row_data %}
    <div class="card">
        <h3>{{ row.heading }}</h3>
        <p>{{ row.body }}</p>
    </div>
{% endfor %}
</div>
```

**With loop index:**

```twig
<ol>
{% for row in row_data %}
    <li>{{ loop.index }}. {{ row.title }}</li>
{% endfor %}
</ol>
```

**With first/last detection:**

```twig
{% for row in row_data %}
    <div class="card{% if loop.first %} card--first{% endif %}{% if loop.last %} card--last{% endif %}">
        {{ row.title }}
    </div>
{% endfor %}
```

**With conditional rendering per row:**

```twig
{% for row in row_data %}
    {% if row.published %}
        <div class="card">
            <h3>{{ row.heading }}</h3>
            {% if row.image %}
                <img src="{{ row.image }}" alt="{{ row.heading }}">
            {% endif %}
            <p>{{ row.body }}</p>
        </div>
    {% endif %}
{% endfor %}
```

**Counting rows:**

```twig
<p>{{ row_data|length }} items</p>
```

**Grid with row-count class:**

```twig
<div class="grid grid--{{ row_data|length }}-cols">
{% for row in row_data %}
    <div class="grid__item">{{ row.title }}</div>
{% endfor %}
</div>
```

**Comma-separated list:**

```twig
{% for row in row_data %}{{ row.name }}{% if not loop.last %}, {% endif %}{% endfor %}
```

### The Twig `loop` variable

Inside a `{% for %}` block, Twig provides a `loop` variable with useful properties:

| Property | Description |
|----------|-------------|
| `loop.index` | Current iteration (1-based) |
| `loop.index0` | Current iteration (0-based) |
| `loop.first` | `true` on the first iteration |
| `loop.last` | `true` on the last iteration |
| `loop.length` | Total number of items |
| `loop.revindex` | Iterations remaining (1-based) |

### When to use `rows` vs `row_data`

Use `rows` when:

- You only need to wrap the pre-rendered HTML in a container
- The row template already handles all per-row logic
- You want ContentBlocks to handle the individual row rendering

Use `row_data` when:

- You need to loop over rows with Twig logic (conditionals, filters, index-based classes)
- You need to skip certain rows based on field values
- You want to count rows or detect first/last
- You want to render everything in the wrapper template and leave the row template minimal or empty

You can use both in the same template. For example, output the standard rendered rows but also show a count:

```twig
<p>{{ row_data|length }} team members</p>
<div class="team-grid">
    {{ rows|raw }}
</div>
```

## Complete Examples

### FAQ accordion

Repeater sub-fields: `question`, `answer`

**Wrapper template:**

```twig
<div class="faq">
    <h2>Frequently Asked Questions ({{ row_data|length }})</h2>
    {% for row in row_data %}
        <details{% if loop.first %} open{% endif %}>
            <summary>{{ row.question }}</summary>
            <div class="faq__answer">{{ row.answer|raw }}</div>
        </details>
    {% endfor %}
</div>
```

### Team grid with alternating layout

Repeater sub-fields: `name`, `role`, `photo`, `bio`

**Wrapper template:**

```twig
<section class="team">
{% for member in row_data %}
    <div class="team__member{% if loop.index is odd %} team__member--left{% else %} team__member--right{% endif %}">
        {% if member.photo %}
            <img src="{{ member.photo }}" alt="{{ member.name }}">
        {% endif %}
        <div class="team__info">
            <h3>{{ member.name }}</h3>
            <p class="team__role">{{ member.role }}</p>
            {% if member.bio %}
                <div class="team__bio">{{ member.bio|raw }}</div>
            {% endif %}
        </div>
    </div>
{% endfor %}
</section>
```

### Pricing table

Repeater sub-fields: `plan`, `price`, `features`, `highlighted`

**Wrapper template:**

```twig
<div class="pricing pricing--{{ row_data|length }}-plans">
{% for row in row_data %}
    <div class="pricing__plan{% if row.highlighted %} pricing__plan--featured{% endif %}">
        <h3>{{ row.plan }}</h3>
        <div class="pricing__price">{{ row.price }}</div>
        <div class="pricing__features">{{ row.features|raw }}</div>
    </div>
{% endfor %}
</div>
```

### Image gallery with lightbox data attributes

Repeater sub-fields: `image`, `caption`, `alt`

**Wrapper template:**

```twig
{% if row_data|length > 0 %}
<div class="gallery gallery--{{ row_data|length }}" data-lightbox="gallery-{{ idx }}">
    {% for img in row_data %}
        <figure class="gallery__item">
            <a href="{{ img.image }}" data-index="{{ loop.index0 }}">
                <img src="{{ img.image }}" alt="{{ img.alt|default(img.caption)|default('') }}">
            </a>
            {% if img.caption %}
                <figcaption>{{ img.caption }}</figcaption>
            {% endif %}
        </figure>
    {% endfor %}
</div>
{% endif %}
```

### Tabbed content

Repeater sub-fields: `tab_title`, `tab_content`

**Wrapper template:**

```twig
{% if row_data|length > 0 %}
<div class="tabs">
    <ul class="tabs__nav" role="tablist">
    {% for row in row_data %}
        <li role="presentation">
            <button role="tab" id="tab-{{ idx }}-{{ loop.index }}"
                    aria-controls="panel-{{ idx }}-{{ loop.index }}"
                    {% if loop.first %}aria-selected="true"{% endif %}>
                {{ row.tab_title }}
            </button>
        </li>
    {% endfor %}
    </ul>
    {% for row in row_data %}
        <div role="tabpanel" id="panel-{{ idx }}-{{ loop.index }}"
             aria-labelledby="tab-{{ idx }}-{{ loop.index }}"
             {% if not loop.first %}hidden{% endif %}>
            {{ row.tab_content|raw }}
        </div>
    {% endfor %}
</div>
{% endif %}
```

## Inspecting Available Variables

Use `dump()` to see what data ContentBlocks is passing to your template.

### Dump everything

Call `dump()` with no arguments to see every variable available in the current template:

```twig
{{ dump() }}
```

This shows only the ContentBlocks field placeholders -- globals like `modx`, `resource`, and `placeholders` are excluded from no-arg dumps because they are always present and would obscure the field data you are looking for. To inspect a global, dump it explicitly:

```twig
{{ dump(resource) }}
{{ dump(modx) }}
```

See the [dump() reference in the usage guide](./usage.md#debugging-with-dump) for more details.

### Dump a single variable

```twig
{{ dump(row_data) }}
{{ dump(value) }}
{{ dump(idx) }}
```

### What you will see

**Simple field template** -- the output shows the field's placeholders:

```
array(3) {
  ["value"]=> string(11) "Hello world"
  ["idx"]=> int(1)
  ["setting"]=> string(10) "full-width"
}
```

**Repeater row template** -- the output shows that row's sub-field values:

```
array(4) {
  ["heading"]=> string(10) "Card Title"
  ["body"]=> string(21) "Card content goes here"
  ["image"]=> string(15) "/images/card.jpg"
  ["idx"]=> int(1)
}
```

**Repeater wrapper template** -- the output shows the rendered rows string, the raw row data array, and any wrapper-level variables:

```
array(3) {
  ["rows"]=> string(82) "<div>...</div><div>...</div>"
  ["row_data"]=> array(2) {
    [0]=> array(2) {
      ["heading"]=> string(5) "First"
      ["body"]=> string(11) "Content one"
    }
    [1]=> array(2) {
      ["heading"]=> string(6) "Second"
      ["body"]=> string(11) "Content two"
    }
  }
  ["idx"]=> int(1)
}
```

The `row_data` array is only present when the [ContentBlocks patch](#required-contentblocks-patch) is applied. Without the patch, the wrapper output will show `rows` as a string but no `row_data` key.

### Tips

- Put `{{ dump() }}` at the top of a template you are building to get a quick reference of what variables are available, then remove it when you are done.
- When Symfony VarDumper is installed (included as a dev dependency), the dump renders as interactive, collapsible HTML inside an iframe. You can expand and collapse nodes to explore the data. Otherwise it uses PHP's `var_dump` format in a `<pre>` block.
- Array keys are the variable names you use in Twig (`{{ heading }}`, `{{ row.body }}`, etc.).
- Remove all `dump()` calls before going to production.

## Using MODX Functions Inside ContentBlocks Templates

The built-in Twig functions work inside ContentBlocks templates. You can call chunks, snippets, read system settings, and generate URLs.

```twig
{% for row in row_data %}
    {{ chunk('TeamMemberCard', {'name': row.name, 'role': row.role}) }}
{% endfor %}
```

```twig
<a href="{{ link(row.link_resource_id) }}">{{ row.link_text }}</a>
```

```twig
{{ option('site_name') }}
```

## Required ContentBlocks Patch

The `row_data` variable is only available if you apply a small patch to ContentBlocks. Without this patch, the wrapper template only receives `rows` (the pre-rendered HTML string) and you cannot loop over the raw row data in Twig.

The patch is described in `core/components/twig/patches/contentblocks/README.md` and has been submitted for inclusion in ContentBlocks.

### What the patch does

In ContentBlocks' `repeaterinput.class.php`, before the rendered output replaces the raw rows array, the patch saves a copy:

```php
// Before (original):
$data['rows'] = $rowsOutput;

// After (patched):
$data['row_data'] = $data['rows'];   // keep raw row data
$data['rows'] = $rowsOutput;          // rendered output
```

This preserves the original array of row data in `row_data` while `rows` still contains the rendered HTML. Both are then passed to the template.

### Without the patch

If you cannot patch ContentBlocks, you can still use Twig in:

- Simple field templates (all variables work normally)
- Repeater row templates (per-row variables work normally)
- Repeater wrapper templates (only `rows` is available as a pre-rendered string)

The main thing you lose is the ability to loop over raw row data in the wrapper template using `row_data`.
