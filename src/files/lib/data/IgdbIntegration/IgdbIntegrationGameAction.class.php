<?php

namespace wcf\data\IgdbIntegration;

use Exception;
use wcf\data\IgdbIntegration\IgdbIntegrationGame;
use wcf\util\IgdbIntegrationUtil;
use wcf\system\WCF;
use wcf\data\user\UserEditor;
use wcf\data\AbstractDatabaseObjectAction;
use wcf\system\event\EventHandler;
use wcf\system\form\builder\DialogFormDocument;
use wcf\system\form\builder\field\BooleanFormField;
use wcf\system\form\builder\field\RatingFormField;
use wcf\system\form\builder\field\TitleFormField;
use wcf\system\form\builder\field\TextFormField;
use wcf\system\form\builder\field\DescriptionFormField;
use wcf\system\form\builder\TemplateFormNode;

/**
 * Executes game-related actions.
 *
 * @author      Berny23
 * @copyright   2023 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\Data\IgdbIntegration
 */
class IgdbIntegrationGameAction extends AbstractDatabaseObjectAction
{
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
		$sql = "SELECT DISTINCT rating 
				FROM wcf1_igdb_integration_game_user 
				WHERE gameId = ? AND userId = ?";
		$statement = WCF::getDB()->prepare($sql);
		$statement->execute([$this->game->gameId, WCF::getUser()->userID]);
		$gameUserRow = $statement->fetchSingleRow();

		$name = IgdbIntegrationUtil::getLocalizedGameNameColumn();
		$sql = "SELECT DISTINCT CASE WHEN 
					" . $name . " = '' 
					THEN name ELSE " . $name . " END 
					AS displayName 
				FROM wcf1_igdb_integration_game 
				WHERE gameId = ?";
		$statement = WCF::getDB()->prepare($sql);
		$statement->execute([$this->game->gameId]);
		$gameRow = $statement->fetchSingleRow();

		$this->dialog = DialogFormDocument::create('personGameEditDialog' . $this->game->gameId)
			->appendChildren([
				TitleFormField::create('name')
					->label('wcf.igdb_integration.game.name')
					->value($gameRow['displayName'] ?? '')
					->immutable(),
				TextFormField::create('releaseYear')
					->label('wcf.igdb_integration.game.year')
					->value($this->game->releaseYear)
					->immutable(),
				TextFormField::create('platforms')
					->label('wcf.igdb_integration.game.platforms')
					->value($this->game->platforms)
					->immutable(),
				DescriptionFormField::create('summary')
					->label('wcf.igdb_integration.game.summary')
					->value($this->game->summary)
					->immutable()
			]);
		if (WCF::getSession()->getPermission('user.igdb_integration.can_manage_own_games')) {
			$this->dialog->appendChildren([
				BooleanFormField::create('isOwned')
					->label('wcf.igdb_integration.dialog.game_user_edit_is_owned')
					->value(!empty($gameUserRow))
					->required(),
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
		$this->validateGetGameUserEditDialog();

		// If there are any validation errors, show the form again.
		if (!isset($this->parameters['gameId']) || !isset($this->parameters['data']['isOwned'])) {
			return $this->getGameUserEditDialog();
		}
	}

	/**
	 * Handles submitting the form.
	 */
	public function submitGameUserEditDialog()
	{
		$gameId = $this->parameters['gameId'];
		$userId = WCF::getUser()->userID;
		$isOwned = boolval($this->parameters['data']['isOwned']);
		$rating = $this->parameters['data']['rating'] ?? 0;

		if ($isOwned) {
			// Insert or update association data

			$sql = "SELECT gameId,userId 
					FROM wcf1_igdb_integration_game_user 
					WHERE gameId = ? AND userId = ?";
			$statement = WCF::getDB()->prepare($sql);
			$statement->execute([$gameId, $userId]);
			$row = $statement->fetchSingleRow();

			if (!empty($row)) {
				$sql = "UPDATE wcf1_igdb_integration_game_user 
						SET rating = ? 
						WHERE gameId = ? AND userId = ?";
				$statement = WCF::getDB()->prepare($sql);
				$statement->execute([$rating, $gameId, $userId]);
			} else {
				$sql = "INSERT INTO wcf1_igdb_integration_game_user 
						SET gameId = ?, userId = ?, rating = ?";
				$statement = WCF::getDB()->prepare($sql);
				$statement->execute([$gameId, $userId, $rating]);
			}
		} else {
			// Remove association data

			$sql = "DELETE FROM wcf1_igdb_integration_game_user 
					WHERE gameId = ? AND userId = ?";
			$statement = WCF::getDB()->prepare($sql);
			$statement->execute([$gameId, $userId]);
		}

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
			$sql = "SELECT DISTINCT rating 
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

		// Get game count for user
		$sql = "SELECT DISTINCT COUNT(*) AS gameCount 
				FROM wcf1_igdb_integration_game_user 
				WHERE userId = ?";
		$statement = WCF::getDB()->prepare($sql);
		$statement->execute([$userId]);
		$gameCount = $statement->fetchSingleRow();

		//update game count profile field used for display only
		$userEditor = new UserEditor(WCF::getUser());
		$userEditor->updateUserOptions([
			WCF::getUser()->getUserOptionID('igdb_integration_game_count') => $gameCount['gameCount']
		]);

		// Update user database info used for trophies
		$sql = "UPDATE wcf1_user 
				SET IgdbIntegrationGameCount = ? 
				WHERE userID = ?";
		$statement = WCF::getDB()->prepare($sql);
		$statement->execute([$gameCount['gameCount'], $userId]);

		// Return data for displaying in HTML
		return [
			'gameId' => $gameId,
			'isOwned' => $isOwned,
			'playerCount' => isset($playerCount) ? $playerCount : -1,
			'averageRating' => isset($averageRating) ? $averageRating : -1,
			'ownRating' => isset($ownerRating) ? $ownerRating : -1,
			'ownerRating' => isset($ownerRating) ? $ownerRating : -1
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

		$this->dialog = DialogFormDocument::create('personGameEditDialog' . $this->game->gameId);
		$this->dialog->addDefaultButton(false);

		$this->dialog->appendChildren([
			TemplateFormNode::create('playerList')
				->templateName('__igdbIntegrationGamePlayerList')
				->variables([
					'gameOwners' => $gameOwners
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
