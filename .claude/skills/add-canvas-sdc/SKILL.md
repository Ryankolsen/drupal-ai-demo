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

   If `config:get` returns nothing, the SDC failed Canvas discovery — re-run the validator (step 3) and check `ddev drush watchdog:show` for shape-matching errors. A prop that won't shape-match keeps the whole component out.

6. **Tell the user** to reload the Canvas editor; the component now appears in the Library (usually under "Other"). Drop it on the page to see its props form and slots.

## Quick reference

- Prop shapes & exact YAML: [REFERENCE.md](REFERENCE.md)
- Validator: `scripts/validate-canvas-sdc.mjs` (dependency-free Node)
- Canonical example in-repo: `web/themes/custom/guardrails/components/card/`
