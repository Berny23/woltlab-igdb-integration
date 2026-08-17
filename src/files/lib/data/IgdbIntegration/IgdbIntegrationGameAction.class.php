<?php

namespace wcf\data\IgdbIntegration;

use wcf\data\IgdbIntegration\IgdbIntegrationGame;
use wcf\data\IgdbIntegration\IgdbIntegrationGameList;
use wcf\util\IgdbIntegrationUtil;
use wcf\system\WCF;
use wcf\data\user\UserEditor;
use wcf\data\user\User;
use wcf\data\AbstractDatabaseObjectAction;
use wcf\system\cache\runtime\UserProfileRuntimeCache;
use wcf\system\database\util\PreparedStatementConditionBuilder;
use wcf\system\event\EventHandler;
use wcf\system\form\builder\DialogFormDocument;
use wcf\system\form\builder\field\BooleanFormField;
use wcf\system\form\builder\field\RatingFormField;
use wcf\system\form\builder\field\TextFormField;
use wcf\system\form\builder\field\DescriptionFormField;
use wcf\system\form\builder\TemplateFormNode;
use wcf\system\exception\PermissionDeniedException;
use wcf\system\exception\UserInputException;
use wcf\system\user\activity\event\UserActivityEventHandler;
use wcf\util\JSON;
use wcf\util\StringUtil;

/**
 * Executes game-related actions.
 *
 * @author      Berny23
 * @copyright   2026 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\Data\IgdbIntegration
 */
class IgdbIntegrationGameAction extends AbstractDatabaseObjectAction
{
	/**
	 * Number of Steam app ids fetched from IGDB per import step.
	 */
	const STEAM_IMPORT_BATCH_SIZE = 250;

	/**
	 * Maximum number of per-title IGDB search requests during a Steam import.
	 */
	const STEAM_IMPORT_SEARCH_LIMIT = 100;

	/**
	 * Number of per-title IGDB search requests per import step. The searches
	 * are throttled to respect the IGDB rate limit, so this keeps a single
	 * step reasonably fast.
	 */
	const STEAM_IMPORT_SEARCHES_PER_STEP = 5;

	/**
	 * Session variable holding the state of a running Steam import.
	 */
	const STEAM_IMPORT_STATE_KEY = 'igdbSteamImportState';

	/**
	 * Number of pages of the public GOG profile games endpoint fetched per
	 * import step. The requests are throttled to respect the GOG rate limit,
	 * so this keeps a single step reasonably fast.
	 */
	const GOG_IMPORT_PAGES_PER_STEP = 5;

	/**
	 * Number of GOG product ids fetched from IGDB per import step.
	 */
	const GOG_IMPORT_BATCH_SIZE = 250;

	/**
	 * Maximum number of per-title IGDB search requests during a GOG import.
	 */
	const GOG_IMPORT_SEARCH_LIMIT = 100;

	/**
	 * Number of per-title IGDB search requests per GOG import step.
	 */
	const GOG_IMPORT_SEARCHES_PER_STEP = 5;

	/**
	 * Session variable holding the state of a running GOG import.
	 */
	const GOG_IMPORT_STATE_KEY = 'igdbGogImportState';

	/**
	 * Number of Steam app ids from a Playnite export fetched from IGDB per
	 * import step.
	 */
	const PLAYNITE_IMPORT_BATCH_SIZE = 250;

	/**
	 * Maximum number of per-title IGDB search requests during a Playnite
	 * import. Unlike the Steam and GOG imports, most launchers in a Playnite
	 * export provide no usable external id, so the searches are the main
	 * matching path and the budget has to cover large libraries. The limit
	 * only bounds the import time of pathological files.
	 */
	const PLAYNITE_IMPORT_SEARCH_LIMIT = 1000;

	/**
	 * Number of per-title IGDB search requests per Playnite import step.
	 */
	const PLAYNITE_IMPORT_SEARCHES_PER_STEP = 5;

	/**
	 * Maximum number of games accepted from a Playnite library export file.
	 */
	const PLAYNITE_IMPORT_MAX_GAMES = 100000;

	/**
	 * Session variable holding the state of a running Playnite import.
	 */
	const PLAYNITE_IMPORT_STATE_KEY = 'igdbPlayniteImportState';

	/**
	 * Number of IGDB game ids fetched from IGDB per import step. Ids are
	 * unique per game, so a batch can never exceed the result limit of 500.
	 */
	const IGDB_IMPORT_BATCH_SIZE = 400;

	/**
	 * Maximum number of games accepted from an IGDB list import file.
	 */
	const IGDB_IMPORT_MAX_GAMES = 100000;

	/**
	 * Session variable holding the state of a running IGDB list import.
	 */
	const IGDB_IMPORT_STATE_KEY = 'igdbListImportState';

	/**
	 * Maximum number of results returned by the game search of the editor
	 * button dialog.
	 */
	const GAME_SEARCH_RESULT_LIMIT = 20;

	/**
	 * Minimum length of the search string of the game search.
	 */
	const GAME_SEARCH_MIN_LENGTH = 2;

	/**
	 * @inheritDoc
	 */
	protected $permissionsCreate = ['admin.igdb_integration.can_manage_games'];

	/**
	 * @inheritDoc
	 */
	protected $permissionsUpdate = ['admin.igdb_integration.can_manage_games'];

	/**
	 * @inheritDoc
	 */
	protected $permissionsDelete = ['admin.igdb_integration.can_manage_games'];

	/**
	 * @inheritDoc
	 */
	protected $requireACP = ['create', 'delete', 'update'];

	/**
	 * @inheritDoc
	 */
	protected $allowGuestAccess = ['getGameUserEditDialog', 'getGamePlayerListDialog', 'searchGames'];

	/**
	 * @var DialogFormDocument
	 */
	protected $dialog;

	/**
	 * @var IgdbIntegrationGame
	 */
	protected $game;

	/**
	 * The user ID of the game owner, if sent in request.
	 */
	protected $ownerId;

	/**
	 * Checks for permission to show the game user edit dialog.
	 */
	public function validateGetGameUserEditDialog()
	{
		$this->readInteger('gameId', true);
		$this->readInteger('userId', true);

		// If userId is present, use the user's rating instead of the average rating
		$this->ownerId = !empty($this->parameters['userId']) ? $this->parameters['userId'] : null;

		$this->game = new IgdbIntegrationGame($this->parameters['gameId']);
		if (!$this->game->getObjectID()) {
			throw new UserInputException('gameId');
		}
	}

	/**
	 * Returns the data to show the dialog to edit a relationship between a user and a game.
	 */
	public function getGameUserEditDialog()
	{
		$gameUserRow = $this->getGameUserRow($this->game->gameId, WCF::getUser()->userID);

		$this->dialog = DialogFormDocument::create('personGameEditDialog' . $this->game->gameId)
			->appendChildren([
				TextFormField::create('platforms')
					->label('wcf.igdb_integration.game.platforms')
					->value($this->game->platforms)
					->immutable(),
				DescriptionFormField::create('summary')
					->label('wcf.igdb_integration.game.summary')
					->value($this->game->summary)
					->rows(4)
					->immutable(),
				TemplateFormNode::create('igdbLink')
					->templateName('__igdbIntegrationGameLink')
					->variables([
						'gameUrl' => $this->game->slug ? 'https://www.igdb.com/games/' . rawurlencode($this->game->slug) : ''
					])
			]);
		if (WCF::getSession()->getPermission('user.igdb_integration.can_manage_own_games')) {
			$this->dialog->appendChildren([
				BooleanFormField::create('isOwned')
					->label('wcf.igdb_integration.dialog.game_user_edit_is_owned')
					->value(!empty($gameUserRow)),
				RatingFormField::create('rating')
					->label('wcf.form.field.rating')
					->value($gameUserRow['rating'] ?? 0)
			]);
		} else {
			$this->dialog->addDefaultButton(false);
		}

		EventHandler::getInstance()->fireAction($this, 'getGameUserEditDialog');

		$this->dialog->build();

		return [
			'dialog' => $this->dialog->getHtml(),
			'formId' => $this->dialog->getId(),
		];
	}

