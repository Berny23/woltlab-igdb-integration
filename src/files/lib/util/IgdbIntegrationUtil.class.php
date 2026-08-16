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
	private static function isConnectionDataValid()
	{
		return (!empty(IGDB_INTEGRATION_AUTH_CLIENT_ID) && !empty(IGDB_INTEGRATION_AUTH_CLIENT_SECRET) && !empty(IGDB_INTEGRATION_GENERAL_RESULT_LIMIT));
	}

	/**
	 * Saves a new authentication token for the Twitch/IGDB API in the hidden user option.
	 */
	private static function saveNewAccessToken(): bool
	{
		if (self::$client === null) {
			self::$client = HttpFactory::getDefaultClient();
		}
		$request = new Request('POST', self::TWITCH_URL_BASE . '?client_id=' . rawurlencode(IGDB_INTEGRATION_AUTH_CLIENT_ID) . '&client_secret=' . rawurlencode(IGDB_INTEGRATION_AUTH_CLIENT_SECRET) . '&grant_type=client_credentials');

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
	 * Returns response with fetched game data from IGDB.
	 */
	private static function fetchGameDataByName($name)
	{
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
		$body = 'search "' . str_replace('"', '', $name) . '";
				fields id,name,alternative_names.comment,alternative_names.name,first_release_date,platforms.abbreviation,platforms.name,summary,cover.image_id,slug,game_localizations.cover.image_id,game_localizations.region;
				limit ' . IGDB_INTEGRATION_GENERAL_RESULT_LIMIT . ';';
		$request = new Request('POST', self::URL_BASE . 'games', $headers, $body);
		return self::$client->send($request);
	}

	/**
	 * Updates the game database with search results, if gameId doesn't already exist.
	 */
	public static function updateDatabaseGamesByName($name, $isRetry = false): bool
	{
		if (!self::isConnectionDataValid()) {
			return false;
		}

		try {
			$response = self::fetchGameDataByName($name);
		} catch (Exception $ex) {
			if (self::saveNewAccessToken()) {
				// Retry IGDB request if successfully got new token
				$response = self::fetchGameDataByName($name);
				self::$tempAccessToken = null;
			} else {
				// Failed getting new token
				return false;
			}
		}

		// Insert into games database
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
					localizedCovers = ?
				ON DUPLICATE KEY UPDATE
					name = ?,
					germanName = ?,
					releaseYear = ?,
					platforms = ?,
					summary = ?,
					coverImageId = ?,
					slug = ?,
					localizedCovers = ?";
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
						if (empty($gameGermanName) && (stripos($altName->comment, 'german') !== false || stripos($altName->comment, 'german') !== false)) {
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

			$gameId = $game->id;
			$gameName = $game->name ?? '';
			$gameYear = isset($game->first_release_date) ? gmdate('Y', $game->first_release_date) : null;
			$gameSummary = $game->summary ?? '';
			$gameCoverId = isset($game->cover) ? $game->cover->image_id : 'nocover';
			$gameSlug = $game->slug ?? '';
			$gameLocalizedCoversJson = JSON::encode($gameLocalizedCovers);

			$statement->execute([$gameId, $gameName, $gameGermanName, $gameYear, $gamePlatforms, $gameSummary, $gameCoverId, $gameSlug, $gameLocalizedCoversJson,
								/* UPDATE starts here */
								$gameName, $gameGermanName, $gameYear, $gamePlatforms, $gameSummary, $gameCoverId, $gameSlug, $gameLocalizedCoversJson]);
		}
		WCF::getDB()->commitTransaction();

		return true;
	}

	public static function validateRating($value)
	{
		return $value != 0;
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
	 * Returns the preferred IGDB region id for region-specific covers, or 0 if disabled.
	 */
	public static function getPreferredRegionId(): int
	{
		$region = WCF::getUser()->getUserOption('igdb_integration_preferred_region');
		if ($region === null || $region === '' || $region === 'default') {
			$region = IGDB_INTEGRATION_GENERAL_PREFERRED_REGION;
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
