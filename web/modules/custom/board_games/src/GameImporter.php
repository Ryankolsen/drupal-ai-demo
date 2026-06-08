<?php

declare(strict_types=1);

namespace Drupal\board_games;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\media\MediaInterface;
use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Default fixture importer for board games.
 *
 * All real import logic lives here so the Drush command can stay a thin
 * wrapper and the importer can be exercised directly from kernel tests.
 */
final class GameImporter implements GameImporterInterface {

  /**
   * The directory (within the module) holding fixture cover images.
   */
  private const IMAGE_FIXTURE_DIR = __DIR__ . '/../fixtures/images';

  /**
   * Destination stream for imported cover images.
   */
  private const IMAGE_DESTINATION_DIR = 'public://board_games';

  private LoggerChannelInterface $logger;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly FileRepositoryInterface $fileRepository,
    private readonly FileSystemInterface $fileSystem,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('board_games');
  }

  /**
   * {@inheritdoc}
   */
  public function import(array $games): array {
    $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0];

    foreach ($games as $game) {
      if (empty($game['bgg_id']) || empty($game['name'])) {
        $this->logger->warning('Skipping fixture row without bgg_id or name: @row', [
          '@row' => print_r($game, TRUE),
        ]);
        $counts['skipped']++;
        continue;
      }

      $node = $this->resolveNode((int) $game['bgg_id']);
      $is_new = $node->isNew();

      $node->set('title', $game['name']);
      $node->set('field_bgg_id', (int) $game['bgg_id']);
      $node->set('field_min_players', (int) ($game['min_players'] ?? 0));
      $node->set('field_max_players', (int) ($game['max_players'] ?? 0));
      $node->set('field_play_time', (int) ($game['play_time'] ?? 0));
      $node->set('field_complexity', $game['complexity'] ?? NULL);
      $node->set('field_rating', $game['rating'] ?? NULL);
      $node->set('status', NodeInterface::PUBLISHED);

      if (!empty($game['description'])) {
        $node->set('field_description', [
          'value' => $game['description'],
          'format' => 'basic_html',
        ]);
      }

      // Many-to-many mechanics, resolve-or-create by name.
      $term_ids = [];
      foreach ($game['mechanics'] ?? [] as $mechanic) {
        $term_ids[] = $this->resolveTerm($mechanic)->id();
      }
      $node->set('field_mechanics', $term_ids);

      // Cover image: resolve-or-create File + Media by source filename.
      if (!empty($game['image'])) {
        $media = $this->resolveCoverMedia($game['image'], $game['name']);
        if ($media) {
          $node->set('field_cover', $media->id());
        }
      }

      $node->save();
      $counts[$is_new ? 'created' : 'updated']++;
    }

    return $counts;
  }

  /**
   * Loads the Board Game node for a BGG id, or returns a new unsaved one.
   */
  private function resolveNode(int $bgg_id): NodeInterface {
    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'board_game')
      ->condition('field_bgg_id', $bgg_id)
      ->range(0, 1)
      ->execute();

    if ($ids) {
      /** @var \Drupal\node\NodeInterface $node */
      $node = $storage->load(reset($ids));
      return $node;
    }

    /** @var \Drupal\node\NodeInterface $node */
    $node = $storage->create(['type' => 'board_game']);
    return $node;
  }

  /**
   * Resolves a Mechanics term by name, creating it if needed.
   */
  private function resolveTerm(string $name): TermInterface {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $existing = $storage->loadByProperties([
      'vid' => 'mechanics',
      'name' => $name,
    ]);
    if ($existing) {
      return reset($existing);
    }

    /** @var \Drupal\taxonomy\TermInterface $term */
    $term = $storage->create(['vid' => 'mechanics', 'name' => $name]);
    $term->save();
    return $term;
  }

  /**
   * Resolves (or creates) an image Media entity for a fixture filename.
   *
   * Dedupes the managed File by destination URI and the Media by the file it
   * references, so re-running never creates duplicate files or media.
   */
  private function resolveCoverMedia(string $filename, string $game_name): ?MediaInterface {
    $source = self::IMAGE_FIXTURE_DIR . '/' . $filename;
    if (!is_file($source)) {
      $this->logger->warning('Cover image not found in fixtures: @file', ['@file' => $filename]);
      return NULL;
    }

    $destination = self::IMAGE_DESTINATION_DIR . '/' . $filename;
    $file = $this->resolveFile($source, $destination);

    // Dedupe Media by the file it references.
    $media_storage = $this->entityTypeManager->getStorage('media');
    $existing = $media_storage->loadByProperties([
      'bundle' => 'image',
      'field_media_image.target_id' => $file->id(),
    ]);
    if ($existing) {
      return reset($existing);
    }

    /** @var \Drupal\media\MediaInterface $media */
    $media = $media_storage->create([
      'bundle' => 'image',
      'name' => $game_name,
      'field_media_image' => [
        'target_id' => $file->id(),
        'alt' => $game_name . ' cover',
      ],
    ]);
    $media->save();
    return $media;
  }

  /**
   * Resolves (or creates) a managed File for a fixture image.
   *
   * Dedupes on destination URI: if a managed file already exists there it is
   * reused rather than re-copied.
   */
  private function resolveFile(string $source, string $destination) {
    $file_storage = $this->entityTypeManager->getStorage('file');
    $existing = $file_storage->loadByProperties(['uri' => $destination]);
    if ($existing) {
      return reset($existing);
    }

    // Ensure the destination directory exists and is writable. prepareDirectory()
    // takes the directory by reference, so it must be a variable.
    $directory = self::IMAGE_DESTINATION_DIR;
    $this->fileSystem->prepareDirectory(
      $directory,
      FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS,
    );

    $data = file_get_contents($source);
    // writeData() writes the bytes and returns a saved managed File entity.
    return $this->fileRepository->writeData($data, $destination, FileExists::Replace);
  }

}
