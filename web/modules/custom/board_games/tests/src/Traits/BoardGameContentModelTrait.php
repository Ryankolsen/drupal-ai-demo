<?php

declare(strict_types=1);

namespace Drupal\Tests\board_games\Traits;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\media\Entity\MediaType;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Vocabulary;
use Symfony\Component\Yaml\Yaml;

/**
 * Installs the real, committed Board Game content model into a kernel test.
 *
 * Rather than re-declaring the content type, fields, vocabulary and media
 * bundle inline (which would silently drift from production), this reads the
 * entities straight out of the version-controlled /config/sync directory and
 * recreates them. A kernel test therefore exercises the same model the site
 * ships, captured by the config-export guardrail.
 */
trait BoardGameContentModelTrait {

  /**
   * Absolute path to the committed config sync directory.
   */
  protected function configSyncDir(): string {
    return \Drupal::root() . '/../config/sync';
  }

  /**
   * Reads a config object from sync, stripping install-time-only keys.
   *
   * @param string $name
   *   The config name without the .yml extension, e.g. 'node.type.board_game'.
   *
   * @return array
   *   The decoded config array, minus uuid/_core so create() assigns fresh
   *   identity.
   */
  protected function readSyncConfig(string $name): array {
    $data = Yaml::parseFile($this->configSyncDir() . '/' . $name . '.yml');
    unset($data['uuid'], $data['_core']);
    return $data;
  }

  /**
   * Recreates the Board Game content model from committed config.
   *
   * Order matters: storages before instances, the media source field before
   * the media bundle that references it.
   */
  protected function installBoardGameModel(): void {
    // Mechanics vocabulary (target of field_mechanics).
    Vocabulary::create($this->readSyncConfig('taxonomy.vocabulary.mechanics'))->save();

    // Image media bundle and its source field (target of field_cover).
    FieldStorageConfig::create($this->readSyncConfig('field.storage.media.field_media_image'))->save();
    MediaType::create($this->readSyncConfig('media.type.image'))->save();
    FieldConfig::create($this->readSyncConfig('field.field.media.image.field_media_image'))->save();

    // Board Game content type and its fields (storage then instance).
    NodeType::create($this->readSyncConfig('node.type.board_game'))->save();
    foreach (glob($this->configSyncDir() . '/field.field.node.board_game.*.yml') as $file) {
      $instance = Yaml::parseFile($file);
      $field_name = $instance['field_name'];
      if (!FieldStorageConfig::loadByName('node', $field_name)) {
        FieldStorageConfig::create($this->readSyncConfig('field.storage.node.' . $field_name))->save();
      }
      unset($instance['uuid'], $instance['_core']);
      FieldConfig::create($instance)->save();
    }
  }

}
