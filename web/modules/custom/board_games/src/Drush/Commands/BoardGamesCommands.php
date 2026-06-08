<?php

declare(strict_types=1);

namespace Drupal\board_games\Drush\Commands;

use Drupal\board_games\GameImporterInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for the Board Games demo.
 *
 * Thin wrapper around the importer service: it only reads the committed
 * fixture and delegates all logic to \Drupal\board_games\GameImporter.
 */
final class BoardGamesCommands extends DrushCommands {

  use AutowireTrait;

  /**
   * Path to the committed fixture, relative to this command's module root.
   */
  private const FIXTURE = __DIR__ . '/../../../fixtures/games.json';

  public function __construct(
    private readonly GameImporterInterface $importer,
  ) {
    parent::__construct();
  }

  /**
   * Seeds board games idempotently from the committed JSON fixture.
   */
  #[CLI\Command(name: 'board_games:seed', aliases: ['bg:seed'])]
  #[CLI\Usage(name: 'drush bg:seed', description: 'Seed board games from fixtures/games.json.')]
  public function seed(): int {
    $path = self::FIXTURE;
    if (!is_file($path)) {
      $this->logger()->error('Fixture not found at {path}', ['path' => $path]);
      return self::EXIT_FAILURE;
    }

    $games = json_decode((string) file_get_contents($path), TRUE);
    if (!is_array($games)) {
      $this->logger()->error('Fixture is not valid JSON: {path}', ['path' => $path]);
      return self::EXIT_FAILURE;
    }

    $counts = $this->importer->import($games);

    $this->logger()->success(dt(
      'Seeded board games: @created created, @updated updated, @skipped skipped.',
      [
        '@created' => $counts['created'],
        '@updated' => $counts['updated'],
        '@skipped' => $counts['skipped'],
      ],
    ));

    return self::EXIT_SUCCESS;
  }

}
