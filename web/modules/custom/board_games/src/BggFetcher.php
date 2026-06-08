<?php

declare(strict_types=1);

namespace Drupal\board_games;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use GuzzleHttp\ClientInterface;

/**
 * Fetches and parses board games from the BoardGameGeek XML API (xmlapi2).
 *
 * Dev-time only: this is invoked by the `bg:fetch` Drush command to regenerate
 * the committed fixture. The runtime seed (`bg:seed`) reads that fixture and
 * never touches the network, so the on-stage demo is deterministic.
 *
 * The HTTP fetch and the XML→fixture transform are kept separate
 * (parseThings()) so the parsing logic can be unit-tested against a captured
 * response with no network access.
 *
 * Not final: the protected backoff hook is overridden in unit tests, and a
 * service class may legitimately be decorated.
 */
class BggFetcher implements BggFetcherInterface {

  /**
   * Base URL of the BGG XML API v2 "thing" endpoint.
   */
  private const ENDPOINT = 'https://boardgamegeek.com/xmlapi2/thing';

  /**
   * How many ids to request per HTTP call.
   *
   * BGG accepts a comma-separated id list; batching keeps the request count
   * (and the chance of a queued 202 response) low while staying well under any
   * URL-length limit.
   */
  private const BATCH_SIZE = 20;

  /**
   * Max attempts to re-request a batch BGG answers with 202 (queued).
   */
  private const MAX_RETRIES = 5;

  /**
   * The board_games logger channel.
   */
  private LoggerChannelInterface $logger;

  public function __construct(
    private readonly ClientInterface $httpClient,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    $this->logger = $loggerFactory->get('board_games');
  }

  /**
   * {@inheritdoc}
   */
  public function fetch(array $ids): array {
    $ids = array_values(array_unique(array_map('intval', $ids)));
    $games = [];

    foreach (array_chunk($ids, self::BATCH_SIZE) as $batch) {
      $xml = $this->request($batch);
      if ($xml === NULL) {
        continue;
      }
      foreach ($this->parseThings($xml) as $game) {
        $games[] = $game;
      }
    }

    return $games;
  }

  /**
   * Requests one batch of ids, retrying while BGG answers 202 (queued).
   *
   * BGG returns 202 Accepted while it builds a large response; the documented
   * behaviour is to re-request the same URL until it returns 200.
   *
   * @param int[] $batch
   *   The ids for this request.
   *
   * @return string|null
   *   The XML body, or NULL if the request ultimately failed.
   */
  private function request(array $batch): ?string {
    $query = [
      'id' => implode(',', $batch),
      'stats' => 1,
    ];

    for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
      $response = $this->httpClient->request('GET', self::ENDPOINT, [
        'query' => $query,
        'http_errors' => FALSE,
        'headers' => ['Accept' => 'text/xml'],
      ]);
      $status = $response->getStatusCode();

      if ($status === 200) {
        return (string) $response->getBody();
      }

      if ($status === 202) {
        // Queued: wait and retry the same request.
        $this->sleepBetweenRetries($attempt);
        continue;
      }

      $this->logger->error('BGG returned HTTP @status for ids @ids', [
        '@status' => $status,
        '@ids' => $query['id'],
      ]);
      return NULL;
    }

