---
name: editing-views
description: Hand-edit an existing Drupal View's YAML to add/change filters, fields, sorts, arguments, or displays, and normalize the result to match what Drupal would export. Use when modifying `config/**/views.view.*.yml` directly.
---

# Editing View YAML Directly

Views YAML has many handler-specific keys that aren't inferrable from neighboring entries. A hand-authored filter often works functionally but drifts from the canonical export shape — the drift shows up the next time someone opens the View in the UI and re-exports. This skill keeps the edit close to canonical from the start, and verifies it.

## Workflow

1. **Find a donor**: grep `config/` for an existing filter/field/sort on the same field, or with the same handler type. Its `plugin_id` is the handler; its shape is the template.
  - **If no donor exists, stop hand-editing.** Tell the user: "I can't find a canonical example of this handler in the codebase. Safer to make this change in the Drupal UI so Drupal picks the right handler and we export a known-good shape." Then walk them through it (see "UI walkthrough" below).
2. **Copy the donor's full shape** into the target view — every key, including ones that look redundant (`expose.reduce`, `reduce_duplicates`, full `group_info`, etc.).
3. **Apply the edit**.
4. **Normalize**: import + re-export so Drupal rewrites the YAML canonically.
   ```bash
   ddev drush cim -y && ddev drush cex -y
   ```
   Review `git diff` — any churn is Drupal normalizing your edit. Commit the normalized version, not the hand-authored one. If `cim` errors, read the error and fix the YAML; do not bypass with `--partial`.
5. **Verify in UI**: load `/admin/structure/views/view/{id}` and confirm the filter appears as intended. If it doesn't, the handler was wrong — revert and fall back to the UI walkthrough.

## Handler resolution

Views picks the handler based on the **indexed field's type** (for Search API views) or the **field's column schema** (for SQL views). Common gotchas:

| Field looks like | Actual Views handler | `plugin_id` |
|---|---|---|
| Content type / bundle (e.g., `node_bundle`) | Options (not string) | `search_api_options` |
| List (text/integer) field | Options | `views_handler_filter_in_operator` / SA equivalent |
| Taxonomy term reference | Entity reference / term | `search_api_term` or `taxonomy_index_tid` |
| Boolean | Boolean | `search_api_boolean` / `boolean` |
| Integer / decimal | Numeric | `search_api_numeric` / `numeric` |
| String (plain text) | String | `search_api_string` / `string` |
| Full-text searched | Fulltext | `search_api_fulltext` |
| Date | Date | `search_api_date` / `date` |

**To confirm before editing**: find any existing filter on the same field in any view. That tells you the handler Drupal assigned.

```bash
# e.g., to see how node_bundle has been filtered elsewhere
grep -rn "field: node_bundle" config/ | grep -i views
```

## Handler shape cheat sheet

Each handler has non-obvious required keys. When copying a donor, bring *all* of these:

**Options handler (`search_api_options`, `in_operator`)**
- `operator`: `in` | `not` (not `=` / `<>`)
- `value`: keyed map `{ key: key, key2: key2 }` — never a scalar or list
- `expose.reduce: false` — required even when not exposed
- Trailing `reduce_duplicates: false` — outside `expose`

**String handler (`search_api_string`, `string`)**
- `operator`: `=` | `<>` | `contains` | `starts` | `ends` | `empty` | `not empty`
- `value`: scalar string

**Boolean handler (`search_api_boolean`, `boolean`)**
- `value`: `'0'` | `'1'` (string, not bool)

**All filters regardless of handler**
- `id`, `table`, `field`, `relationship: none`, `group_type: group`, `admin_label: ''`, `plugin_id`, `group: 1`
- Full `expose` block with `remember_roles`, even when `exposed: false`
- Full `group_info` block, even when `is_grouped: false`

## Example: excluding a bundle from a Search API view (NYC-842)

Wrong (scalar value, string handler, non-canonical operator):
```yaml
plugin_id: search_api_string
operator: '<>'
value: faq
```

Correct (options handler because `node_bundle` is a bundle field):
```yaml
node_bundle:
  id: node_bundle
  table: search_api_index_content
  field: node_bundle
  relationship: none
  group_type: group
  admin_label: ''
  plugin_id: search_api_options
  operator: not
  value:
    faq: faq
  group: 1
  exposed: false
  expose:
    operator_id: ''
    label: ''
    description: ''
    use_operator: false
    operator: ''
    operator_limit_selection: false
    operator_list: {  }
    identifier: ''
    required: false
    remember: false
    multiple: false
    remember_roles:
      authenticated: authenticated
    reduce: false
  is_grouped: false
  group_info:
    label: ''
    description: ''
    identifier: ''
    optional: true
    widget: select
    multiple: false
    remember: false
    default_group: All
    default_group_multiple: {  }
    group_items: {  }
  reduce_duplicates: false
```

## UI walkthrough (fallback)

When no donor is available, or the hand-edit doesn't verify in the UI, guide the user through making the change in Drupal:

1. Open `/admin/structure/views/view/{view_id}` in the target env (local `ddev launch` usually).
2. Pick the correct **display** from the left column (e.g., `page_1`, not "Default", unless the change should apply to all displays).
3. In the appropriate section (Filter criteria / Fields / Sort criteria / Contextual filters), click **Add**, pick the field, configure it.
4. Confirm "This display" scope (not "All displays") unless the change is global.
5. **Save** the view.
6. In terminal: `ddev drush cex -y`.
7. Review the diff — should only touch the target `views.view.{id}.yml`. Commit.

## Related skills

- `views-development` — creating new views, programmatic execution, hooks
- `commit-message` — commit conventions for config changes