	/**
	 * Checks for permission to submit the game user edit dialog.
	 */
	public function validateSubmitGameUserEditDialog()
	{
		// The dialog itself is visible to everyone, but submitting it modifies
		// the own game library
		WCF::getSession()->checkPermissions(['user.igdb_integration.can_manage_own_games']);

		$this->validateGetGameUserEditDialog();

		if (!isset($this->parameters['data']['isOwned'])) {
			throw new UserInputException('isOwned');
		}

		$rating = intval($this->parameters['data']['rating'] ?? 0);
		if ($rating < 0 || $rating > 5) {
			throw new UserInputException('rating');
		}
		$this->parameters['data']['rating'] = $rating;
	}

	/**
	 * Handles submitting the form.
	 */
	public function submitGameUserEditDialog()
	{
		$gameId = $this->parameters['gameId'];
		$userId = WCF::getUser()->userID;
		$isOwned = boolval($this->parameters['data']['isOwned']);
		$rating = $this->parameters['data']['rating'];

		$row = $this->getGameUserRow($gameId, $userId);

		if ($isOwned) {
			// Insert or update association data

			if (!empty($row)) {
				$sql = "UPDATE wcf1_igdb_integration_game_user
						SET rating = ?
						WHERE gameId = ? AND userId = ?";
				$statement = WCF::getDB()->prepare($sql);
				$statement->execute([$rating, $gameId, $userId]);

				if ($rating > 0 && $rating != $row['rating']) {
					$this->fireActivityEvent($gameId, $userId, 'rating', $rating);
				}
			} else {
				$sql = "INSERT INTO wcf1_igdb_integration_game_user
						SET gameId = ?, userId = ?, rating = ?";
				$statement = WCF::getDB()->prepare($sql);
				$statement->execute([$gameId, $userId, $rating]);

				// Adding and rating together is shown as a single activity
				$this->fireActivityEvent($gameId, $userId, 'add', $rating);
			}
		} else {
			// Remove association data

			$sql = "DELETE FROM wcf1_igdb_integration_game_user
					WHERE gameId = ? AND userId = ?";
			$statement = WCF::getDB()->prepare($sql);
			$statement->execute([$gameId, $userId]);

			if (!empty($row)) {
				$this->fireActivityEvent($gameId, $userId, 'remove');
			}
		}

		$data = $this->getUpdatedGameUserData($gameId, $userId, $isOwned);
		// The embedded game boxes show the rating of the message author, which
		// has to follow the dialog if the author edits their own entry
		$data['currentUserRating'] = $isOwned ? $rating : 0;

		return $data;
	}

	/**
	 * Checks for permission to add a game to the current user without opening the dialog.
	 */
	public function validateQuickAddGame()
	{
		WCF::getSession()->checkPermissions(['user.igdb_integration.can_manage_own_games']);

		$this->validateGetGameUserEditDialog();
	}

	/**
	 * Adds a game to the current user, keeping an existing rating if the game is already owned.
	 */
	public function quickAddGame()
	{
		$gameId = $this->game->gameId;
		$userId = WCF::getUser()->userID;

		$row = $this->getGameUserRow($gameId, $userId);

		if (empty($row)) {
			$sql = "INSERT INTO wcf1_igdb_integration_game_user
					SET gameId = ?, userId = ?, rating = ?";
			$statement = WCF::getDB()->prepare($sql);
			$statement->execute([$gameId, $userId, 0]);

			$this->fireActivityEvent($gameId, $userId, 'add');
		}

		$data = $this->getUpdatedGameUserData($gameId, $userId, true);
		$data['currentUserRating'] = !empty($row) ? intval($row['rating']) : 0;

		return $data;
	}

	/**
	 * Checks for permission to remove a game from the current user without opening the dialog.
	 */
	public function validateQuickRemoveGame()
	{
		WCF::getSession()->checkPermissions(['user.igdb_integration.can_manage_own_games']);

		$this->validateGetGameUserEditDialog();
	}

	/**
	 * Removes a game from the current user.
	 */
	public function quickRemoveGame()
	{
		$gameId = $this->game->gameId;
		$userId = WCF::getUser()->userID;

		$row = $this->getGameUserRow($gameId, $userId);

		$sql = "DELETE FROM wcf1_igdb_integration_game_user
				WHERE gameId = ? AND userId = ?";
		$statement = WCF::getDB()->prepare($sql);
		$statement->execute([$gameId, $userId]);

		if (!empty($row)) {
			$this->fireActivityEvent($gameId, $userId, 'remove');
		}

		$data = $this->getUpdatedGameUserData($gameId, $userId, false);
		$data['currentUserRating'] = 0;

		return $data;
	}

	/**
	 * Checks for permission to prepare the Steam library import.
	 */
	public function validateGetSteamImportData()
	{
		if (!WCF::getUser()->userID) {
			throw new PermissionDeniedException();
		}
		WCF::getSession()->checkPermissions(['user.igdb_integration.can_import_steam_games']);

		if (!IgdbIntegrationUtil::isSteamConnectionDataValid()) {
			throw new PermissionDeniedException();
		}
		if (!WCF::getSession()->getVar('igdbSteamId')) {
			// The Steam account has to be verified via the login flow first
			throw new PermissionDeniedException();
		}
	}

	/**
	 * Returns the data for the dialog that confirms the Steam library import.
	 */
	public function getSteamImportData()
	{
		$steamId = WCF::getSession()->getVar('igdbSteamId');
		$steamGames = IgdbIntegrationUtil::fetchSteamOwnedGames($steamId);

		return [
			'steamId' => $steamId,
			'gameCount' => is_array($steamGames) ? count($steamGames) : 0,
			'requestFailed' => $steamGames === null,
		];
	}

	/**
	 * Checks for permission to start the Steam library import.
	 */
	public function validateStartSteamImport()
	{
		$this->validateGetSteamImportData();
	}

	/**
	 * Starts the Steam library import: fetches the Steam library, matches it
	 * against the local database and prepares the IGDB request batches for the
	 * following import steps.
	 */
	public function startSteamImport()
	{
		$steamId = WCF::getSession()->getVar('igdbSteamId');
		$steamGames = IgdbIntegrationUtil::fetchSteamOwnedGames($steamId);
		if (empty($steamGames)) {
			// The library became unavailable between dialog and submit, e.g.
			// because the profile privacy changed
			WCF::getSession()->unregister('igdbSteamId');

			return [
				'failed' => true,
				'batchCount' => 0,
				'gameCount' => 0,
			];
		}

		$state = [
			'remaining' => $steamGames, // Steam app id => name
			'ambiguous' => [],          // Steam app id => name
			'stats' => ['imported' => 0, 'alreadyOwned' => 0],
			'searchQueue' => null,      // filled on the first search step
			'searchTotal' => 0,
			'searched' => 0,
		];

		// Match everything that needs no IGDB request: games already linked to
		// a Steam app id and games with the exact same normalized title
		$matched = [];
		$this->matchRemainingByExternalId('steamAppId', $state['remaining'], $matched);
		$this->matchRemainingByName($state['remaining'], $matched, $state['ambiguous'], false);
		$this->insertImportMatches($matched, $state['stats']);

		// The remaining app ids are fetched from IGDB in batches
		$state['batches'] = array_chunk(array_keys($state['remaining']), self::STEAM_IMPORT_BATCH_SIZE);

		WCF::getSession()->register(self::STEAM_IMPORT_STATE_KEY, $state);

		return [
			'failed' => false,
			'batchCount' => count($state['batches']),
			'gameCount' => count($steamGames),
		];
	}

	/**
	 * Checks for permission to run a step of a started Steam library import.
	 */
	public function validateProcessSteamImportBatch()
	{
		if (!WCF::getUser()->userID) {
			throw new PermissionDeniedException();
		}
		WCF::getSession()->checkPermissions(['user.igdb_integration.can_import_steam_games']);

		if (!is_array(WCF::getSession()->getVar(self::STEAM_IMPORT_STATE_KEY))) {
			// No import has been started in this session
			throw new UserInputException('steamImportState');
		}
	}

