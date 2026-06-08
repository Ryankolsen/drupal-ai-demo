<?php

declare(strict_types=1);

namespace Drupal\board_games\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Filters board games to those that support a given number of players.
 *
 * A board game "supports N players" when field_min_players ≤ N ≤
 * field_max_players. Two ordinary numeric filters would expose two separate
 * inputs (a min and a max) and could not express that relationship against a
 * *single* exposed value. This plugin takes one integer N and adds both
 * boundary conditions against the two field columns, joining their tables on
 * demand, so a visitor asks "what plays with 4?" and gets exactly the games
 * whose supported range covers 4.
 */
#[ViewsFilter("board_games_player_count")]
final class PlayerCount extends FilterPluginBase {

  /**
   * This filter has a single value and no operator selector.
   *
   * @var bool
   */
  // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName
  public $no_operator = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['value']['default'] = '';
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  protected function valueForm(&$form, FormStateInterface $form_state) {
    $form['value'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of players'),
      '#min' => 1,
      '#step' => 1,
      '#default_value' => $this->value,
      '#description' => $this->t('Show games that support this many players.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    // The exposed single-value filter may arrive as a scalar or a one-element
    // array (Views wraps non-multiple exposed values). Anything that is not a
    // positive integer — empty string, null, 0 — means "no constraint", so the
    // finder is unfiltered rather than matching nothing.
    $value = is_array($this->value) ? reset($this->value) : $this->value;
    $value = (int) $value;
    if ($value < 1) {
      return;
    }

    // Join the two field tables (each defines a views join back to the node
    // base table) and bound the supported range from both sides.
    $min_alias = $this->query->ensureTable('node__field_min_players', $this->relationship);
    $max_alias = $this->query->ensureTable('node__field_max_players', $this->relationship);

    $this->query->addWhere($this->options['group'], "$min_alias.field_min_players_value", $value, '<=');
    $this->query->addWhere($this->options['group'], "$max_alias.field_max_players_value", $value, '>=');
  }

  /**
   * {@inheritdoc}
   */
  public function adminSummary(): string {
    return $this->value === '' || $this->value === NULL ? '' : (string) $this->value;
  }

}
