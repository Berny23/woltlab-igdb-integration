<?php

namespace wcf\util;

use Exception;
use wcf\system\request\LinkHandler;
use wcf\util\CryptoUtil;
use wcf\system\io\HttpFactory;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Request;
use \wcf\system\WCF;
use \wcf\data\option\OptionEditor;
use \wcf\data\option\Option;
use wcf\system\user\activity\point\UserActivityPointHandler;
use wcf\system\user\group\assignment\UserGroupAssignmentHandler;

/**
 * A utility class for API interactions with IGDB.
 *
 * @author      Berny23
 * @copyright   2026 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\Util
 * @see         https://api-docs.igdb.com/
 */
class IgdbIntegrationUtil
{
	const ACTIVITY_POINT_OBJECT_TYPE = 'de.berny23.igdb_integration.activityPointEvent.game';
	const URL_BASE = 'https://api.igdb.com/v4/';
	const TWITCH_URL_BASE = 'https://id.twitch.tv/oauth2/token';
	const COVER_URL_BASE = 'https://images.igdb.com/igdb/image/upload/t_cover_med/';
	const COVER_URL_FILETYPE = '.jpg';
	const STEAM_API_URL_BASE = 'https://api.steampowered.com/';

	/**
	 * Id of Steam in IGDB's external game source list.
	 */
	const EXTERNAL_GAME_SOURCE_STEAM = 1;

	/**
	 * Fields requested for every game fetched from IGDB.
	 */
	const GAME_FIELDS = 'id,name,alternative_names.comment,alternative_names.name,first_release_date,platforms.abbreviation,platforms.name,summary,cover.image_id,slug,game_localizations.cover.image_id,game_localizations.region,external_games.uid,external_games.external_game_source';

	/**
	 * Roman numeral words replaced during title normalization.
	 */
	private const ROMAN_NUMERALS = [
		'i' => 1, 'ii' => 2, 'iii' => 3, 'iv' => 4, 'v' => 5,
		'vi' => 6, 'vii' => 7, 'viii' => 8, 'ix' => 9, 'x' => 10,
		'xi' => 11, 'xii' => 12, 'xiii' => 13, 'xiv' => 14, 'xv' => 15,
		'xvi' => 16, 'xvii' => 17, 'xviii' => 18, 'xix' => 19, 'xx' => 20,
	];

	/**
	 * @var ClientInterface
	 */
	private static $client = null;

	/**
	 * Temporary access token to fix exception without reloading page.
	 */
	private static $tempAccessToken = null;

	/**
	 * Check if all authentication variables are available
	 */
	public static function isConnectionDataValid()
	{
		return (!empty(IGDB_INTEGRATION_AUTH_CLIENT_ID) && !empty(IGDB_INTEGRATION_AUTH_CLIENT_SECRET) && !empty(IGDB_INTEGRATION_GENERAL_RESULT_LIMIT));
	}

	/**
	 * Check if the Steam library import can be used, i.e. the IGDB credentials
	 * and the Steam Web API key are available.
	 */
	public static function isSteamConnectionDataValid()
	{
		return self::isConnectionDataValid()
			&& defined('IGDB_INTEGRATION_AUTH_STEAM_API_KEY')
			&& !empty(IGDB_INTEGRATION_AUTH_STEAM_API_KEY);
	}

	/**
	 * Saves a new authentication token for the Twitch/IGDB API in the hidden user option.
	 */
	private static function saveNewAccessToken(): bool
	{
		if (self::$client === null) {
			self::$client = HttpFactory::getDefaultClient();
		}
		// Send the credentials in the POST body so the client secret cannot
		// leak into proxy logs or exception messages containing the URL
		$request = new Request(
			'POST',
			self::TWITCH_URL_BASE,
			['Content-Type' => 'application/x-www-form-urlencoded'],
			http_build_query([
				'client_id' => IGDB_INTEGRATION_AUTH_CLIENT_ID,
				'client_secret' => IGDB_INTEGRATION_AUTH_CLIENT_SECRET,
				'grant_type' => 'client_credentials',
			])
		);

		try {
			$response = self::$client->send($request);
			self::$tempAccessToken = JSON::decode($response->getBody())['access_token'];

			// Update the option with the new token.
			$optionId = Option::getOptionByName('igdb_integration_auth_access_token')->getObjectID();
			OptionEditor::updateAll(array(
				$optionId => self::$tempAccessToken
			));

			return true;
		} catch (Exception $ex) {
			return false;
		}
	}