	/**
	 * Fetches the next batch of Steam app ids from IGDB and matches the new
	 * local data against the remaining Steam games.
	 */
	public function processSteamImportBatch()
	{
		$state = WCF::getSession()->getVar(self::STEAM_IMPORT_STATE_KEY);

		$batch = array_shift($state['batches']);
		if ($batch !== null) {
			// Earlier steps may have matched some of the batch's games already
			$appIds = array_values(array_intersect($batch, array_keys($state['remaining'])));
			if (!empty($appIds)) {
				IgdbIntegrationUtil::updateDatabaseGamesBySteamAppIds($appIds);
			}

			$matched = [];
			$this->matchRemainingByExternalId('steamAppId', $state['remaining'], $matched);
			$this->matchRemainingByName($state['remaining'], $matched, $state['ambiguous'], false);
			$this->insertImportMatches($matched, $state['stats']);
		}

		WCF::getSession()->register(self::STEAM_IMPORT_STATE_KEY, $state);

		return [
			'remainingBatches' => count($state['batches']),
		];
	}

	/**
	 * @see IgdbIntegrationGameAction::validateProcessSteamImportBatch()
	 */
	public function validateProcessSteamImportSearch()
	{
		$this->validateProcessSteamImportBatch();
	}

	/**
	 * Searches IGDB for a few of the remaining unmatched titles and matches
	 * the results. The client repeats this step until it reports completion.
	 */
	public function processSteamImportSearch()
	{
		$state = WCF::getSession()->getVar(self::STEAM_IMPORT_STATE_KEY);

		if ($state['searchQueue'] === null) {
			$state['searchQueue'] = array_slice($state['remaining'], 0, self::STEAM_IMPORT_SEARCH_LIMIT, true);
			$state['searchTotal'] = count($state['searchQueue']);
		}

		if (IgdbIntegrationUtil::isConnectionDataValid()) {
			$searchedThisStep = 0;
			while (!empty($state['searchQueue']) && $searchedThisStep < self::STEAM_IMPORT_SEARCHES_PER_STEP) {
				$appId = array_key_first($state['searchQueue']);
				$name = $state['searchQueue'][$appId];
				unset($state['searchQueue'][$appId]);
				$state['searched']++;

				// May have been matched by a search result of a previous step
				if (!isset($state['remaining'][$appId])) {
					continue;
				}

				// The request pacing is handled by the sitewide rate limit queue
				IgdbIntegrationUtil::updateDatabaseGamesByName($name);
				$searchedThisStep++;
			}

			// The search results may contain the Steam link or the exact title
			$matched = [];
			$this->matchRemainingByExternalId('steamAppId', $state['remaining'], $matched);
			$this->matchRemainingByName($state['remaining'], $matched, $state['ambiguous'], false);
			$this->insertImportMatches($matched, $state['stats']);
		} else {
			$state['searchQueue'] = [];
		}

		WCF::getSession()->register(self::STEAM_IMPORT_STATE_KEY, $state);

		return [
			'searched' => $state['searched'],
			'searchTotal' => $state['searchTotal'],
			'done' => empty($state['searchQueue']),
		];
	}

	/**
	 * @see IgdbIntegrationGameAction::validateProcessSteamImportBatch()
	 */
	public function validateFinishSteamImport()
	{
		$this->validateProcessSteamImportBatch();
	}

	/**
	 * Runs the roman numeral matching pass as a last resort, synchronizes all
	 * computed values and returns the import summary.
	 */
	public function finishSteamImport()
	{
		$state = WCF::getSession()->getVar(self::STEAM_IMPORT_STATE_KEY);

		$matched = [];
		$this->matchRemainingByName($state['remaining'], $matched, $state['ambiguous'], true);
		$this->insertImportMatches($matched, $state['stats']);

		if ($state['stats']['imported'] > 0) {
			IgdbIntegrationUtil::updateAllGameStats();
		}
		$gameCount = $this->updateUserGameCount(WCF::getUser()->userID);

		// The verification is intended for a single import
		WCF::getSession()->unregister(self::STEAM_IMPORT_STATE_KEY);
		WCF::getSession()->unregister('igdbSteamId');

		$unmatchedNames = array_values($state['remaining']);
		sort($unmatchedNames, SORT_NATURAL | SORT_FLAG_CASE);
		$ambiguousNames = array_values($state['ambiguous']);
		sort($ambiguousNames, SORT_NATURAL | SORT_FLAG_CASE);

		return [
			'failed' => false,
			'importedCount' => $state['stats']['imported'],
			'alreadyOwnedCount' => $state['stats']['alreadyOwned'],
			'unmatched' => $unmatchedNames,
			'ambiguous' => $ambiguousNames,
			'gameCount' => $gameCount,
		];
	}

	/**
	 * Checks for permission to prepare the GOG library import and validates
	 * the submitted GOG username.
	 */
	public function validateGetGogImportData()
	{
		if (!WCF::getUser()->userID) {
			throw new PermissionDeniedException();
		}
		WCF::getSession()->checkPermissions(['user.igdb_integration.can_import_gog_games']);

		if (!IgdbIntegrationUtil::isConnectionDataValid()) {
			throw new PermissionDeniedException();
		}

		$this->readString('gogUsername');
		if (!preg_match(IgdbIntegrationUtil::GOG_USERNAME_REGEX, $this->parameters['gogUsername'])) {
			throw new UserInputException('gogUsername');
		}
	}

	/**
	 * Returns the data for the dialog that confirms the GOG library import.
	 * There is no account verification.
	 */
	public function getGogImportData()
	{
		$username = $this->parameters['gogUsername'];
		$firstPage = IgdbIntegrationUtil::fetchGogLibraryPage($username, 1);

		return [
			'gogUsername' => $username,
			'gameCount' => is_array($firstPage) ? $firstPage['total'] : 0,
			'requestFailed' => $firstPage === null,
		];
	}

	/**
	 * @see IgdbIntegrationGameAction::validateGetGogImportData()
	 */
	public function validateStartGogImport()
	{
		$this->validateGetGogImportData();
	}

	/**
	 * Starts the GOG library import: fetches the first page of the public
	 * games list and prepares the state for the following fetch steps. The
	 * matching begins once all pages have been fetched.
	 */
	public function startGogImport()
	{
		$username = $this->parameters['gogUsername'];
		$firstPage = IgdbIntegrationUtil::fetchGogLibraryPage($username, 1);
		if ($firstPage === null || empty($firstPage['games'])) {
			// The profile became unavailable between dialog and submit
			return [
				'failed' => true,
				'pageCount' => 0,
				'gameCount' => 0,
			];
		}

		$state = [
			'username' => $username,
			'remaining' => $firstPage['games'], // GOG product id => title
			'ambiguous' => [],                  // GOG product id => title
			'stats' => ['imported' => 0, 'alreadyOwned' => 0],
			'page' => 1,
			'pageCount' => $firstPage['pages'],
			'batches' => null,                  // filled when all pages are fetched
			'searchQueue' => null,              // filled on the first search step
			'searchTotal' => 0,
			'searched' => 0,
		];

		WCF::getSession()->register(self::GOG_IMPORT_STATE_KEY, $state);

		return [
			'failed' => false,
			'pageCount' => $firstPage['pages'],
			'gameCount' => $firstPage['total'],
		];
	}

	/**
	 * Checks for permission to run a step of a started GOG library import.
	 */
	public function validateProcessGogImportFetch()
	{
		if (!WCF::getUser()->userID) {
			throw new PermissionDeniedException();
		}
		WCF::getSession()->checkPermissions(['user.igdb_integration.can_import_gog_games']);

		if (!is_array(WCF::getSession()->getVar(self::GOG_IMPORT_STATE_KEY))) {
			// No import has been started in this session
			throw new UserInputException('gogImportState');
		}
	}

