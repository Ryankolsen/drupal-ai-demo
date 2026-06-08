<?php

declare(strict_types=1);

namespace Drupal\Tests\board_games\Kernel;

use Drupal\Core\Render\RenderContext;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Renders the guardrails stat SDCs and asserts their schema + a11y contract.
 *
 * These components carry no PHP logic, but they do declare prop schemas that
 * Single Directory Components validate at render time, and they must meet WCAG
 * AA (value never conveyed by color alone; a single labelled image rather than
 * one announcement per glyph). Rendering them here — no browser needed — guards
 * the schema, the Twig, the accessibility markup, and the rule that an SDC
 * rejects an explicit null for a typed prop (so optional props must be omitted,
 * not passed as null).
 */
#[Group('board_games')]
final class SdcComponentRenderTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'guardrails';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    \Drupal::service('theme_installer')->install(['guardrails']);
  }

  /**
   * Renders a component to its HTML string.
   */
  private function renderComponent(string $component, array $props): string {
    $build = [
      '#type' => 'component',
      '#component' => $component,
      '#props' => $props,
    ];
    $renderer = \Drupal::service('renderer');
    return (string) $renderer->executeInRenderContext(
      new RenderContext(),
      static fn () => $renderer->render($build),
    );
  }

  /**
   * The rating maps onto stars as a single labelled image with a readout.
   */
  public function testRatingStars(): void {
    $html = $this->renderComponent('guardrails:rating_stars', ['rating' => 7.1]);
    // Accessible name conveys the score once, not per star.
    $this->assertStringContainsString('role="img"', $html);
    $this->assertStringContainsString('aria-label="Rated 7.1 out of 10"', $html);
    // Visible numeric readout (value never by color alone).
    $this->assertStringContainsString('7.1 / 10', $html);
    // Fill is a proportion of the scale: 7.1 / 10 → 71%.
    $this->assertStringContainsString('width: 71%', $html);
  }

  /**
   * The complexity meter draws filled pips plus an exact numeric readout.
   */
  public function testComplexityMeter(): void {
    $html = $this->renderComponent('guardrails:complexity_meter', ['value' => 2.3]);
    $this->assertStringContainsString('role="img"', $html);
    $this->assertStringContainsString('aria-label="Complexity: 2.3 out of 5"', $html);
    $this->assertStringContainsString('2.3 / 5', $html);
    // floor(2.3) = 2 filled pips out of the 5 drawn.
    $this->assertSame(2, substr_count($html, 'is-filled'));
    $this->assertSame(5, substr_count($html, 'gr-complexity-meter__pip'));
  }

  /**
   * The badge row renders a badge per supplied value.
   */
  public function testBadgeRow(): void {
    $html = $this->renderComponent('guardrails:badge_row', [
      'min_players' => 2,
      'max_players' => 4,
      'play_time' => 45,
    ]);
    $this->assertStringContainsString('2–4', $html);
    $this->assertStringContainsString('players', $html);
    $this->assertStringContainsString('45', $html);
    $this->assertStringContainsString('min', $html);
    // Icons are decorative.
    $this->assertStringContainsString('aria-hidden="true"', $html);
  }

  /**
   * A single-value player range collapses to one number, not "N–N".
   */
  public function testBadgeRowSinglePlayerCount(): void {
    $html = $this->renderComponent('guardrails:badge_row', [
      'min_players' => 2,
      'max_players' => 2,
    ]);
    $this->assertStringContainsString('>2<', $html);
    $this->assertStringNotContainsString('2–2', $html);
  }

  /**
   * A partial badge row renders when optional props are omitted.
   *
   * Omitting (rather than passing null for) absent optional props exercises the
   * rule that SDC rejects an explicit null for a typed prop.
   */
  public function testBadgeRowPartialPropsRenderWithoutError(): void {
    $html = $this->renderComponent('guardrails:badge_row', ['play_time' => 30]);
    $this->assertStringContainsString('30', $html);
    // No players badge when the range is absent.
    $this->assertStringNotContainsString('players', $html);
  }

}
