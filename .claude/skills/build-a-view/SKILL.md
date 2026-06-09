---
name: build-a-view
description: Build a Drupal View as version-controlled config — a listing page, a block, or a related-items list via a reverse entity-reference contextual filter — then capture it to exported config and verify it with a fast kernel test. Use when the user wants to add/create a View, a listing page, a "related content" or "items by X" list (e.g. all articles by an author, all products in a category), a contextual filter / argument, a Views block, or a reverse-relationship listing.
---

# Build a View the config-managed way

A View is **configuration** (`views.view.<id>.yml`): base table, displays,
filters, sorts, contextual filters (arguments), style, and row plugin all live
in exported config. The mistake to avoid is clicking it together in the UI and
never exporting — the View then lives only in one database. This skill produces a
View and **captures it to `config/sync`** in one pass.

## Decide the View first

- **Base entity/table**: usually `node_field_data` (content), `base_field: nid`.
- **What it lists**: filters (e.g. `status = 1`, `type = <bundle>`).
- **Order**: sorts (e.g. a rating field DESC).
- **Displays**: a `page` (has a `path`), a `block` (placeable in a region), or
  both. Each display inherits `default` and overrides only what differs.
- **Row + style**: rendered entity in a view mode (`row.type: 'entity:node'`,
  `options.view_mode: card`) inside a `grid`/`unformatted` style — or Views fields.
- **Contextual filter?** If the list depends on the URL or current page (an
  author, a term, the current node), that's an **argument**, not an exposed filter.

## Author the YAML

The file is `views.view.<id>.yml`. Filters/sorts wrapping a real entity field
carry `entity_type: node` + `entity_field: <field_name>` and are keyed by the
column (e.g. `field_rating_value`, `status`, `type`). Copy the shape from an
existing committed View rather than inventing keys.
→ Full annotated skeleton: [REFERENCE.md](REFERENCE.md#full-viewsviewidyml-anatomy).

## Name every display descriptively (not `page_1` / `Page`)

Drupal hands new displays generic identities (`page_1`/`block_1`, names
`Page`/`Block`) — leave them and the config reads as boilerplate
(`views_block:games_by_designer-block_1` is opaque). **Rename both the display
machine name and its Display name** to describe the display, scoped to its View:
this repo uses `<subject>_page` / `<subject>_block` (e.g. `finder_page`,
`publisher_block`) with a spoken-language Display name. Keep the `default` display
as `default`. Do this **when you author the View** — renaming later means chasing
the block placement, the `view.<view_id>.<display_id>` route, and test
`setDisplay()` calls. → [REFERENCE.md](REFERENCE.md#name-every-display-example--chase-the-references).

## Reverse entity-reference pattern (the useful part)

To list "all A that reference entity B" (games by a designer, articles by an
author), you do **not** need a Views relationship. Add a **contextual filter
(argument)** on the reference field's data column. For `field_designers` on nodes,
the column is table `node__field_designers`, column `field_designers_target_id`:

```yaml
arguments:
  field_designers_target_id:
    id: field_designers_target_id
    table: node__field_designers
    field: field_designers_target_id
    entity_type: node
    entity_field: field_designers
    plugin_id: numeric
    default_action: 'not found'        # no arg → 404 (page) ; 'empty' for a block
    specify_validation: true
    validate:
      type: 'entity:node'              # the arg must be a node…
      fail: 'not found'
    validate_options:
      bundles: { designer: designer }  # …of the right bundle
    break_phrase: false
```

The argument is the **id of the referenced entity**. Views joins
`node__field_designers` to the base automatically. A single-value field
(`field_publisher`) works identically via `node__field_publisher` /
`field_publisher_target_id`. Supply the argument two ways:

- **Page** — put a `%` placeholder in the path: `path: designer/%/games`.
- **Block** — set `default_argument_type: node` ("Content ID from URL") + a bundle
  visibility condition so it reads the current node id on a designer's page; use
  `default_action: empty` so it renders nothing off-context.
  → block placement YAML: [REFERENCE.md](REFERENCE.md#placing-the-reverse-ref-block-on-the-right-pages).

## Exposed filters & facets

An **exposed** filter renders a control so the visitor narrows the list
(`exposed: true` + an `expose:` block; the `identifier` becomes the query key).
Three stock shapes: single-value numeric, a `between` range (two inputs), and a
`taxonomy_index_tid` term facet. → exact shapes:
[REFERENCE.md](REFERENCE.md#exposed-filters-detailed-shapes). When one value must
test two columns (`field_min_players ≤ N ≤ field_max_players`), write a custom
`FilterPluginBase` registered via `hook_views_data()` →
[REFERENCE.md](REFERENCE.md#custom-range-filter-one-value-against-two-columns).

## Capture to config (non-negotiable)

```bash
ddev drush cex -y
git status config/sync
```

Expect `views.view.<id>.yml` and any `block.block.*.yml`. **Isolate your
change**: a fresh `cex` can surface unrelated cosmetic drift — `git restore`
those so the commit contains only the new View. Round-trip with
`ddev drush cim -y` to prove it imports cleanly.

## Verify with a kernel test (no browser, no DB server)

Execute the View at the data layer — load it, `setDisplay()` the descriptive id,
set the argument (`setArguments`) or exposed input (`setExposedInput`),
`execute()`, and assert the result entity ids/labels. Install the committed View
from sync in `setUp()` so the test exercises exactly what ships. → full test
examples + the `taxonomy_index` schema setup:
[REFERENCE.md](REFERENCE.md#kernel-test-examples). (See `test-module` for
the content-model trait.)

## Gotchas

- **A not-submitted exposed filter must be a no-op.** Views still calls
  `query()` on an exposed filter the visitor left blank — stock numeric/term
  filters self-skip an empty stored value, but a *custom* filter must guard:
  return early on an empty/0 value or it silently matches nothing (and then the
  unfiltered `setExposedInput([])` baseline comes back empty too — the tell that
  one filter isn't guarding).
- **Reverse lookup ≠ relationship.** A contextual filter on `<field>_target_id`
  is simpler than a Views relationship and is the idiomatic way to list "items
  referencing X".
- **Argument validation matters.** Without `validate.type: 'entity:node'` +
  `bundles`, any numeric id (a node of the wrong type, a stale id) leaks rows.
- **`default_action`**: `'not found'` (404) suits a page; `empty` suits a block
  that may render off a matching page.
- **Cache contexts.** A View driven by a URL/route argument needs `url` (or
  `url.path`) in `cache_metadata.contexts`, not just `url.query_args`.
- **Unlimited-cardinality reference** fields have their own `node__<field>`
  table — that's the table the argument lives on.
- A `block` display has no path; a `page` display must have a unique `path`.