	/**
	 * Sends a query to the IGDB games endpoint and returns the response. The
	 * request waits for a free slot of the sitewide rate limit queue.
	 */
	private static function fetchIgdbGames($body)
	{
		IgdbIntegrationApiRateLimiter::acquireSlot(IgdbIntegrationApiRateLimiter::API_IGDB);

		if (self::$client === null) {
			self::$client = HttpFactory::getDefaultClient();
		}

		if (is_null(self::$tempAccessToken)) {
			$accessToken = IGDB_INTEGRATION_AUTH_ACCESS_TOKEN;
		} else {
			$accessToken = self::$tempAccessToken;
		}

		$headers = [
			'Client-ID' => IGDB_INTEGRATION_AUTH_CLIENT_ID,
			'Authorization' => 'Bearer ' . $accessToken
		];
		$request = new Request('POST', self::URL_BASE . 'games', $headers, $body);
		return self::$client->send($request);
	}

	/**
	 * Sends a query to the IGDB games endpoint, fetching a new access token and
	 * retrying once if the request fails, e.g. because the token has expired.
	 */
	private static function fetchIgdbGamesWithTokenRefresh($body)
	{
		try {
			return self::fetchIgdbGames($body);
		} catch (Exception $ex) {
			if (!self::saveNewAccessToken()) {
				throw $ex;
			}

			// The fresh token is kept in self::$tempAccessToken for all
			// following requests of this page load
			return self::fetchIgdbGames($body);
		}
	}

	/**
	 * Updates the game database with search results, if gameId doesn't already exist.
	 */
	public static function updateDatabaseGamesByName($name): bool
	{
		if (!self::isConnectionDataValid()) {
			return false;
		}

		$body = 'search "' . str_replace(['"', '\\'], '', $name) . '";
				fields ' . self::GAME_FIELDS . ';
				limit ' . IGDB_INTEGRATION_GENERAL_RESULT_LIMIT . ';';

		try {
			$response = self::fetchIgdbGamesWithTokenRefresh($body);
		} catch (Exception $ex) {
			return false;
		}

		self::insertGamesFromResponse($response);

		return true;
	}

	/**
	 * Updates the game database with all IGDB games that are linked to one of
	 * the given Steam app ids. Returns false if any request failed.
	 */
	public static function updateDatabaseGamesBySteamAppIds(array $steamAppIds): bool
	{
		if (!self::isConnectionDataValid() || empty($steamAppIds)) {
			return false;
		}

		// Multiple IGDB games (e.g. editions) can share an app id, so request
		// fewer app ids per batch than the maximum result limit of 500. The
		// request pacing is handled by the sitewide rate limit queue.
		$chunks = array_chunk($steamAppIds, 250);
		foreach ($chunks as $chunk) {
			$uidList = '("' . implode('","', array_map('intval', $chunk)) . '")';
			$body = 'fields ' . self::GAME_FIELDS . ';
					where external_games.uid = ' . $uidList . ' & external_games.external_game_source = ' . self::EXTERNAL_GAME_SOURCE_STEAM . ';
					limit 500;';

			try {
				$response = self::fetchIgdbGamesWithTokenRefresh($body);
			} catch (Exception $ex) {
				return false;
			}

			self::insertGamesFromResponse($response);
		}

		return true;
	}

