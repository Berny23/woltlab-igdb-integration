<?php

namespace wcf\system\menu\user\profile\content;

use wcf\util\ArrayUtil;
use wcf\util\IgdbIntegrationUtil;
use wcf\system\cache\runtime\UserProfileRuntimeCache;
use wcf\system\SingletonFactory;
use wcf\system\WCF;

/**
 * Shows the list of owned games on user profiles.
 *
 * @author      Berny23
 * @copyright   2026 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\System\Menu\User\Profile\Content
 */
class IgdbIntegrationGameListUserProfileMenuContent extends SingletonFactory implements IUserProfileMenuContent
{
	/**
	 * All sort fields supported by the profile game list.
	 */
	const VALID_SORT_FIELDS = ['ownRating', 'displayName', 'releaseYear', 'playerCount', 'lastInteractionTime'];

	/**
	 * Returns whether the current request contains filter parameters for the
	 * profile game list, i.e. the games tab should be shown.
	 */
	public static function hasFilterParameters(): bool
	{
		return isset($_REQUEST['gameSearch'])
			|| isset($_REQUEST['gameSortField'])
			|| isset($_REQUEST['gameSortOrder'])
			|| isset($_REQUEST['gamePlatforms']);
	}

	/**
	 * Returns the validated filter parameters of the current request, falling
	 * back to the defaults for missing values.
	 */
	public static function getFilterParameters(): array
	{
		$search = '';
		if (isset($_REQUEST['gameSearch'])) {
			$search = (string)$_REQUEST['gameSearch'];
		}

		$platforms = [];
		if (isset($_REQUEST['gamePlatforms']) && is_array($_REQUEST['gamePlatforms'])) {
			$platforms = array_filter(ArrayUtil::trim($_REQUEST['gamePlatforms']));
		}

		if (isset($_REQUEST['gameSortField']) && in_array($_REQUEST['gameSortField'], self::VALID_SORT_FIELDS)) {
			$sortField = $_REQUEST['gameSortField'];
		} else {
			// Guests have no user options, so fall back to the global defaults
			$userOptionGameSortField = WCF::getUser()->getUserOption('igdb_integration_default_game_sort_field');
			$sortField = ($userOptionGameSortField !== null && $userOptionGameSortField !== 'default') ? $userOptionGameSortField : IGDB_INTEGRATION_GENERAL_GAME_SORT_FIELD;

			// The profile list shows the profile owner's rating instead of the
			// average rating
			if ($sortField == 'averageRating' || !in_array($sortField, self::VALID_SORT_FIELDS)) {
				$sortField = 'ownRating';
			}
		}

		if (isset($_REQUEST['gameSortOrder']) && in_array($_REQUEST['gameSortOrder'], ['ASC', 'DESC'])) {
			$sortOrder = $_REQUEST['gameSortOrder'];
		} else {
			$userOptionGameSortOrder = WCF::getUser()->getUserOption('igdb_integration_default_game_sort_order');
			$sortOrder = ($userOptionGameSortOrder !== null && $userOptionGameSortOrder !== 'default') ? $userOptionGameSortOrder : IGDB_INTEGRATION_GENERAL_GAME_SORT_ORDER;

			if (!in_array($sortOrder, ['ASC', 'DESC'])) {
				$sortOrder = 'DESC';
			}
		}

		$pageNo = 1;
		if (isset($_REQUEST['pageNo'])) {
			$pageNo = max(1, intval($_REQUEST['pageNo']));
		}

		return [
			'search' => $search,
			'platforms' => $platforms,
			'sortField' => $sortField,
			'sortOrder' => $sortOrder,
			'pageNo' => $pageNo
		];
	}

	/**
	 * Returns the query parameter string of the given filter values, without
	 * the page number.
	 */
	public static function getLinkParameters(array $filter): string
	{
		$parameters = 'gameSearch=' . rawurlencode($filter['search'])
			. '&gameSortField=' . rawurlencode($filter['sortField'])
			. '&gameSortOrder=' . rawurlencode($filter['sortOrder']);
		foreach ($filter['platforms'] as $platform) {
			$parameters .= '&gamePlatforms[]=' . rawurlencode($platform);
		}

		return $parameters;
	}

