---
name: build-a-view
description: Build a Drupal View as version-controlled config — a listing page, a block, or a related-items list via a reverse entity-reference contextual filter — then capture it to exported config and verify it with a fast kernel test. Use when the user wants to add/create a View, a listing page, a "related content" or "items by X" list (e.g. all articles by an author, all products in a category), a contextual filter / argument, a Views block, or a reverse-relationship listing.
---

# Build a View the config-managed way

A View is **configuration** (`views.view.<id>.yml`): its base table, displays,
filters, sorts, contextual filters (arguments), style, and row plugin all live
in exported config. The mistake to avoid is clicking it together in the UI and
never exporting — the View then lives only in one database. This skill produces
a View and **captures it to `config/sync`** in one pass, and covers the
**reverse entity-reference** pattern ("list the games by *this* designer").

## Decide the View first

Before authoring, write down:

- **Base entity/table**: usually `node_field_data` (content), `base_field: nid`.
- **What it lists**: filters (e.g. `status = 1`, `type = <bundle>`).
- **Order**: sorts (e.g. a rating field DESC).
- **Displays**: a `page` (has a `path`), a `block` (placeable in a region), or
  both. Each display inherits the `default` display and overrides only what
  differs.
- **Row + style**: render each result as a rendered entity in a view mode
  (`row.type: 'entity:node'`, `options.view_mode: card`) inside a `grid`/`unformatted` style — or as Views fields.
- **Contextual filter?** If the list depends on something in the URL or the
  current page (an author, a term, the current node), that's an **argument**,
  not an exposed filter.

## Anatomy of `views.view.<id>.yml`

```yaml
uuid: <uuid>            # fresh random UUID; the kernel-test trait strips it
langcode: en
status: true
dependencies:
  config: [core.entity_view_mode.node.card, node.type.<bundle>]
  module: [node, user]
id: <id>
label: '<Human label>'
module: views
base_table: node_field_data
base_field: nid
display:
  default:
    display_plugin: default
    display_options:
      pager: { type: full, options: { items_per_page: 12, id: 0 } }
      access: { type: perm, options: { perm: 'access content' } }
      cache: { type: tag, options: {} }
      sorts: { ... }      # see below
      arguments: { ... }  # contextual filters — see the reverse-ref pattern
      filters: { ... }    # status + type, keyed by entity_field
      style: { type: grid, options: { columns: 3 } }
      row: { type: 'entity:node', options: { view_mode: card } }
    cache_metadata: { max-age: -1, contexts: [...], tags: {} }
  page_1:
    display_plugin: page
    display_options: { path: <path> }       # a page has a path
  block_1:
    display_plugin: block
    display_options: { block_description: '<Admin block name>' }
```

Filters and sorts that wrap a real entity field carry `entity_type: node` and
`entity_field: <field_name>`, and are keyed by the column (e.g.
`field_rating_value`, `status`, `type`). Copy the shape from an existing committed
View rather than inventing keys.

## The reverse entity-reference pattern (the useful part)

To list "all A that reference entity B" (games designed by a designer, articles
by an author), you do **not** need a Views relationship. Add a **contextual
filter (argument)** directly on the reference field's data column. For a field
`field_designers` on nodes, the column lives in table `node__field_designers`,
column `field_designers_target_id`:

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

The argument is the **id of the referenced entity** (the designer). Views joins
`node__field_designers` to the base automatically and returns every game whose
`field_designers` contains that id. A single-value field (`field_publisher`)
works identically via `node__field_publisher` / `field_publisher_target_id`.

### Two ways to supply the argument

- **Page display** — put a `%` placeholder in the path: `path: designer/%/games`.
  The URL segment becomes the argument.
- **Block display** — set `default_argument_type: node` ("Content ID from URL").
  Placed on a node's canonical page, the block reads the current node id, so a
  Designer's page can show *that* designer's games with no path argument. Use
  `default_action: empty` so it simply renders nothing off-context.

### Placing the block on the right pages

A block display becomes a `views_block:<view_id>-block_1` plugin. Place it with a
`block.block.<theme>_<id>.yml`, region `content`, weight after the main content,
and a **bundle visibility condition** so it only appears on the intended pages:

```yaml
plugin: 'views_block:games_by_designer-block_1'
visibility:
  'entity_bundle:node':
    id: 'entity_bundle:node'
    context_mapping: { node: '@node.node_route_context:node' }
    bundles: { designer: designer }
```

The bundle condition adds a `config: node.type.designer` dependency — declare it.

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

Execute the View at the data layer — load it, set the argument, assert the
result ids. This catches a wrong table/column or filter without rendering:

```php
$view = \Drupal\views\Views::getView('games_by_designer');
$view->setDisplay('page_1');
$view->setArguments([$designer->id()]);
$view->execute();
$ids = array_map(fn($row) => (int) $row->_entity->id(), $view->result);
$this->assertSame([$expected2, $expected1], $ids); // ordered by the sort
```

Install the committed View from sync in `setUp()` so the test exercises exactly
what ships: `View::create($this->readSyncConfig('views.view.games_by_designer'))->save();`
(see the `setup-drupal-phpunit` skill for the content-model trait). Also assert
the page `path` and that the `status`/bundle filters and argument validation
exclude what they should (unpublished rows, wrong-bundle arguments).

## Gotchas

- **Reverse lookup ≠ relationship.** A contextual filter on
  `<field>_target_id` is simpler than a Views relationship and is the idiomatic
  way to list "items referencing X".
- **Argument validation matters.** Without `validate.type: 'entity:node'` +
  `bundles`, any numeric id (a node of the wrong type, a stale id) leaks rows.
- **`default_action`**: `'not found'` (404) suits a page; `empty` suits a block
  that may render off a matching page.
- **Cache contexts.** A View driven by a URL/route argument needs `url` (or
  `url.path`) in `cache_metadata.contexts`, not just `url.query_args`.
- **Unlimited-cardinality reference** fields have their own `node__<field>`
  table — that's the table the argument lives on.
- A `block` display has no path; a `page` display must have a unique `path`.
