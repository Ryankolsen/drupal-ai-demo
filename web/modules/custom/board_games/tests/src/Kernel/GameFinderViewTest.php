<?php

declare(strict_types=1);

namespace Drupal\Tests\board_games\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\board_games\Traits\BoardGameContentModelTrait;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for the committed Game Finder view.
 *
 * Loads the real /config/sync view and asserts its query contract: only
 * published Board Game nodes, ordered by rating descending. The view is
 * executed (not rendered) so the test stays at the data layer and needs no
 * theme or SDC.
 */
#[Group('board_games')]
final class GameFinderViewTest extends KernelTestBase {

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
    $this->installConfig(['system', 'field', 'filter', 'node', 'file', 'image', 'media']);

    $this->installBoardGameModel();

    // Install the committed views exactly as they ship.
    View::create($this->readSyncConfig('views.view.game_finder'))->save();
    View::create($this->readSyncConfig('views.view.top_rated'))->save();
  }

  /**
   * Creates a board game node with a given title, rating and published state.
   */
  private function createGame(string $title, string $rating, bool $published = TRUE): Node {
    $node = Node::create([
      'type' => 'board_game',
      'title' => $title,
      'field_rating' => $rating,
      'status' => $published,
    ]);
    $node->save();
    return $node;
  }

  /**
   * The page lists published Board Games, highest rating first.
   */
  public function testGameFinderListsPublishedGamesByRatingDescending(): void {
    $low = $this->createGame('Catan', '7.10');
    $high = $this->createGame('Pandemic', '7.60');
    $mid = $this->createGame('Ticket to Ride', '7.40');

    // Excluded: an unpublished board game and a non-board-game node.
    $this->createGame('Secret Prototype', '9.90', FALSE);
    NodeType::create(['type' => 'page', 'name' => 'Basic page'])->save();
    Node::create(['type' => 'page', 'title' => 'About', 'status' => TRUE])->save();

    $view = Views::getView('game_finder');
    $this->assertNotNull($view, 'The game_finder view loaded.');
    $view->setDisplay('page_1');
    // The view now carries exposed filters; processing empty exposed input
    // applies none of them, so this asserts the unfiltered baseline.
    $view->setExposedInput([]);
    $view->execute();

    $ids = array_map(static fn($row) => (int) $row->_entity->id(), $view->result);
    $this->assertSame(
      [(int) $high->id(), (int) $mid->id(), (int) $low->id()],
      $ids,
      'Only the three published board games appear, ordered by rating DESC.',
    );
  }

  /**
   * The page display is served at /games.
   */
  public function testGameFinderPagePath(): void {
    $view = Views::getView('game_finder');
    $view->setDisplay('page_1');
    $this->assertSame('games', $view->getDisplay()->getOption('path'));
  }

  /**
   * Top Rated ranks published games by rating DESC and is served at /top-rated.
   */
  public function testTopRatedRanksByRating(): void {
    $low = $this->createGame('Catan', '7.10');
    $high = $this->createGame('Pandemic', '7.60');
    $mid = $this->createGame('Ticket to Ride', '7.40');
    $this->createGame('Secret Prototype', '9.90', FALSE);

    $view = Views::getView('top_rated');
    $this->assertNotNull($view, 'The top_rated view loaded.');
    $view->setDisplay('page_1');
    $view->execute();

    $ids = array_map(static fn($row) => (int) $row->_entity->id(), $view->result);
    $this->assertSame(
      [(int) $high->id(), (int) $mid->id(), (int) $low->id()],
      $ids,
      'Only published board games appear, ranked by rating DESC.',
    );
    $this->assertSame('top-rated', $view->getDisplay()->getOption('path'));
  }

}
