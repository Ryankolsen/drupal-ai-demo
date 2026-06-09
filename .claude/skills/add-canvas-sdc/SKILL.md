---
name: add-canvas-sdc
description: Scaffold a Drupal Single Directory Component (SDC) that works in Drupal Canvas / Experience Builder. Use when the user wants to add, create, or generate an SDC component, a card/hero/banner component, or make an existing SDC usable in the Canvas page builder. Covers the Canvas-specific requirements (examples on every prop, shape matching, slots) that a plain SDC omits.
---

# Add a Canvas-compatible SDC

A normal SDC renders fine in Twig but stays invisible/unusable in **Drupal Canvas** unless each prop can be *shape-matched* into the editor form. The #1 cause of "my component doesn't show up in Canvas" is **a prop with no `examples`**.

## The Canvas rules (what makes this different from a plain SDC)

1. **Every prop needs `examples:`** — Canvas uses the first example as the default/preview value. It is a YAML **array**, and mandatory for `required` props.
2. **Pick a prop shape Canvas understands** — string, string+HTML, textarea, boolean, integer, number, link, enum, image object, date, array. Exact YAML per shape: [REFERENCE.md](REFERENCE.md).
3. **Links** use `format: uri-reference` (relative or absolute) or `format: uri` (absolute only) — a bare string renders as a plain text box.
4. **Enums** add `meta:enum:` for labels and must **never contain an empty value** (`enum: ['', x]`) — Canvas rejects the whole component and auto-disables it. For an optional choice, leave the prop out of `required:` instead.
5. **Slots** work as drop zones automatically — no `examples` needed.
6. **Do not set `noUi: true`** (that hides the component from Canvas).

## Workflow

1. **Locate the theme.** Default target: `web/themes/custom/guardrails/components/<machine_name>/`. If absent, look for another `web/themes/custom/*` or custom module with a `components/` dir and confirm with the user. Machine name must be lowercase `a-z0-9_` and match the directory and `*.component.yml` filename.

2. **Create three files** (`<name>.component.yml`, `<name>.twig`, `<name>.css`). Use the card in this repo as the canonical example, and [REFERENCE.md](REFERENCE.md) for prop shapes. Skeleton:

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

   In Twig, render props with `{{ propname }}` and slots with `{% block slotname %}{% endblock %}`. Name CSS `<name>.css` and SDC auto-attaches it.

3. **Validate** before rebuilding cache, fixing every ❌ until it prints `✅ Canvas-ready`:

   ```bash
   node .claude/skills/add-canvas-sdc/scripts/validate-canvas-sdc.mjs \
     web/themes/custom/guardrails/components/<name>/<name>.component.yml
   ```

4. **Rebuild cache** so Drupal re-discovers the component (also validates the YAML schema — an invalid SDC errors during discovery):

   ```bash
   ddev drush cache:rebuild
   ```

5. **Enable the component in Canvas.** The rebuild auto-registers each valid SDC as config entity `canvas.component.sdc.<theme>.<machine_name>` but creates it **disabled** (`status: false`), so it won't appear in the Library until opted in:

   ```bash
   # Confirm it registered and that every prop shape-matched:
   ddev drush config:get canvas.component.sdc.guardrails.<name>
   # Enable it so it shows in the Library:
   ddev drush config:set canvas.component.sdc.guardrails.<name> status true -y
   ```

   If `status` flips back to `false` on the next `cache:rebuild`, the component is **ineligible** and Canvas re-disables it every rebuild — `config:set` won't stick. See Troubleshooting.

6. **Tell the user** to reload the Canvas editor; the component appears in the Library (usually under "Other"). Drop it to see its props form and slots.

7. **Persist the enabled status (optional).** `status: true` lives in *active* config only; a later `drush cim` reverts it (and the next rebuild regenerates it disabled). To keep it across imports/deploys, `ddev drush cex -y` (the component entity is usually the only diff). Note the sync dir is often `web/sites/default/files/sync` and git-ignored, so this persists locally but may not travel with the repo.

## Beyond placement

- **Composing components** (a component that includes another SDC): always `include(...)` with `with_context = false`, and **never pass an explicit `null` for a typed prop** (throws `InvalidComponentException`) — omit the key instead. Full patterns: [REFERENCE.md](REFERENCE.md).
- **Derive presentation in the component, not preprocess** — Canvas passes prop values straight to the component with no preprocess hook in its render path, so any prop→markup math (fill %, pip count) must live in the component Twig. See [REFERENCE.md](REFERENCE.md).
- **Accessibility for visual components** (gauges, meters, ratings) — never convey value by color alone, expose the visual as one labelled `role="img"`/`aria-label` element with decorative glyphs `aria-hidden="true"`. See [REFERENCE.md](REFERENCE.md).

## Troubleshooting

**Component never appears / `status` reverts to `false` on every `cache:rebuild`.** It's failing Canvas's requirements check, so Canvas auto-disables it each rebuild. The reason is stored in a key-value collection (NOT watchdog):

```bash
ddev drush php:eval '$r = \Drupal::service("Drupal\canvas\ComponentIncompatibilityReasonRepository")->getReasons(); echo json_encode($r["sdc"] ?? "none", JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);'
```

Prints messages like `Prop "modifier" has an empty enum value.` Fix the prop, `cache:rebuild`, re-check. Common causes: an empty `enum` value (rule 4), a prop with no `examples`, or an unmappable prop shape. (Reasons are append-only — a stale entry can linger after a fix, so trust a `true` status that survives a rebuild over the reason list.)

## Quick reference

- Prop shapes & exact YAML, composing, accessibility: [REFERENCE.md](REFERENCE.md)
- Validator: `scripts/validate-canvas-sdc.mjs` (dependency-free Node)
- Canonical example in-repo: `web/themes/custom/guardrails/components/card/`