	/**
	 * Fetches the next pages of the public GOG games list. Once the last page
	 * has been fetched, the games are matched against the local database and
	 * the IGDB request batches are prepared.
	 */
	public function processGogImportFetch()
	{
		$state = WCF::getSession()->getVar(self::GOG_IMPORT_STATE_KEY);

		if (!is_array($state['batches'])) {
			$fetchedThisStep = 0;
			while ($state['page'] < $state['pageCount'] && $fetchedThisStep < self::GOG_IMPORT_PAGES_PER_STEP) {
				$page = IgdbIntegrationUtil::fetchGogLibraryPage($state['username'], $state['page'] + 1);
				if ($page === null) {
					WCF::getSession()->unregister(self::GOG_IMPORT_STATE_KEY);

					return [
						'failed' => true,
						'currentPage' => $state['page'],
						'pageCount' => $state['pageCount'],
						'done' => true,
						'batchCount' => 0,
					];
				}

				// The product ids are unique across all pages
				$state['remaining'] += $page['games'];
				$state['page']++;
				$fetchedThisStep++;
			}

			if ($state['page'] >= $state['pageCount']) {
				// Match everything that needs no IGDB request: games already
				// linked to a GOG product id and games with the exact same
				// normalized title
				$matched = [];
				$this->matchRemainingByExternalId('gogId', $state['remaining'], $matched);
				$this->matchRemainingByName($state['remaining'], $matched, $state['ambiguous'], false, 'gogId');
				$this->insertImportMatches($matched, $state['stats']);

				// The remaining product ids are fetched from IGDB in batches
				$state['batches'] = array_chunk(array_keys($state['remaining']), self::GOG_IMPORT_BATCH_SIZE);
			}

			WCF::getSession()->register(self::GOG_IMPORT_STATE_KEY, $state);
		}

		return [
			'failed' => false,
			'currentPage' => $state['page'],
			'pageCount' => $state['pageCount'],
			'done' => is_array($state['batches']),
			'batchCount' => is_array($state['batches']) ? count($state['batches']) : 0,
		];
	}

	/**
	 * @see IgdbIntegrationGameAction::validateProcessGogImportFetch()
	 */
	public function validateProcessGogImportBatch()
	{
		$this->validateProcessGogImportFetch();
	}

	/**
	 * Fetches the next batch of GOG product ids from IGDB and matches the new
	 * local data against the remaining GOG games.
	 */
	public function processGogImportBatch()
	{
		$state = WCF::getSession()->getVar(self::GOG_IMPORT_STATE_KEY);

		$batch = is_array($state['batches']) ? array_shift($state['batches']) : null;
		if ($batch !== null) {
			// Earlier steps may have matched some of the batch's games already
			$gogIds = array_values(array_intersect($batch, array_keys($state['remaining'])));
			if (!empty($gogIds)) {
				IgdbIntegrationUtil::updateDatabaseGamesByGogIds($gogIds);
			}

			$matched = [];
			$this->matchRemainingByExternalId('gogId', $state['remaining'], $matched);
			$this->matchRemainingByName($state['remaining'], $matched, $state['ambiguous'], false, 'gogId');
			$this->insertImportMatches($matched, $state['stats']);
		}

		WCF::getSession()->register(self::GOG_IMPORT_STATE_KEY, $state);

		return [
			'remainingBatches' => is_array($state['batches']) ? count($state['batches']) : 0,
		];
	}

	/**
	 * @see IgdbIntegrationGameAction::validateProcessGogImportFetch()
	 */
	public function validateProcessGogImportSearch()
	{
		$this->validateProcessGogImportFetch();
	}

	/**
	 * Searches IGDB for a few of the remaining unmatched titles and matches
	 * the results. The client repeats this step until it reports completion.
	 */
	public function processGogImportSearch()
	{
		$state = WCF::getSession()->getVar(self::GOG_IMPORT_STATE_KEY);

		if ($state['searchQueue'] === null) {
			$state['searchQueue'] = array_slice($state['remaining'], 0, self::GOG_IMPORT_SEARCH_LIMIT, true);
			$state['searchTotal'] = count($state['searchQueue']);
		}

		if (IgdbIntegrationUtil::isConnectionDataValid()) {
			$searchedThisStep = 0;
			while (!empty($state['searchQueue']) && $searchedThisStep < self::GOG_IMPORT_SEARCHES_PER_STEP) {
				$gogId = array_key_first($state['searchQueue']);
				$name = $state['searchQueue'][$gogId];
				unset($state['searchQueue'][$gogId]);
				$state['searched']++;

				// May have been matched by a search result of a previous step
				if (!isset($state['remaining'][$gogId])) {
					continue;
				}

				// The request pacing is handled by the sitewide rate limit queue
				IgdbIntegrationUtil::updateDatabaseGamesByName($name);
				$searchedThisStep++;
			}

			// The search results may contain the GOG link or the exact title
			$matched = [];
			$this->matchRemainingByExternalId('gogId', $state['remaining'], $matched);
			$this->matchRemainingByName($state['remaining'], $matched, $state['ambiguous'], false, 'gogId');
			$this->insertImportMatches($matched, $state['stats']);
		} else {
			$state['searchQueue'] = [];
		}

		WCF::getSession()->register(self::GOG_IMPORT_STATE_KEY, $state);

		return [
			'searched' => $state['searched'],
			'searchTotal' => $state['searchTotal'],
			'done' => empty($state['searchQueue']),
		];
	}

	/**
	 * @see IgdbIntegrationGameAction::validateProcessGogImportFetch()
	 */
	public function validateFinishGogImport()
	{
		$this->validateProcessGogImportFetch();
	}

	/**
	 * Runs the roman numeral matching pass as a last resort, synchronizes all
	 * computed values and returns the import summary.
	 */
	public function finishGogImport()
	{
		$state = WCF::getSession()->getVar(self::GOG_IMPORT_STATE_KEY);

		$matched = [];
		$this->matchRemainingByName($state['remaining'], $matched, $state['ambiguous'], true, 'gogId');
		$this->insertImportMatches($matched, $state['stats']);

		if ($state['stats']['imported'] > 0) {
			IgdbIntegrationUtil::updateAllGameStats();
		}
		$gameCount = $this->updateUserGameCount(WCF::getUser()->userID);

		WCF::getSession()->unregister(self::GOG_IMPORT_STATE_KEY);

		$unmatchedNames = array_values($state['remaining']);
		sort($unmatchedNames, SORT_NATURAL | SORT_FLAG_CASE);
		$ambiguousNames = array_values($state['ambiguous']);
		sort($ambiguousNames, SORT_NATURAL | SORT_FLAG_CASE);

		return [
			'failed' => false,
			'importedCount' => $state['stats']['imported'],
			'alreadyOwnedCount' => $state['stats']['alreadyOwned'],
			'unmatched' => $unmatchedNames,
			'ambiguous' => $ambiguousNames,
			'gameCount' => $gameCount,
		];
	}

	/**
	 * Checks for permission to start a Playnite library import and parses the
	 * submitted game list.
	 */
	public function validateStartPlayniteImport()
	{
		if (!WCF::getUser()->userID) {
			throw new PermissionDeniedException();
		}
		WCF::getSession()->checkPermissions(['user.igdb_integration.can_import_playnite_games']);

		// Unlike the Steam import, only the IGDB credentials are required
		if (!IgdbIntegrationUtil::isConnectionDataValid()) {
			throw new PermissionDeniedException();
		}

		// The games are sent as a single JSON string, because a large library
		// as an array parameter would exceed PHP's max_input_vars
		$this->readString('gameList');

		try {
			$entries = JSON::decode($this->parameters['gameList']);
		} catch (\Exception $ex) {
			throw new UserInputException('gameList');
		}
		if (!is_array($entries)) {
			throw new UserInputException('gameList');
		}

		// Each entry is a [steam app id or 0, GOG product id or 0, name]
		// triple. Entries with an external id are collected first, so that a
		// name-only duplicate of the same game from another launcher is
		// dropped in favor of the entry that can be matched exactly. The pools
		// stay separate because a Steam app id and a GOG product id may share
		// the same integer value.
		$steamGames = [];
		$gogGames = [];
		$otherGames = [];
		$seenNames = [];
		foreach ($entries as $entry) {
			if (!is_array($entry) || count($entry) !== 3) {
				continue;
			}
			$steamAppId = intval($entry[0]);
			$name = StringUtil::trim((string)$entry[2]);
			if ($steamAppId > 0 && $name !== '' && !isset($steamGames[$steamAppId])) {
				$steamGames[$steamAppId] = $name;
				$seenNames[mb_strtolower($name)] = true;
			}
		}
		foreach ($entries as $entry) {
			if (!is_array($entry) || count($entry) !== 3) {
				continue;
			}
			$gogId = intval($entry[1]);
			$name = StringUtil::trim((string)$entry[2]);
			if (intval($entry[0]) <= 0 && $gogId > 0 && $name !== ''
				&& !isset($gogGames[$gogId]) && !isset($seenNames[mb_strtolower($name)])) {
				$gogGames[$gogId] = $name;
				$seenNames[mb_strtolower($name)] = true;
			}
		}
		foreach ($entries as $index => $entry) {
			if (!is_array($entry) || count($entry) !== 3) {
				continue;
			}
			$name = StringUtil::trim((string)$entry[2]);
			if (intval($entry[0]) <= 0 && intval($entry[1]) <= 0 && $name !== ''
				&& !isset($seenNames[mb_strtolower($name)])) {
				// The synthetic keys must never collide with an external id
				$otherGames['n' . $index] = $name;
				$seenNames[mb_strtolower($name)] = true;
			}
		}

		if (empty($steamGames) && empty($gogGames) && empty($otherGames)) {
			throw new UserInputException('gameList');
		}

		$this->parameters['steamGames'] = array_slice($steamGames, 0, self::PLAYNITE_IMPORT_MAX_GAMES, true);
		$this->parameters['gogGames'] = array_slice(
			$gogGames,
			0,
			max(0, self::PLAYNITE_IMPORT_MAX_GAMES - count($this->parameters['steamGames'])),
			true
		);
		$this->parameters['otherGames'] = array_slice(
			$otherGames,
			0,
			max(0, self::PLAYNITE_IMPORT_MAX_GAMES - count($this->parameters['steamGames']) - count($this->parameters['gogGames'])),
			true
		);
	}

