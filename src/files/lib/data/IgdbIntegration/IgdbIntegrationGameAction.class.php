<?php

namespace wcf\data\IgdbIntegration;

use wcf\data\IgdbIntegration\IgdbIntegrationGame;
use wcf\util\IgdbIntegrationUtil;
use CuyZ\Valinor\Mapper\Source\Source;
use CuyZ\Valinor\Mapper\TreeMapper;
use CuyZ\Valinor\MapperBuilder;
use Laminas\Diactoros\Response\JsonResponse;
use Laminas\Diactoros\Response\TextResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use wcf\system\WCF;
use wcf\data\user\UserEditor;
use wcf\data\user\User;
use wcf\data\user\UserProfile;
use wcf\data\AbstractDatabaseObjectAction;
use wcf\system\event\EventHandler;
use wcf\system\form\builder\Psr15DialogForm;
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
class IgdbIntegrationGameAction extends AbstractDatabaseObjectAction implements RequestHandlerInterface
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

	private const PARAMETERS = <<<'EOT'
        array {
            gameId: positive-int
        }
        EOT;

	private TreeMapper $mapper;

	public function __construct()
	{
		$this->mapper = (new MapperBuilder())
			->allowSuperfluousKeys()
			->enableFlexibleCasting()
			->mapper();
	}

	/**
	 * @inheritDoc
	 */
	public function handle(ServerRequestInterface $request): ResponseInterface
	{
		$parameters = $this->mapper->map(
			self::PARAMETERS,
			Source::array($request->getQueryParams())
		);

		// Validate parameters
		// If userId is present, use the user's rating instead of the average rating
		$ownerId = !empty($parameters['userId']) ? $parameters['userId'] : null;

		$game = new IgdbIntegrationGame($parameters['gameId']);
		if (!$game->getObjectID()) {
			throw new UserInputException('gameId');
		}

		// Create form
		$form = $this->getForm($game);

		if ($request->getMethod() === 'GET') {
			return $form->toJsonResponse();
		} elseif ($request->getMethod() === 'POST') {
			$response = $form->validatePsr7Request($request);
			if ($response !== null) {
				return $response;
			}

			// Process form submit
			$data = $form->getData()['data'];

			$userId = WCF::getUser()->userID;
			$isOwned = boolval($data['isOwned']);
			$rating = $data['rating'] ?? 0;

			if ($isOwned) {
				// Insert or update association data
				$sql = "SELECT gameId,userId 
					FROM wcf1_igdb_integration_game_user 
					WHERE gameId = ? AND userId = ?";
				$statement = WCF::getDB()->prepare($sql);
				$statement->execute([$game->gameId, $userId]);
				$row = $statement->fetchSingleRow();

				if (!empty($row)) {
					$sql = "UPDATE wcf1_igdb_integration_game_user 
						SET rating = ? 
						WHERE gameId = ? AND userId = ?";
					$statement = WCF::getDB()->prepare($sql);
					$statement->execute([$rating, $game->gameId, $userId]);
				} else {
					$sql = "INSERT INTO wcf1_igdb_integration_game_user 
						SET gameId = ?, userId = ?, rating = ?";
					$statement = WCF::getDB()->prepare($sql);
					$statement->execute([$game->gameId, $userId, $rating]);
				}
			} else {
				// Remove association data
				$sql = "DELETE FROM wcf1_igdb_integration_game_user 
					WHERE gameId = ? AND userId = ?";
				$statement = WCF::getDB()->prepare($sql);
				$statement->execute([$game->gameId, $userId]);
			}

			// Reload the game <-> user association for the game.
			// Either calculate average rating and count users or get single rating of owner
			if (is_null($ownerId)) {
				$sql = "SELECT rating 
					FROM wcf1_igdb_integration_game_user 
					WHERE gameId = ?";
				$statement = WCF::getDB()->prepare($sql);
				$statement->execute([$game->gameId]);
				$owners = $statement->fetchAll(\PDO::FETCH_COLUMN);

				$ratingArray = array_filter($owners, "wcf\util\IgdbIntegrationUtil::validateRating");
				$averageRating = count($ratingArray) ? array_sum($ratingArray) / count($ratingArray) : 0;
				$playerCount = count($owners);
			} else {
				$sql = "SELECT rating 
					FROM wcf1_igdb_integration_game_user 
					WHERE gameId = ? AND userId = ?";
				$statement = WCF::getDB()->prepare($sql);
				$statement->execute([$game->gameId, $ownerId]);
				$owner = $statement->fetchSingleRow();

				// First check if owner still owns game
				if ($owner) {
					$ownerRating = $owner['rating'];
					$playerCount = 1;
				}
			}

			// Get game count for user
			$sql = "SELECT COUNT(*) AS gameCount 
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
			return new JsonResponse([
				'result' => [
					'gameId' => $game->gameId,
					'isOwned' => $isOwned,
					'playerCount' => isset($playerCount) ? $playerCount : -1,
					'averageRating' => isset($averageRating) ? $averageRating : -1,
					'ownRating' => isset($ownerRating) ? $ownerRating : -1,
					'ownerRating' => isset($ownerRating) ? $ownerRating : -1
				],
			]);
		} else {
			return new TextResponse('The used HTTP method is not allowed.', 405, [
				'allow' => 'POST, GET',
			]);
		}
	}

	/**
	 * Returns the data to show the dialog to edit a relationship between a user and a game.
	 */
	private function getForm(IgdbIntegrationGame $game): Psr15DialogForm
	{
		$sql = "SELECT rating 
				FROM wcf1_igdb_integration_game_user 
				WHERE gameId = ? AND userId = ?";
		$statement = WCF::getDB()->prepare($sql);
		$statement->execute([$game->gameId, WCF::getUser()->userID]);
		$gameUserRow = $statement->fetchSingleRow();

		$name = IgdbIntegrationUtil::getLocalizedGameNameColumn();
		$sql = "SELECT CASE WHEN 
					" . $name . " = '' 
					THEN name ELSE " . $name . " END 
					AS displayName 
				FROM wcf1_igdb_integration_game 
				WHERE gameId = ?";
		$statement = WCF::getDB()->prepare($sql);
		$statement->execute([$game->gameId]);
		$gameRow = $statement->fetchSingleRow();

		$form = new Psr15DialogForm(
			'gameUserEditDialog' + $game->gameId,
			WCF::getLanguage()->get('wcf.igdb_integration.dialog.game_user_edit_title')
		);
		$form->appendChildren([
			TitleFormField::create('name')
				->label('wcf.igdb_integration.game.name')
				->value($gameRow['displayName'] ?? '')
				->immutable(),
			TextFormField::create('releaseYear')
				->label('wcf.igdb_integration.game.year')
				->value($game->releaseYear ?? '')
				->immutable(),
			TextFormField::create('platforms')
				->label('wcf.igdb_integration.game.platforms')
				->value($game->platforms)
				->immutable(),
			DescriptionFormField::create('summary')
				->label('wcf.igdb_integration.game.summary')
				->value($game->summary)
				->immutable()
		]);

		if (WCF::getSession()->getPermission('user.igdb_integration.can_manage_own_games')) {
			$form->appendChildren([
				BooleanFormField::create('isOwned')
					->label('wcf.igdb_integration.dialog.game_user_edit_is_owned')
					->value(!empty($gameUserRow)),
				RatingFormField::create('rating')
					->label('wcf.form.field.rating')
					->value($gameUserRow['rating'] ?? 0)
			]);
		} else {
			$form->addDefaultButton(false);
		}

		EventHandler::getInstance()->fireAction($this, 'getGameUserEditDialog');
		$form->build();

		return $form;
	}





	// TODO: 

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
		$name = IgdbIntegrationUtil::getLocalizedGameNameColumn();
		$sql = "SELECT CASE WHEN 
					" . $name . " = '' 
					THEN name ELSE " . $name . " END 
					AS displayName 
				FROM wcf1_igdb_integration_game 
				WHERE gameId = ?";
		$statement = WCF::getDB()->prepare($sql);
		$statement->execute([$this->game->gameId]);
		$gameRow = $statement->fetchSingleRow();

		$sql = "SELECT gu.userId AS userId,username,rating 
				FROM wcf1_igdb_integration_game_user gu 
				LEFT JOIN wcf1_user u 
				ON u.userID = gu.userId 
				WHERE gameId = ? 
				ORDER BY username ASC";
		$statement = WCF::getDB()->prepare($sql);
		$statement->execute([$this->game->gameId]);
		$gameOwners = $statement->fetchAll(\PDO::FETCH_ASSOC);

		$gameOwnerProfileLinks = array();
		foreach ($gameOwners as $owner) {
			$gameOwnerProfileLinks[$owner['userId']] = (new UserProfile(new User($owner['userId'])))->getAnchorTag();
		}

		$this->dialog = Psr15DialogForm::create('personGameEditDialog' . $this->game->gameId);
		$this->dialog->addDefaultButton(false);

		$this->dialog->appendChildren([
			TitleFormField::create('name')
				->label('wcf.igdb_integration.game.name')
				->value($gameRow['displayName'] ?? '')
				->immutable(),
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
