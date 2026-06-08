c---
name: add-canvas-sdc
description: Scaffold a Drupal Single Directory Component (SDC) that works in Drupal Canvas / Experience Builder. Use when the user wants to add, create, or generate an SDC component, a card/hero/banner component, or make an existing SDC usable in the Canvas page builder. Covers the Canvas-specific requirements (examples on every prop, shape matching, slots) that a plain SDC omits.
---

# Add a Canvas-compatible SDC

A normal SDC renders fine in Twig but stays invisible/unusable in **Drupal Canvas** unless each prop can be *shape-matched* into the editor form. The #1 cause of "my component doesn't show up in Canvas" is **a prop with no `examples`**. This skill scaffolds a component that avoids that and the other common gotchas.

## The Canvas rules (what makes this different from a plain SDC)

1. **Every prop needs `examples:`** — Canvas uses the first example as the default/preview value when the component is placed. `examples` is a YAML **array**. For `required` props it is mandatory.
2. **Pick a prop shape Canvas understands** — string, string+HTML, textarea, boolean, integer, link, enum (dropdown), image object, date, array. See [REFERENCE.md](REFERENCE.md) for the exact YAML per shape.
3. **Links** use `format: uri-reference` (relative or absolute) or `format: uri` (absolute only) — a bare string renders as a plain text box.
4. **Enums** add `meta:enum:` for human-friendly dropdown labels.
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

5. **Tell the user** to reload the Canvas editor; the component appears in the components/`+` panel. If it still doesn't show, it may need enabling in Canvas's component list, but discovery now succeeds.

## Quick reference

- Prop shapes & exact YAML: [REFERENCE.md](REFERENCE.md)
- Validator: `scripts/validate-canvas-sdc.mjs` (dependency-free Node)
- Canonical example in-repo: `web/themes/custom/guardrails/components/card/`
