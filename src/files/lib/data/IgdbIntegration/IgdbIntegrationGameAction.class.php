<?php

namespace wcf\data\IgdbIntegration;

use wcf\data\IgdbIntegration\IgdbIntegrationGame;
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
	protected $allowGuestAccess = ['getGameUserEditDialog', 'getGamePlayerListDialog'];

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

		return $this->getUpdatedGameUserData($gameId, $userId, $isOwned);
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

		return $this->getUpdatedGameUserData($gameId, $userId, true);
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

		return $this->getUpdatedGameUserData($gameId, $userId, false);
	}

	/**
	 * Checks for permission to show the Steam import dialog.
	 */
	public function validateGetSteamImportDialog()
	{
		if (!WCF::getUser()->userID) {
			throw new PermissionDeniedException();
		}
		WCF::getSession()->checkPermissions(['user.igdb_integration.can_import_games']);

		if (!IgdbIntegrationUtil::isSteamConnectionDataValid()) {
			throw new PermissionDeniedException();
		}
		if (!WCF::getSession()->getVar('igdbSteamId')) {
			// The Steam account has to be verified via the login flow first
			throw new PermissionDeniedException();
		}
	}

	/**
	 * Returns the data to show the dialog that confirms the Steam library import.
	 */
	public function getSteamImportDialog()
	{
		$steamId = WCF::getSession()->getVar('igdbSteamId');
		$steamGames = IgdbIntegrationUtil::fetchSteamOwnedGames($steamId);
		$steamGameCount = is_array($steamGames) ? count($steamGames) : 0;

		$this->dialog = DialogFormDocument::create('steamImportDialog')
			->appendChildren([
				TemplateFormNode::create('steamImportInfo')
					->templateName('__igdbIntegrationSteamImportInfo')
					->variables([
						'steamImportSteamId' => $steamId,
						'steamGameCount' => $steamGameCount,
						'steamRequestFailed' => $steamGames === null,
					])
			]);

		if (!$steamGameCount) {
			$this->dialog->addDefaultButton(false);
		}

		EventHandler::getInstance()->fireAction($this, 'getSteamImportDialog');

		$this->dialog->build();

		return [
			'dialog' => $this->dialog->getHtml(),
			'formId' => $this->dialog->getId(),
		];
	}

	/**
	 * Checks for permission to start the Steam library import.
	 */
	public function validateStartSteamImport()
	{
		$this->validateGetSteamImportDialog();
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
		$this->matchRemainingBySteamAppId($state['remaining'], $matched);
		$this->matchRemainingByName($state['remaining'], $matched, $state['ambiguous'], false);
		$this->insertSteamImportMatches($matched, $state['stats']);

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
		WCF::getSession()->checkPermissions(['user.igdb_integration.can_import_games']);

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
			$this->matchRemainingBySteamAppId($state['remaining'], $matched);
			$this->matchRemainingByName($state['remaining'], $matched, $state['ambiguous'], false);
			$this->insertSteamImportMatches($matched, $state['stats']);
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
			$this->matchRemainingBySteamAppId($state['remaining'], $matched);
			$this->matchRemainingByName($state['remaining'], $matched, $state['ambiguous'], false);
			$this->insertSteamImportMatches($matched, $state['stats']);
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
		$this->insertSteamImportMatches($matched, $state['stats']);

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
	 * Adds all matched games that the current user does not own yet, without
	 * firing activity events to not flood the recent activity list, and adds
	 * the counts to the import statistics.
	 */
	protected function insertSteamImportMatches(array $matched, array &$stats)
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
	 * Matches the remaining Steam games against the steamAppId column and
	 * moves all hits from $remaining to $matched.
	 */
	protected function matchRemainingBySteamAppId(array &$remaining, array &$matched)
	{
		if (empty($remaining)) {
			return;
		}

		foreach (array_chunk(array_keys($remaining), 500) as $chunk) {
			$conditions = new PreparedStatementConditionBuilder();
			$conditions->add('steamAppId IN (?)', [$chunk]);
			$sql = "SELECT steamAppId, gameId
					FROM wcf1_igdb_integration_game
					" . $conditions;
			$statement = WCF::getDB()->prepare($sql);
			$statement->execute($conditions->getParameters());
			while ($row = $statement->fetchArray()) {
				$steamAppId = intval($row['steamAppId']);
				if (isset($remaining[$steamAppId])) {
					$matched[$steamAppId] = intval($row['gameId']);
					unset($remaining[$steamAppId]);
				}
			}
		}
	}

	/**
	 * Matches remaining Steam games by normalized title against all local games.
	 * Unique hits are moved to $matched. Titles shared by multiple games (e.g. game +
	 * same-named remaster) are moved to $ambiguous, because Steam provides no year.
	 */
	protected function matchRemainingByName(array &$remaining, array &$matched, array &$ambiguous, bool $convertRomanNumerals)
	{
		if (empty($remaining)) {
			return;
		}

		// Token match to not confuse "PC" with platforms like "PC-FX"
		$platformPattern = '(^|, )(PC|Mac|Linux)(,|$)';
		$sql = "SELECT gameId, name, steamAppId
				FROM wcf1_igdb_integration_game
				WHERE platforms REGEXP ?";
		$statement = WCF::getDB()->prepare($sql);
		$statement->execute([$platformPattern]);

		$gamesByTitle = [];
		while ($row = $statement->fetchArray()) {
			$title = IgdbIntegrationUtil::normalizeGameTitle($row['name'], $convertRomanNumerals);
			if ($title !== '') {
				$gamesByTitle[$title][] = $row;
			}
		}

		$backfillStatement = null;
		foreach ($remaining as $steamAppId => $name) {
			$title = IgdbIntegrationUtil::normalizeGameTitle($name, $convertRomanNumerals);
			if ($title === '' || !isset($gamesByTitle[$title])) {
				continue;
			}

			$gameIds = array_unique(array_column($gamesByTitle[$title], 'gameId'));
			if (count($gameIds) === 1) {
				$matched[$steamAppId] = intval(reset($gameIds));

				// Remember direct matches, so future imports resolve them by
				// app id; roman numeral matches are too fuzzy to persist
				if (!$convertRomanNumerals && $gamesByTitle[$title][0]['steamAppId'] === null) {
					if ($backfillStatement === null) {
						$sql = "UPDATE wcf1_igdb_integration_game
								SET steamAppId = ?
								WHERE gameId = ? AND steamAppId IS NULL";
						$backfillStatement = WCF::getDB()->prepare($sql);
					}
					$backfillStatement->execute([$steamAppId, $matched[$steamAppId]]);
				}
			} else {
				$ambiguous[$steamAppId] = $name;
			}
			unset($remaining[$steamAppId]);
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
}