	/**
	 * @inheritDoc
	 */
	public function isVisible($userID)
	{
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getContent($userID)
	{
		$filter = self::getFilterParameters();

		$itemsPerPage = IGDB_INTEGRATION_GENERAL_GAMES_PER_PAGE;
		$userOptionItemsPerPage = WCF::getUser()->getUserOption('igdb_integration_games_per_page');
		if (!is_null($userOptionItemsPerPage) && $userOptionItemsPerPage > 0) {
			$itemsPerPage = $userOptionItemsPerPage;
		}

		// Build the WHERE conditions shared by the count and the list query
		$conditionSql = "gu.userId = ?";
		$conditionParams = [$userID];

		if ($filter['search'] !== '') {
			// Search for all parts, separated with a space
			$parts = explode(' ', $filter['search']);
			foreach ($parts as $part) {
				$conditionSql .= " AND (name LIKE ? OR germanName LIKE ?)";
				$conditionParams[] = '%' . $part . '%';
				$conditionParams[] = '%' . $part . '%';
			}
		}

		if (!empty($filter['platforms'])) {
			// Match games that are available on any of the selected platforms
			$platformConditions = [];
			foreach ($filter['platforms'] as $platform) {
				$platformConditions[] = "FIND_IN_SET(?, REPLACE(platforms, ', ', ','))";
				$conditionParams[] = $platform;
			}
			$conditionSql .= " AND (" . implode(' OR ', $platformConditions) . ")";
		}

		// Get the filtered game count for the pagination
		$sql = "SELECT COUNT(*)
				FROM wcf1_igdb_integration_game g
				INNER JOIN wcf1_igdb_integration_game_user gu
				ON gu.gameId = g.gameId
				WHERE " . $conditionSql;
		$statement = WCF::getDB()->prepare($sql);
		$statement->execute($conditionParams);
		$filteredGameCount = intval($statement->fetchSingleColumn());

		$pages = max(1, intval(ceil($filteredGameCount / $itemsPerPage)));
		$pageNo = min($filter['pageNo'], $pages);

		// The own rating is the primary sort field by default, all other
		// fields use the display name to break ties
		$orderBySql = $filter['sortField'] . ' ' . $filter['sortOrder'];
		if ($filter['sortField'] != 'displayName') {
			$orderBySql .= ', displayName ASC';
		}

		$sql = "SELECT
					g.gameId AS gameId,
					coverImageId,
					localizedCovers,
					releaseYear,
					rating AS ownRating,
					g.playerCount AS playerCount,
					CASE WHEN
						EXISTS (
							SELECT userId
							FROM wcf1_igdb_integration_game_user guTemp
							WHERE guTemp.gameId = gu.gameId
							AND guTemp.userId = ?
						)
						THEN 1 ELSE 0 END
						AS isOwned,
					" . IgdbIntegrationUtil::getDisplayNameSql() . " AS displayName
				FROM wcf1_igdb_integration_game g
				INNER JOIN wcf1_igdb_integration_game_user gu
				ON gu.gameId = g.gameId
				WHERE " . $conditionSql . "
				ORDER BY " . $orderBySql;
		$statement = WCF::getDB()->prepare($sql, $itemsPerPage, ($pageNo - 1) * $itemsPerPage);
		$statement->execute(array_merge([WCF::getUser()->userID], $conditionParams));
		$userGames = $statement->fetchAll(\PDO::FETCH_ASSOC);

		// Generate image proxy links, if enabled
		foreach ($userGames as &$game) {
			$game['coverImageUrl'] = IgdbIntegrationUtil::getCoverImageUrl($game['coverImageId'], $game['localizedCovers']);
		}
		unset($game);

		$profile = UserProfileRuntimeCache::getInstance()->getObject($userID);

		// The pagination links keep the active filter, the page number itself
		// is appended by the pagination element
		$baseUrl = $profile->getLink();
		$baseUrl .= (str_contains($baseUrl, '?') ? '&' : '?') . self::getLinkParameters($filter);

		WCF::getTPL()->assign([
			'userGames' => $userGames,
			'userId' => $userID,
			// The displayed total is not affected by the filter
			'gameCount' => intval($profile->IgdbIntegrationGameCount),
			'gameListPageNo' => $pageNo,
			'gameListPages' => $pages,
			'gameListBaseUrl' => $baseUrl
		]);

		return WCF::getTPL()->fetch('igdbIntegrationGameListUserProfile');
	}
}
