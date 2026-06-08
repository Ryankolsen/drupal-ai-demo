# CLAUDE.md — Guardrails for this codebase

This is the companion demo site for the conference talk **"Guardrails, Not Guesswork: Shipping Drupal Features with Claude Code."** These rules are not optional. They exist to protect config, content, and accessibility — and to make the AI follow *our* architecture, not its training data. **Read this before touching anything.**

## What this is

- **Drupal 11.3** (`drupal/recommended-project`), running under **DDEV**. Run every CLI command through DDEV: `ddev drush …`, `ddev composer …`, `ddev exec …`.
- **Drupal Canvas** (Experience Builder) is the page builder. Components meant for editors must be Canvas-compatible (see SDC rules below).
- Custom theme: **`guardrails`** (Olivero subtheme) at `web/themes/custom/guardrails`. Single Directory Components live in its `components/` directory.
- Custom code lives in `web/modules/custom/`. Contrib lives in `web/modules/contrib/` and is **gitignored** (Composer-managed).
- **Domain:** a board-games catalog. The work is sliced into GitHub issues (see issue #1 and its child slices).

## The meta-goal: harvest skills

The real deliverable of this project is a **starter set of reusable, shareable Claude skills**, not just the site. After completing a unit of work, **harvest the repeatable procedure into a project-local skill** in `.claude/skills/` (e.g. `create-content-type`, `build-a-view`, `seed-content-from-fixture`, `setup-drupal-phpunit`, `patch-contrib-module`). Keep skills **generic and shareable** — not board-game-specific. Use the `write-a-skill` skill for structure. Always prefer doing a task in a way that generalizes into a skill, even when a one-off shortcut would be faster.

## Prime directives

1. **Never edit untracked or contrib files in place.** Anything under `web/core/`, `web/modules/contrib/`, `web/themes/contrib/`, `web/libraries/`, or `vendor/` is Composer-managed and gitignored — your edits will be silently wiped on the next `composer install`. To change contrib behavior, **write a patch** (see *Patching contrib*).

2. **Capture configuration after every model change.** Content types, fields, vocabularies, view modes, and Views are **configuration**, not content. The moment you create or change any of them in the UI or via Drush, export and commit:
   ```
   ddev drush config:export -y    # alias: cex
   ```
   Config is committed to **`/config/sync`** at the repo root (version-controlled, outside the webroot). To reload committed config: `ddev drush config:import -y` (`cim`).
   > `config_sync_directory` is set to `../config/sync` in the committed `web/sites/default/settings.php`. Verify with `ddev drush ev "echo \Drupal\Core\Site\Settings::get('config_sync_directory');"` — it must print `../config/sync`, never `sites/default/files/sync` (that location is gitignored, so config there would not be committed).

3. **Twig is presentational; logic lives in preprocess.** No querying, loading entities, or business logic in `.twig` files. Map data to variables in a `*_preprocess_*()` hook (theme or module), and let the template render. Templates should read like markup.

4. **Content/data is never hand-edited in the database for the demo.** Seed content **deterministically from committed fixtures** via a Drush command so the site can be rebuilt and reviewed from version control. Seeders must be **idempotent** (re-running creates no duplicates).

5. **Accessibility is a requirement, not a polish step.** Target **WCAG AA**: images need meaningful `alt` text, markup is semantic, color contrast meets AA, and interactive elements are keyboard-accessible. This mirrors the real-world constraints the talk is about.

## Single Directory Components (SDC) + Canvas

- SDCs live in `web/themes/custom/guardrails/components/<machine_name>/` as a trio: `<name>.component.yml`, `<name>.twig`, `<name>.css`.
- To be usable in **Canvas**, follow the **`add-canvas-sdc`** skill. The cardinal rule: **every prop needs an `examples:` entry**, enums must never contain an empty value, and links use `format: uri-reference`. A prop without an example is the #1 reason a component silently fails to appear in Canvas.
- Validate a component with the skill's `scripts/validate-canvas-sdc.mjs`.
- The existing `card` component is the canonical reference.

## Patching contrib

To fix a bug or add behavior in a contributed module, **patch it — never edit it in place**:
1. Make the change in `web/modules/contrib/<module>/` locally to develop the fix, then capture it as a patch file committed under e.g. `patches/`.
2. Register the patch via `cweagans/composer-patches` in `composer.json`.
3. Verify it applies on a clean tree: `ddev composer install` re-applies patches.
4. A patch that applies cleanly on a fresh install is the only acceptable end state — the module directory itself stays unmodified in version control.

## Working agreements

- **Plan before building.** Non-trivial work flows PRD → multi-phase plan → tracer-bullet slice. Each slice is a thin, demoable cut through every layer.
- **Verify your work.** After changes: `ddev drush cr` (rebuild cache), confirm the page renders, and run the test suite: `ddev exec phpunit -c phpunit.xml`. Tests live in each module's `tests/src/{Unit,Kernel,Functional}`; kernel tests run on SQLite with no extra DB server. See the `setup-drupal-phpunit` skill.
- **Match the surrounding code** — its naming, structure, and comment density.

## Quick command reference

| Task | Command |
|---|---|
| Drush | `ddev drush <cmd>` |
| Export config | `ddev drush cex -y` |
| Import config | `ddev drush cim -y` |
| Rebuild cache | `ddev drush cr` |
| Enable a module | `ddev drush en <module> -y` |
| Composer | `ddev composer <cmd>` |
| Run tests | `ddev exec phpunit -c phpunit.xml` |
| Run one group | `ddev exec phpunit -c phpunit.xml --group <group>` |
