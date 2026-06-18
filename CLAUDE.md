# CLAUDE.md — Guardrails for this codebase

Companion demo for the talk **"Guardrails, Not Guesswork: Shipping Drupal Features with Claude Code."** These rules are not optional — they protect config, content, and accessibility, and keep the AI on *our* architecture, not its training data.

## What this is

- **Drupal 11.3** (`drupal/recommended-project`) under **DDEV**. Run every CLI command through DDEV: `ddev drush …`, `ddev composer …`, `ddev exec …`.
- **Drupal Canvas** (Experience Builder) is the page builder. Editor-facing components must be Canvas-compatible (see SDC rules below).
- Custom theme **`guardrails`** (Olivero subtheme) at `web/themes/custom/guardrails`; SDCs live in its `components/`.
- Custom code in `web/modules/custom/`. Contrib in `web/modules/contrib/` is **gitignored** (Composer-managed).
- **Domain:** a board-games catalog, sliced into GitHub issues (see issue #1 and its children).

## The meta-goal: harvest skills

The real deliverable is a **starter set of reusable, shareable Claude skills**, not just the site. After each unit of work, **harvest the repeatable procedure into a project-local skill** in `.claude/skills/` — kept generic, not board-game-specific. Use `write-a-skill` for structure, `trim-a-skill` once one passes ~100 lines. Prefer doing a task in a way that generalizes into a skill, even when a one-off shortcut is faster.

## Skills — reach for these first

When a task matches a project-local skill (`.claude/skills/`), **invoke it before improvising** — it encodes these guardrails as a repeatable procedure. The `do-work` skill holds the full "which skill to reach for" decision map; the skill descriptions (always in context) list each trigger.

## Prime directives

1. **Never edit untracked or contrib files in place.** Anything under `web/core/`, `web/modules/contrib/`, `web/themes/contrib/`, `web/libraries/`, or `vendor/` is Composer-managed and gitignored — edits are wiped on the next `composer install`. To change contrib behavior, **write a patch** (see *Patching contrib*).
2. **Capture configuration after every model change.** Content types, fields, vocabularies, view modes, and Views are **configuration**, not content. Export with `ddev drush cex -y` and commit — it must land in **`../config/sync`** at the repo root, never the gitignored `sites/default/files/sync`. Reload with `ddev drush cim -y`. (`do-work` has the one-line verify command.)
3. **Twig is presentational; logic lives in preprocess.** No querying, entity loading, or business logic in `.twig`. Map data to variables in a `*_preprocess_*()` hook; templates read like markup.
4. **Content is never hand-edited in the database for the demo.** Seed **deterministically from committed fixtures** via a Drush command, so the site rebuilds from version control. Seeders must be **idempotent** (no duplicates on re-run).
5. **Accessibility is a requirement, not a polish step.** Target **WCAG AA**: meaningful `alt` text, semantic markup, AA color contrast, keyboard-accessible interactive elements.

## Single Directory Components (SDC) + Canvas

SDCs are the default for presentational UI — a trio under `web/themes/custom/guardrails/components/<machine_name>/` (`<name>.component.yml` / `.twig` / `.css`); the `card` component is the canonical reference. To work in **Canvas**, follow the **`add-canvas-sdc`** skill: **every prop needs an `examples:` entry** (a prop without one is the #1 reason a component fails to appear), enums never contain an empty value, links use `format: uri-reference`. Validate with the skill's `scripts/validate-canvas-sdc.mjs`.

## Patching contrib

Never edit contrib/core in place (directive 1) — **patch it** via the **`create-patch`** skill: develop the fix in `web/modules/contrib/<module>/`, capture it under `patches/`, register it with `cweagans/composer-patches` in `composer.json`, and prove it re-applies on a clean tree (`ddev composer install`). The module directory stays unmodified in version control.

## Working agreements

- **Plan before building.** Non-trivial work flows PRD → multi-phase plan → tracer-bullet slice (a thin, demoable cut through every layer).
- **Verify your work.** After changes: `ddev drush cr`, confirm the page renders, run `ddev exec composer phpcs` + `ddev exec composer phpstan`, and the suite `ddev exec phpunit -c phpunit.xml`. Tests live in each module's `tests/src/{Unit,Kernel,Functional}`; kernel tests run on SQLite with no extra DB server. See `test-module`.
- **Static analysis runs on every commit.** The committed `.githooks/pre-commit` runs phpcs + phpstan on `web/modules/custom` and `web/themes/custom` and **blocks on failure**. Enable once per clone: `git config core.hooksPath .githooks`. `ddev exec composer phpcbf` auto-fixes most style. Configs: `phpcs.xml.dist` (Drupal + DrupalPractice), `phpstan.neon.dist` (level 1).
- **Match the surrounding code** — its naming, structure, and comment density.

## Quick command reference

| Task | Command |
|---|---|
| Drush | `ddev drush <cmd>` |
| Export / import config | `ddev drush cex -y` / `ddev drush cim -y` |
| Rebuild cache | `ddev drush cr` |
| Enable a module | `ddev drush en <module> -y` |
| Composer | `ddev composer <cmd>` |
| Lint / auto-fix style | `ddev exec composer phpcs` / `ddev exec composer phpcbf` |
| Static analysis | `ddev exec composer phpstan` |
| Run tests / one group | `ddev exec phpunit -c phpunit.xml` / `… --group <group>` |