	/**
	 * Starts the import of a Playnite library export: matches the games
	 * against the local database and prepares the IGDB request batches for
	 * the following import steps.
	 */
	public function startPlayniteImport()
	{
		$state = [
			'remaining' => $this->parameters['steamGames'],      // Steam app id => name
			'remainingGog' => $this->parameters['gogGames'],     // GOG product id => name
			'remainingNames' => $this->parameters['otherGames'], // synthetic key => name
			'ambiguous' => [],                                   // list of names
			'stats' => ['imported' => 0, 'alreadyOwned' => 0],
			'searchQueue' => null,                               // filled on the first search step
			'searchTotal' => 0,
			'searched' => 0,
		];

		// Match everything that needs no IGDB request: games already linked to
		// a Steam app id or GOG product id and games with the exact same
		// normalized title
		$this->matchPlayniteRemaining($state, false);

		// The remaining Steam app ids and GOG product ids are fetched from
		// IGDB in batches; the games of other launchers can only be matched
		// by title
		$state['batches'] = [];
		foreach (array_chunk(array_keys($state['remaining']), self::PLAYNITE_IMPORT_BATCH_SIZE) as $chunk) {
			$state['batches'][] = ['steamAppId', $chunk];
		}
		foreach (array_chunk(array_keys($state['remainingGog']), self::PLAYNITE_IMPORT_BATCH_SIZE) as $chunk) {
			$state['batches'][] = ['gogId', $chunk];
		}

		WCF::getSession()->register(self::PLAYNITE_IMPORT_STATE_KEY, $state);

		return [
			'failed' => false,
			'batchCount' => count($state['batches']),
			'gameCount' => count($this->parameters['steamGames'])
				+ count($this->parameters['gogGames'])
				+ count($this->parameters['otherGames']),
		];
	}

	/**
	 * Checks for permission to run a step of a started Playnite import.
	 */
	public function validateProcessPlayniteImportBatch()
	{
		if (!WCF::getUser()->userID) {
			throw new PermissionDeniedException();
		}
		WCF::getSession()->checkPermissions(['user.igdb_integration.can_import_playnite_games']);

		if (!is_array(WCF::getSession()->getVar(self::PLAYNITE_IMPORT_STATE_KEY))) {
			// No import has been started in this session
			throw new UserInputException('playniteImportState');
		}
	}

	/**
	 * Fetches the next batch of Steam app ids or GOG product ids from IGDB
	 * and matches the new local data against the remaining Playnite games.
	 */
	public function processPlayniteImportBatch()
	{
		$state = WCF::getSession()->getVar(self::PLAYNITE_IMPORT_STATE_KEY);

		$batch = array_shift($state['batches']);
		if ($batch !== null) {
			[$idType, $ids] = $batch;

			// Earlier steps may have matched some of the batch's games already
			if ($idType === 'gogId') {
				$ids = array_values(array_intersect($ids, array_keys($state['remainingGog'])));
				if (!empty($ids)) {
					IgdbIntegrationUtil::updateDatabaseGamesByGogIds($ids);
				}
			} else {
				$ids = array_values(array_intersect($ids, array_keys($state['remaining'])));
				if (!empty($ids)) {
					IgdbIntegrationUtil::updateDatabaseGamesBySteamAppIds($ids);
				}
			}

			$this->matchPlayniteRemaining($state, false);
		}

		WCF::getSession()->register(self::PLAYNITE_IMPORT_STATE_KEY, $state);

		return [
			'remainingBatches' => count($state['batches']),
		];
	}

	/**
	 * @see IgdbIntegrationGameAction::validateProcessPlayniteImportBatch()
	 */
	public function validateProcessPlayniteImportSearch()
	{
		$this->validateProcessPlayniteImportBatch();
	}

	/**
	 * Searches IGDB for a few of the remaining unmatched titles and matches
	 * the results. The client repeats this step until it reports completion.
	 */
	public function processPlayniteImportSearch()
	{
		$state = WCF::getSession()->getVar(self::PLAYNITE_IMPORT_STATE_KEY);

		if ($state['searchQueue'] === null) {
			// All pools share the search budget; the key prefixes keep the id
			// namespaces apart, because a Steam app id and a GOG product id
			// may share the same integer value
			$queue = [];
			foreach ($state['remaining'] as $appId => $name) {
				$queue['s' . $appId] = $name;
			}
			foreach ($state['remainingGog'] as $gogId => $name) {
				$queue['g' . $gogId] = $name;
			}
			// The synthetic keys are already prefixed with 'n'
			$queue += $state['remainingNames'];

			$state['searchQueue'] = array_slice($queue, 0, self::PLAYNITE_IMPORT_SEARCH_LIMIT, true);
			$state['searchTotal'] = count($state['searchQueue']);
		}

		if (IgdbIntegrationUtil::isConnectionDataValid()) {
			$searchedThisStep = 0;
			while (!empty($state['searchQueue']) && $searchedThisStep < self::PLAYNITE_IMPORT_SEARCHES_PER_STEP) {
				$key = array_key_first($state['searchQueue']);
				$name = $state['searchQueue'][$key];
				unset($state['searchQueue'][$key]);
				$state['searched']++;

				// May have been matched by a search result of a previous step
				$prefix = substr((string)$key, 0, 1);
				if ($prefix === 's') {
					$stillRemaining = isset($state['remaining'][intval(substr((string)$key, 1))]);
				} elseif ($prefix === 'g') {
					$stillRemaining = isset($state['remainingGog'][intval(substr((string)$key, 1))]);
				} else {
					$stillRemaining = isset($state['remainingNames'][$key]);
				}
				if (!$stillRemaining) {
					continue;
				}

				// The request pacing is handled by the sitewide rate limit queue
				IgdbIntegrationUtil::updateDatabaseGamesByName($name);
				$searchedThisStep++;
			}

			// The search results may contain the store link or the exact title
			$this->matchPlayniteRemaining($state, false);
		} else {
			$state['searchQueue'] = [];
		}

		WCF::getSession()->register(self::PLAYNITE_IMPORT_STATE_KEY, $state);

		return [
			'searched' => $state['searched'],
			'searchTotal' => $state['searchTotal'],
			'done' => empty($state['searchQueue']),
		];
	}

	/**
	 * @see IgdbIntegrationGameAction::validateProcessPlayniteImportBatch()
	 */
	public function validateFinishPlayniteImport()
	{
		$this->validateProcessPlayniteImportBatch();
	}

