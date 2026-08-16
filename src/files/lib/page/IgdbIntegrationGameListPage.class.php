<?php

namespace wcf\page;

use wcf\data\IgdbIntegration\IgdbIntegrationGameList;
use wcf\util\ArrayUtil;
use wcf\util\HeaderUtil;
use wcf\util\IgdbIntegrationUtil;
use wcf\system\request\LinkHandler;
use wcf\system\WCF;
use wcf\data\user\User;
use wcf\data\user\UserProfile;

/**
 * Shows the list of games.
 *
 * @author      Berny23
 * @copyright   2026 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\Page
 */
class IgdbIntegrationGameListPage extends SortablePage
{
	/**
	 * @inheritDoc
	 */
	public $objectListClassName = IgdbIntegrationGameList::class;

	/**
	 * @inheritDoc
	 */
	public $defaultSortField = '';

	/**
	 * @inheritDoc
	 */
	public $defaultSortOrder = '';

	/**
	 * @inheritDoc
	 */
	public $validSortFields = ['displayName', 'releaseYear', 'playerCount', 'averageRating', 'lastInteractionTime'];

	/**
	 * @inheritDoc
	 */
	public $itemsPerPage = IGDB_INTEGRATION_GENERAL_GAMES_PER_PAGE;

	/**
	 * The name search field.
	 */
	private $searchField = '';

	/**
	 * The platforms selected in the platform filter.
	 */
	private $platformFilter = [];

	/**
	 * Show error message if retreiving IGDB data resulted in an error.
	 */
	private $showIgdbError = false;

