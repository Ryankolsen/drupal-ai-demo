# CLAUDE.md — Guardrails for this codebase

This is the companion demo site for the conference talk **"Guardrails, Not Guesswork: Shipping Drupal Features with Claude Code."** These rules are not optional. They exist to protect config, content, and accessibility — and to make the AI follow *our* architecture, not its training data. **Read this before touching anything.**

## What this is

- **Drupal 11.3** (`drupal/recommended-project`), running under **DDEV**. Run every CLI command through DDEV: `ddev drush …`, `ddev composer …`, `ddev exec …`.
- **Drupal Canvas** (Experience Builder) is the page builder. Components meant for editors must be Canvas-compatible (see SDC rules below).
- Custom theme: **`guardrails`** (Olivero subtheme) at `web/themes/custom/guardrails`. Single Directory Components live in its `components/` directory.
- Custom code lives in `web/modules/custom/`. Contrib lives in `web/modules/contrib/` and is **gitignored** (Composer-managed).
- **Domain:** a board-games catalog. The work is sliced into GitHub issues (see issue #1 and its child slices).

## The meta-goal: harvest skills

The real deliverable of this project is a **starter set of reusable, shareable Claude skills**, not just the site. After completing a unit of work, **harvest the repeatable procedure into a project-local skill** in `.claude/skills/` (e.g. `create-content-type`, `build-a-view`, `seed-content-from-fixture`, `test-module`, `create-patch`). Keep skills **generic and shareable** — not board-game-specific. Use the `write-a-skill` skill for structure, and `trim-a-skill` to slim one that has grown past ~100 lines. Always prefer doing a task in a way that generalizes into a skill, even when a one-off shortcut would be faster.

## Skills — reach for these first

Project-local skills live in `.claude/skills/`. When a task matches one, **invoke the skill before improvising** — it encodes these guardrails as a repeatable procedure. `development-practices` holds the full "which skill to reach for" decision map.

- **Model & config:** `create-content-type` (bundle + fields + vocab), `adding-fields` (one field on an existing bundle), `build-a-view` / `editing-views` (Views as config)
- **Front-end:** `add-canvas-sdc` (Canvas-ready SDC — see SDC rules below)
- **Content:** `seed-content-from-fixture` (idempotent importer from a committed fixture)
- **Quality:** `test-module` (PHPUnit / kernel tests), `drupal-code-review` (pre-PR review)
- **Contrib & core:** `create-patch` (patch, never edit in place), `drupal-core-update` (security release)
- **Process:** `build-feature` (tracer-bullet slice), `to-prd`, `to-issues`
- **Meta:** `write-a-skill`, `trim-a-skill`, `development-practices`

## Prime directives

1. **Never edit untracked or contrib files in place.** Anything under `web/core/`, `web/modules/contrib/`, `web/themes/contrib/`, `web/libraries/`, or `vendor/` is Composer-managed and gitignored — your edits will be silently wiped on the next `composer install`. To change contrib behavior, **write a patch** (see *Patching contrib*).

2. **Capture configuration after every model change.** Content types, fields, vocabularies, view modes, and Views are **configuration**, not content. After any such change, export and commit it with `ddev drush cex -y` — it writes to **`/config/sync`** at the repo root (version-controlled, outside the webroot). Reload with `ddev drush cim -y`. Config must land in `../config/sync`, never the gitignored `sites/default/files/sync`; `development-practices` has the one-line verify command.

3. **Twig is presentational; logic lives in preprocess.** No querying, loading entities, or business logic in `.twig` files. Map data to variables in a `*_preprocess_*()` hook (theme or module), and let the template render. Templates should read like markup.

4. **Content/data is never hand-edited in the database for the demo.** Seed content **deterministically from committed fixtures** via a Drush command so the site can be rebuilt and reviewed from version control. Seeders must be **idempotent** (re-running creates no duplicates).

5. **Accessibility is a requirement, not a polish step.** Target **WCAG AA**: images need meaningful `alt` text, markup is semantic, color contrast meets AA, and interactive elements are keyboard-accessible. This mirrors the real-world constraints the talk is about.

## Single Directory Components (SDC) + Canvas

SDCs are the default for presentational UI — a trio under `web/themes/custom/guardrails/components/<machine_name>/` (`<name>.component.yml` / `.twig` / `.css`); the `card` component is the canonical reference. To work in **Canvas**, follow the **`add-canvas-sdc`** skill: **every prop needs an `examples:` entry** (a prop without one is the #1 reason a component fails to appear), enums never contain an empty value, links use `format: uri-reference`. Validate with the skill's `scripts/validate-canvas-sdc.mjs`.

## Patching contrib

Never edit contrib/core in place (directive 1) — **patch it** via the **`create-patch`** skill: develop the fix in `web/modules/contrib/<module>/`, capture it under `patches/`, register it with `cweagans/composer-patches` in `composer.json`, and prove it re-applies on a clean tree (`ddev composer install`). The module directory itself stays unmodified in version control.

## Working agreements

- **Plan before building.** Non-trivial work flows PRD → multi-phase plan → tracer-bullet slice. Each slice is a thin, demoable cut through every layer.
- **Verify your work.** After changes: `ddev drush cr` (rebuild cache), confirm the page renders, run static analysis (`ddev exec composer phpcs` and `ddev exec composer phpstan`), and run the test suite: `ddev exec phpunit -c phpunit.xml`. Tests live in each module's `tests/src/{Unit,Kernel,Functional}`; kernel tests run on SQLite with no extra DB server. See the `test-module` skill.
- **Static analysis runs on every commit.** A committed pre-commit hook (`.githooks/pre-commit`) runs phpcs + phpstan on `web/modules/custom` and `web/themes/custom` and **blocks the commit on failure**. Enable it once per clone with `git config core.hooksPath .githooks`. Style: `ddev exec composer phpcbf` auto-fixes most violations. Configs: `phpcs.xml.dist` (Drupal + DrupalPractice) and `phpstan.neon.dist` (level 1).
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
| Lint (phpcs) | `ddev exec composer phpcs` |
| Auto-fix style | `ddev exec composer phpcbf` |
| Static analysis (phpstan) | `ddev exec composer phpstan` |
| Run tests | `ddev exec phpunit -c phpunit.xml` |
| Run one group | `ddev exec phpunit -c phpunit.xml --group <group>` |
