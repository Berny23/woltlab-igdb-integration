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
	const COVER_URL_BASE_BIG = 'https://images.igdb.com/igdb/image/upload/t_cover_big/';
	// Retina (DPR 2.0) variant, IGDB offers it for every size by appending "_2x"
	const COVER_URL_BASE_BIG_2X = 'https://images.igdb.com/igdb/image/upload/t_cover_big_2x/';
	const COVER_URL_FILETYPE = '.jpg';
	const STEAM_API_URL_BASE = 'https://api.steampowered.com/';
	const GOG_URL_BASE = 'https://www.gog.com/';

	/**
	 * Id of Steam in IGDB's external game source list.
	 */
	const EXTERNAL_GAME_SOURCE_STEAM = 1;

	/**
	 * Id of GOG in IGDB's external game source list.
	 */
	const EXTERNAL_GAME_SOURCE_GOG = 5;

	/**
	 * Number of games per page of the public GOG profile games endpoint. The
	 * page size is fixed by GOG and only used to size progress indicators.
	 */
	const GOG_GAMES_PER_PAGE = 50;

	/**
	 * Pattern of a valid GOG username.
	 */
	const GOG_USERNAME_REGEX = '/^[a-zA-Z0-9._+-]{1,60}$/';

	/**
	 * Fields requested for every game fetched from IGDB.
	 */
	const GAME_FIELDS = 'id,name,alternative_names.comment,alternative_names.name,first_release_date,platforms.abbreviation,platforms.name,summary,cover.image_id,slug,game_localizations.cover.image_id,game_localizations.region,external_games.uid,external_games.external_game_source,external_games.url';

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
	 * Alias matches are included by default for the manual search; the imports
	 * defer them via updateDatabaseGamesByAlternativeName() to the titles that
	 * the search results did not match, saving the second request.
	 */
	public static function updateDatabaseGamesByName($name, bool $includeAliasMatches = true): bool
	{
		if (!self::isConnectionDataValid()) {
			return false;
		}

		// Trademark symbols make the IGDB search miss otherwise exact matches,
		// e.g. GOG's "Ultima II™ " not finding "Ultima II: The Revenge of the
		// Enchantress"
		$name = trim(preg_replace('/[™®©]/u', '', $name));

		$body = 'search "' . str_replace(['"', '\\'], '', $name) . '";
				fields ' . self::GAME_FIELDS . ';
				limit ' . IGDB_INTEGRATION_GENERAL_RESULT_LIMIT . ';';

		try {
			$response = self::fetchIgdbGamesWithTokenRefresh($body);
		} catch (Exception $ex) {
			return false;
		}

		self::insertGamesFromResponse($response);

		// A trailing year like "doom 2016" is a hint to find the game of that
		// year. The plain search above still runs, because the year may be part
		// of the title itself ("FIFA 2000"), and the date window is widened by
		// a year for offsets and regional delays.
		$yearSearch = self::parseSearchYear($name);
		if ($yearSearch !== null) {
			[$title, $year] = $yearSearch;
			$body = 'search "' . str_replace(['"', '\\'], '', $title) . '";
					fields ' . self::GAME_FIELDS . ';
					where first_release_date >= ' . gmmktime(0, 0, 0, 1, 1, $year - 1)
						. ' & first_release_date < ' . gmmktime(0, 0, 0, 1, 1, $year + 2) . ';
					limit ' . IGDB_INTEGRATION_GENERAL_RESULT_LIMIT . ';';

			try {
				$response = self::fetchIgdbGamesWithTokenRefresh($body);
			} catch (Exception $ex) {
				return false;
			}

			self::insertGamesFromResponse($response);
		}

		if ($includeAliasMatches) {
			return self::updateDatabaseGamesByAlternativeName($name);
		}

		return true;
	}

	/**
	 * Updates the game database with all IGDB games whose alternative name
	 * matches the given name exactly (case-insensitive). Because the search
	 * ranking may push a game with matching alias out of the result window (e.g.
	 * "Overwatch 2" => "Overwatch", while many season entries share the prefix).
	 */
	public static function updateDatabaseGamesByAlternativeName($name): bool
	{
		if (!self::isConnectionDataValid()) {
			return false;
		}

		$name = trim(preg_replace('/[™®©]/u', '', $name));

		$body = 'fields ' . self::GAME_FIELDS . ';
				where alternative_names.name ~ "' . str_replace(['"', '\\'], '', $name) . '";
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
	 * Updates the game database with all IGDB games that are linked to one of
	 * the given GOG product ids. Returns false if any request failed.
	 */
	public static function updateDatabaseGamesByGogIds(array $gogIds): bool
	{
		if (!self::isConnectionDataValid() || empty($gogIds)) {
			return false;
		}

		// Multiple IGDB games (e.g. editions) can share a product id, so
		// request fewer product ids per batch than the maximum result limit of
		// 500. The request pacing is handled by the sitewide rate limit queue.
		$chunks = array_chunk($gogIds, 250);
		foreach ($chunks as $chunk) {
			$uidList = '("' . implode('","', array_map('intval', $chunk)) . '")';
			$body = 'fields ' . self::GAME_FIELDS . ';
					where external_games.uid = ' . $uidList . ' & external_games.external_game_source = ' . self::EXTERNAL_GAME_SOURCE_GOG . ';
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
	 * Returns the number of games received from IGDB (ids that IGDB no longer
	 * knows are missing from that count), or null if the connection data is
	 * invalid or any request failed.
	 */
	public static function updateDatabaseGamesByIds(array $gameIds): ?int
	{
		if (!self::isConnectionDataValid() || empty($gameIds)) {
			return null;
		}

		$receivedGameCount = 0;
		foreach (array_chunk($gameIds, 400) as $chunk) {
			$body = 'fields ' . self::GAME_FIELDS . ';
					where id = (' . implode(',', array_map('intval', $chunk)) . ');
					limit 500;';

			try {
				$response = self::fetchIgdbGamesWithTokenRefresh($body);
			} catch (Exception $ex) {
				return null;
			}

			$receivedGameCount += self::insertGamesFromResponse($response);
		}

		return $receivedGameCount;
	}

	/**
	 * Inserts or updates all games of an IGDB games response in the database.
	 * Returns the number of games contained in the response.
	 */
	private static function insertGamesFromResponse($response): int
	{
		$gamesJson = JSON::decode($response->getBody(), false);
		$sql = "INSERT INTO wcf1_igdb_integration_game
				SET gameId = ?,
					name = ?,
					germanName = ?,
					alternativeNames = ?,
					releaseYear = ?,
					platforms = ?,
					summary = ?,
					coverImageId = ?,
					slug = ?,
					localizedCovers = ?,
					steamAppId = ?,
					gogId = ?,
					gogSlug = ?
				ON DUPLICATE KEY UPDATE
					name = ?,
					germanName = ?,
					alternativeNames = ?,
					releaseYear = ?,
					platforms = ?,
					summary = ?,
					coverImageId = ?,
					slug = ?,
					localizedCovers = ?,
					steamAppId = COALESCE(?, steamAppId),
					gogId = COALESCE(?, gogId),
					gogSlug = IF(? = '', gogSlug, ?)";
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
			$gameAlternativeNames = [];
			if (isset($game->alternative_names)) {
				foreach ($game->alternative_names as $altName) {
					if (!isset($altName->name)) {
						continue;
					}

					// Collect all aliases for the import name matching, e.g.
					// "Ultima 3" for "Ultima III: Exodus"
					if (!in_array($altName->name, $gameAlternativeNames)) {
						$gameAlternativeNames[] = $altName->name;
					}

					if (isset($altName->comment)) {
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

			// A NULL external id never overwrites a known one on update, so
			// links backfilled by the imports survive later IGDB refreshes
			$gameSteamAppId = null;
			$gameGogId = null;
			$gameGogSlug = '';
			if (isset($game->external_games)) {
				foreach ($game->external_games as $externalGame) {
					if (!isset($externalGame->external_game_source) || empty($externalGame->uid)) {
						continue;
					}
					if ($gameSteamAppId === null && $externalGame->external_game_source == self::EXTERNAL_GAME_SOURCE_STEAM) {
						$gameSteamAppId = intval($externalGame->uid);
					} elseif ($gameGogId === null && $externalGame->external_game_source == self::EXTERNAL_GAME_SOURCE_GOG) {
						$gameGogId = intval($externalGame->uid);
						$gameGogSlug = self::getGogSlugFromUrl($externalGame->url ?? '');
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
			// Unescaped unicode keeps the aliases searchable with LIKE; the hex
			// digits of \u escapes would otherwise match digit searches, e.g.
			// "3" finding the "イ" of a Japanese alias
			$gameAlternativeNamesJson = JSON::encode($gameAlternativeNames, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

			$statement->execute([$gameId, $gameName, $gameGermanName, $gameAlternativeNamesJson, $gameYear, $gamePlatforms, $gameSummary, $gameCoverId, $gameSlug, $gameLocalizedCoversJson, $gameSteamAppId, $gameGogId, $gameGogSlug,
								/* UPDATE starts here */
								$gameName, $gameGermanName, $gameAlternativeNamesJson, $gameYear, $gamePlatforms, $gameSummary, $gameCoverId, $gameSlug, $gameLocalizedCoversJson, $gameSteamAppId, $gameGogId, $gameGogSlug, $gameGogSlug]);
		}
		WCF::getDB()->commitTransaction();

		return count($gamesJson);
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
	 * Returns one page of the games owned by the given GOG account, or null if
	 * the request failed. GOG returns a 404 error for unknown usernames, for
	 * profiles whose games are not publicly visible and for pages beyond the
	 * last one, so all of these cases end up as null as well.
	 *
	 * The result contains the games of the page as a map of GOG product id to
	 * title, the total game count and the total page count.
	 */
	public static function fetchGogLibraryPage($username, int $page): ?array
	{
		// GOG needs no API key because the public profile endpoint is used.
		// The IGDB credentials are needed for the matching
		if (!self::isConnectionDataValid() || !preg_match(self::GOG_USERNAME_REGEX, $username) || $page < 1) {
			return null;
		}

		if (self::$client === null) {
			self::$client = HttpFactory::getDefaultClient();
		}

		$url = self::GOG_URL_BASE . 'u/' . rawurlencode($username) . '/games/stats?' . http_build_query([
			'page' => $page,
		]);

		try {
			// Wait for a free slot of the sitewide rate limit queue
			IgdbIntegrationApiRateLimiter::acquireSlot(IgdbIntegrationApiRateLimiter::API_GOG);

			$response = self::$client->send(new Request('GET', $url));
			$data = JSON::decode($response->getBody());
		} catch (Exception $ex) {
			return null;
		}

		if (!isset($data['_embedded']['items']) || !is_array($data['_embedded']['items'])) {
			return null;
		}

		$games = [];
		foreach ($data['_embedded']['items'] as $item) {
			if (isset($item['game']['id']) && isset($item['game']['title'])) {
				$games[intval($item['game']['id'])] = $item['game']['title'];
			}
		}

		return [
			'games' => $games,
			'total' => intval($data['total'] ?? count($games)),
			'pages' => max(1, intval($data['pages'] ?? 1)),
		];
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
	 * Returns the given string as it appears inside the alternativeNames JSON
	 * array, without the surrounding quotes.
	 */
	private static function encodeAliasSearchString(string $value): string
	{
		return substr(JSON::encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 1, -1);
	}

	/**
	 * Returns the SQL condition and its parameters that match one part of a
	 * name search against all known names of a game, including its aliases,
	 * e.g. "Dark Souls 3" for "Dark Souls III".
	 */
	public static function getNameSearchCondition(string $part): array
	{
		// alternativeNames holds a JSON array, so quotes and backslashes are
		// stored escaped and need the same encoding to match
		$jsonPart = self::encodeAliasSearchString($part);

		return [
			"(name LIKE ? OR germanName LIKE ? OR alternativeNames LIKE ?)",
			['%' . $part . '%', '%' . $part . '%', '%' . $jsonPart . '%'],
		];
	}

	/**
	 * Splits a search string that ends with a release year into the title and
	 * the year, e.g. "doom 2016" or "prey (2017)". Returns null if there is no
	 * trailing year or nothing in front of it ("1942" is a title by itself).
	 */
	public static function parseSearchYear(string $search): ?array
	{
		if (!preg_match('/^(.+?)(?:\s+|\s*[(\[])((?:19|20)\d{2})[)\]]?$/u', trim($search), $matches)) {
			return null;
		}

		$title = trim($matches[1]);
		if ($title === '') {
			return null;
		}

		return [$title, (int)$matches[2]];
	}

	/**
	 * Returns the list of [sql, params] conditions for a search string, one
	 * per space-separated part, all of which have to match. A trailing year
	 * ("doom 2016") is also satisfied by a release within a year of it, the
	 * same window the IGDB search uses, so its results actually show up.
	 */
	public static function getSearchConditions(string $search): array
	{
		$conditions = [];

		$yearSearch = self::parseSearchYear($search);
		if ($yearSearch !== null) {
			[$search, $year] = $yearSearch;
			[$conditionSql, $conditionParams] = self::getNameSearchCondition((string)$year);
			$conditions[] = [
				'(' . $conditionSql . ' OR releaseYear BETWEEN ? AND ?)',
				array_merge($conditionParams, [$year - 1, $year + 1]),
			];
		}

		foreach (explode(' ', $search) as $part) {
			$conditions[] = self::getNameSearchCondition($part);
		}

		return $conditions;
	}

	/**
	 * Returns the SQL expression that ranks a game by how well its names match
	 * the full search string: exact match, then prefix match, then the search
	 * string as a whole phrase, then everything the per-part conditions let
	 * through. Lower is better, so it is meant for an ascending ORDER BY in
	 * front of the user-defined sort order.
	 */
	public static function getNameSearchRelevanceSql(string $search): string
	{
		$yearSearch = self::parseSearchYear($search);
		if ($yearSearch !== null) {
			// "doom 2016": The full string ranks titles containing the year
			// ("FIFA 2004") first, then the title alone, where ties are ranked
			// by how close the release is to the year
			[$title, $year] = $yearSearch;

			return self::getNameRelevanceSql($search)
				. ', ' . self::getNameRelevanceSql($title)
				. ', CASE
					WHEN releaseYear = ' . $year . ' THEN 0
					WHEN releaseYear BETWEEN ' . ($year - 1) . ' AND ' . ($year + 1) . ' THEN 1
					ELSE 2
					END';
		}

		return self::getNameRelevanceSql($search);
	}

	/**
	 * Returns the ranking expression of getNameSearchRelevanceSql() for a
	 * single name without year handling.
	 */
	private static function getNameRelevanceSql(string $search): string
	{
		$db = WCF::getDB();
		$name = $db->escapeString($search);

		// An alias is exact if its full JSON entry including both quotes
		// occurs in the column, and a prefix if the opening quote directly
		// precedes the search string
		$jsonSearch = self::encodeAliasSearchString($search);
		$aliasExact = $db->escapeString('"' . $jsonSearch . '"');
		$aliasPrefix = $db->escapeString('"' . $jsonSearch);
		$aliasPhrase = $db->escapeString($jsonSearch);

		return "CASE
				WHEN name LIKE '" . $name . "'
					OR germanName LIKE '" . $name . "'
					OR alternativeNames LIKE '%" . $aliasExact . "%' THEN 0
				WHEN name LIKE '" . $name . "%'
					OR germanName LIKE '" . $name . "%'
					OR alternativeNames LIKE '%" . $aliasPrefix . "%' THEN 1
				WHEN name LIKE '%" . $name . "%'
					OR germanName LIKE '%" . $name . "%'
					OR alternativeNames LIKE '%" . $aliasPhrase . "%' THEN 2
				ELSE 3
				END";
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
	 * Extracts the store slug from a GOG store url as delivered by IGDB's
	 * external game links, e.g. "https://www.gog.com/game/the_witcher" or
	 * "https://www.gog.com/en/game/the_witcher". Returns an empty string if
	 * the url has no recognizable slug.
	 */
	public static function getGogSlugFromUrl(string $url): string
	{
		if (preg_match('~gog\.com/(?:[a-z]{2}/)?game/([a-z0-9_\-]+)~i', $url, $matches)) {
			return $matches[1];
		}

		return '';
	}

	/**
	 * Returns the external store and database links of a game in display order
	 * (IGDB, Steam, GOG) as an array of [name => url], omitting links whose
	 * ids are unknown.
	 */
	public static function getGameLinks($game): array
	{
		$links = [];
		if ($game->slug) {
			$links['igdb'] = 'https://www.igdb.com/games/' . rawurlencode($game->slug);
		}
		if ($game->steamAppId) {
			$links['steam'] = 'https://store.steampowered.com/app/' . intval($game->steamAppId) . '/';
		}
		if ($game->gogSlug) {
			$links['gog'] = self::GOG_URL_BASE . 'game/' . rawurlencode($game->gogSlug);
		}

		return $links;
	}

	/**
	 * Returns the full cover image url for a game, using the localized cover of
	 * the preferred region if available and the image proxy if enabled. The
	 * retina variant is only available in combination with the large size.
	 */
	public static function getCoverImageUrl($coverImageId, $localizedCovers, bool $useLargeSize = false, bool $useRetinaSize = false): string
	{
		$coverImageId = self::getLocalizedCoverImageId($coverImageId, $localizedCovers);
		if ($useLargeSize) {
			$urlBase = $useRetinaSize ? self::COVER_URL_BASE_BIG_2X : self::COVER_URL_BASE_BIG;
		} else {
			$urlBase = self::COVER_URL_BASE;
		}

		return self::getImageProxyLink($urlBase . $coverImageId . self::COVER_URL_FILETYPE);
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
	 * Returns all distinct platform names of the games in the database,
	 * limited to the games owned by the given user if set.
	 */
	public static function getAvailablePlatforms(?int $userId = null): array
	{
		$parameters = [];
		if ($userId === null) {
			$sql = "SELECT DISTINCT platforms
					FROM wcf1_igdb_integration_game
					WHERE platforms <> ''";
		} else {
			$sql = "SELECT DISTINCT g.platforms
					FROM wcf1_igdb_integration_game g
					INNER JOIN wcf1_igdb_integration_game_user gu
					ON gu.gameId = g.gameId
					WHERE gu.userId = ?
					AND g.platforms <> ''";
			$parameters[] = $userId;
		}
		$statement = WCF::getDB()->prepare($sql);
		$statement->execute($parameters);
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
	 * Returns the query parameters of the current request as a string, without
	 * the given parameters, to keep things like the page number.
	 */
	public static function getPreservedRequestParameters(array $excludedParameters): string
	{
		$parameters = $_GET;
		foreach (array_keys($parameters) as $key) {
			// The controller route is already part of every generated link
			if (str_contains((string)$key, '/') || in_array((string)$key, $excludedParameters, true)) {
				unset($parameters[$key]);
			}
		}

		return http_build_query($parameters, '', '&');
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
