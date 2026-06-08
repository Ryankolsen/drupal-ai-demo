---
name: create-content-type
description: Scaffold a Drupal content type (or any fieldable bundle) with its fields and a supporting taxonomy vocabulary, then capture the result to exported config. Use when the user wants to add/create a content type, node type, entity bundle, fields, or a vocabulary, and have the model version-controlled as config. Covers field storage vs. field instance, entity-reference handler settings, view modes, and the config-capture workflow.
---

# Create a content type the config-managed way

A content type in Drupal is **configuration**, not content: the bundle, every
field, the form/display arrangement, and any vocabulary it references must end up
in exported config so the model is version-controlled and reproducible. The
mistake to avoid is clicking it together in the UI and never exporting — the work
then lives only in one database.

This skill produces a content type + fields + vocabulary and **captures it to
`config/sync`** in one pass.

## Decide the model first

Before creating anything, write down:

- **Bundle**: machine name (`a-z0-9_`, e.g. `board_game`) and human label.
- **Fields**: for each, the machine name (`field_*`), field **type**,
  **cardinality** (1 or unlimited), whether it's **required**, and any
  type-specific settings (decimal precision/scale, entity-reference target).
- **Vocabularies**: any taxonomy the content references (machine name + label).
  Decide if terms are author-managed or **resolved-or-created by an importer**.

Common field types: `integer`, `decimal` (`settings.precision`/`scale`),
`string`, `text_long`, `boolean`, `datetime`, `link`, `image`,
`entity_reference` (`settings.target_type`, instance
`handler_settings.target_bundles`).

> Field storage vs. field instance: **storage** (`field.storage.*`) defines the
> field once per entity type (type + cardinality, shared across bundles);
> **instance** (`field.field.*`) attaches it to one bundle with a label,
> required flag, and handler settings. You create both.

## Build it (two routes — pick one)

### Route A — Drush / Entity API (scriptable, reproducible, CI-friendly)

Author a short PHP script and run it with `drush php:script`. This is
deterministic and reviewable, and the same script can be re-run safely if you
guard each create with an existence check. Skeleton:

```php
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;

// 1. Vocabulary (if referenced).
if (!Vocabulary::load('my_vocab')) {
  Vocabulary::create(['vid' => 'my_vocab', 'name' => 'My Vocab'])->save();
}

// 2. Bundle.
if (!NodeType::load('my_type')) {
  NodeType::create(['type' => 'my_type', 'name' => 'My Type'])->save();
}

// 3. Each field: storage once, instance per bundle.
if (!FieldStorageConfig::loadByName('node', 'field_example')) {
  FieldStorageConfig::create([
    'field_name' => 'field_example',
    'entity_type' => 'node',
    'type' => 'integer',          // or decimal/string/entity_reference/...
    'cardinality' => 1,           // -1 for unlimited
    'settings' => [],             // e.g. ['precision' => 3, 'scale' => 2] for decimal,
  ])->save();                     //      ['target_type' => 'taxonomy_term'] for refs
}
if (!FieldConfig::loadByName('node', 'my_type', 'field_example')) {
  FieldConfig::create([
    'field_name' => 'field_example',
    'entity_type' => 'node',
    'bundle' => 'my_type',
    'label' => 'Example',
    'required' => TRUE,
    'settings' => [],             // entity_reference: ['handler' => 'default:taxonomy_term',
  ])->save();                     //   'handler_settings' => ['target_bundles' => ['my_vocab' => 'my_vocab']]]
}

// 4. (Optional) a dedicated view mode, e.g. 'card'.
if (!EntityViewMode::load('node.card')) {
  EntityViewMode::create(['id' => 'node.card', 'targetEntityType' => 'node', 'label' => 'Card'])->save();
}

// 5. Place fields on the form + view displays with setComponent()/removeComponent().
//    Load or create EntityFormDisplay::load('node.my_type.default') and
//    EntityViewDisplay::load("node.my_type.$mode"), then ->save().
```

Run it:

```bash
ddev drush php:script path/to/setup.php
```

### Route B — Admin UI

Structure → Content types → Add content type, then add each field. Use this when
you want to see widget/formatter choices interactively. The fields and displays
still become config — you must export afterward (next step).

## Capture to config (non-negotiable)

Export and review the diff so only the intended entities are committed:

```bash
ddev drush cex -y
git status config/sync
```

Expect new files: `node.type.<bundle>.yml`, `field.storage.node.<field>.yml`,
`field.field.node.<bundle>.<field>.yml`, `core.entity_form_display.*`,
`core.entity_view_display.*`, `core.entity_view_mode.*`,
`taxonomy.vocabulary.<vid>.yml`, plus an updated `core.extension.yml` if you
enabled a module.

> **Isolate your change.** A fresh `cex` can surface pre-existing, cosmetic
> drift (key reordering, `1` vs `true`, an environment-only setting) in
> unrelated files. `git restore` those so the commit contains only the new
> content type and its fields. Verify the result is self-consistent with a
> round-trip: `ddev drush cim -y` should import with no errors.

## Verify

- `ddev drush field:info node <bundle>` lists every field with the right type,
  cardinality, and required flag.
- Create one entity (UI or a seed command) and confirm each field saves and
  renders.
- `ddev drush cim -y` re-imports the exported config cleanly on a synced site.

## Gotchas

- **Decimal precision/scale** live in field **storage** settings, not the
  instance.
- **Entity-reference** needs `settings.target_type` on the storage and
  `handler` + `handler_settings.target_bundles` on the instance, or the
  reference autocomplete returns everything.
- **Unlimited cardinality** is `-1`, not `0`.
- Renaming or retyping a field after data exists is destructive — decide the
  type up front.
- Removing a field instance then re-exporting also removes its storage only when
  no other bundle uses it; check the diff.
