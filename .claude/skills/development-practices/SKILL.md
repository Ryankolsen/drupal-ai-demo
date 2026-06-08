---
name: development-practices
description: Development guardrails for this Drupal 11 + DDEV board-games demo — which skill to reach for, Git/config discipline, Twig & SDC front-end rules, caching, and a decision checklist. Use when implementing features, making code changes, or planning work in this repo.
---

# Development Practices

These complement the repo-root `CLAUDE.md` (the authoritative guardrails). When a
task matches one of the skills below, delegate to it first.

## Delegate to these skills first

| Task | Skill |
|------|-------|
| Creating a content type, bundle, or vocabulary (with its fields) | `create-content-type` |
| Adding a field to an *existing* bundle | `adding-fields` |
| Building a new View — listing page, block, or related-items list | `build-a-view` |
| Editing an existing View's YAML | `editing-views` |
| Building/registering a Single Directory Component for Canvas | `add-canvas-sdc` |
| Seeding content from a committed fixture | `seed-content-from-fixture` |
| Setting up PHPUnit / writing kernel tests | `setup-drupal-phpunit` |
| Patching a contrib or core file | `create-patch` |
| Reviewing a diff before a PR | `drupal-code-review` |
| Updating Drupal core | `drupal-core-update` |

## Environment

- **Drupal 11.3** under **DDEV** — run every CLI command through DDEV
  (`ddev drush …`, `ddev composer …`, `ddev exec …`).
- Custom code: `web/modules/custom/`. Custom theme: `guardrails` (Olivero
  subtheme) at `web/themes/custom/guardrails`. SDCs live in its `components/`.
- Contrib/core under `web/core`, `web/modules/contrib`, etc. are
  Composer-managed and gitignored — **never edit in place; patch instead**
  (`create-patch`).

## Git

- Never force-push; never skip hooks (`--no-verify`).
- Commit only when asked. `git push` is blocked by a local hook — hand pushes
  back to the user.
- Capture config after any model change (see below) and commit it with the code.

## Config discipline

Content types, fields, vocabularies, view modes, and Views are **configuration**.
After changing any of them, export and commit:

```bash
ddev drush cex -y          # exports to /config/sync
git status config/sync     # isolate your change; git restore unrelated drift
ddev drush cim -y          # round-trip to prove it imports cleanly
```

## Twig rules

**Twig = presentation only. Logic → preprocess.** No querying, loading entities,
or business logic in `.twig`.

1. **Render complete fields** — never drill into `content.field[0]['#markup']`; bypasses cache + access checks
2. **Use `|without` when excluding fields** — `{{ content|without('body') }}` preserves cache metadata
3. **Never use `|raw`** — disables escaping → XSS. Use `{{ content.field_text }}`
4. **No logic in templates** — move to a `*_preprocess_*()` hook
5. **Isolate includes** — `{{ include('guardrails:card', {heading: title}, with_context = false) }}`
6. **Use the Attribute object** — `attributes.addClass([...])`, not string-built attributes
7. **Bubble entity cache tags** — `{% set _c = content.field_image|render %}` before reading entity values directly

## Single Directory Components (preferred)

SDCs are the **default** way to build presentational UI in this repo — prefer an
SDC over an ad-hoc theme template or a custom render element.

- A component is a trio under `web/themes/custom/guardrails/components/<name>/`:
  `<name>.component.yml`, `<name>.twig`, `<name>.css`. The `card`/`game_card`
  components are the canonical references.
- Map entity fields to component props in a preprocess hook, then forward them:
  `{{ include('guardrails:game_card', game_card) }}`. Keep the field→prop mapping
  out of Twig.
- For a component to work in **Canvas**, follow `add-canvas-sdc` (every prop needs
  an `examples:` entry; enums never empty; links use `format: uri-reference`).

## Caching

Use cache metadata APIs — never inline tag strings like
`$build['#cache']['tags'][] = 'node:' . $id`.

- Services: expose `getCacheMetadata(): CacheableMetadata`
- Consumers: `addCacheableDependency($entity)` then `applyTo($build)`
- Blocks: declare tags in `getCacheTags()` via `Cache::mergeTags()`

## Code style & static analysis

`drupal/core-dev` provides the tooling (binaries in `vendor/bin`):

```bash
ddev exec vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/custom
ddev exec vendor/bin/phpstan analyse web/modules/custom
```

## Verify your work

```bash
ddev drush cr                       # rebuild cache
ddev exec phpunit -c phpunit.xml    # run the suite (kernel tests need no DB server)
```

## Composer

Always use Composer for dependencies; commit both `composer.json` and
`composer.lock`. Add a `vcs`/`package` repository entry for sources not on
Packagist. Never hand-edit anything under `web/core` or `web/modules/contrib`.

## Decision checklist

1. Logic in a small, named, testable unit (hooks stay thin)?
2. Presentational layer built as an SDC where it makes sense?
3. Config captured to `/config/sync` and round-tripped?
4. Accessible (WCAG AA) — alt text, semantics, contrast, keyboard?
5. Matches Drupal best practices and the surrounding code?
