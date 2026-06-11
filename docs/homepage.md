# Homepage (Canvas front page)

**Stable path:** `/home`  ·  **Entity:** `canvas_page` id `3` (content, not config)  ·  **Set as the site front page**

A board-game-themed homepage: a `hero` banner over the existing **Game Cards
grid**. The page is *content*, so it is not exported to `config/sync`. Rebuild it
from these steps after a clean `ddev drush cim -y` of the committed config (the
hero component, the Views block, and the `page.front` setting are all config and
import automatically).

> **Front-page caveat:** `system.site.yml` sets `page.front: /home` (config), but
> the page at `/home` is content. On a fresh clone the import points the front
> page at `/home`, yet `/home` 404s until this page is rebuilt from the steps
> below. **This doc is the reproduction mechanism** — build the page, then `/`
> resolves to it.

## Prerequisites (config — imported by `cim`)

- SDC Canvas component `canvas.component.sdc.guardrails.hero` (`status: true`) —
  the `hero` component (`web/themes/custom/guardrails/components/hero/`). SDCs
  register **disabled**; this one is committed enabled so it appears in the
  Library. Its props default to the homepage copy and a `/games` CTA.
- The banner image `components/hero/images/hero-banner.webp` (committed theme
  asset; referenced by the hero's `background_src`).
- Canvas Block component
  `canvas.component.block.views_block.top_rated-top_rated_block` (`status: true`)
  — the **Game Cards grid** (see `docs/canvas-demo-page.md`). Requires Drupal
  Canvas ≥ 1.5.1.
- `system.site.yml` `page.front: /home` (set after the page is built — see below).

## Rebuild steps

1. `/admin/content/pages/add` → **Title** `Home`, **Path** `/home`.
2. Open the Canvas editor (`/canvas/editor/canvas_page/<id>`) → open the
   **Library** panel.
3. Find **Hero** (search by name) → **drag it to the top** of the layout. Its
   props are pre-filled from the component defaults (heading, tagline, banner
   image, and a "Browse the catalog" → `/games` CTA). Adjust copy if desired.
4. Find **Game Cards grid** (Views blocks sort under "Other"; search by name) →
   **drag it directly below the hero**. It renders live rows immediately.
5. **Publish.**
6. Point the site front page at it (config — capture and commit):

   ```bash
   ddev drush config:set system.site page.front /home -y
   ddev drush cex -y          # writes system.site.yml to ../config/sync
   ```

## Verify it's live

```bash
ddev drush cr
curl -s -o /dev/null -w "%{http_code}\n" "$(ddev exec 'echo $DDEV_PRIMARY_URL')/"
```

`/` returns 200 and shows the hero banner above the games grid. Publish or
unpublish a board-game node and reload — the grid reflects the change, proving
the block is live and not a static snapshot.

---

The **hero component** is covered by a kernel render test
(`SdcComponentRenderTest::testHero`, see `add-canvas-sdc` / `test-module`). The
**data source** for the grid is covered by `TopRatedBlockViewTest`. The **page**
itself is content authored in the browser — it has no automated test; this doc is
its reproducibility guarantee, and the config round-trips via `ddev drush cim -y`.