	/**
	 * Runs the roman numeral matching pass as a last resort, synchronizes all
	 * computed values and returns the import summary.
	 */
	public function finishPlayniteImport()
	{
		$state = WCF::getSession()->getVar(self::PLAYNITE_IMPORT_STATE_KEY);

		$this->matchPlayniteRemaining($state, true);

		if ($state['stats']['imported'] > 0) {
			IgdbIntegrationUtil::updateAllGameStats();
		}
		$gameCount = $this->updateUserGameCount(WCF::getUser()->userID);

		WCF::getSession()->unregister(self::PLAYNITE_IMPORT_STATE_KEY);

		$unmatchedNames = array_merge(
			array_values($state['remaining']),
			array_values($state['remainingGog']),
			array_values($state['remainingNames'])
		);
		sort($unmatchedNames, SORT_NATURAL | SORT_FLAG_CASE);
		$ambiguousNames = array_values($state['ambiguous']);
		sort($ambiguousNames, SORT_NATURAL | SORT_FLAG_CASE);

		return [
			'failed' => false,
			'importedCount' => $state['stats']['imported'],
			'alreadyOwnedCount' => $state['stats']['alreadyOwned'],
			'unmatched' => $unmatchedNames,
			'ambiguous' => $ambiguousNames,
			'gameCount' => $gameCount,
		];
	}

	/**
	 * Matches all pools of remaining Playnite games and inserts the hits:
	 * entries with a Steam app id or GOG product id by their external id and
	 * by title, entries of other launchers by title only. Each pool uses its
	 * own working arrays, because a Steam app id and a GOG product id may
	 * share the same integer value.
	 */
	protected function matchPlayniteRemaining(array &$state, bool $convertRomanNumerals)
	{
		$matchedSteam = $matchedGog = $matchedNames = [];
		$ambiguousSteam = $ambiguousGog = $ambiguousNames = [];

		if (!$convertRomanNumerals) {
			$this->matchRemainingByExternalId('steamAppId', $state['remaining'], $matchedSteam);
			$this->matchRemainingByExternalId('gogId', $state['remainingGog'], $matchedGog);
		}
		$this->matchRemainingByName($state['remaining'], $matchedSteam, $ambiguousSteam, $convertRomanNumerals);
		$this->matchRemainingByName($state['remainingGog'], $matchedGog, $ambiguousGog, $convertRomanNumerals, 'gogId');
		// The synthetic keys of the name-only pool are no external ids, so
		// they must not be backfilled into the game table
		$this->matchRemainingByName($state['remainingNames'], $matchedNames, $ambiguousNames, $convertRomanNumerals, 'steamAppId', false);

		$state['ambiguous'] = array_merge(
			$state['ambiguous'],
			array_values($ambiguousSteam),
			array_values($ambiguousGog),
			array_values($ambiguousNames)
		);

		$this->insertImportMatches(
			array_merge(array_values($matchedSteam), array_values($matchedGog), array_values($matchedNames)),
			$state['stats']
		);
	}

	/**
	 * Checks for permission to start an IGDB list import and parses the
	 * submitted game id list.
	 */
	public function validateStartIgdbImport()
	{
		if (!WCF::getUser()->userID) {
			throw new PermissionDeniedException();
		}
		WCF::getSession()->checkPermissions(['user.igdb_integration.can_import_igdb_games']);

		// Unlike the Steam import, only the IGDB credentials are required
		if (!IgdbIntegrationUtil::isConnectionDataValid()) {
			throw new PermissionDeniedException();
		}

		// The ids are sent as a single comma-separated string, because a large
		// library as an array parameter would exceed PHP's max_input_vars
		$this->readString('idList');

		$gameIds = [];
		foreach (explode(',', $this->parameters['idList']) as $gameId) {
			$gameId = intval($gameId);
			if ($gameId > 0) {
				$gameIds[$gameId] = $gameId;
			}
		}
		if (empty($gameIds)) {
			throw new UserInputException('idList');
		}

		$this->parameters['gameIds'] = array_slice($gameIds, 0, self::IGDB_IMPORT_MAX_GAMES, true);
	}

	/**
	 * Starts the import of an IGDB list export: matches the game ids against
	 * the local database and prepares the IGDB request batches for the
	 * following import steps.
	 */
	public function startIgdbImport()
	{
		$gameIds = $this->parameters['gameIds'];

		$state = [
			'remaining' => $gameIds, // IGDB game id => IGDB game id
			'stats' => ['imported' => 0, 'alreadyOwned' => 0],
		];

		// Games that are already in the local database need no IGDB request
		$matched = $this->loadExistingGameIds($state['remaining']);
		foreach ($matched as $gameId) {
			unset($state['remaining'][$gameId]);
		}
		$this->insertImportMatches($matched, $state['stats']);

		$state['batches'] = array_chunk(array_keys($state['remaining']), self::IGDB_IMPORT_BATCH_SIZE);

		WCF::getSession()->register(self::IGDB_IMPORT_STATE_KEY, $state);

		return [
			'failed' => false,
			'batchCount' => count($state['batches']),
			'gameCount' => count($gameIds),
		];
	}

	/**
	 * Checks for permission to run a step of a started IGDB list import.
	 */
	public function validateProcessIgdbImportBatch()
	{
		if (!WCF::getUser()->userID) {
			throw new PermissionDeniedException();
		}
		WCF::getSession()->checkPermissions(['user.igdb_integration.can_import_igdb_games']);

		if (!is_array(WCF::getSession()->getVar(self::IGDB_IMPORT_STATE_KEY))) {
			// No import has been started in this session
			throw new UserInputException('igdbImportState');
		}
	}

	/**
	 * Fetches the next batch of game ids from IGDB and adds all games that
	 * exist there to the library of the current user.
	 */
	public function processIgdbImportBatch()
	{
		$state = WCF::getSession()->getVar(self::IGDB_IMPORT_STATE_KEY);

		$batch = array_shift($state['batches']);
		if ($batch !== null) {
			$gameIds = array_values(array_intersect($batch, array_keys($state['remaining'])));
			if (!empty($gameIds)) {
				IgdbIntegrationUtil::updateDatabaseGamesByIds($gameIds);

				// Ids that IGDB did not return (e.g. deleted games) stay in
				// the remaining list and are reported as unmatched at the end
				$matched = $this->loadExistingGameIds($gameIds);
				foreach ($matched as $gameId) {
					unset($state['remaining'][$gameId]);
				}
				$this->insertImportMatches($matched, $state['stats']);
			}
		}

		WCF::getSession()->register(self::IGDB_IMPORT_STATE_KEY, $state);

		return [
			'remainingBatches' => count($state['batches']),
		];
	}

	/**
	 * @see IgdbIntegrationGameAction::validateProcessIgdbImportBatch()
	 */
	public function validateFinishIgdbImport()
	{
		$this->validateProcessIgdbImportBatch();
	}

	/**
	 * Synchronizes all computed values and returns the summary of the IGDB
	 * list import.
	 */
	public function finishIgdbImport()
	{
		$state = WCF::getSession()->getVar(self::IGDB_IMPORT_STATE_KEY);

		if ($state['stats']['imported'] > 0) {
			IgdbIntegrationUtil::updateAllGameStats();
		}
		$gameCount = $this->updateUserGameCount(WCF::getUser()->userID);

		WCF::getSession()->unregister(self::IGDB_IMPORT_STATE_KEY);

		$unmatchedIds = array_values($state['remaining']);
		sort($unmatchedIds);

		return [
			'failed' => false,
			'importedCount' => $state['stats']['imported'],
			'alreadyOwnedCount' => $state['stats']['alreadyOwned'],
			'unmatchedIds' => $unmatchedIds,
			'gameCount' => $gameCount,
		];
	}

	/**
	 * Returns the subset of the given IGDB game ids that exist in the local
	 * game database, as a map of game id to game id.
	 */
	protected function loadExistingGameIds(array $gameIds): array
	{
		$existingGameIds = [];
		foreach (array_chunk(array_values($gameIds), 500) as $chunk) {
			$conditions = new PreparedStatementConditionBuilder();
			$conditions->add('gameId IN (?)', [$chunk]);
			$sql = "SELECT gameId
					FROM wcf1_igdb_integration_game
					" . $conditions;
			$statement = WCF::getDB()->prepare($sql);
			$statement->execute($conditions->getParameters());
			while ($gameId = $statement->fetchColumn()) {
				$existingGameIds[intval($gameId)] = intval($gameId);
			}
		}

		return $existingGameIds;
	}

