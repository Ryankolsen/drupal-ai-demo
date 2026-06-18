# Do Work — Reference

Detailed enumerations pulled out of SKILL.md. The headline rules live there; the
full lists and exact commands live here.

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
