<?php

declare(strict_types=1);

namespace Drupal\board_games;

/**
 * Imports board games from parsed fixture data into Drupal entities.
 */
interface GameImporterInterface {

  /**
   * Imports a list of games idempotently.
   *
   * Board Game nodes are deduplicated on field_bgg_id, Mechanics terms by
   * name, and cover File/Media by source filename, so re-running creates no
   * duplicates.
   *
   * @param array $games
   *   A list of game arrays. Each game supports the keys: name (string,
   *   required), bgg_id (int, required), min_players, max_players, play_time
   *   (int), complexity, rating (float|string), description (string),
   *   mechanics (string[]), categories (string[]), designers (string[],
   *   resolved-or-created as Designer nodes), publisher (string, resolved-or-
   *   created as a Publisher node), and image (string filename in the fixtures
   *   images directory). Unknown keys (e.g. min_age, which has no field yet)
   *   are ignored, so the fixture can carry data ahead of the content model.
   *
   * @return array
   *   Associative array of counts with keys 'created', 'updated', 'skipped'.
   */
  public function import(array $games): array;

}
