---
name: adding-fields
description: Add (or remove) a field on an existing Drupal bundle via exported config YAML, then normalize and capture it. Use when wiring a single field onto a content type or other fieldable bundle that already exists — not when scaffolding a whole new bundle (use create-content-type for that).
---

# Adding a Field to an Existing Bundle

For a brand-new content type *with* its fields, use `create-content-type`. This
skill is the narrower case: one field onto a bundle that already exists. Prefer
**copying a sibling field's YAML** and retargeting it over hand-authoring keys.

Config lives in `/config/sync` (single directory — this repo has no
`config_split`). Custom code lives in `web/modules/custom`.

## Checklist

For `field_foo` on bundle `<bundle>` (entity type usually `node`):

- [ ] `field.storage.node.field_foo.yml` — **only if new storage**; reuse existing storage when the field already exists on another bundle
- [ ] `field.field.node.<bundle>.field_foo.yml` — the instance (label, required, handler settings)
- [ ] `core.entity_form_display.node.<bundle>.default.yml` — widget + dependency
- [ ] **Every** `core.entity_view_display.node.<bundle>.*.yml` — add the dependency and either render the field under `content:` or list it under `hidden:`. Miss one and the field silently won't appear in that view mode.
- [ ] `grep -rn 'field_foo' web/modules/custom web/themes/custom` — adding an instance can activate dormant form alters / preprocess logic

Find all displays for a bundle:

```bash
ls config/sync/core.entity_{form,view}_display.node.<bundle>.*.yml
```

## Pattern — copy, don't compose

1. Find a sibling field of the same type/cardinality on the same (or a peer) bundle.
2. Copy its storage (if needed), instance, and every display entry.
3. Retarget `id:`, `bundle:`, `field_name:`, `label:`.
4. Mirror the render/hide decision in each view mode.

## Surface the field through a component

Presentational output in this repo is built with **Single Directory Components**
(see `add-canvas-sdc`). When the new field should show on a card or detail view:
add the field to the relevant `core.entity_view_display.*` (or `hidden:` it if a
preprocess maps it), then map it to the component's prop in the bundle's
`*_preprocess_*()` hook — never read the field value in Twig.

## Capture & normalize

```bash
ddev drush cim -y
ddev drush cex -y
ddev drush cr
```

**Why `cex` after `cim` is mandatory.** Hand-authored field YAML almost always
differs from Drupal's exporter output — missing `uuid:`, key ordering, absent
optional keys. `cex` after `cim` lets Drupal normalize every file: it writes back
the auto-assigned UUID onto `field.field.*.yml`, fixes key order, and adds missing
keys. Review the resulting `git diff` — expect at minimum a `uuid:` added to each
new `field.field.*.yml`, and commit that normalized diff.

If `cex` shows deletions or unrelated changes, stop and investigate — the only
expected diff is normalization of the files you just added.

## Gotchas

- **Omit `uuid:`** in new hand-authored files; never copy a sibling's UUID. Drupal
  assigns a fresh one on import, and `cex` writes it back. Shipping a
  `field.field.*.yml` with a stale UUID causes infinite drift — every `cim`
  re-applies the file because the DB UUID won't match.
- **Storage is shared across bundles.** Don't change cardinality / `target_type`
  on existing storage for one bundle's sake — create a new field instead.
- **Unlimited cardinality is `-1`**, not `0`.
- **Entity-reference** needs `handler` + `handler_settings.target_bundles` on the
  instance, or autocomplete returns every entity.

QA: the edit form shows the widget, save persists, every view mode renders or
hides the field as intended, and any hook keyed on the field name behaves.
