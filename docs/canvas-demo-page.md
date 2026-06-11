# Top Rated Games (Canvas demo page)

**Stable path:** `/top-rated-games`  ·  **Entity:** `canvas_page` id `2` (content, not config)

This page is *content*, so it is not exported to `config/sync`. Rebuild it from
these steps after a clean `ddev drush cim -y` of the committed config (the View
and its Canvas Block component are config and import automatically).

## Prerequisites (config — imported by `cim`)

- View `views.view.top_rated` with the **`top_rated_block`** block display
  ("Game Cards grid block") — renders board games in the `card` view mode, capped
  to a small page. Added in #15.
- Canvas Block component `canvas.component.block.views_block.top_rated-top_rated_block`
  (`status: true`). Auto-enables on `ddev drush cr` because the `top_rated` View is
  tagged `guardrails`, not `default`. Registered correctly only on **Drupal Canvas
  ≥ 1.5.1** — 1.5.0 silently rejected every Views block from the Library.

## Rebuild steps

1. `/admin/content/pages/add` → **Title** `Top Rated Games`, **Path** `/top-rated-games`.
2. Open the Canvas editor (`/canvas/editor/canvas_page/<id>`) → open the **Library**
   panel → find **Game Cards grid** (Views blocks sort under "Other"; search by
   name) → **drag it into the layout**. It renders live rows immediately.
3. No component settings to change — the block uses the View's defaults.
4. **Publish.** Confirm the live grid at `/top-rated-games` (canonical: `/page/<id>`).

## Verify it's live

Publish or unpublish a board-game node and reload `/top-rated-games` — the grid
reflects the change, proving it's a live View block and not a static snapshot.

---

The **data source** (the `top_rated_block` display) is covered by a kernel test
(see #15 / `build-a-view`). The **page** itself is content authored in the browser
— it has no automated test; this doc is its reproducibility guarantee, and the
config round-trips via `ddev drush cim -y`.