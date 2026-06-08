---
name: setup-drupal-phpunit
description: Stand up PHPUnit for a Drupal project and write fast kernel tests for a custom module — the test config, the SQLite-only run setup, and the fixture patterns (installEntitySchema/installSchema/installConfig, plus rebuilding a content model from committed config). Use when the user wants to add tests, set up PHPUnit/phpunit.xml, write a kernel/unit/functional test, or test a service, importer, or View in Drupal.
---

# Set up PHPUnit + kernel tests for a Drupal module

The goal is a test suite that runs from the repo root with **no extra database
server** (SQLite), exercises **real custom code** (services, Views, hooks), and
stays green on a clean checkout via `ddev composer install`. Kernel tests are
the workhorse: they boot a real container and database but no webserver, so they
are fast and cover the data layer where most custom logic lives.

## 1. Add the dev dependency

`drupal/core-dev` brings PHPUnit and Drupal's test base classes. Pin it to the
**same minor as core** so the lock resolves:

```
ddev composer require --dev drupal/core-dev:^11.3 -W
```

`-W` (`--with-all-dependencies`) is usually required — core-dev pins
`phpunit/phpunit` and its `sebastian/*` deps, which conflict with whatever is
already locked unless you let them move. Commit `composer.json` + `composer.lock`.

## 2. Add a root `phpunit.xml`

Place it at the **repo root** (not in `web/`). Drupal's bootstrap resolves the
app root to `web/` via `dirname(__DIR__, 2)` of `web/core/tests/bootstrap.php`,
so the bootstrap path is `web/core/tests/bootstrap.php` regardless of where you
run from. Point the test suites at `web/modules/custom/*` and `web/themes/custom/*`.

Key settings:
- `bootstrap="web/core/tests/bootstrap.php"`
- `SIMPLETEST_DB` = `sqlite://localhost//absolute/path.sqlite` (double slash =
  absolute) so kernel tests need no MySQL.
- `SIMPLETEST_BASE_URL` empty (only functional/BrowserTestBase tests need it).
- `xsi:noNamespaceSchemaLocation` must match the installed PHPUnit major
  (`.../11.5/phpunit.xsd` for PHPUnit 11).
- **Comments cannot contain `--`** — a literal `--testsuite` in an XML comment
  breaks the file. Write it as `(testsuite name)`.

Add `/.phpunit.cache/` to `.gitignore`.

Run: `ddev exec phpunit -c phpunit.xml` (or a single group:
`ddev exec phpunit -c phpunit.xml --group <group>`).

## 3. Test file location and shape

Tests live at `web/modules/custom/<module>/tests/src/{Unit,Kernel,Functional}/`
with namespace `Drupal\Tests\<module>\{Unit,Kernel,Functional}`. Shared helpers
go in `tests/src/Traits/` (`Drupal\Tests\<module>\Traits`).

Use **PHP attributes, not doc-comment annotations** — PHPUnit 11 deprecates
`@group`/`@covers`/`@dataProvider` in doc-comments and Drupal's strict test
runner surfaces every one:

```php
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\CoversClass;

#[Group('mymodule')]
#[CoversClass(MyService::class)]
final class MyServiceTest extends KernelTestBase { ... }
```

## 4. Kernel test boot recipe

In `setUp()`, after `parent::setUp()`:

- `protected static $modules = [...]` — list **every** module explicitly,
  including dependencies (e.g. `system, user, field, text, filter, node,
  taxonomy, file, image, media, views`).
- `installEntitySchema('<id>')` for each **content** entity you touch
  (`user`, `node`, `taxonomy_term`, `file`, `media`). Config entities
  (node type, vocabulary, view) need no schema.
- `installSchema(...)` for plain (non-entity) tables your code hits:
  - `installSchema('node', ['node_access'])` — **required** whenever you save
    nodes or execute a node View, or you get `no such table: node_access`.
  - `installSchema('file', ['file_usage'])` — when managing files/media.
- `installConfig(['system', 'field', 'filter', 'node', ...])` for module
  default config (filter formats, field settings).

### Rebuild a content model from committed config (don't re-declare it)

If the bundle/fields live in `/config/sync` (site config, not the module's
`config/install`), recreate them from the **real committed YAML** instead of
hand-writing field definitions that silently drift. Read each file, strip
`uuid`/`_core`, and `create()` the config entity. Order: field **storages**
before **instances**; a media source field before the media bundle that
references it.

```php
protected function readSyncConfig(string $name): array {
  $data = \Symfony\Component\Yaml\Yaml::parseFile(\Drupal::root() . '/../config/sync/' . $name . '.yml');
  unset($data['uuid'], $data['_core']);
  return $data;
}
// FieldStorageConfig::create($this->readSyncConfig('field.storage.node.field_x'))->save();
// then FieldConfig::create(...), NodeType::create(...), Vocabulary::create(...), MediaType::create(...).
```

Put this in a reusable trait so every test in the module installs the same model.

## 5. Testing a service / importer

The stable boundary is the **service interface**, not the Drush command or hook
that calls it. Pull it from the container and assert its contract:

```php
$importer = $this->container->get('mymodule.importer');
$this->assertSame(['created' => 2, 'updated' => 0, 'skipped' => 0], $importer->import($rows));
// Re-run to prove idempotency: created => 0, and entity counts unchanged.
```

## 6. Testing a View without rendering

Load the committed view config, **execute** it, and inspect `$view->result` —
this tests the query (filters, sorts, access) at the data layer with no theme or
template:

```php
\Drupal\views\Entity\View::create($this->readSyncConfig('views.view.my_view'))->save();
$view = \Drupal\views\Views::getView('my_view');
$view->setDisplay('page_1');
$view->execute();
$ids = array_map(fn($row) => (int) $row->_entity->id(), $view->result);
```

Rendering rows (`$view->render()`) pulls in the theme + SDC and is much heavier;
prefer executing unless you specifically test markup (use a functional test then).

## Gotchas

- **Decimal fields normalise**: a stored `'7.10'` reads back as `'7.1'`. Compare
  as float (`assertEquals(7.1, (float) $value)`), not `assertSame` on the string.
- **Strict runner**: Drupal sets `failOnWarning`/`beStrictAbout*`. Unused data
  providers, risky tests, and doc-comment metadata all fail the build — fix them,
  don't suppress.
- **Setting an unknown field throws**: `$entity->set('field_x', …)` on a content
  entity errors if `field_x` isn't installed, so install every field the code
  under test writes.