	/**
	 * @inheritDoc
	 */
	public function readParameters()
	{
		parent::readParameters();

		$userOptionItemsPerPage = WCF::getUser()->getUserOption('igdb_integration_games_per_page');
		if (!is_null($userOptionItemsPerPage) && $userOptionItemsPerPage > 0) {
			$this->itemsPerPage = $userOptionItemsPerPage;
		}

		$userOptionGameSortField = WCF::getUser()->getUserOption('igdb_integration_default_game_sort_field');
		$this->defaultSortField = $userOptionGameSortField !== 'default' ? $userOptionGameSortField : IGDB_INTEGRATION_GENERAL_GAME_SORT_FIELD;

		$userOptionGameSortOrder = WCF::getUser()->getUserOption('igdb_integration_default_game_sort_order');
		$this->defaultSortOrder = $userOptionGameSortOrder !== 'default' ? $userOptionGameSortOrder : IGDB_INTEGRATION_GENERAL_GAME_SORT_ORDER;

		$this->searchField = '';
		if (isset($_REQUEST['searchField'])) {
			$this->searchField = $_REQUEST['searchField'];
		}

		$this->platformFilter = [];
		if (isset($_REQUEST['platforms']) && is_array($_REQUEST['platforms'])) {
			$this->platformFilter = array_filter(ArrayUtil::trim($_REQUEST['platforms']));
		}

		if (!empty($_POST)) {
			// Redirect the form submit to a GET request so that all search
			// parameters are visible in the URL
			$parameters = 'searchField=' . rawurlencode($this->searchField)
				. '&sortField=' . rawurlencode($this->sortField)
				. '&sortOrder=' . rawurlencode($this->sortOrder);
			foreach ($this->platformFilter as $platform) {
				$parameters .= '&platforms[]=' . rawurlencode($platform);
			}

			HeaderUtil::redirect(LinkHandler::getInstance()->getLink('IgdbIntegrationGameList', [], $parameters));

			exit;
		}

		if (isset($_REQUEST['searchField']) && !isset($_REQUEST['pageNo']) && WCF::getSession()->getPermission('user.igdb_integration.can_search_igdb')) {
			// Search for games on IGDB and update local database
			$result = IgdbIntegrationUtil::updateDatabaseGamesByName($this->searchField);
			$this->showIgdbError = !$result;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function assignVariables()
	{
		parent::assignVariables();

		// Generate image proxy links, if enabled
		$coverImageUrls = array();
		foreach ($this->objectList->getObjects() as $game) {
			$coverImageId = IgdbIntegrationUtil::getLocalizedCoverImageId($game->coverImageId, $game->localizedCovers);
			$coverImageUrls[$game->gameId] = IgdbIntegrationUtil::getImageProxyLink(IgdbIntegrationUtil::COVER_URL_BASE . $coverImageId . IgdbIntegrationUtil::COVER_URL_FILETYPE);
		}

		// Get game count for player toplist
		$userOptionPlayerToplistLimit = WCF::getUser()->getUserOption('igdb_integration_player_toplist_limit');
		if (is_null($userOptionPlayerToplistLimit) || $userOptionPlayerToplistLimit <= 0) {
			$userOptionPlayerToplistLimit = IGDB_INTEGRATION_GENERAL_PLAYER_TOPLIST_LIMIT;
		}
		$sql = "SELECT userId, COUNT(gameId) AS gameCount 
				FROM wcf1_igdb_integration_game_user 
				GROUP BY userId
				ORDER BY gameCount DESC
				LIMIT ?";
		$statement = WCF::getDB()->prepare($sql);
		$statement->execute([$userOptionPlayerToplistLimit]);
		$topPlayers = $statement->fetchAll(\PDO::FETCH_ASSOC);

		// Get links to the players in the toplist
		$topPlayerProfileLinks = array();
		foreach ($topPlayers as $player) {
			$topPlayerProfileLinks[$player['userId']] = (new UserProfile(new User($player['userId'])))->getAnchorTag();
		}

		// Collect all distinct platforms for the platform filter
		$availablePlatforms = IgdbIntegrationUtil::getAvailablePlatforms();

		// The game list itself is unfiltered on a fresh visit, but the default
		// platforms are pre-selected in the filter form for the next submit
		$platformPreselection = $this->platformFilter;
		if (!isset($_REQUEST['searchField'])) {
			$defaultPlatformFilter = WCF::getUser()->getUserOption('igdb_integration_default_platform_filter');
			if (empty($defaultPlatformFilter)) {
				$defaultPlatformFilter = IGDB_INTEGRATION_GENERAL_DEFAULT_PLATFORM_FILTER;
			}
			$platformPreselection = array_filter(ArrayUtil::trim(explode("\n", $defaultPlatformFilter)));
		}

		// Preserve the platform filter in pagination links
		$platformFilterParams = '';
		foreach ($this->platformFilter as $platform) {
			$platformFilterParams .= '&platforms[]=' . rawurlencode($platform);
		}

		WCF::getTPL()->assign([
			'searchField' => $this->searchField,
			'showIgdbError' => $this->showIgdbError,
			'coverImageUrls' => $coverImageUrls,
			'topPlayers' => $topPlayers,
			'topPlayerProfileLinks' => $topPlayerProfileLinks,
			'availablePlatforms' => $availablePlatforms,
			'platformFilter' => $platformPreselection,
			'platformFilterParams' => $platformFilterParams
		]);
	}

	/**
	 * @inheritDoc
	 */
	public function initObjectList()
	{
		parent::initObjectList();

		$name = IgdbIntegrationUtil::getLocalizedGameNameColumn();
		$this->objectList->sqlSelects .= "DISTINCT CASE WHEN 
											" . $name . " = '' 
											THEN name ELSE " . $name . " END 
											AS displayName,";
		$this->objectList->sqlSelects .= "COUNT(gu.userId) 
											OVER (PARTITION BY gu.gameId) 
											AS playerCount,";
		$this->objectList->sqlSelects .= "(
												SELECT DISTINCT ROUND(AVG(rating) 
												OVER (PARTITION BY guTempA.gameId), 0) 
												FROM wcf" . WCF_N . "_igdb_integration_game_user guTempA 
												WHERE guTempA.gameId = gu.gameId 
												AND guTempA.rating > 0
											) AS averageRating,";
		$this->objectList->sqlSelects .= "CASE WHEN 
											EXISTS (
												SELECT userId 
												FROM wcf" . WCF_N . "_igdb_integration_game_user guTempB 
												WHERE guTempB.gameId = gu.gameId 
												AND guTempB.userId = " . WCF::getUser()->userID . ") 
											THEN 1 ELSE 0 END 
											AS isOwned";
		$this->objectList->sqlJoins .= "LEFT JOIN wcf" . WCF_N . "_igdb_integration_game_user gu 
										ON gu.gameId = " . $this->objectList->getDatabaseTableAlias() . ".gameId";

		if (!empty($this->searchField)) {
			// Search for all parts, separated with a space
			$parts = explode(' ', $this->searchField);
			foreach ($parts as $part) {
				$this->objectList->getConditionBuilder()->add(
					"(name LIKE ?
					OR germanName LIKE ?)",
					['%' . $part . '%', '%' . $part . '%']
				);
			}
		}

		if (!empty($this->platformFilter)) {
			// Match games that are available on any of the selected platforms
			$conditions = [];
			$parameters = [];
			foreach ($this->platformFilter as $platform) {
				$conditions[] = "FIND_IN_SET(?, REPLACE(platforms, ', ', ','))";
				$parameters[] = $platform;
			}
			$this->objectList->getConditionBuilder()->add('(' . implode(' OR ', $conditions) . ')', $parameters);
		}
	}
}