	/**
	 * Updates the game database with all IGDB games with the given IGDB ids.
	 * Returns false if any request failed.
	 */
	public static function updateDatabaseGamesByIds(array $gameIds): bool
	{
		if (!self::isConnectionDataValid() || empty($gameIds)) {
			return false;
		}

		foreach (array_chunk($gameIds, 400) as $chunk) {
			$body = 'fields ' . self::GAME_FIELDS . ';
					where id = (' . implode(',', array_map('intval', $chunk)) . ');
					limit 500;';

			try {
				$response = self::fetchIgdbGamesWithTokenRefresh($body);
			} catch (Exception $ex) {
				return false;
			}

			self::insertGamesFromResponse($response);
		}

		return true;
	}

	/**
	 * Inserts or updates all games of an IGDB games response in the database.
	 */
	private static function insertGamesFromResponse($response)
	{
		$gamesJson = JSON::decode($response->getBody(), false);
		$sql = "INSERT INTO wcf1_igdb_integration_game
				SET gameId = ?,
					name = ?,
					germanName = ?,
					releaseYear = ?,
					platforms = ?,
					summary = ?,
					coverImageId = ?,
					slug = ?,
					localizedCovers = ?,
					steamAppId = ?
				ON DUPLICATE KEY UPDATE
					name = ?,
					germanName = ?,
					releaseYear = ?,
					platforms = ?,
					summary = ?,
					coverImageId = ?,
					slug = ?,
					localizedCovers = ?,
					steamAppId = COALESCE(?, steamAppId)";
		$statement = WCF::getDB()->prepare($sql);
		foreach ($gamesJson as $game) {
			$gamePlatforms = '';
			if (isset($game->platforms)) {
				foreach ($game->platforms as $platform) {
					if (isset($platform->abbreviation)) {
						$gamePlatforms .= $platform->abbreviation . ', ';
					} elseif (isset($platform->name)) {
						$gamePlatforms .= $platform->name . ', ';
					}
				}
				$gamePlatforms = substr($gamePlatforms, 0, -2); // Remove last separator
			}

			$gameGermanName = '';
			if (isset($game->alternative_names)) {
				foreach ($game->alternative_names as $altName) {
					if (isset($altName->comment) && isset($altName->name)) {
						// Find language name in comment of alternative name
						if (empty($gameGermanName) && (stripos($altName->comment, 'german') !== false || stripos($altName->comment, 'deutsch') !== false)) {
							$gameGermanName = $altName->name;
						}
					}
				}
			}

			// Collect region-specific covers, indexed by IGDB region id
			$gameLocalizedCovers = [];
			if (isset($game->game_localizations)) {
				foreach ($game->game_localizations as $localization) {
					if (isset($localization->region) && isset($localization->cover->image_id)) {
						$gameLocalizedCovers[$localization->region] = $localization->cover->image_id;
					}
				}
			}

			// A NULL app id never overwrites a known one on update, so links
			// backfilled by the Steam import survive later IGDB refreshes
			$gameSteamAppId = null;
			if (isset($game->external_games)) {
				foreach ($game->external_games as $externalGame) {
					if (isset($externalGame->external_game_source) && $externalGame->external_game_source == self::EXTERNAL_GAME_SOURCE_STEAM && !empty($externalGame->uid)) {
						$gameSteamAppId = intval($externalGame->uid);
						break;
					}
				}
			}

			$gameId = $game->id;
			$gameName = $game->name ?? '';
			$gameYear = isset($game->first_release_date) ? gmdate('Y', $game->first_release_date) : null;
			$gameSummary = $game->summary ?? '';
			$gameCoverId = isset($game->cover) ? $game->cover->image_id : 'nocover';
			$gameSlug = $game->slug ?? '';
			$gameLocalizedCoversJson = JSON::encode($gameLocalizedCovers);

			$statement->execute([$gameId, $gameName, $gameGermanName, $gameYear, $gamePlatforms, $gameSummary, $gameCoverId, $gameSlug, $gameLocalizedCoversJson, $gameSteamAppId,
								/* UPDATE starts here */
								$gameName, $gameGermanName, $gameYear, $gamePlatforms, $gameSummary, $gameCoverId, $gameSlug, $gameLocalizedCoversJson, $gameSteamAppId]);
		}
		WCF::getDB()->commitTransaction();
	}