	/**
	 * Adds all matched games that the current user does not own yet, without
	 * firing activity events to not flood the recent activity list, and adds
	 * the counts to the import statistics.
	 */
	protected function insertImportMatches(array $matched, array &$stats)
	{
		if (empty($matched)) {
			return;
		}

		$userId = WCF::getUser()->userID;
		$matchedGameIds = array_values(array_unique($matched));

		$conditions = new PreparedStatementConditionBuilder();
		$conditions->add('userId = ?', [$userId]);
		$conditions->add('gameId IN (?)', [$matchedGameIds]);
		$sql = "SELECT gameId
				FROM wcf1_igdb_integration_game_user
				" . $conditions;
		$statement = WCF::getDB()->prepare($sql);
		$statement->execute($conditions->getParameters());
		$ownedGameIds = $statement->fetchAll(\PDO::FETCH_COLUMN);

		$newGameIds = array_diff($matchedGameIds, $ownedGameIds);
		if (!empty($newGameIds)) {
			$sql = "INSERT INTO wcf1_igdb_integration_game_user
					SET gameId = ?, userId = ?, rating = ?";
			$statement = WCF::getDB()->prepare($sql);
			foreach ($newGameIds as $gameId) {
				$statement->execute([$gameId, $userId, 0]);
			}
			WCF::getDB()->commitTransaction();

			// Remember the time of the interaction for sorting
			$conditions = new PreparedStatementConditionBuilder();
			$conditions->add('gameId IN (?)', [array_values($newGameIds)]);
			$sql = "UPDATE wcf1_igdb_integration_game
					SET lastInteractionTime = " . TIME_NOW . "
					" . $conditions;
			$statement = WCF::getDB()->prepare($sql);
			$statement->execute($conditions->getParameters());
		}

		$stats['imported'] += count($newGameIds);
		$stats['alreadyOwned'] += count($matchedGameIds) - count($newGameIds);
	}

	/**
	 * Matches the remaining store games against the given external id column
	 * (steamAppId or gogId) and moves all hits from $remaining to $matched.
	 */
	protected function matchRemainingByExternalId(string $externalIdColumn, array &$remaining, array &$matched)
	{
		if (empty($remaining)) {
			return;
		}

		foreach (array_chunk(array_keys($remaining), 500) as $chunk) {
			$conditions = new PreparedStatementConditionBuilder();
			$conditions->add($externalIdColumn . ' IN (?)', [$chunk]);
			$sql = "SELECT " . $externalIdColumn . " AS externalId, gameId
					FROM wcf1_igdb_integration_game
					" . $conditions;
			$statement = WCF::getDB()->prepare($sql);
			$statement->execute($conditions->getParameters());
			while ($row = $statement->fetchArray()) {
				$externalId = intval($row['externalId']);
				if (isset($remaining[$externalId])) {
					$matched[$externalId] = intval($row['gameId']);
					unset($remaining[$externalId]);
				}
			}
		}
	}

	/**
	 * Matches remaining store games by normalized title against all local games,
	 * using the primary name first and the IGDB aliases as a fallback (e.g. GOG's
	 * "Ultima III" matching "Ultima III: Exodus" via the alias "Ultima 3").
	 * Unique hits are moved to $matched. Titles shared by multiple games (e.g. game +
	 * same-named remaster) are moved to $ambiguous, because the stores provide no year.
	 * $backfillExternalId must be disabled if the keys of $remaining are no real
	 * external ids, e.g. for the name-only pool of the Playnite import.
	 */
	protected function matchRemainingByName(array &$remaining, array &$matched, array &$ambiguous, bool $convertRomanNumerals, string $externalIdColumn = 'steamAppId', bool $backfillExternalId = true)
	{
		if (empty($remaining)) {
			return;
		}

		// Token match to not confuse "PC" with platforms like "PC-FX". Games
		// without platform data (some smaller IGDB entries, e.g. "Hellgrinder")
		// are included as well, because excluding them would make their titles
		// unmatchable
		$platformPattern = '(^|, )(PC|Mac|Linux)(,|$)';
		$sql = "SELECT gameId, name, alternativeNames, " . $externalIdColumn . " AS externalId
				FROM wcf1_igdb_integration_game
				WHERE platforms REGEXP ?
					OR platforms = ''";
		$statement = WCF::getDB()->prepare($sql);
		$statement->execute([$platformPattern]);

		$gamesByTitle = [];
		$gamesByAlternativeTitle = [];
		while ($row = $statement->fetchArray()) {
			$title = IgdbIntegrationUtil::normalizeGameTitle($row['name'], $convertRomanNumerals);
			if ($title !== '') {
				$gamesByTitle[$title][] = $row;
			}

			if (!empty($row['alternativeNames'])) {
				try {
					$alternativeNames = JSON::decode($row['alternativeNames']);
				} catch (\Exception $ex) {
					$alternativeNames = [];
				}
				if (is_array($alternativeNames)) {
					foreach ($alternativeNames as $alternativeName) {
						$title = IgdbIntegrationUtil::normalizeGameTitle((string)$alternativeName, $convertRomanNumerals);
						if ($title !== '') {
							$gamesByAlternativeTitle[$title][] = $row;
						}
					}
				}
			}
		}

		$backfillStatement = null;
		foreach ($remaining as $externalId => $name) {
			$title = IgdbIntegrationUtil::normalizeGameTitle($name, $convertRomanNumerals);
			if ($title === '') {
				continue;
			}

			// The aliases are only consulted if no primary name matches
			$candidates = $gamesByTitle[$title] ?? $gamesByAlternativeTitle[$title] ?? null;
			if ($candidates === null) {
				continue;
			}

			$gameIds = array_unique(array_column($candidates, 'gameId'));
			if (count($gameIds) === 1) {
				$matched[$externalId] = intval(reset($gameIds));

				// Remember direct matches, so future imports resolve them by
				// external id; roman numeral matches are too fuzzy to persist
				if ($backfillExternalId && !$convertRomanNumerals && $candidates[0]['externalId'] === null) {
					if ($backfillStatement === null) {
						$sql = "UPDATE wcf1_igdb_integration_game
								SET " . $externalIdColumn . " = ?
								WHERE gameId = ? AND " . $externalIdColumn . " IS NULL";
						$backfillStatement = WCF::getDB()->prepare($sql);
					}
					$backfillStatement->execute([$externalId, $matched[$externalId]]);
				}
			} else {
				$ambiguous[$externalId] = $name;
			}
			unset($remaining[$externalId]);
		}
	}

	/**
	 * Returns the game <-> user association row of the given user, or null.
	 */
	protected function getGameUserRow($gameId, $userId)
	{
		$sql = "SELECT rating
				FROM wcf1_igdb_integration_game_user
				WHERE gameId = ? AND userId = ?";
		$statement = WCF::getDB()->prepare($sql);
		$statement->execute([$gameId, $userId]);

		return $statement->fetchSingleRow();
	}

	/**
	 * Synchronizes the owned game count of a user into the profile field, the
	 * trophy condition column and the activity points. Returns the count.
	 */
	protected function updateUserGameCount($userId)
	{
		$sql = "SELECT COUNT(*) AS gameCount
				FROM wcf1_igdb_integration_game_user
				WHERE userId = ?";
		$statement = WCF::getDB()->prepare($sql);
		$statement->execute([$userId]);
		$gameCount = $statement->fetchSingleRow()['gameCount'];

		// Update game count profile field used for display only
		$user = new User($userId);
		$userEditor = new UserEditor($user);
		$userEditor->updateUserOptions([
			$user->getUserOptionID('igdb_integration_game_count') => $gameCount
		]);

		// Update user database info used for trophies
		$sql = "UPDATE wcf1_user
				SET IgdbIntegrationGameCount = ?
				WHERE userID = ?";
		$statement = WCF::getDB()->prepare($sql);
		$statement->execute([$gameCount, $userId]);

		// Synchronize activity points with the owned game count
		IgdbIntegrationUtil::updateActivityPoints($userId, $gameCount);

		return $gameCount;
	}

