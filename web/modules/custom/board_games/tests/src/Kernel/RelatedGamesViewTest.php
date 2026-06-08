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
 * Kernel tests for the Designer/Publisher reverse-relationship views.
 *
 * The games_by_designer and games_by_publisher views each take a node id as a
 * contextual filter and return the board games that reference that Designer
 * (multi-value field_designers) or Publisher (single field_publisher). The
 * views are executed (not rendered) so the test stays at the data layer.
 */
#[Group('board_games')]
final class RelatedGamesViewTest extends KernelTestBase {

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

    View::create($this->readSyncConfig('views.view.games_by_designer'))->save();
    View::create($this->readSyncConfig('views.view.games_by_publisher'))->save();
  }

  /**
   * Creates a published node of a bundle, returning it.
   */
  private function createNode(string $bundle, string $title, array $values = []): Node {
    $node = Node::create(array_merge(['type' => $bundle, 'title' => $title, 'status' => TRUE], $values));
    $node->save();
    return $node;
  }

  /**
   * Runs a view display with one contextual argument and returns the node ids.
   */
  private function resultIds(string $view_id, int $argument): array {
    $view = Views::getView($view_id);
    $this->assertNotNull($view, "The $view_id view loaded.");
    $view->setDisplay('page_1');
    $view->setArguments([$argument]);
    $view->execute();
    return array_map(static fn($row) => (int) $row->_entity->id(), $view->result);
  }

  /**
   * The designer view returns that designer's games, highest rating first.
   */
  public function testGamesByDesigner(): void {
    $alice = $this->createNode('designer', 'Alice');
    $bob = $this->createNode('designer', 'Bob');

    $solo = $this->createNode('board_game', 'Solo', [
      'field_rating' => '7.50',
      'field_designers' => [$alice->id()],
    ]);
    $duet = $this->createNode('board_game', 'Duet', [
      'field_rating' => '8.00',
      'field_designers' => [$alice->id(), $bob->id()],
    ]);
    $bobs = $this->createNode('board_game', 'Bobs Game', [
      'field_rating' => '7.00',
      'field_designers' => [$bob->id()],
    ]);

    // Alice: both games credited to Alice, ordered by rating DESC.
    $this->assertSame(
      [(int) $duet->id(), (int) $solo->id()],
      $this->resultIds('games_by_designer', (int) $alice->id()),
    );
    // Bob: the co-designed game plus the solo one credited to Bob.
    $this->assertSame(
      [(int) $duet->id(), (int) $bobs->id()],
      $this->resultIds('games_by_designer', (int) $bob->id()),
    );
  }

  /**
   * The publisher view returns that publisher's games, highest rating first.
   */
  public function testGamesByPublisher(): void {
    $acme = $this->createNode('publisher', 'Acme Games');
    $other = $this->createNode('publisher', 'Other Co');

    $a1 = $this->createNode('board_game', 'A One', [
      'field_rating' => '7.20',
      'field_publisher' => $acme->id(),
    ]);
    $a2 = $this->createNode('board_game', 'A Two', [
      'field_rating' => '7.90',
      'field_publisher' => $acme->id(),
    ]);
    $this->createNode('board_game', 'Elsewhere', [
      'field_rating' => '9.90',
      'field_publisher' => $other->id(),
    ]);

    $this->assertSame(
      [(int) $a2->id(), (int) $a1->id()],
      $this->resultIds('games_by_publisher', (int) $acme->id()),
    );
  }

  /**
   * An unpublished game is excluded from a designer's list.
   */
  public function testUnpublishedGamesExcluded(): void {
    $alice = $this->createNode('designer', 'Alice');
    $live = $this->createNode('board_game', 'Live', [
      'field_rating' => '7.00',
      'field_designers' => [$alice->id()],
    ]);
    $this->createNode('board_game', 'Draft', [
      'field_rating' => '9.00',
      'field_designers' => [$alice->id()],
      'status' => FALSE,
    ]);

    $this->assertSame(
      [(int) $live->id()],
      $this->resultIds('games_by_designer', (int) $alice->id()),
    );
  }

  /**
   * Passing a non-designer node id yields no results (argument validation).
   */
  public function testWrongBundleArgumentReturnsNothing(): void {
    $publisher = $this->createNode('publisher', 'Acme Games');
    $alice = $this->createNode('designer', 'Alice');
    $this->createNode('board_game', 'Solo', [
      'field_rating' => '7.50',
      'field_designers' => [$alice->id()],
    ]);

    // A publisher id is not a valid argument for the designer view.
    $this->assertSame([], $this->resultIds('games_by_designer', (int) $publisher->id()));
  }

  /**
   * Each view's page display is served at the expected path.
   */
  public function testPagePaths(): void {
    $designer = Views::getView('games_by_designer');
    $designer->setDisplay('page_1');
    $this->assertSame('designer/%/games', $designer->getDisplay()->getOption('path'));

    $publisher = Views::getView('games_by_publisher');
    $publisher->setDisplay('page_1');
    $this->assertSame('publisher/%/games', $publisher->getDisplay()->getOption('path'));
  }

}
