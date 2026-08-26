<?php

namespace wcf\data\IgdbIntegration;

use wcf\data\IgdbIntegration\IgdbIntegrationGame;
use wcf\data\IgdbIntegration\IgdbIntegrationGameList;
use wcf\util\IgdbIntegrationOgdbUtil;
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
	const STEAM_IMPORT_SEARCH_LIMIT = 500;

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
	const GOG_IMPORT_SEARCH_LIMIT = 500;

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
	const PLAYNITE_IMPORT_SEARCH_LIMIT = 2000;

	/**
	 * Number of per-title IGDB search requests per Playnite import step.
	 */
	const PLAYNITE_IMPORT_SEARCHES_PER_STEP = 5;

	/**
	 * Maximum number of games accepted from a library export file (Playnite,
	 * OGDB).
	 */
	const PLAYNITE_IMPORT_MAX_GAMES = 100000;

	/**
	 * Session variable holding the state of a running Playnite import.
	 */
	const PLAYNITE_IMPORT_STATE_KEY = 'igdbPlayniteImportState';

	/**
	 * Maximum number of per-title IGDB search requests during an OGDB import.
	 * OGDB exports list physical releases and store purchases, most of which
	 * carry no usable external id. 
	*/
	const OGDB_IMPORT_SEARCH_LIMIT = 5000;

	/**
	 * Number of per-title IGDB search requests per OGDB import step.
	 */
	const OGDB_IMPORT_SEARCHES_PER_STEP = 5;

	/**
	 * Session variable holding the state of a running OGDB import.
	 */
	const OGDB_IMPORT_STATE_KEY = 'igdbOgdbImportState';

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
	 * Session variable holding the state of a running ACP refresh of the
	 * whole game database.
	 */
	const ACP_REFRESH_STATE_KEY = 'igdbAcpRefreshState';

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
		// Games that have not been fetched from IGDB for a while may be missing
		// data added there in the meantime, e.g. localized covers or store
		// links. The record is refreshed at most once per refresh interval
		$this->game = IgdbIntegrationUtil::refreshGameIfStale($this->game);

		$gameUserRow = $this->getGameUserRow($this->game->gameId, WCF::getUser()->userID);

		// The large cover is shown next to or above the game data, but only if
		// the game has a cover at all
		$coverImageUrl = '';
		$coverOriginalUrl = '';
		if (IgdbIntegrationUtil::getLocalizedCoverImageId($this->game->coverImageId, $this->game->localizedCovers) !== '') {
			$coverImageUrl = IgdbIntegrationUtil::getCoverImageUrl($this->game->coverImageId, $this->game->localizedCovers, true, true);
			$coverOriginalUrl = IgdbIntegrationUtil::getOriginalCoverImageUrl($this->game->coverImageId, $this->game->localizedCovers);
		}

		$this->dialog = DialogFormDocument::create('personGameEditDialog' . $this->game->gameId)
			->appendChildren([
				TemplateFormNode::create('cover')
					->templateName('__igdbIntegrationGameCover')
					->variables(['coverImageUrl' => $coverImageUrl, 'coverOriginalUrl' => $coverOriginalUrl]),
				TextFormField::create('platforms')
					->label('wcf.igdb_integration.game.platforms')
					->value($this->game->platforms)
					->immutable(),
				DescriptionFormField::create('summary')
					->label('wcf.igdb_integration.game.summary')
					->value($this->game->summary)
					->rows(4)
					->immutable(),
				TemplateFormNode::create('gameLinks')
					->templateName('__igdbIntegrationGameLinks')
					->variables(['gameLinks' => IgdbIntegrationUtil::getGameLinks($this->game)])
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
			$searchedThisStep = [];
			while (!empty($state['searchQueue']) && count($searchedThisStep) < self::STEAM_IMPORT_SEARCHES_PER_STEP) {
				$appId = array_key_first($state['searchQueue']);
				$name = $state['searchQueue'][$appId];
				unset($state['searchQueue'][$appId]);
				$state['searched']++;

				// May have been matched by a search result of a previous step
				if (!isset($state['remaining'][$appId])) {
					continue;
				}

				// The request pacing is handled by the sitewide rate limit queue
				IgdbIntegrationUtil::updateDatabaseGamesByName($name, false);
				$searchedThisStep[$appId] = $name;
			}

			// The search results may contain the Steam link or the exact title
			$matched = [];
			$this->matchRemainingByExternalId('steamAppId', $state['remaining'], $matched);
			$this->matchRemainingByName($state['remaining'], $matched, $state['ambiguous'], false);
			$this->insertImportMatches($matched, $state['stats']);

			// Titles that the search results did not match may be the alias of
			// a differently named game, which the search ranking can push out
			// of the result window. The extra request is only needed on the
			// first import: afterwards the fetched game is matched directly
			// from the local database
			$aliasQueried = false;
			foreach ($searchedThisStep as $appId => $name) {
				if (isset($state['remaining'][$appId])) {
					IgdbIntegrationUtil::updateDatabaseGamesByAlternativeName($name);
					$aliasQueried = true;
				}
			}
			if ($aliasQueried) {
				$matched = [];
				$this->matchRemainingByExternalId('steamAppId', $state['remaining'], $matched);
				$this->matchRemainingByName($state['remaining'], $matched, $state['ambiguous'], false);
				$this->insertImportMatches($matched, $state['stats']);
			}
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
			$searchedThisStep = [];
			while (!empty($state['searchQueue']) && count($searchedThisStep) < self::GOG_IMPORT_SEARCHES_PER_STEP) {
				$gogId = array_key_first($state['searchQueue']);
				$name = $state['searchQueue'][$gogId];
				unset($state['searchQueue'][$gogId]);
				$state['searched']++;

				// May have been matched by a search result of a previous step
				if (!isset($state['remaining'][$gogId])) {
					continue;
				}

				// The request pacing is handled by the sitewide rate limit queue
				IgdbIntegrationUtil::updateDatabaseGamesByName($name, false);
				$searchedThisStep[$gogId] = $name;
			}

			// The search results may contain the GOG link or the exact title
			$matched = [];
			$this->matchRemainingByExternalId('gogId', $state['remaining'], $matched);
			$this->matchRemainingByName($state['remaining'], $matched, $state['ambiguous'], false, 'gogId');
			$this->insertImportMatches($matched, $state['stats']);

			// Titles that the search results did not match may be the alias of
			// a differently named game, which the search ranking can push out
			// of the result window. The extra request is only needed on the
			// first import: afterwards the fetched game is matched directly
			// from the local database
			$aliasQueried = false;
			foreach ($searchedThisStep as $gogId => $name) {
				if (isset($state['remaining'][$gogId])) {
					IgdbIntegrationUtil::updateDatabaseGamesByAlternativeName($name);
					$aliasQueried = true;
				}
			}
			if ($aliasQueried) {
				$matched = [];
				$this->matchRemainingByExternalId('gogId', $state['remaining'], $matched);
				$this->matchRemainingByName($state['remaining'], $matched, $state['ambiguous'], false, 'gogId');
				$this->insertImportMatches($matched, $state['stats']);
			}
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
		$this->validateStartLibraryImport('user.igdb_integration.can_import_playnite_games');
	}

	/**
	 * @see IgdbIntegrationGameAction::startLibraryImport()
	 */
	public function startPlayniteImport()
	{
		return $this->startLibraryImport(self::PLAYNITE_IMPORT_STATE_KEY);
	}

	/**
	 * Checks for permission to run a step of a started Playnite import.
	 */
	public function validateProcessPlayniteImportBatch()
	{
		$this->validateLibraryImportStep(
			'user.igdb_integration.can_import_playnite_games',
			self::PLAYNITE_IMPORT_STATE_KEY,
			'playniteImportState'
		);
	}

	/**
	 * @see IgdbIntegrationGameAction::processLibraryImportBatch()
	 */
	public function processPlayniteImportBatch()
	{
		return $this->processLibraryImportBatch(self::PLAYNITE_IMPORT_STATE_KEY);
	}

	/**
	 * @see IgdbIntegrationGameAction::validateProcessPlayniteImportBatch()
	 */
	public function validateProcessPlayniteImportSearch()
	{
		$this->validateProcessPlayniteImportBatch();
	}

	/**
	 * @see IgdbIntegrationGameAction::processLibraryImportSearch()
	 */
	public function processPlayniteImportSearch()
	{
		return $this->processLibraryImportSearch(
			self::PLAYNITE_IMPORT_STATE_KEY,
			self::PLAYNITE_IMPORT_SEARCH_LIMIT,
			self::PLAYNITE_IMPORT_SEARCHES_PER_STEP
		);
	}

	/**
	 * @see IgdbIntegrationGameAction::validateProcessPlayniteImportBatch()
	 */
	public function validateFinishPlayniteImport()
	{
		$this->validateProcessPlayniteImportBatch();
	}

	/**
	 * @see IgdbIntegrationGameAction::finishLibraryImport()
	 */
	public function finishPlayniteImport()
	{
		return $this->finishLibraryImport(self::PLAYNITE_IMPORT_STATE_KEY);
	}

	/**
	 * Checks for permission to start an OGDB collection import and parses the
	 * submitted game list. The client has already stripped the edition and
	 * store suffixes of the OGDB titles and extracted the Steam app ids and
	 * GOG product ids from the manufacturer codes, so the entries have the
	 * same shape as the ones of a Playnite export.
	 */
	public function validateStartOgdbImport()
	{
		$this->validateStartLibraryImport('user.igdb_integration.can_import_ogdb_games');
	}

	/**
	 * @see IgdbIntegrationGameAction::startLibraryImport()
	 */
	public function startOgdbImport()
	{
		return $this->startLibraryImport(self::OGDB_IMPORT_STATE_KEY);
	}

	/**
	 * Checks for permission to run a step of a started OGDB import.
	 */
	public function validateProcessOgdbImportBatch()
	{
		$this->validateLibraryImportStep(
			'user.igdb_integration.can_import_ogdb_games',
			self::OGDB_IMPORT_STATE_KEY,
			'ogdbImportState'
		);
	}

	/**
	 * @see IgdbIntegrationGameAction::processLibraryImportBatch()
	 */
	public function processOgdbImportBatch()
	{
		return $this->processLibraryImportBatch(self::OGDB_IMPORT_STATE_KEY);
	}

	/**
	 * @see IgdbIntegrationGameAction::validateProcessOgdbImportBatch()
	 */
	public function validateProcessOgdbImportSearch()
	{
		$this->validateProcessOgdbImportBatch();
	}

	/**
	 * @see IgdbIntegrationGameAction::processLibraryImportSearch()
	 */
	public function processOgdbImportSearch()
	{
		return $this->processLibraryImportSearch(
			self::OGDB_IMPORT_STATE_KEY,
			self::OGDB_IMPORT_SEARCH_LIMIT,
			self::OGDB_IMPORT_SEARCHES_PER_STEP
		);
	}

	/**
	 * @see IgdbIntegrationGameAction::validateProcessOgdbImportBatch()
	 */
	public function validateFinishOgdbImport()
	{
		$this->validateProcessOgdbImportBatch();
	}

	/**
	 * @see IgdbIntegrationGameAction::finishLibraryImport()
	 */
	public function finishOgdbImport()
	{
		return $this->finishLibraryImport(self::OGDB_IMPORT_STATE_KEY);
	}

	/**
	 * Checks for the given import permission and parses the submitted game
	 * list of a library export (Playnite, OGDB) into the three pools of Steam
	 * app ids, GOG product ids and name-only entries.
	 */
	protected function validateStartLibraryImport(string $permission)
	{
		if (!WCF::getUser()->userID) {
			throw new PermissionDeniedException();
		}
		WCF::getSession()->checkPermissions([$permission]);

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
		// triple, optionally followed by the OGDB system name of the release.
		// Entries with an external id are collected first, so that a
		// name-only duplicate of the same game from another launcher is
		// dropped in favor of the entry that can be matched exactly. The pools
		// stay separate because a Steam app id and a GOG product id may share
		// the same integer value.
		$steamGames = [];
		$gogGames = [];
		$otherGames = [];
		$otherPlatforms = [];
		$otherSystemNames = [];
		$seenNames = [];
		$seenOtherGames = [];
		foreach ($entries as $entry) {
			if (!is_array($entry) || count($entry) < 3 || count($entry) > 4) {
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
			if (!is_array($entry) || count($entry) < 3 || count($entry) > 4) {
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
			if (!is_array($entry) || count($entry) < 3 || count($entry) > 4) {
				continue;
			}
			$name = StringUtil::trim((string)$entry[2]);
			if (intval($entry[0]) > 0 || intval($entry[1]) > 0 || $name === '') {
				continue;
			}

			// OGDB releases carry their system, which restricts the title
			// matching to the mapped IGDB platforms. Store entries (Steam,
			// GOG) are PC releases, so they only replace name-only entries
			// of PC systems or of unknown systems; the same title on a
			// console stays a separate entry
			$systemName = isset($entry[3]) ? StringUtil::trim((string)$entry[3]) : '';
			$platforms = $systemName !== '' ? IgdbIntegrationOgdbUtil::getIgdbPlatforms($systemName) : null;
			if (isset($seenNames[mb_strtolower($name)]) && IgdbIntegrationOgdbUtil::isPcPlatformList($platforms)) {
				continue;
			}

			// Several releases of the same game for the same platforms (e.g.
			// the CD-ROM and the download version) are matched only once
			$duplicateKey = mb_strtolower($name) . '|' . ($platforms === null ? '*' : implode('|', $platforms));
			if (isset($seenOtherGames[$duplicateKey])) {
				continue;
			}
			$seenOtherGames[$duplicateKey] = true;

			// The synthetic keys must never collide with an external id
			$key = 'n' . $index;
			$otherGames[$key] = $name;
			if ($platforms !== null) {
				$otherPlatforms[$key] = $platforms;
			}
			if ($systemName !== '') {
				$otherSystemNames[$key] = $systemName;
			}
		}

		if (empty($steamGames) && empty($gogGames) && empty($otherGames)) {
			throw new UserInputException('gameList');
		}

		$this->parameters['otherPlatforms'] = $otherPlatforms;
		$this->parameters['otherSystemNames'] = $otherSystemNames;

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
	 * Starts the import of a library export: matches the games against the
	 * local database and prepares the IGDB request batches for the following
	 * import steps.
	 */
	protected function startLibraryImport(string $stateKey)
	{
		$state = [
			'remaining' => $this->parameters['steamGames'],      // Steam app id => name
			'remainingGog' => $this->parameters['gogGames'],     // GOG product id => name
			'remainingNames' => $this->parameters['otherGames'], // synthetic key => name
			'platforms' => $this->parameters['otherPlatforms'],  // synthetic key => IGDB platform tokens, if known
			'systemNames' => $this->parameters['otherSystemNames'], // synthetic key => OGDB system name, if given
			'ambiguous' => [],                                   // list of names
			'stats' => ['imported' => 0, 'alreadyOwned' => 0],
			'searchQueue' => null,                               // filled on the first search step
			'searchTotal' => 0,
			'searched' => 0,
		];

		// Match everything that needs no IGDB request: games already linked to
		// a Steam app id or GOG product id and games with the exact same
		// normalized title
		$this->matchLibraryRemaining($state, false);

		// The remaining Steam app ids and GOG product ids are fetched from
		// IGDB in batches; the games of other sources can only be matched by
		// title
		$state['batches'] = [];
		foreach (array_chunk(array_keys($state['remaining']), self::PLAYNITE_IMPORT_BATCH_SIZE) as $chunk) {
			$state['batches'][] = ['steamAppId', $chunk];
		}
		foreach (array_chunk(array_keys($state['remainingGog']), self::PLAYNITE_IMPORT_BATCH_SIZE) as $chunk) {
			$state['batches'][] = ['gogId', $chunk];
		}

		WCF::getSession()->register($stateKey, $state);

		return [
			'failed' => false,
			'batchCount' => count($state['batches']),
			'gameCount' => count($this->parameters['steamGames'])
				+ count($this->parameters['gogGames'])
				+ count($this->parameters['otherGames']),
		];
	}

	/**
	 * Checks for the given permission and for a started library import in the
	 * session.
	 */
	protected function validateLibraryImportStep(string $permission, string $stateKey, string $errorField)
	{
		if (!WCF::getUser()->userID) {
			throw new PermissionDeniedException();
		}
		WCF::getSession()->checkPermissions([$permission]);

		if (!is_array(WCF::getSession()->getVar($stateKey))) {
			// No import has been started in this session
			throw new UserInputException($errorField);
		}
	}

	/**
	 * Fetches the next batch of Steam app ids or GOG product ids from IGDB
	 * and matches the new local data against the remaining library games.
	 */
	protected function processLibraryImportBatch(string $stateKey)
	{
		$state = WCF::getSession()->getVar($stateKey);

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

			$this->matchLibraryRemaining($state, false);
		}

		WCF::getSession()->register($stateKey, $state);

		return [
			'remainingBatches' => count($state['batches']),
		];
	}

	/**
	 * Searches IGDB for a few of the remaining unmatched titles and matches
	 * the results. The client repeats this step until it reports completion.
	 */
	protected function processLibraryImportSearch(string $stateKey, int $searchLimit, int $searchesPerStep)
	{
		$state = WCF::getSession()->getVar($stateKey);

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

			$state['searchQueue'] = array_slice($queue, 0, $searchLimit, true);
			$state['searchTotal'] = count($state['searchQueue']);
		}

		if (IgdbIntegrationUtil::isConnectionDataValid()) {
			$searchedThisStep = [];
			while (!empty($state['searchQueue']) && count($searchedThisStep) < $searchesPerStep) {
				$key = (string)array_key_first($state['searchQueue']);
				$name = $state['searchQueue'][$key];
				unset($state['searchQueue'][$key]);
				$state['searched']++;

				// May have been matched by a search result of a previous step
				if (!$this->isLibraryGameRemaining($state, $key)) {
					continue;
				}

				// The request pacing is handled by the sitewide rate limit queue
				IgdbIntegrationUtil::updateDatabaseGamesByName($name, false);
				$searchedThisStep[$key] = $name;
			}

			// The search results may contain the store link or the exact title
			$this->matchLibraryRemaining($state, false);

			// Titles that the search results did not match may be the alias of
			// a differently named game (e.g. "Overwatch 2" for "Overwatch"),
			// which the search ranking can push out of the result window. The
			// extra request is only needed on the first import: afterwards the
			// fetched game is matched directly from the local database
			$aliasQueried = false;
			foreach ($searchedThisStep as $key => $name) {
				if ($this->isLibraryGameRemaining($state, (string)$key)) {
					IgdbIntegrationUtil::updateDatabaseGamesByAlternativeName($name);
					$aliasQueried = true;
				}
			}
			if ($aliasQueried) {
				$this->matchLibraryRemaining($state, false);
			}
		} else {
			$state['searchQueue'] = [];
		}

		WCF::getSession()->register($stateKey, $state);

		return [
			'searched' => $state['searched'],
			'searchTotal' => $state['searchTotal'],
			'done' => empty($state['searchQueue']),
		];
	}

	/**
	 * Runs the roman numeral matching pass as a last resort, synchronizes all
	 * computed values and returns the import summary.
	 */
	protected function finishLibraryImport(string $stateKey)
	{
		$state = WCF::getSession()->getVar($stateKey);

		$this->matchLibraryRemaining($state, true);

		if ($state['stats']['imported'] > 0) {
			IgdbIntegrationUtil::updateAllGameStats();
		}
		$gameCount = $this->updateUserGameCount(WCF::getUser()->userID);

		WCF::getSession()->unregister($stateKey);

		// Name-only entries of a known system are listed with their system,
		// because the same title may be listed for several systems
		$unmatchedOtherNames = [];
		foreach ($state['remainingNames'] as $key => $name) {
			$unmatchedOtherNames[] = isset($state['systemNames'][$key])
				? $name . ' (' . $state['systemNames'][$key] . ')'
				: $name;
		}
		$unmatchedNames = array_merge(
			array_values($state['remaining']),
			array_values($state['remainingGog']),
			$unmatchedOtherNames
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
	 * Returns whether the game behind a prefixed search queue key of a
	 * library import is still unmatched in its pool.
	 */
	protected function isLibraryGameRemaining(array $state, string $key): bool
	{
		$prefix = substr($key, 0, 1);
		if ($prefix === 's') {
			return isset($state['remaining'][intval(substr($key, 1))]);
		}
		if ($prefix === 'g') {
			return isset($state['remainingGog'][intval(substr($key, 1))]);
		}

		return isset($state['remainingNames'][$key]);
	}

	/**
	 * Matches all pools of remaining library games and inserts the hits:
	 * entries with a Steam app id or GOG product id by their external id and
	 * by title, entries of other sources by title only. Each pool uses its
	 * own working arrays, because a Steam app id and a GOG product id may
	 * share the same integer value.
	 */
	protected function matchLibraryRemaining(array &$state, bool $convertRomanNumerals)
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
		// they must not be backfilled into the game table. Entries of a known
		// system are only matched against games of the mapped platforms
		$this->matchRemainingByName($state['remainingNames'], $matchedNames, $ambiguousNames, $convertRomanNumerals, 'steamAppId', false, $state['platforms']);

		foreach ($ambiguousNames as $key => $name) {
			if (isset($state['systemNames'][$key])) {
				$ambiguousNames[$key] = $name . ' (' . $state['systemNames'][$key] . ')';
			}
		}
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
	 * Checks for permission to refresh the whole game database from IGDB in
	 * the ACP and reads whether only stale games are meant.
	 */
	public function validateStartAcpRefresh()
	{
		WCF::getSession()->checkPermissions(['admin.igdb_integration.can_manage_games']);
		if (!IgdbIntegrationUtil::isConnectionDataValid()) {
			throw new PermissionDeniedException();
		}

		$this->readBoolean('staleOnly', true);
	}

	/**
	 * Collects the ids of the games to refresh, stalest first, in batches of
	 * one IGDB request each, and returns the batch count.
	 */
	public function startAcpRefresh()
	{
		$conditions = new PreparedStatementConditionBuilder();
		if ($this->parameters['staleOnly']) {
			$conditions->add('lastFetchTime < ?', [IgdbIntegrationUtil::getStaleFetchTimeThreshold()]);
		}
		$sql = "SELECT gameId
				FROM wcf1_igdb_integration_game
				" . $conditions . "
				ORDER BY lastFetchTime ASC, gameId ASC";
		$statement = WCF::getDB()->prepare($sql);
		$statement->execute($conditions->getParameters());
		$gameIds = array_map('intval', $statement->fetchAll(\PDO::FETCH_COLUMN));

		WCF::getSession()->register(self::ACP_REFRESH_STATE_KEY, [
			'batches' => array_chunk($gameIds, self::IGDB_IMPORT_BATCH_SIZE),
			'missing' => [],
			'failed' => false,
		]);

		return [
			'batchCount' => intval(ceil(count($gameIds) / self::IGDB_IMPORT_BATCH_SIZE)),
			'gameCount' => count($gameIds),
		];
	}

	/**
	 * Checks for permission to run a step of a started ACP refresh.
	 */
	public function validateProcessAcpRefreshBatch()
	{
		WCF::getSession()->checkPermissions(['admin.igdb_integration.can_manage_games']);

		if (!is_array(WCF::getSession()->getVar(self::ACP_REFRESH_STATE_KEY))) {
			// No refresh has been started in this session
			throw new UserInputException('acpRefreshState');
		}
	}

	/**
	 * Fetches the next batch of games from IGDB. Games that IGDB no longer
	 * knows are collected for the summary. A failed request stops the
	 * refresh, the games fetched so far stay updated.
	 */
	public function processAcpRefreshBatch()
	{
		$state = WCF::getSession()->getVar(self::ACP_REFRESH_STATE_KEY);

		$batch = array_shift($state['batches']);
		if ($batch !== null && !$state['failed']) {
			$receivedGameIds = IgdbIntegrationUtil::updateDatabaseGamesByIds($batch);
			if ($receivedGameIds === null) {
				$state['failed'] = true;
				$state['batches'] = [];
			} else {
				$state['missing'] = array_merge($state['missing'], array_values(array_diff($batch, $receivedGameIds)));
			}
		}

		WCF::getSession()->register(self::ACP_REFRESH_STATE_KEY, $state);

		return [
			'failed' => $state['failed'],
			'remainingBatches' => count($state['batches']),
		];
	}

	/**
	 * @see IgdbIntegrationGameAction::validateProcessAcpRefreshBatch()
	 */
	public function validateFinishAcpRefresh()
	{
		$this->validateProcessAcpRefreshBatch();
	}

	/**
	 * Returns the summary of the ACP refresh: whether it failed and the games
	 * that IGDB no longer knows.
	 */
	public function finishAcpRefresh()
	{
		$state = WCF::getSession()->getVar(self::ACP_REFRESH_STATE_KEY);
		WCF::getSession()->unregister(self::ACP_REFRESH_STATE_KEY);

		return [
			'failed' => $state['failed'],
			'missingGames' => IgdbIntegrationUtil::loadGameSummaries($state['missing']),
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
	 *
	 * Store games are PC games, so by default only PC, Mac and Linux games are
	 * candidates. If $platformsByKey is given (OGDB import), the candidates of
	 * an entry are the games of its IGDB platform tokens instead, and entries
	 * without tokens (unknown system) are matched against games of any
	 * platform.
	 */
	protected function matchRemainingByName(array &$remaining, array &$matched, array &$ambiguous, bool $convertRomanNumerals, string $externalIdColumn = 'steamAppId', bool $backfillExternalId = true, ?array $platformsByKey = null)
	{
		if (empty($remaining)) {
			return;
		}

		if ($platformsByKey === null) {
			// Token match to not confuse "PC" with platforms like "PC-FX". The
			// bare "PC" is the abbreviation stored by versions before 2.1.2, kept
			// for games that were not refreshed since. Games without platform
			// data (some smaller IGDB entries, e.g. "Hellgrinder") are included
			// as well, because excluding them would make their titles unmatchable
			$platformPattern = '(^|, )(PC|PC \\(Microsoft Windows\\)|Mac|Linux)(,|$)';
			$sql = "SELECT gameId, name, alternativeNames, platforms, " . $externalIdColumn . " AS externalId
					FROM wcf1_igdb_integration_game
					WHERE platforms REGEXP ?
						OR platforms = ''";
			$statement = WCF::getDB()->prepare($sql);
			$statement->execute([$platformPattern]);
		} else {
			$sql = "SELECT gameId, name, alternativeNames, platforms, " . $externalIdColumn . " AS externalId
					FROM wcf1_igdb_integration_game";
			$statement = WCF::getDB()->prepare($sql);
			$statement->execute();
		}

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

			// Entries of a known system only match the games of its platforms;
			// a title that exists for other platforms only stays unmatched, so
			// that a later IGDB search can still find the right version
			if ($platformsByKey !== null && isset($platformsByKey[$externalId])) {
				$platforms = $platformsByKey[$externalId];
				$candidates = array_values(array_filter($candidates, function ($candidate) use ($platforms) {
					return IgdbIntegrationOgdbUtil::gameHasPlatform((string)$candidate['platforms'], $platforms);
				}));
				if (empty($candidates)) {
					continue;
				}
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

		// Calculate average rating and count users of the game
		$sql = "SELECT rating
				FROM wcf1_igdb_integration_game_user
				WHERE gameId = ?";
		$statement = WCF::getDB()->prepare($sql);
		$statement->execute([$gameId]);
		$ratings = $statement->fetchAll(\PDO::FETCH_COLUMN);

		$ratingArray = array_filter($ratings, "wcf\util\IgdbIntegrationUtil::validateRating");
		$averageRating = count($ratingArray) ? array_sum($ratingArray) / count($ratingArray) : 0;
		$playerCount = count($ratings);

		// On a profile the rating of the profile owner is shown instead of the
		// average rating, and the game is only listed while the owner owns it
		$ownerOwnsGame = true;
		if (!is_null($this->ownerId)) {
			$sql = "SELECT rating
					FROM wcf1_igdb_integration_game_user
					WHERE gameId = ? AND userId = ?";
			$statement = WCF::getDB()->prepare($sql);
			$statement->execute([$gameId, $this->ownerId]);
			$owner = $statement->fetchSingleRow();

			$ownerOwnsGame = !empty($owner);
			if ($ownerOwnsGame) {
				$ownerRating = $owner['rating'];
			}
		}

		$gameCount = $this->updateUserGameCount($userId);

		// Return data for displaying in HTML
		return [
			'gameId' => $gameId,
			'isOwned' => $isOwned,
			'playerCount' => $playerCount,
			'ownerOwnsGame' => $ownerOwnsGame,
			'averageRating' => $averageRating,
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
		foreach (IgdbIntegrationUtil::getSearchConditions($searchString) as [$conditionSql, $conditionParams]) {
			$gameList->getConditionBuilder()->add($conditionSql, $conditionParams);
		}
		// The best name matches come first so that the result limit cannot cut
		// off the searched game itself
		$gameList->sqlOrderBy = IgdbIntegrationUtil::getNameSearchRelevanceSql($searchString) . ', displayName ASC';
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
