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

## Number (decimal / float)
For non-integer values (ratings, weights, percentages). Same shape as integer
but `type: number`; Canvas maps it to a float field and accepts decimals.
```yaml
rating:
  type: number
  title: Rating
  minimum: 0
  maximum: 10
  examples:
    - 7.1
```
```twig
{{ rating|number_format(1) }}
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

## Composing components (a component that includes another)

A component can render another SDC to build larger pieces from smaller ones
(e.g. a card that embeds a rating and a stats row). Include the child by its
plugin id and pass its props explicitly:

```twig
{{ include('guardrails:rating_stars', { rating: rating }, with_context = false) }}
```

- **Always pass `with_context = false`.** Otherwise the parent's whole context —
  including its own `attributes` object — leaks into the child, so the child
  reuses the parent's CSS classes and stray variables. SDC always gives the
  child a fresh `attributes`; isolating context keeps that clean.
- Pass only the props the child declares; map the parent's props/values to the
  child's prop names inline.
- Guard a child's **required** props at the include site (`{% if value is not
  null %}`) so you never invoke it with a missing required prop.
- **Never pass an explicit `null` for a typed prop.** SDC validates props and
  throws `InvalidComponentException` ("NULL value found, but a …") when a typed
  prop (integer/number/string/…) receives `null` — even an *optional* one. So
  for optional props, omit the key entirely rather than passing null: build the
  props object conditionally in Twig with `merge`, or `array_filter(..., fn($v)
  => $v !== NULL)` the array in preprocess.

  ```twig
  {% set badges = {} %}
  {% if play_time is not null %}{% set badges = badges|merge({ play_time: play_time }) %}{% endif %}
  {% if badges is not empty %}{{ include('guardrails:badge_row', badges, with_context = false) }}{% endif %}
  ```

## Derive presentation in the component, not in preprocess

Canvas passes prop values **straight to the component** — there is no preprocess
hook in the Canvas render path. So any presentational math that turns a prop
into markup (a fill percentage, a pip/star count, a rounded label) must live in
the **component Twig**, computed from the props, not in a theme preprocess
function. (Preprocess still maps *entity fields* to props when the component is
rendered through a node template — but the component must stand on its own when
an editor places it in Canvas with raw prop values.)

## Accessibility for visual components (gauges, meters, ratings)

Visual indicators must meet WCAG AA on their own:

- **Never convey the value by color alone** (WCAG 1.4.1). Pair every gauge,
  meter or star row with a text/numeric readout of the value.
- Expose the visual as a single labelled image: put `role="img"` and a
  descriptive `aria-label` (e.g. `"Rated 7.1 out of 10"`) on the wrapper, and
  mark the decorative glyphs/pips/icons `aria-hidden="true"` so assistive tech
  hears the label once, not every star.
- If a visible numeric readout duplicates the aria-label, mark the readout
  `aria-hidden="true"` to avoid a double announcement.
- Graphical objects (filled vs empty pips/stars) should differ by luminance,
  not hue alone, and target ~3:1 contrast against their background/each other
  (WCAG 1.4.11).

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