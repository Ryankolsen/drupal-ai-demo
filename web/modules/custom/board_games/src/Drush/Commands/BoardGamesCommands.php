<?php

declare(strict_types=1);

namespace Drupal\board_games\Drush\Commands;

use Drupal\board_games\BggFetcherInterface;
use Drupal\board_games\GameImporterInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for the Board Games demo.
 *
 * Two commands with a deliberate separation of concerns:
 * - bg:seed (runtime) reads the committed fixture and delegates all import
 *   logic to \Drupal\board_games\GameImporter. This is what runs on stage.
 * - bg:fetch (dev-time) calls the BGG XML API and (re)writes that fixture. It
 *   is never on the runtime path, so the demo never depends on a live API.
 */
final class BoardGamesCommands extends DrushCommands {

  use AutowireTrait;

  /**
   * Path to the committed fixture, relative to this command's module root.
   */
  private const FIXTURE = __DIR__ . '/../../../fixtures/games.json';

  /**
   * Curated list of BGG thing ids used as the default fetch set.
   *
   * A spread of widely-known modern classics, gateway games and heavier
   * titles — enough variety to exercise faceting (player count, time,
   * complexity, mechanics, categories). Override with --ids to refresh a
   * different set.
   */
  private const CURATED_IDS = [
    13, 9209, 30549, 822, 230802, 174430, 167791, 169786, 12333, 31260,
    68448, 36218, 178900, 84876, 102794, 2651, 224517, 266192, 162886, 199792,
    124361, 173346, 163412, 148228, 312484, 342942, 220308, 237182, 161936, 187645,
    182028, 177736, 193738, 183394, 157354, 284083, 316554, 295947, 201808, 170216,
    205059, 146021, 233078, 115746, 521, 93, 3076, 110327, 34635, 155426,
    204583, 54043,
  ];

  public function __construct(
    private readonly GameImporterInterface $importer,
    private readonly BggFetcherInterface $fetcher,
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

  /**
   * Fetches games from the BGG XML API and (re)writes the committed fixture.
   *
   * Dev-time only — run this once when you want to refresh the fixture, then
   * commit the result. The runtime seed (bg:seed) never calls the API.
   */
  #[CLI\Command(name: 'board_games:fetch', aliases: ['bg:fetch'])]
  #[CLI\Option(name: 'ids', description: 'Comma-separated BGG thing ids to fetch. Defaults to the curated list.')]
  #[CLI\Option(name: 'out', description: 'Path to write the JSON fixture. Defaults to the committed fixtures/games.json.')]
  #[CLI\Usage(name: 'drush bg:fetch', description: 'Refresh fixtures/games.json from the curated BGG id list.')]
  #[CLI\Usage(name: 'drush bg:fetch --ids=13,822', description: 'Fetch only the given games.')]
  public function fetch(array $options = ['ids' => NULL, 'out' => NULL]): int {
    $ids = $options['ids'] !== NULL
      ? array_filter(array_map('intval', explode(',', (string) $options['ids'])))
      : self::CURATED_IDS;

    if (!$ids) {
      $this->logger()->error('No valid BGG ids to fetch.');
      return self::EXIT_FAILURE;
    }

    $this->logger()->notice(dt('Fetching @count games from BoardGameGeek…', ['@count' => count($ids)]));
    $games = $this->fetcher->fetch($ids);

    if (!$games) {
      $this->logger()->error('BGG returned no games; fixture left unchanged.');
      return self::EXIT_FAILURE;
    }

    $out = $options['out'] ?? self::FIXTURE;
    $json = json_encode($games, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === FALSE || file_put_contents($out, $json . "\n") === FALSE) {
      $this->logger()->error('Failed to write fixture to {path}', ['path' => $out]);
      return self::EXIT_FAILURE;
    }

    $this->logger()->success(dt('Wrote @count games to @path.', [
      '@count' => count($games),
      '@path' => $out,
    ]));

    return self::EXIT_SUCCESS;
  }

}
