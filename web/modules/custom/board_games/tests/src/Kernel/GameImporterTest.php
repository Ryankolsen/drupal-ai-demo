<?php

declare(strict_types=1);

namespace Drupal\Tests\board_games\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\board_games\Traits\BoardGameContentModelTrait;
use Drupal\board_games\GameImporter;
use Drupal\board_games\GameImporterInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for the board game fixture importer.
 *
 * The importer service (board_games.importer) is the stable test boundary: a
 * thin Drush command delegates to it, so testing import() directly covers the
 * real logic. These tests assert the created/updated/skipped contract and the
 * idempotency the seeder guarantee depends on.
 */
#[Group('board_games')]
#[CoversClass(GameImporter::class)]
final class GameImporterTest extends KernelTestBase {

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
    'board_games',
  ];

  /**
   * The importer under test.
   */
  private GameImporterInterface $importer;

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
    $this->installSchema('file', ['file_usage']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'field', 'filter', 'node', 'file', 'image', 'media']);

    $this->installBoardGameModel();

    $this->importer = $this->container->get('board_games.importer');
  }

  /**
   * Two games sharing a mechanic, each with a real fixture cover image.
   *
   * @return array
   *   Fixture rows in the shape import() consumes.
   */
  private function sampleGames(): array {
    return [
      [
        'name' => 'Catan',
        'bgg_id' => 13,
        'min_players' => 3,
        'max_players' => 4,
        'play_time' => 60,
        'complexity' => '2.30',
        'rating' => '7.10',
        'description' => 'Trade, build, and settle the island of Catan.',
        // "Set Collection" is shared with Pandemic to test term dedup.
        'mechanics' => ['Dice Rolling', 'Set Collection'],
        'image' => 'catan.png',
      ],
      [
        'name' => 'Pandemic',
        'bgg_id' => 30549,
        'min_players' => 2,
        'max_players' => 4,
        'play_time' => 45,
        'complexity' => '2.40',
        'rating' => '7.60',
        'description' => 'Cooperatively cure four diseases.',
        'mechanics' => ['Cooperative Play', 'Set Collection'],
        'image' => 'pandemic.png',
      ],
    ];
  }

  /**
   * Counts entities of a given type.
   */
  private function countEntities(string $entity_type_id): int {
    return (int) $this->container->get('entity_type.manager')
      ->getStorage($entity_type_id)
      ->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
  }

  /**
   * Importing fresh fixtures creates one node per game with all relations.
   */
  public function testImportCreatesGames(): void {
    $result = $this->importer->import($this->sampleGames());

    $this->assertSame(['created' => 2, 'updated' => 0, 'skipped' => 0], $result);
    $this->assertSame(2, $this->countEntities('node'));
    // Three distinct mechanics across the two games (Set Collection shared).
    $this->assertSame(3, $this->countEntities('taxonomy_term'));
    $this->assertSame(2, $this->countEntities('media'));
    $this->assertSame(2, $this->countEntities('file'));

    $node = current($this->container->get('entity_type.manager')
      ->getStorage('node')
      ->loadByProperties(['field_bgg_id' => 13]));
    $this->assertSame('Catan', $node->label());
    // Decimal storage normalises '7.10' to '7.1'.
    $this->assertEquals(7.1, (float) $node->get('field_rating')->value);
    $this->assertCount(2, $node->get('field_mechanics'));
    $this->assertFalse($node->get('field_cover')->isEmpty());
  }

  /**
   * Re-importing the same fixtures updates in place and creates no duplicates.
   */
  public function testImportIsIdempotent(): void {
    $this->importer->import($this->sampleGames());
    $second = $this->importer->import($this->sampleGames());

    $this->assertSame(['created' => 0, 'updated' => 2, 'skipped' => 0], $second);
    // Nothing duplicated on the second pass.
    $this->assertSame(2, $this->countEntities('node'));
    $this->assertSame(3, $this->countEntities('taxonomy_term'));
    $this->assertSame(2, $this->countEntities('media'));
    $this->assertSame(2, $this->countEntities('file'));
  }

  /**
   * Designers and publisher are resolved-or-created and linked to the game.
   *
   * A designer shared by two games is created once (dedup by title), and
   * re-importing creates no duplicate Designer/Publisher nodes.
   */
  public function testImportLinksDesignersAndPublisher(): void {
    $games = [
      [
        'name' => 'Catan',
        'bgg_id' => 13,
        'min_players' => 3,
        'max_players' => 4,
        'designers' => ['Klaus Teuber'],
        'publisher' => 'Kosmos',
      ],
      [
        'name' => 'Catan: Seafarers',
        'bgg_id' => 325,
        'min_players' => 3,
        'max_players' => 4,
        // Klaus Teuber is shared with Catan: must dedup to one Designer node.
        'designers' => ['Klaus Teuber', 'Pete Fenlon'],
        'publisher' => 'Kosmos',
      ],
    ];

    $result = $this->importer->import($games);
    $this->assertSame(['created' => 2, 'updated' => 0, 'skipped' => 0], $result);

    $node_storage = $this->container->get('entity_type.manager')->getStorage('node');

    // Two distinct designers (Klaus Teuber deduped), one publisher (Kosmos).
    $this->assertCount(2, $node_storage->loadByProperties(['type' => 'designer']));
    $this->assertCount(1, $node_storage->loadByProperties(['type' => 'publisher']));

    $catan = current($node_storage->loadByProperties(['field_bgg_id' => 13]));
    $this->assertCount(1, $catan->get('field_designers'));
    $this->assertSame('Klaus Teuber', $catan->get('field_designers')->entity->label());
    $this->assertSame('Kosmos', $catan->get('field_publisher')->entity->label());

    $seafarers = current($node_storage->loadByProperties(['field_bgg_id' => 325]));
    $this->assertCount(2, $seafarers->get('field_designers'));

    // Re-importing creates no duplicate designer/publisher nodes.
    $this->importer->import($games);
    $this->assertCount(2, $node_storage->loadByProperties(['type' => 'designer']));
    $this->assertCount(1, $node_storage->loadByProperties(['type' => 'publisher']));
  }

  /**
   * Rows missing the required bgg_id or name are skipped, not imported.
   */
  public function testRowsMissingRequiredKeysAreSkipped(): void {
    $result = $this->importer->import([
      ['name' => 'No id'],
      ['bgg_id' => 99],
      ['name' => 'Azul', 'bgg_id' => 230802],
    ]);

    $this->assertSame(['created' => 1, 'updated' => 0, 'skipped' => 2], $result);
    $this->assertSame(1, $this->countEntities('node'));
  }

}