    $this->logger->error('BGG did not finish queuing ids @ids after @n attempts', [
      '@ids' => $query['id'],
      '@n' => self::MAX_RETRIES,
    ]);
    return NULL;
  }

  /**
   * Sleeps with linear backoff between queued-response retries.
   *
   * Extracted so tests can override it and not actually sleep.
   *
   * @param int $attempt
   *   The 1-based attempt number.
   */
  protected function sleepBetweenRetries(int $attempt): void {
    sleep($attempt * 2);
  }

  /**
   * {@inheritdoc}
   */
  public function parseThings(string $xml): array {
    $previous = libxml_use_internal_errors(TRUE);
    $items = simplexml_load_string($xml);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if ($items === FALSE) {
      $this->logger->error('Could not parse BGG XML response.');
      return [];
    }

    $games = [];
    foreach ($items->item as $item) {
      $game = $this->parseItem($item);
      if ($game !== NULL) {
        $games[] = $game;
      }
    }

    return $games;
  }

  /**
   * Maps one BGG <item> element to an importer-shaped game row.
   *
   * @param \SimpleXMLElement $item
   *   A single <item> from a /thing response.
   *
   * @return array|null
   *   The game row, or NULL if the item lacks an id or primary name.
   */
  private function parseItem(\SimpleXMLElement $item): ?array {
    $bgg_id = (int) $item['id'];
    $name = $this->primaryName($item);
    if ($bgg_id === 0 || $name === '') {
      return NULL;
    }

    $game = [
      'name' => $name,
      'bgg_id' => $bgg_id,
      'min_players' => $this->intValue($item->minplayers),
      'max_players' => $this->intValue($item->maxplayers),
      // Prefer the headline playing time; fall back to the max if it is 0.
      'play_time' => $this->intValue($item->playingtime) ?: $this->intValue($item->maxplaytime),
      'min_age' => $this->intValue($item->minage),
      'complexity' => $this->decimalValue($item->statistics->ratings->averageweight ?? NULL),
      'rating' => $this->decimalValue($item->statistics->ratings->average ?? NULL),
      'description' => $this->cleanDescription((string) $item->description),
      'mechanics' => $this->links($item, 'boardgamemechanic'),
      'categories' => $this->links($item, 'boardgamecategory'),
      'designers' => $this->links($item, 'boardgamedesigner'),
    ];

    // Publisher: take the first publisher BGG lists (the original publisher).
    $publishers = $this->links($item, 'boardgamepublisher');
    if ($publishers) {
      $game['publisher'] = reset($publishers);
    }

    return $game;
  }

  /**
   * Returns the primary (English) name from an item's <name> elements.
   */
  private function primaryName(\SimpleXMLElement $item): string {
    foreach ($item->name as $name) {
      if ((string) $name['type'] === 'primary') {
        return (string) $name['value'];
      }
    }
    // Fall back to the first name if none is flagged primary.
    return isset($item->name[0]) ? (string) $item->name[0]['value'] : '';
  }

  /**
   * Reads the integer `value` attribute of an element, or 0 if absent.
   */
  private function intValue(?\SimpleXMLElement $element): int {
    return $element === NULL ? 0 : (int) $element['value'];
  }

  /**
   * Formats an element's `value` attribute as a 2-decimal string, or NULL.
   *
   * Matches the fixture's complexity/rating shape (e.g. "2.30").
   */
  private function decimalValue(?\SimpleXMLElement $element): ?string {
    if ($element === NULL) {
      return NULL;
    }
    $value = (float) $element['value'];
    return $value > 0 ? number_format($value, 2, '.', '') : NULL;
  }

  /**
   * Collects the `value` of every <link> of a given type, deduped in order.
   *
   * @param \SimpleXMLElement $item
   *   The item element.
   * @param string $type
   *   The BGG link type, e.g. 'boardgamemechanic'.
   *
   * @return string[]
   *   The link values.
   */
  private function links(\SimpleXMLElement $item, string $type): array {
    $values = [];
    foreach ($item->link as $link) {
      if ((string) $link['type'] === $type) {
        $value = (string) $link['value'];
        if ($value !== '' && !in_array($value, $values, TRUE)) {
          $values[] = $value;
        }
      }
    }
    return $values;
  }

  /**
   * Normalises a BGG description into clean, plain-ish text.
   *
   * BGG descriptions arrive HTML-encoded with &#10; line breaks and stray
   * markup; decode entities, drop tags, and collapse whitespace so the fixture
   * holds readable prose.
   */
  private function cleanDescription(string $raw): string {
    $text = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = strip_tags($text);
    // Collapse whitespace runs (incl. decoded newlines) to single spaces.
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim((string) $text);
  }

}
