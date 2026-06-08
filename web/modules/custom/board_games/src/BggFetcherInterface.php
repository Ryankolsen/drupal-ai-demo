<?php

declare(strict_types=1);

namespace Drupal\board_games;

/**
 * Fetches board game data from the BoardGameGeek XML API.
 *
 * This is a dev-time tool, deliberately separate from the runtime seed path:
 * the Drush fetch command calls it once to produce the committed JSON fixture,
 * and the site is seeded from that fixture — never from a live API call.
 */
interface BggFetcherInterface {

  /**
   * Fetches games for the given BGG "thing" ids.
   *
   * @param int[] $ids
   *   BoardGameGeek thing ids to fetch.
   *
   * @return array
   *   A list of game rows in the shape the importer consumes (see
   *   \Drupal\board_games\GameImporterInterface::import()). Ids that BGG does
   *   not return (unknown / non-boardgame) are silently omitted.
   */
  public function fetch(array $ids): array;

  /**
   * Parses a BGG "thing" XML document into importer-shaped game rows.
   *
   * Pure transform with no I/O, so it can be unit-tested against a captured
   * API response with no network access.
   *
   * @param string $xml
   *   The XML body of a BGG xmlapi2 /thing?stats=1 response.
   *
   * @return array
   *   A list of game rows.
   */
  public function parseThings(string $xml): array;

}
