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
  differs. **Name every display descriptively** — see below.
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
  <descriptive>_page:                        # NOT page_1 — see "Name every display"
    display_plugin: page
    display_title: '<Descriptive> page'
    display_options: { path: <path> }       # a page has a path
  <descriptive>_block:                       # NOT block_1
    display_plugin: block
    display_title: '<Descriptive> block'
    display_options: { block_description: '<Admin block name>' }
```

Filters and sorts that wrap a real entity field carry `entity_type: node` and
`entity_field: <field_name>`, and are keyed by the column (e.g.
`field_rating_value`, `status`, `type`). Copy the shape from an existing committed
View rather than inventing keys.

## Name every display descriptively (not `page_1` / `Page`)

Drupal hands new displays generic identities — machine name `page_1` / `block_1`
and **Display name** `Page` / `Block`. Leave them and the config reads as
boilerplate: a site with four Views all referring to `page_1` tells you nothing,
and a block placement `views_block:games_by_designer-block_1` is opaque. **Rename
both the display machine name and its Display name to describe what the display
is** — scoped to the View it lives in, since display ids only need to be unique
within their View:

```yaml
display:
  default:                     # keep the default display as `default` — it is special
    id: default
    display_title: Default
  designer_page:               # was page_1 — machine name describes the display
    id: designer_page          # the inner `id:` must equal the YAML key
    display_title: 'Designer games page'   # was "Page" — human Display name
    display_plugin: page
  designer_block:              # was block_1
    id: designer_block
    display_title: 'Designer games block'  # was "Block"
    display_plugin: block
```

Convention used in this repo: `<subject>_page` / `<subject>_block` for the
machine name (e.g. `finder_page`, `top_rated_page`, `publisher_block`), and a
spoken-language Display name (`Finder page`, `Top rated page`). The View id
already carries the subject, so the display name only needs to disambiguate the
display variant.

**Renaming a display is a rename, not just a relabel — chase the references:**

- The YAML **key** under `display:` and its inner `id:` must match.
- A **block placement** names the display: `plugin` /`settings.id`
  `views_block:<view_id>-<display_id>` in `block.block.*.yml` must use the new id.
- The auto-generated **route** is `view.<view_id>.<display_id>` — any
  `Url::fromRoute()` / `{{ url(...) }}` referencing it must change (the *path*
  is unaffected).
- **Kernel tests** call `$view->setDisplay('<display_id>')` — update them.

Do this when you author the View; renaming later means touching every reference.

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

## Exposed filters & facets (the faceted-finder part)

An **exposed** filter renders a control on the page so a visitor narrows the
list themselves. Set `exposed: true` and an `expose:` block (give it a clean
`identifier` — that becomes the query-string key, e.g. `?max_time=45`). Lock the
operator (`use_operator: false`, fixed `operator:`) when the visitor should only
supply a value, not choose a comparison.

- **Single-value numeric** (`plugin_id: numeric`, `operator: '<='`) — one input,
  e.g. "max play time". Value shape is `{ min: '', max: '', value: '' }`.
- **Range** (`plugin_id: numeric`, `operator: between`) — renders *two* inputs
  (min/max), e.g. a complexity band. Same value shape; `between` reads min+max.
- **Taxonomy facet** (`plugin_id: taxonomy_index_tid`, `table: taxonomy_index`) —
  a term dropdown. Scope it to one vocabulary with `vid: <machine_name>` +
  `limit: true`, `type: select`. It filters via the `taxonomy_index` table,
  which Drupal maintains on node save (so the node's term references are indexed
  automatically — no relationship needed). Two such filters (e.g. Mechanic and
  Category) coexist; each joins `taxonomy_index` on its own alias and AND-narrows.

### Custom range filter: one value against two columns

When "supports N players" means `field_min_players ≤ N ≤ field_max_players`, no
stock filter fits — two numeric filters would expose two inputs and can't relate
them. Write a tiny **`FilterPluginBase`** plugin that takes one value and adds
both bounds, then register it via `hook_views_data()` so config can name it:

```php
// board_games.module
function board_games_views_data(): array {
  $data['node_field_data']['board_game_player_count'] = [
    'title' => t('Supported player count'),
    'filter' => ['id' => 'board_games_player_count'],
  ];
  return $data;
}
```

```php
#[ViewsFilter("board_games_player_count")]
final class PlayerCount extends FilterPluginBase {
  public $no_operator = TRUE;                 // single value, no operator UI
  protected function valueForm(&$form, $fs) {
    $form['value'] = ['#type' => 'number', '#min' => 1, '#default_value' => $this->value];
  }
  public function query(): void {
    // Views wraps a non-multiple exposed value in a 1-element array; an
    // empty/0/'' value must be treated as "no constraint" or the finder
    // matches nothing. Guard BEFORE touching the query.
    $v = (int) (is_array($this->value) ? reset($this->value) : $this->value);
    if ($v < 1) { return; }
    $min = $this->query->ensureTable('node__field_min_players', $this->relationship);
    $max = $this->query->ensureTable('node__field_max_players', $this->relationship);
    $this->query->addWhere($this->options['group'], "$min.field_min_players_value", $v, '<=');
    $this->query->addWhere($this->options['group'], "$max.field_max_players_value", $v, '>=');
  }
}
```

`ensureTable()` joins each field's dedicated table (their views data defines the
join back to `node_field_data`). In the view config the filter is just
`{ table: node_field_data, field: board_game_player_count, plugin_id: board_games_player_count }`.
Its view then declares `dependencies.module: [board_games]`.

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

Execute the View at the data layer — load it, set the argument or exposed input,
assert the result ids. This catches a wrong table/column or filter without
rendering:

```php
$view = \Drupal\views\Views::getView('games_by_designer');
$view->setDisplay('designer_page');                // the descriptive display id
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

For an **exposed** filter, drive it with `setExposedInput()` and assert the
matching set (canonicalize when order is incidental):

```php
$view = \Drupal\views\Views::getView('game_finder');
$view->setDisplay('finder_page');                  // the descriptive display id
$view->setExposedInput(['players' => 4]);          // ?players=4
$view->execute();
$titles = array_map(fn($r) => $r->_entity->label(), $view->result);
$this->assertEqualsCanonicalizing(['Party', 'Mid'], $titles);
```

`taxonomy_index_tid` filters need the index table; `installEntitySchema('taxonomy_term')`
creates `taxonomy_index` (it lives in `TermStorageSchema`), and
`installConfig(['taxonomy'])` enables the on-save indexing — no separate
`installSchema('taxonomy', ['taxonomy_index'])` (there is no such schema hook).

## Gotchas

- **A not-submitted exposed filter must be a no-op.** Views still calls
  `query()` on an exposed filter the visitor left blank — stock numeric/term
  filters self-skip an empty stored value, but a *custom* filter must guard:
  return early on an empty/0 value or it silently matches nothing (and then the
  unfiltered `setExposedInput([])` baseline comes back empty too — the tell that
  one filter isn't guarding).
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
