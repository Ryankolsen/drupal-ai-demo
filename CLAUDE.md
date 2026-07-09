# CLAUDE.md — Guardrails for this codebase

Companion demo for the talk **"Guardrails, Not Guesswork: Shipping Drupal Features with Claude Code."** These rules are not optional — they protect config, content, and accessibility, and keep the AI on *our* architecture, not its training data.

## What this is

- **Drupal 11.3** under **DDEV** — run every CLI command through DDEV (`ddev drush …`, `ddev composer …`, `ddev exec …`).
- **Drupal Canvas** (Experience Builder) is the page builder — editor-facing components must be Canvas-compatible.
- Custom theme **`guardrails`** (Olivero subtheme) at `web/themes/custom/guardrails`, SDCs in its `components/`; custom code in `web/modules/custom/`.
- **Domain:** a board-games catalog, sliced into GitHub issues (see issue #1 and its children).

## The meta-goal: harvest skills

The real deliverable is a **starter set of reusable, shareable Claude skills**, not just the site. After each unit of work, **harvest the repeatable procedure into a project-local skill** in `.claude/skills/` — kept generic, not board-game-specific. Use `write-a-skill` for structure, `trim-a-skill` once one passes ~100 lines. Prefer doing a task in a way that generalizes into a skill, even when a one-off shortcut is faster.

## Reach for `do-work` first

For any implementation task, start with the **`do-work`** skill — it holds the full "which skill to reach for" decision map and the detailed workflow (plan → implement → validate → commit). The standing rules below apply even outside that flow (e.g. a quick edit in passing):

1. **Never edit untracked or contrib files in place** (`web/core/`, `web/modules/contrib/`, `web/themes/contrib/`, `web/libraries/`, `vendor/`) — Composer-managed and wiped on next install. Patch instead, via `create-patch`.
2. **Capture configuration after every model change** (content types, fields, vocabularies, view modes, Views) — it must land in the committed `../config/sync`, never the gitignored `sites/default/files/sync`.
3. **Twig is presentational only; logic lives in preprocess** — no querying, entity loading, or business logic in `.twig`.
4. **Content is never hand-edited in the database** — seed deterministically from committed fixtures via an idempotent Drush command.
5. **Accessibility is WCAG AA, not a polish step** — alt text, semantic markup, AA contrast, keyboard-accessible interactions.

## SDC + Canvas

SDCs are the default for presentational UI (`card` is the canonical reference). For Canvas compatibility, follow the **`add-canvas-sdc`** skill.

## One-time repo setup

Enable the commit-blocking style/static-analysis hook once per clone: `git config core.hooksPath .githooks`.
