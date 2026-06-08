<?php

declare(strict_types=1);

namespace Drupal\Tests\board_games\Unit;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\board_games\BggFetcher;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Unit tests for the dev-time BGG fetch tool.
 *
 * The XML→fixture transform (parseThings) is pure, so it is tested against a
 * captured-shape response with no network. The HTTP path is exercised with a
 * mocked Guzzle client, including BGG's 202 "queued" retry behaviour.
 */
#[Group('board_games')]
#[CoversClass(BggFetcher::class)]
final class BggFetcherTest extends UnitTestCase {

  /**
   * A representative BGG /thing?stats=1 response.
   *
   * Covers: a primary name alongside an alternate, an HTML-encoded
   * description, a duplicate mechanic link (must dedup), two publishers (only
   * the first is kept), and a second item missing a name (must be skipped).
   */
  private const SAMPLE_XML = <<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<items>
  <item type="boardgame" id="13">
    <name type="primary" sortindex="1" value="Catan"/>
    <name type="alternate" sortindex="1" value="Die Siedler von Catan"/>
    <description>Trade, build &amp; settle.&#10;&#10;Race to ten points.&lt;br/&gt;</description>
    <minplayers value="3"/>
    <maxplayers value="4"/>
    <playingtime value="120"/>
    <minplaytime value="60"/>
    <maxplaytime value="120"/>
    <minage value="10"/>
    <link type="boardgamecategory" id="1015" value="Economic"/>
    <link type="boardgamecategory" id="1026" value="Negotiation"/>
    <link type="boardgamemechanic" id="2072" value="Dice Rolling"/>
    <link type="boardgamemechanic" id="2040" value="Trading"/>
    <link type="boardgamemechanic" id="2072" value="Dice Rolling"/>
    <link type="boardgamedesigner" id="11" value="Klaus Teuber"/>
    <link type="boardgamepublisher" id="93" value="KOSMOS"/>
    <link type="boardgamepublisher" id="37" value="Mayfair Games"/>
    <statistics page="1">
      <ratings>
        <average value="7.06633"/>
        <averageweight value="2.2972"/>
      </ratings>
    </statistics>
  </item>
  <item type="boardgame" id="99999">
    <minplayers value="2"/>
    <maxplayers value="2"/>
  </item>
</items>
XML;

  /**
   * Builds a fetcher with a mocked HTTP client and a no-op logger.
   */
  private function fetcher(ClientInterface $client): BggFetcher {
    $logger = $this->createMock(LoggerChannelInterface::class);
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($logger);
    return new BggFetcher($client, $factory);
  }

  /**
   * ParseThings maps a BGG item to the importer's fixture shape.
   */
  public function testParseThingsMapsFields(): void {
    $fetcher = $this->fetcher($this->createMock(ClientInterface::class));
    $games = $fetcher->parseThings(self::SAMPLE_XML);

    // The second item has no primary name and must be skipped.
    $this->assertCount(1, $games);
    $game = $games[0];

    $this->assertSame('Catan', $game['name']);
    $this->assertSame(13, $game['bgg_id']);
    $this->assertSame(3, $game['min_players']);
    $this->assertSame(4, $game['max_players']);
    // Playingtime is preferred over maxplaytime.
    $this->assertSame(120, $game['play_time']);
    $this->assertSame(10, $game['min_age']);
    // Decimals normalised to the fixture's 2-place string shape.
    $this->assertSame('2.30', $game['complexity']);
    $this->assertSame('7.07', $game['rating']);
    // Description: entities decoded, tags stripped, whitespace collapsed.
    $this->assertSame('Trade, build & settle. Race to ten points.', $game['description']);
    // Mechanics deduped, order preserved.
    $this->assertSame(['Dice Rolling', 'Trading'], $game['mechanics']);
    $this->assertSame(['Economic', 'Negotiation'], $game['categories']);
    $this->assertSame(['Klaus Teuber'], $game['designers']);
    // Only the first publisher is kept.
    $this->assertSame('KOSMOS', $game['publisher']);
  }

  /**
   * Malformed XML yields an empty list rather than throwing.
   */
  public function testParseThingsHandlesGarbage(): void {
    $fetcher = $this->fetcher($this->createMock(ClientInterface::class));
    $this->assertSame([], $fetcher->parseThings('not xml <<<'));
  }

  /**
   * Fetch() requests the endpoint and returns parsed games on a 200.
   */
  public function testFetchReturnsParsedGames(): void {
    $client = $this->createMock(ClientInterface::class);
    $client->expects($this->once())
      ->method('request')
      ->with('GET', $this->stringContains('xmlapi2/thing'), $this->anything())
      ->willReturn(new Response(200, [], self::SAMPLE_XML));

    $games = $this->fetcher($client)->fetch([13]);

    $this->assertCount(1, $games);
    $this->assertSame('Catan', $games[0]['name']);
  }

  /**
   * Fetch() retries while BGG answers 202 (queued), then parses the 200.
   */
  public function testFetchRetriesOnQueuedResponse(): void {
    $client = $this->createMock(ClientInterface::class);
    $client->expects($this->exactly(3))
      ->method('request')
      ->willReturnOnConsecutiveCalls(
        new Response(202),
        new Response(202),
        new Response(200, [], self::SAMPLE_XML),
      );

    $logger = $this->createMock(LoggerChannelInterface::class);
    $factory = $this->createMock(LoggerChannelFactoryInterface::class);
    $factory->method('get')->willReturn($logger);

    // Subclass to neutralise the real sleep between retries.
    $fetcher = new class($client, $factory) extends BggFetcher {

      /**
       * {@inheritdoc}
       */
      protected function sleepBetweenRetries(int $attempt): void {
        // No-op in tests: do not actually sleep between retries.
      }

    };

    $games = $fetcher->fetch([13]);
    $this->assertCount(1, $games);
    $this->assertSame('Catan', $games[0]['name']);
  }

  /**
   * A non-200/202 response yields no games (and is logged, not thrown).
   */
  public function testFetchReturnsEmptyOnError(): void {
    $client = $this->createMock(ClientInterface::class);
    $client->method('request')->willReturn(new Response(500));

    $this->assertSame([], $this->fetcher($client)->fetch([13]));
  }

}
