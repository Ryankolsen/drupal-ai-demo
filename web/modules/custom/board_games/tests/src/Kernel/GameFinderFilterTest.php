<?php

declare(strict_types=1);

namespace Drupal\Tests\board_games\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\board_games\Traits\BoardGameContentModelTrait;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for the Game Finder's exposed filters.
 *
 * Each filter is exercised by executing the committed view with a given
 * exposed input and asserting the matching games. The headline case is the
 * custom player-count filter, which turns one exposed value N into the range
 * test field_min_players ≤ N ≤ field_max_players — the relationship two plain
 * numeric filters cannot express. The view is executed (not rendered), so the
 * tests stay at the query layer with no theme or SDC.
 */
#[Group('board_games')]
final class GameFinderFilterTest extends KernelTestBase {

  use BoardGameContentModelTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'taxonomy',
    'file',
    'image',
    'media',
    'views',
    'board_games',
  ];

  /**
   * Category terms keyed by name, for referencing and filtering by tid.
   *
   * @var array<string, \Drupal\taxonomy\TermInterface>
   */
  private array $categories = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('node', ['node_access']);
    // installEntitySchema('taxonomy_term') also creates the taxonomy_index
    // table (TermStorageSchema); taxonomy's node hooks keep it current on save
    // when taxonomy.settings:maintain_index_table is on, so the mechanic and
    // category exposed filters resolve.
    $this->installConfig(['system', 'field', 'filter', 'node', 'taxonomy', 'file', 'image', 'media']);

    $this->installBoardGameModel();

    // Install the committed view exactly as it ships.
    View::create($this->readSyncConfig('views.view.game_finder'))->save();

    // A pair of category terms shared across the sample games.
    foreach (['Strategy', 'Family'] as $name) {
      $term = Term::create(['vid' => 'categories', 'name' => $name]);
      $term->save();
      $this->categories[$name] = $term;
    }

    $this->seedGames();
  }

  /**
   * Creates four games spanning distinct player ranges, times and weights.
   *
   * | title  | players | play_time | complexity | rating | category |
   * |--------|---------|-----------|------------|--------|----------|
   * | Solo   | 1–1     | 30        | 1.0        | 6.0    | Strategy |
   * | Duo    | 2–2     | 60        | 2.0        | 7.0    | Family   |
   * | Party  | 2–8     | 90        | 3.0        | 8.0    | Strategy |
   * | Mid    | 3–4     | 45        | 4.0        | 9.0    | Family   |
   */
  private function seedGames(): void {
    $this->createGame('Solo', 1, 1, 30, '1.0', '6.0', 'Strategy');
    $this->createGame('Duo', 2, 2, 60, '2.0', '7.0', 'Family');
    $this->createGame('Party', 2, 8, 90, '3.0', '8.0', 'Strategy');
    $this->createGame('Mid', 3, 4, 45, '4.0', '9.0', 'Family');
  }

  /**
   * Creates one published board game node.
   */
  private function createGame(string $title, int $min, int $max, int $time, string $complexity, string $rating, string $category): Node {
    $node = Node::create([
      'type' => 'board_game',
      'title' => $title,
      'status' => TRUE,
      'field_min_players' => $min,
      'field_max_players' => $max,
      'field_play_time' => $time,
      'field_complexity' => $complexity,
      'field_rating' => $rating,
      'field_categories' => [$this->categories[$category]->id()],
    ]);
    $node->save();
    return $node;
  }

  /**
   * Executes the Game Finder page with the given exposed input.
   *
   * @return string[]
   *   The titles of the matching games, in result order.
   */
  private function runFinder(array $exposed): array {
    $view = Views::getView('game_finder');
    $view->setDisplay('finder_page');
    $view->setExposedInput($exposed);
    $view->execute();
    return array_map(static fn($row) => (string) $row->_entity->label(), $view->result);
  }

  /**
   * With no exposed input the finder lists every published game.
   */
  public function testNoFilterListsAllGames(): void {
    $this->assertEqualsCanonicalizing(
      ['Solo', 'Duo', 'Party', 'Mid'],
      $this->runFinder([]),
    );
  }

  /**
   * The player-count filter returns games whose range covers N.
   *
   * A game supports N when min_players ≤ N ≤ max_players. For N = 4 only Party
   * (2–8) and Mid (3–4) qualify; Solo (1–1) and Duo (2–2) do not.
   */
  public function testPlayerCountFilterUsesMinMaxRange(): void {
    $this->assertEqualsCanonicalizing(['Party', 'Mid'], $this->runFinder(['players' => 4]));
    // A boundary value: N = 2 hits the low edge of Duo and Party.
    $this->assertEqualsCanonicalizing(['Duo', 'Party'], $this->runFinder(['players' => 2]));
    // N = 8 only the wide-range Party still qualifies.
    $this->assertEqualsCanonicalizing(['Party'], $this->runFinder(['players' => 8]));
    // Nobody supports 9 players.
    $this->assertSame([], $this->runFinder(['players' => 9]));
  }

  /**
   * The max-play-time filter keeps games at or under the chosen minutes.
   */
  public function testMaxPlayTimeFilter(): void {
    // ≤ 45 minutes: Solo (30) and Mid (45); Duo (60) and Party (90) drop.
    $this->assertEqualsCanonicalizing(['Solo', 'Mid'], $this->runFinder(['max_time' => 45]));
  }

  /**
   * The complexity range filter keeps games whose weight falls in the band.
   */
  public function testComplexityRangeFilter(): void {
    // Between 2.0 and 3.0 inclusive: Duo (2.0) and Party (3.0).
    $this->assertEqualsCanonicalizing(
      ['Duo', 'Party'],
      $this->runFinder(['complexity' => ['min' => '2.0', 'max' => '3.0']]),
    );
  }

  /**
   * The category filter keeps games referencing the chosen term.
   */
  public function testCategoryFilter(): void {
    $strategy = (int) $this->categories['Strategy']->id();
    $this->assertEqualsCanonicalizing(
      ['Solo', 'Party'],
      $this->runFinder(['category' => $strategy]),
    );
  }

  /**
   * Filters combine: player count and category narrow together (AND).
   */
  public function testFiltersCombine(): void {
    $strategy = (int) $this->categories['Strategy']->id();
    // Supports 4 players AND tagged Strategy: only Party (Mid is Family).
    $this->assertSame(
      ['Party'],
      $this->runFinder(['players' => 4, 'category' => $strategy]),
    );
  }

}
