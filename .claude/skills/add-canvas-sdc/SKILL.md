---
name: add-canvas-sdc
description: Scaffold a Drupal Single Directory Component (SDC) that works in Drupal Canvas / Experience Builder. Use when the user wants to add, create, or generate an SDC component, a card/hero/banner component, or make an existing SDC usable in the Canvas page builder. Covers the Canvas-specific requirements (examples on every prop, shape matching, slots) that a plain SDC omits.
---

# Add a Canvas-compatible SDC

A normal SDC renders fine in Twig but stays invisible/unusable in **Drupal Canvas** unless each prop can be *shape-matched* into the editor form. The #1 cause of "my component doesn't show up in Canvas" is **a prop with no `examples`**. This skill scaffolds a component that avoids that and the other common gotchas.

## The Canvas rules (what makes this different from a plain SDC)

1. **Every prop needs `examples:`** — Canvas uses the first example as the default/preview value when the component is placed. `examples` is a YAML **array**. For `required` props it is mandatory.
2. **Pick a prop shape Canvas understands** — string, string+HTML, textarea, boolean, integer, link, enum (dropdown), image object, date, array. See [REFERENCE.md](REFERENCE.md) for the exact YAML per shape.
3. **Links** use `format: uri-reference` (relative or absolute) or `format: uri` (absolute only) — a bare string renders as a plain text box.
4. **Enums** add `meta:enum:` for labels, and must **never contain an empty value** (`enum: ['', x]`) — Canvas rejects the whole component and auto-disables it. For an optional choice, leave the prop out of `required:` instead.
5. **Slots** work as drop zones automatically — no `examples` needed.
6. **Do not set `noUi: true`** (that hides the component from Canvas).

## Workflow

1. **Locate the theme.** Default target is the custom theme `guardrails`:
   `web/themes/custom/guardrails/components/<machine_name>/`. If that path doesn't exist, look for another `web/themes/custom/*` or custom module with a `components/` dir and confirm with the user. The machine name must be lowercase, `a-z0-9_`, and match the directory and the `*.component.yml` filename.

2. **Create three files** in that directory (`<name>.component.yml`, `<name>.twig`, `<name>.css`). Use the card in this repo as the canonical example, and [REFERENCE.md](REFERENCE.md) for prop shapes. Skeleton:

   ```yaml
   # <name>.component.yml
   '$schema': 'https://git.drupalcode.org/project/drupal/-/raw/HEAD/core/assets/schemas/v1/metadata.schema.json'
   name: <Human Name>
   status: stable
   description: '<one line>'
   props:
     type: object
     required: []            # list required prop names here
     properties:
       title:
         type: string
         title: Title
         examples:
           - 'Example title'   # ← REQUIRED for Canvas
   slots:                      # optional
     content:
       title: Content
       description: 'Arbitrary renderable content.'
   ```

   In Twig, render props with `{{ propname }}` and slots with `{% block slotname %}{% endblock %}`. Auto-attach CSS by naming it `<name>.css` (SDC attaches it automatically).

3. **Validate** before rebuilding cache:

   ```bash
   node .claude/skills/add-canvas-sdc/scripts/validate-canvas-sdc.mjs \
     web/themes/custom/guardrails/components/<name>/<name>.component.yml
   ```

   Fix every ❌ blocker (and ideally the ⚠️ suggestions), then re-run until it prints `✅ Canvas-ready`.

4. **Rebuild cache** so Drupal re-discovers the component:

   ```bash
   ddev drush cache:rebuild
   ```

   A clean rebuild also confirms the YAML schema is valid (an invalid SDC errors during discovery).

5. **Enable the component in Canvas.** Discovery alone is not enough — Canvas auto-registers each valid SDC as a config entity `canvas.component.sdc.<theme>.<machine_name>` but creates it **disabled** (`status: false`), so it won't appear in the Library until opted in. The cache rebuild in step 4 is what creates the entity, so enable it after:

   ```bash
   # Confirm it was registered and check status / that every prop shape-matched:
   ddev drush config:get canvas.component.sdc.guardrails.<name>
   # Enable it so it shows in the Canvas Library:
   ddev drush config:set canvas.component.sdc.guardrails.<name> status true -y
   ```

   If after this `status` flips back to `false` on the next `cache:rebuild`, the component is **ineligible** (failing Canvas's requirements) and Canvas re-disables it every rebuild — `config:set` won't stick. See Troubleshooting.

6. **Tell the user** to reload the Canvas editor; the component now appears in the Library (usually under "Other"). Drop it on the page to see its props form and slots.

7. **Persist the enabled status (optional).** `status: true` lives in *active* config only; a later `drush cim` reverts it (and the next rebuild regenerates the entity disabled). To keep it enabled across config import/deploys, export it: `ddev drush cex -y` (the component entity is usually the only diff). Note the sync dir is often under `web/sites/default/files/sync` and git-ignored, so this persists locally but may not travel with the repo.

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

## Troubleshooting

**Component never appears / `status` reverts to `false` on every `cache:rebuild`.**
The component is failing Canvas's requirements check, so Canvas auto-disables it each rebuild. The exact reason is stored in a key-value collection (NOT watchdog). Read it:

```bash
ddev drush php:eval '$r = \Drupal::service("Drupal\canvas\ComponentIncompatibilityReasonRepository")->getReasons(); echo json_encode($r["sdc"] ?? "none", JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);'
```

This prints messages like `Prop "modifier" has an empty enum value.` Fix the prop, `cache:rebuild`, and re-check. Common causes: an **empty `enum` value** (see rule 4), a prop with no `examples`, or a prop shape Canvas can't map. (Reasons are append-only — a stale entry can linger after you fix it, so trust a `true` status that survives a rebuild over the reason list.)

## Quick reference

- Prop shapes & exact YAML: [REFERENCE.md](REFERENCE.md)
- Validator: `scripts/validate-canvas-sdc.mjs` (dependency-free Node)
- Canonical example in-repo: `web/themes/custom/guardrails/components/card/`