	/**
	 * Returns the games owned by the given Steam account as a map,
	 * or null if the request failed. An empty result usually means
	 * that the "Game details" privacy setting of the profile is not public.
	 */
	public static function fetchSteamOwnedGames($steamId): ?array
	{
		if (!self::isSteamConnectionDataValid() || !preg_match('/^\d{17}$/', $steamId)) {
			return null;
		}

		if (self::$client === null) {
			self::$client = HttpFactory::getDefaultClient();
		}

		$url = self::STEAM_API_URL_BASE . 'IPlayerService/GetOwnedGames/v1/?' . http_build_query([
			'key' => IGDB_INTEGRATION_AUTH_STEAM_API_KEY,
			'steamid' => $steamId,
			'include_appinfo' => 1,
			'include_played_free_games' => 1,
		]);

		try {
			// Wait for a free slot of the sitewide rate limit queue
			IgdbIntegrationApiRateLimiter::acquireSlot(IgdbIntegrationApiRateLimiter::API_STEAM);

			$response = self::$client->send(new Request('GET', $url));
			$data = JSON::decode($response->getBody());
		} catch (Exception $ex) {
			return null;
		}

		if (!isset($data['response']) || !is_array($data['response'])) {
			return null;
		}

		$games = [];
		foreach ($data['response']['games'] ?? [] as $game) {
			if (isset($game['appid']) && isset($game['name'])) {
				$games[intval($game['appid'])] = $game['name'];
			}
		}

		return $games;
	}

	/**
	 * Normalizes a game title for matching: lowercase, punctuation and symbols
	 * like ™ collapsed to single spaces. Optionally, roman numeral words are
	 * converted to numbers for a less strict second matching pass.
	 */
	public static function normalizeGameTitle($title, bool $convertRomanNumerals = false): string
	{
		$title = mb_strtolower($title);
		$title = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $title);
		$title = trim(preg_replace('/ +/', ' ', $title));

		if ($convertRomanNumerals) {
			$words = explode(' ', $title);
			foreach ($words as &$word) {
				if (isset(self::ROMAN_NUMERALS[$word])) {
					$word = (string)self::ROMAN_NUMERALS[$word];
				}
			}
			unset($word);
			$title = implode(' ', $words);
		}

