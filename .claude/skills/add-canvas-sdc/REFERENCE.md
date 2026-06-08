# Canvas prop shapes — exact YAML

Every example below includes `examples:` because Canvas requires it. Drop these
under `props: > properties:`. Twig usage shown for each.

## String (single-line text)
```yaml
heading:
  type: string
  title: Heading
  examples:
    - 'Build with confidence'
```
```twig
<h2>{{ heading }}</h2>
```

## String with HTML (rich text / CKEditor)
```yaml
body:
  type: string
  title: Body
  contentMediaType: text/html
  x-formatting-context: block
  examples:
    - '<p>This is <strong>formatted</strong> text.</p>'
```
```twig
<div class="body">{{ body }}</div>
```

## Textarea (multi-line plain text)
```yaml
summary:
  type: string
  title: Summary
  $ref: json-schema-definitions://canvas.module/textarea
  examples:
    - "Line one\nLine two"
```
```twig
<p>{{ summary|nl2br }}</p>
```

## Boolean (checkbox)
```yaml
show_image:
  type: boolean
  title: 'Show image'
  examples:
    - true
```
```twig
{% if show_image %}…{% endif %}
```

## Integer (number, with optional bounds)
```yaml
spacing:
  type: integer
  title: 'Spacing (px)'
  minimum: 0
  maximum: 200
  examples:
    - 20
```
```twig
<div style="padding-top: {{ spacing }}px">…</div>
```

## Link (URL field)
- `format: uri-reference` → relative **or** absolute (`/about`, `https://…`).
- `format: uri` → absolute only (must include a scheme).
```yaml
url:
  type: string
  title: URL
  format: uri-reference
  examples:
    - '/about'
    - 'https://www.drupal.org'
```
```twig
<a href="{{ url }}">{{ cta }}</a>
```

## Enum (dropdown) — always add meta:enum labels
```yaml
alignment:
  type: string
  title: Alignment
  enum:
    - left
    - center
    - right
  meta:enum:
    left: Left aligned
    center: Center aligned
    right: Right aligned
  examples:
    - center
```
⚠️ **Never put an empty value in an `enum`** (e.g. `enum: ['', accent]` for a
"none" option). Canvas rejects the whole component — it becomes ineligible and
is auto-disabled on every cache rebuild, so it never appears in the Library.
To make a choice optional, simply leave the prop out of `required:`; an optional
enum can be left unset by the author. Guard the Twig for the unset case:
```yaml
  enum:
    - accent
  meta:enum:
    accent: Accent
```
```twig
<div class="box {{ alignment ? 'box--' ~ alignment }}">…</div>
```

## Image object (media library / upload)
```yaml
image:
  type: object
  title: Image
  $ref: json-schema-definitions://canvas.module/image
  examples:
    - src: '/sites/default/files/example.jpg'
      alt: 'Example image'
      width: 800
      height: 600
```
```twig
{% include 'canvas:image' with image only %}
```

## Date (date picker)
```yaml
event_date:
  type: string
  format: date
  title: 'Event date'
  examples:
    - '2026-06-07'
```
```twig
<time datetime="{{ event_date }}">{{ event_date|date('F j, Y') }}</time>
```

## Array (list of values)
```yaml
tags:
  type: array
  title: Tags
  items:
    type: string
  maxItems: 10
  examples:
    - ['Drupal', 'Canvas', 'SDC']
```
```twig
{% for tag in tags %}<span class="tag">{{ tag }}</span>{% endfor %}
```

## Slots (drop zones — no examples)
```yaml
slots:
  content:
    title: Content
    description: 'Arbitrary renderable content.'
```
```twig
{% block content %}{% endblock %}
```

---

## Required props
List names under `required:` (sibling of `properties:`). Required props **must**
have `examples`. Canvas marks them in the sidebar and blocks saving until filled.
```yaml
props:
  type: object
  required:
    - heading
  properties:
    heading:
      type: string
      examples:
        - 'Hot'
```

## Built-in validations from format / type
- `format: email`, `format: date`, `format: date-time` validate automatically.
- Strings: `minLength`, `maxLength`. Numbers: `minimum`, `maximum`. Arrays: `minItems`, `maxItems`.

## More
The Canvas module ships an **"all-props" test SDC** demonstrating every shape:
`web/modules/contrib/canvas/tests/modules/sdc_test_all_props/components/all-props/`.
Full docs: `web/modules/contrib/canvas/docs/user/src/content/docs/sdc-components/`.