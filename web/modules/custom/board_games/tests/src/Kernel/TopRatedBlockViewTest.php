<?php

declare(strict_types=1);

namespace Drupal\Tests\board_games\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\board_games\Traits\BoardGameContentModelTrait;
use Drupal\node\Entity\Node;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for the top_rated Game Cards grid block display.
 *
 * The top_rated view's block display (top_rated_block) is the live data source
 * the Canvas demo page consumes: a short, rating-ordered grid of published
 * board games rendered in the card view mode (wired to the game_card SDC). The
 * display is executed (not rendered) so the test stays at the data layer; the
 * card -> SDC rendering is covered by SdcComponentRenderTest.
 */
#[Group('board_games')]
final class TopRatedBlockViewTest extends KernelTestBase {

  use BoardGameContentModelTrait;

  /**
   * The block display's machine id.
   */
  private const DISPLAY = 'top_rated_block';

  /**
   * The configured row limit for the grid block.
   */
  private const LIMIT = 6;

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

    View::create($this->readSyncConfig('views.view.top_rated'))->save();
  }

  /**
   * Creates a published board game with a rating, returning it.
   */
  private function createGame(string $title, string $rating, bool $published = TRUE): Node {
    $node = Node::create([
      'type' => 'board_game',
      'title' => $title,
      'status' => $published,
      'field_rating' => $rating,
    ]);
    $node->save();
    return $node;
  }

  /**
   * Executes the block display and returns the resulting node ids in order.
   */
  private function resultIds(): array {
    $view = Views::getView('top_rated');
    $this->assertNotNull($view, 'The top_rated view loaded.');
    $view->setDisplay(self::DISPLAY);
    $view->execute();
    return array_map(static fn($row) => (int) $row->_entity->id(), $view->result);
  }

  /**
   * The block display loads, executes and returns the board-game node ids.
   */
  public function testBlockDisplayReturnsGames(): void {
    $a = $this->createGame('Azul', '8.00');
    $b = $this->createGame('Brass', '9.50');

    $ids = $this->resultIds();
    $this->assertNotEmpty($ids, 'The block display returns rows.');
    $this->assertContains((int) $a->id(), $ids);
    $this->assertContains((int) $b->id(), $ids);
  }

  /**
   * The display is wired to the card view mode and the ~6 row limit.
   */
  public function testDisplayUsesCardViewModeAndLimit(): void {
    $view = Views::getView('top_rated');
    $view->setDisplay(self::DISPLAY);
    $display = $view->getDisplay();

    $row = $display->getOption('row');
    $this->assertSame('entity:node', $row['type']);
    $this->assertSame('card', $row['options']['view_mode']);

    $pager = $display->getOption('pager');
    $this->assertSame(self::LIMIT, (int) $pager['options']['items_per_page']);
  }

  /**
   * Results are ordered by rating, highest first.
   */
  public function testOrderedByRatingDescending(): void {
    $low = $this->createGame('Low', '7.20');
    $high = $this->createGame('High', '9.50');
    $mid = $this->createGame('Mid', '8.00');

    $this->assertSame(
      [(int) $high->id(), (int) $mid->id(), (int) $low->id()],
      $this->resultIds(),
    );
  }

  /**
   * The grid is capped at the row limit when more games exist.
   */
  public function testRowLimitCapsResults(): void {
    // Eight published games, descending ratings so order is deterministic.
    $ratings = ['9.80', '9.50', '9.20', '8.90', '8.60', '8.30', '8.00', '7.70'];
    foreach ($ratings as $i => $rating) {
      $this->createGame('Game ' . $i, $rating);
    }

    $this->assertCount(self::LIMIT, $this->resultIds());
  }

  /**
   * An unpublished high-rated game is excluded from the grid.
   */
  public function testUnpublishedGamesExcluded(): void {
    $live = $this->createGame('Live', '7.00');
    $this->createGame('Draft', '9.90', FALSE);

    $this->assertSame([(int) $live->id()], $this->resultIds());
  }

  /**
   * With fewer than the limit, all published games are returned (no padding).
   */
  public function testUnderLimitReturnsAll(): void {
    $a = $this->createGame('One', '8.00');
    $b = $this->createGame('Two', '7.00');

    $this->assertSame([(int) $a->id(), (int) $b->id()], $this->resultIds());
  }

}