		return $title;
	}

	public static function validateRating($value)
	{
		return $value != 0;
	}

	/**
	 * Returns the SQL expression that resolves the localized display name of a
	 * game, falling back to the original name.
	 */
	public static function getDisplayNameSql(): string
	{
		$name = self::getLocalizedGameNameColumn();

		return "CASE WHEN " . $name . " = '' THEN name ELSE " . $name . " END";
	}

	/**
	 * Returns the full cover image url for a game, using the localized cover of
	 * the preferred region if available and the image proxy if enabled.
	 */
	public static function getCoverImageUrl($coverImageId, $localizedCovers): string
	{
		$coverImageId = self::getLocalizedCoverImageId($coverImageId, $localizedCovers);

		return self::getImageProxyLink(self::COVER_URL_BASE . $coverImageId . self::COVER_URL_FILETYPE);
	}

	/**
	 * Synchronizes the activity points of a user with the given owned game count.
	 */
	public static function updateActivityPoints($userId, $gameCount)
	{
		$handler = UserActivityPointHandler::getInstance();
		$objectType = $handler->getObjectTypeByName(self::ACTIVITY_POINT_OBJECT_TYPE);
		if ($objectType === null) {
			return;
		}

		// Write the absolute owned game count instead of firing incremental
		// events, so the points are always correct regardless of history
		$sql = "INSERT INTO wcf1_user_activity_point
						(userID, objectTypeID, activityPoints, items)
				VALUES (?, ?, ?, ?)
				ON DUPLICATE KEY UPDATE activityPoints = VALUES(activityPoints),
						items = VALUES(items)";
		$statement = WCF::getDB()->prepare($sql);
		$statement->execute([$userId, $objectType->objectTypeID, $gameCount * $objectType->points, $gameCount]);

		$handler->updateUsers([$userId]);
		UserGroupAssignmentHandler::getInstance()->checkUsers([$userId]);
	}

	/**
	 * Recomputes the playerCount and averageRating columns of all games from
	 * the game <-> user association rows. Used to fix drift caused by changes
	 * that bypass the game actions, e.g. deleted user accounts
	 */
	public static function updateAllGameStats()
	{
		$sql = "UPDATE wcf1_igdb_integration_game game
				LEFT JOIN (
					SELECT gameId,
						COUNT(*) AS playerCount,
						ROUND(AVG(CASE WHEN rating > 0 THEN rating END)) AS averageRating
					FROM wcf1_igdb_integration_game_user
					GROUP BY gameId
				) stats ON stats.gameId = game.gameId
				SET game.playerCount = COALESCE(stats.playerCount, 0),
					game.averageRating = COALESCE(stats.averageRating, 0)";
		$statement = WCF::getDB()->prepare($sql);
		$statement->execute();
	}

	/**
	 * Returns the preferred IGDB region id for region-specific covers, or 0 if disabled.
	 */
	public static function getPreferredRegionId(): int
	{
		$region = WCF::getUser()->getUserOption('igdb_integration_preferred_region');
		if ($region === null || $region === '' || $region === 'default') {
			$region = defined('IGDB_INTEGRATION_GENERAL_PREFERRED_REGION')
				? IGDB_INTEGRATION_GENERAL_PREFERRED_REGION
				: 0;
		}

		return intval($region);
	}

	/**
	 * Returns the cover image id of the preferred region, falling back to the default cover.
	 */
	public static function getLocalizedCoverImageId($coverImageId, $localizedCovers): string
	{
		$regionId = self::getPreferredRegionId();
		if ($regionId && !empty($localizedCovers)) {
			try {
				$covers = JSON::decode($localizedCovers);
				if (!empty($covers[$regionId])) {
					return $covers[$regionId];
				}
			} catch (Exception $ex) {
				// Ignore invalid data
			}
		}

		return $coverImageId;
	}

	/**
	 * Returns all distinct platform names of the games in the database.
	 */
	public static function getAvailablePlatforms(): array
	{
		$sql = "SELECT DISTINCT platforms
				FROM wcf1_igdb_integration_game
				WHERE platforms <> ''";
		$statement = WCF::getDB()->prepare($sql);
		$statement->execute();
		$availablePlatforms = [];
		while ($platforms = $statement->fetchColumn()) {
			foreach (ArrayUtil::trim(explode(',', $platforms)) as $platform) {
				$availablePlatforms[$platform] = $platform;
			}
		}
		natcasesort($availablePlatforms);

		return $availablePlatforms;
	}

	/**
	 * Returns the link to a given image url via image proxy
	 * @see https://www.woltlab.com/community/thread/297027-image-proxy-fehlerhaft/?postID=1903894#post1903894
	 */
	public static function getImageProxyLink(string $link): string
	{
		// Return normal link if proxy is disabled
		if (!MODULE_IMAGE_PROXY) {
			return $link;
		}

		try {
			return LinkHandler::getInstance()->getLink(
				'ImageProxy',
				['key' => CryptoUtil::createSignedString($link)]
			);
		} catch (Exception $e) {
			return $link;
		}
	}

	/**
	 * Returns the localized game name.
	 */
	public static function getLocalizedGameNameColumn(): string
	{
		switch (WCF::getLanguage()->getFixedLanguageCode()) {
			case 'de':
				$localizedNameColumn = 'germanName';
				break;
			default:
				$localizedNameColumn = 'name';
				break;
		}
		return $localizedNameColumn;
	}
}