	/**
	 * Synchronizes the computed playerCount and averageRating columns of a
	 * game with its association rows.
	 */
	protected function updateGameStats($gameId)
	{
		$sql = "UPDATE wcf1_igdb_integration_game
				SET playerCount = (
						SELECT COUNT(*)
						FROM wcf1_igdb_integration_game_user
						WHERE gameId = ?
					),
					averageRating = COALESCE((
						SELECT ROUND(AVG(rating))
						FROM wcf1_igdb_integration_game_user
						WHERE gameId = ?
							AND rating > 0
					), 0)
				WHERE gameId = ?";
		$statement = WCF::getDB()->prepare($sql);
		$statement->execute([$gameId, $gameId, $gameId]);
	}

	/**
	 * @inheritDoc
	 */
	public function delete()
	{
		if (empty($this->objects)) {
			$this->readObjects();
		}

		// Collect the owners before the foreign key cascade removes the
		// association rows
		$gameIds = [];
		foreach ($this->getObjects() as $game) {
			$gameIds[] = $game->gameId;
		}

		$userIds = [];
		if (!empty($gameIds)) {
			$conditions = new PreparedStatementConditionBuilder();
			$conditions->add('gameId IN (?)', [$gameIds]);
			$sql = "SELECT DISTINCT userId
					FROM wcf1_igdb_integration_game_user
					" . $conditions;
			$statement = WCF::getDB()->prepare($sql);
			$statement->execute($conditions->getParameters());
			$userIds = $statement->fetchAll(\PDO::FETCH_COLUMN);
		}

		$returnValues = parent::delete();

		foreach ($userIds as $userId) {
			$this->updateUserGameCount($userId);
		}

		return $returnValues;
	}

	/**
	 * Fires a recent activity event for a game action of the given user and
	 * remembers the time of the interaction for sorting.
	 */
	protected function fireActivityEvent($gameId, $userId, string $action, $rating = 0)
	{
		if (defined('IGDB_INTEGRATION_GENERAL_ENABLE_USER_ACTIVITY') && IGDB_INTEGRATION_GENERAL_ENABLE_USER_ACTIVITY) {
			UserActivityEventHandler::getInstance()->fireEvent(
				'de.berny23.igdb_integration.recentActivityEvent.game',
				$gameId,
				null,
				$userId,
				TIME_NOW,
				[
					'action' => $action,
					'rating' => $rating,
				]
			);
		}

		$sql = "UPDATE wcf1_igdb_integration_game
				SET lastInteractionTime = ?
				WHERE gameId = ?";
		$statement = WCF::getDB()->prepare($sql);
		$statement->execute([TIME_NOW, $gameId]);
	}

	/**
	 * Reloads the game <-> user association after a change and returns the updated data.
	 */
	protected function getUpdatedGameUserData($gameId, $userId, $isOwned)
	{
		// Keep the computed sort columns in sync after every change,
		// including rating changes that fire no activity event
		$this->updateGameStats($gameId);

		// Reload the game <-> user association for the game.

		// Either calculate average rating and count users or get single rating of owner
		if (is_null($this->ownerId)) {
			$sql = "SELECT rating 
					FROM wcf1_igdb_integration_game_user 
					WHERE gameId = ?";
			$statement = WCF::getDB()->prepare($sql);
			$statement->execute([$gameId]);
			$owners = $statement->fetchAll(\PDO::FETCH_COLUMN);

			$ratingArray = array_filter($owners, "wcf\util\IgdbIntegrationUtil::validateRating");
			$averageRating = count($ratingArray) ? array_sum($ratingArray) / count($ratingArray) : 0;
			$playerCount = count($owners);
		} else {
			$sql = "SELECT rating 
					FROM wcf1_igdb_integration_game_user 
					WHERE gameId = ? AND userId = ?";
			$statement = WCF::getDB()->prepare($sql);
			$statement->execute([$gameId, $this->ownerId]);
			$owner = $statement->fetchSingleRow();

			// First check if owner still owns game
			if ($owner) {
				$ownerRating = $owner['rating'];
				$playerCount = 1;
			}
		}

		$gameCount = $this->updateUserGameCount($userId);

		// Return data for displaying in HTML
		return [
			'gameId' => $gameId,
			'isOwned' => $isOwned,
			'playerCount' => $playerCount ?? -1,
			'averageRating' => $averageRating ?? -1,
			'ownRating' => $ownerRating ?? -1,
			'gameCount' => $gameCount
		];
	}

	/**
	 * Checks for permission to show the player list dialog.
	 */
	public function validateGetGamePlayerListDialog()
	{
		WCF::getSession()->checkPermissions(['user.igdb_integration.can_see_player_list']);

		$this->game = new IgdbIntegrationGame($this->parameters['gameId']);
		if (!$this->game->getObjectID()) {
			throw new UserInputException('gameId');
		}
	}

	/**
	 * Returns the data to show the dialog to edit a relationship between a user and a game.
	 */
	public function getGamePlayerListDialog()
	{
		$sql = "SELECT gu.userId AS userId,username,rating
				FROM wcf1_igdb_integration_game_user gu 
				LEFT JOIN wcf1_user u 
				ON u.userID = gu.userId 
				WHERE gameId = ? 
				ORDER BY username ASC";
		$statement = WCF::getDB()->prepare($sql);
		$statement->execute([$this->game->gameId]);
		$gameOwners = $statement->fetchAll(\PDO::FETCH_ASSOC);

		// Batch-load the user profiles to avoid one query per owner
		$profiles = UserProfileRuntimeCache::getInstance()->getObjects(array_column($gameOwners, 'userId'));
		$gameOwnerProfileLinks = array();
		foreach ($gameOwners as $owner) {
			if (!empty($profiles[$owner['userId']])) {
				$gameOwnerProfileLinks[$owner['userId']] = $profiles[$owner['userId']]->getAnchorTag();
			} else {
				$gameOwnerProfileLinks[$owner['userId']] = StringUtil::encodeHTML($owner['username']);
			}
		}

		$this->dialog = DialogFormDocument::create('personGameEditDialog' . $this->game->gameId);
		$this->dialog->addDefaultButton(false);

		$this->dialog->appendChildren([
			TemplateFormNode::create('playerList')
				->templateName('__igdbIntegrationGamePlayerList')
				->variables([
					'gameOwners' => $gameOwners,
					'gameOwnerProfileLinks' => $gameOwnerProfileLinks
				])
		]);

		EventHandler::getInstance()->fireAction($this, 'getGamePlayerListDialog');

		$this->dialog->build();

		return [
			'dialog' => $this->dialog->getHtml(),
			'formId' => $this->dialog->getId(),
		];
	}

	/**
	 * Checks the parameters of the game search of the editor button dialog.
	 */
	public function validateSearchGames()
	{
		$this->readString('searchString');
	}

	/**
	 * Returns the games matching the search string for the live search of the
	 * editor button dialog.
	 */
	public function searchGames()
	{
		$searchString = StringUtil::trim($this->parameters['searchString']);
		if (mb_strlen($searchString) < self::GAME_SEARCH_MIN_LENGTH) {
			return ['games' => []];
		}

		$gameList = new IgdbIntegrationGameList();
		$gameList->sqlSelects .= IgdbIntegrationUtil::getDisplayNameSql() . " AS displayName";
		// Search for all parts, separated with a space, like the game list page
		foreach (explode(' ', $searchString) as $part) {
			$gameList->getConditionBuilder()->add(
				"(name LIKE ?
				OR germanName LIKE ?)",
				['%' . $part . '%', '%' . $part . '%']
			);
		}
		$gameList->sqlOrderBy = 'displayName ASC';
		$gameList->sqlLimit = self::GAME_SEARCH_RESULT_LIMIT;
		$gameList->readObjects();

		$games = [];
		foreach ($gameList as $game) {
			$games[] = [
				'gameId' => $game->gameId,
				'name' => $game->displayName,
				'releaseYear' => $game->releaseYear,
				'platforms' => $game->platforms,
				'coverImageUrl' => IgdbIntegrationUtil::getCoverImageUrl($game->coverImageId, $game->localizedCovers),
			];
		}

		return ['games' => $games];
	}
}
