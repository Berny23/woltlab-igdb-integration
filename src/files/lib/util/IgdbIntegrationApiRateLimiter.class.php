<?php

namespace wcf\util;

use Exception;
use wcf\system\WCF;

/**
 * Sitewide rate limiter for outgoing API requests. Every request to the IGDB,
 * Steam or GOG API must reserve a time slot here first, so that the providers'
 * rate limits are never exceeded, no matter how many page requests run
 * concurrently.
 *
 * @author      Berny23
 * @copyright   2026 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\Util
 */
class IgdbIntegrationApiRateLimiter
{
	const API_IGDB = 'igdb';
	const API_STEAM = 'steam';
	const API_GOG = 'gog';

	/**
	 * Minimum time between two requests per API in microseconds. IGDB allows
	 * 4 requests per second; Steam and GOG publish no hard rate limit, so a
	 * conservative pace is used. The GOG library is fetched in pages of 50
	 * games, so its interval is kept lower to not slow down large imports.
	 */
	private const REQUEST_INTERVALS = [
		self::API_IGDB => 250000,
		self::API_STEAM => 1000000,
		self::API_GOG => 500000,
	];

	/**
	 * Maximum time in microseconds to wait for a free slot before giving up,
	 * so that page requests do not pile up indefinitely under heavy use.
	 */
	private const MAX_WAIT = 15000000;

	/**
	 * Reserves the next free request slot of the given API and waits until it
	 * starts. Throws if the queue is so congested that the wait would exceed
	 * the maximum, which callers handle like a failed API request.
	 */
	public static function acquireSlot(string $api)
	{
		if (!isset(self::REQUEST_INTERVALS[$api])) {
			throw new Exception("Unknown API '" . $api . "'.");
		}
		$interval = self::REQUEST_INTERVALS[$api];
		$now = (int)(microtime(true) * 1000000);

		// Reserving the slot and reading it back via LAST_INSERT_ID() makes
		// the reservation a single atomic statement, so no database lock is
		// held while waiting for the slot to start
		$sql = "UPDATE wcf1_igdb_integration_api_slot
				SET nextSlotTime = LAST_INSERT_ID(GREATEST(nextSlotTime, ?) + ?)
				WHERE apiName = ?";
		$statement = WCF::getDB()->prepare($sql);
		$statement->execute([$now, $interval, $api]);

		if ($statement->getAffectedRows() === 0) {
			// First request for this API on this installation
			$sql = "INSERT IGNORE INTO wcf1_igdb_integration_api_slot
							(apiName, nextSlotTime)
					VALUES (?, 0)";
			$insertStatement = WCF::getDB()->prepare($sql);
			$insertStatement->execute([$api]);

			$statement->execute([$now, $interval, $api]);
		}

		$sql = "SELECT LAST_INSERT_ID()";
		$statement = WCF::getDB()->prepare($sql);
		$statement->execute();
		$slotStart = intval($statement->fetchSingleColumn()) - $interval;

		$wait = $slotStart - $now;
		if ($wait > self::MAX_WAIT) {
			throw new Exception("The " . $api . " API request queue is congested.");
		}
		if ($wait > 0) {
			usleep($wait);
		}
	}
}
