<?php

declare(strict_types=1);

namespace Drupal\Tests\board_games\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\board_games\Traits\BoardGameContentModelTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;
use Drupal\views\Entity\View;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for the restyled Taxonomy term (game category) page.
 *
 * The committed taxonomy_term view is the page served at /taxonomy/term/%.
 * Issue #23 restyles it to render the term's board games as a game_card grid
 * (the 'card' view mode) under a term-name title. These tests load the real
 * /config/sync view and assert both its rendering contract (grid style + card
 * rows + term-name argument title) and its query contract (only that term's
 * published games), executed at the data layer so no theme is needed.
 */
#[Group('board_games')]
final class TaxonomyTermViewTest extends KernelTestBase {

  use BoardGameContentModelTrait;
  use UserCreationTrait;

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

    // The view filters/sorts on the denormalized taxonomy_index table, which
    // the taxonomy module maintains on node save only when this setting is on.
    // (Installing the taxonomy module's config would also pull in its default
    // taxonomy_term view and collide with the committed one under test.)
    \Drupal::configFactory()->getEditable('taxonomy.settings')->set('maintain_index_table', TRUE)->save();

    // The term page is public on the live site; the term argument validates
    // view access, so the running user needs 'access content' or the argument
    // resolves to "not found" and the page returns nothing.
    $this->setUpCurrentUser([], ['access content']);

    $this->installBoardGameModel();

    // The core taxonomy_term view installs itself as optional config once its
    // node/taxonomy/views dependencies are met; replace it with the committed
    // grid version so the tests exercise what this repo actually ships.
    if ($existing = View::load('taxonomy_term')) {
      $existing->delete();
    }
    View::create($this->readSyncConfig('views.view.taxonomy_term'))->save();
  }

  /**
   * Creates a category term, returning it.
   */
  private function createCategory(string $name): Term {
    $term = Term::create(['vid' => 'categories', 'name' => $name]);
    $term->save();
    return $term;
  }

  /**
   * Creates a board game in the given categories, returning it.
   */
  private function createGame(string $title, string $rating, array $tids, bool $published = TRUE): Node {
    $node = Node::create([
      'type' => 'board_game',
      'title' => $title,
      'field_rating' => $rating,
      'field_categories' => $tids,
      'status' => $published,
    ]);
    $node->save();
    return $node;
  }

  /**
   * The page lists only that category's published games.
   */
  public function testTermPageListsThatCategorysPublishedGames(): void {
    $strategy = $this->createCategory('Strategy');
    $party = $this->createCategory('Party');

    $catan = $this->createGame('Catan', '7.10', [$strategy->id()]);
    $pandemic = $this->createGame('Pandemic', '7.60', [$strategy->id()]);
    // Excluded: a Party-only game and an unpublished Strategy game.
    $this->createGame('Codenames', '7.80', [$party->id()]);
    $this->createGame('Strategy Draft', '9.90', [$strategy->id()], FALSE);

    $view = Views::getView('taxonomy_term');
    $this->assertNotNull($view, 'The taxonomy_term view loaded.');
    $view->setDisplay('page_1');
    $view->setArguments([(string) $strategy->id()]);
    $view->execute();

    $ids = array_map(static fn($row) => (int) $row->_entity->id(), $view->result);
    sort($ids);
    $expected = [(int) $catan->id(), (int) $pandemic->id()];
    sort($expected);
    $this->assertSame($expected, $ids, 'Only Strategy\'s two published games appear.');
  }

  /**
   * The page is the canonical taxonomy term path.
   */
  public function testTermPagePath(): void {
    $view = Views::getView('taxonomy_term');
    $view->setDisplay('page_1');
    $this->assertSame('taxonomy/term/%', $view->getDisplay()->getOption('path'));
  }

  /**
   * Rows render as a game_card grid (grid style + 'card' view mode).
   */
  public function testRowsRenderAsGameCardGrid(): void {
    $view = Views::getView('taxonomy_term');
    $view->setDisplay('page_1');

    $style = $view->getDisplay()->getOption('style');
    $this->assertSame('grid', $style['type'], 'The page uses the grid style.');

    $row = $view->getDisplay()->getOption('row');
    $this->assertSame('entity:node', $row['type']);
    $this->assertSame('card', $row['options']['view_mode'], 'Rows use the card view mode (the game_card SDC).');
  }

  /**
   * The page title is the term name, not the raw term id.
   */
  public function testTitleIsTermName(): void {
    $view = Views::getView('taxonomy_term');
    $view->setDisplay('page_1');

    $arguments = $view->getDisplay()->getOption('arguments');
    $this->assertArrayHasKey('tid', $arguments);
    $tid = $arguments['tid'];
    $this->assertTrue($tid['title_enable'], 'The argument supplies the page title.');
    // The validated taxonomy-term argument replaces this token with the term
    // label, so the title reads as the category name.
    $this->assertSame('{{ arguments.tid }}', $tid['title']);
    $this->assertSame('entity:taxonomy_term', $tid['validate']['type']);
  }

}
