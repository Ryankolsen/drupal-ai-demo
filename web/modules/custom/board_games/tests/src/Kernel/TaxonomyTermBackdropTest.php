<?php

declare(strict_types=1);

namespace Drupal\Tests\board_games\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Kernel tests for the term-page background-image library wiring.
 *
 * Issue #24 paints a themed full-page backdrop behind taxonomy term pages by
 * attaching the guardrails/taxonomy-term library only when the taxonomy_term
 * view renders, via a new branch in guardrails_preprocess_views_view(). The
 * preprocess hook is a plain function, so these tests require_once the theme
 * file and call the hook directly with a fake view — no full theme render
 * needed, mirroring the existing game_finder branch.
 */
#[Group('board_games')]
final class TaxonomyTermBackdropTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'views'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    require_once \Drupal::root() . '/themes/custom/guardrails/guardrails.theme';
  }

  /**
   * Builds a $variables array whose view->id() returns the given id.
   *
   * @param string|null $id
   *   The view id, or NULL to omit the view entirely.
   *
   * @return array
   *   A preprocess $variables array.
   */
  private function variablesForViewId(?string $id): array {
    if ($id === NULL) {
      return [];
    }
    $view = new class($id) {

      /**
       * Constructs the fake view with its id.
       */
      public function __construct(private readonly string $id) {}

      /**
       * Returns the view id, as ViewExecutable::id() does for the hook.
       */
      public function id(): string {
        return $this->id;
      }

    };
    return ['view' => $view];
  }

  /**
   * The backdrop library attaches when the taxonomy_term view renders.
   */
  public function testBackdropAttachedOnTermView(): void {
    $variables = $this->variablesForViewId('taxonomy_term');
    guardrails_preprocess_views_view($variables);
    $this->assertContains('guardrails/taxonomy-term', $variables['#attached']['library'] ?? []);
  }

  /**
   * The backdrop attaches nowhere else, and game_finder still works.
   */
  public function testBackdropNotAttachedElsewhere(): void {
    // A different view id gets neither nothing nor the backdrop.
    $variables = $this->variablesForViewId('frontpage');
    guardrails_preprocess_views_view($variables);
    $this->assertNotContains('guardrails/taxonomy-term', $variables['#attached']['library'] ?? []);

    // No view at all: the hook must not error or attach the backdrop.
    $variables = $this->variablesForViewId(NULL);
    guardrails_preprocess_views_view($variables);
    $this->assertNotContains('guardrails/taxonomy-term', $variables['#attached']['library'] ?? []);

    // Regression guard: the existing game_finder branch still attaches its lib.
    $variables = $this->variablesForViewId('game_finder');
    guardrails_preprocess_views_view($variables);
    $this->assertContains('guardrails/game-finder', $variables['#attached']['library'] ?? []);
    $this->assertNotContains('guardrails/taxonomy-term', $variables['#attached']['library'] ?? []);
  }

}
