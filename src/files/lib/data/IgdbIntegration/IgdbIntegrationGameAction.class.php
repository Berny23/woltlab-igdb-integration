<?php

namespace wcf\data\IgdbIntegration;

use wcf\data\IgdbIntegration\IgdbIntegrationGame;
use wcf\util\IgdbIntegrationUtil;
use wcf\system\WCF;
use wcf\data\user\UserEditor;
use wcf\data\AbstractDatabaseObjectAction;
use wcf\system\event\EventHandler;
use wcf\system\exception\PermissionDeniedException;
use wcf\system\form\builder\DialogFormDocument;
use wcf\system\form\builder\field\BooleanFormField;
use wcf\system\form\builder\field\RatingFormField;
use wcf\system\form\builder\field\TitleFormField;
use wcf\system\form\builder\field\TextFormField;
use wcf\system\form\builder\field\DescriptionFormField;

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
    protected $permissionsCreate = ['admin.IgdbIntegration.canManageGames'];

    /**
     * @inheritDoc
     */
    protected $permissionsUpdate = ['admin.IgdbIntegration.canManageGames'];

    /**
     * @inheritDoc
     */
    protected $permissionsDelete = ['admin.IgdbIntegration.canManageGames'];

    /**
     * @inheritDoc
     */
    protected $requireACP = ['create', 'delete', 'update'];

    /**
     * @var DialogFormDocument
     */
    protected $dialog;

    /**
     * @var IgdbIntegrationGame
     */
    protected $game;

    /**
     * Override default function because it always returns false, even for admins (I don't know why).
     */
    public function validateAction()
    {
        $this->readInteger('gameId');
        $this->game = new IgdbIntegrationGame($this->parameters['gameId']);
        if (!$this->game->getObjectID()) {
            throw new UserInputException('gameId');
        }

        return true; // Always return true, because this form also shows general info. Permission is later checked.
    }

    /**
     * Returns the data to show the dialog to edit a relationship between a user and a game.
     */
    public function getGameUserEditDialog()
    {
        $sql = "SELECT DISTINCT rating FROM wcf" . WCF_N . "_igdb_integration_game_user WHERE gameId = ? AND userId = ?";
        $statement = WCF::getDB()->prepare($sql);
        $statement->execute([$this->game->gameId, WCF::getUser()->userID]);
        $gameUserRow = $statement->fetchSingleRow();

        $name = IgdbIntegrationUtil::getLocalizedGameNameColumn();
        $sql = "SELECT DISTINCT CASE WHEN " . $name . " = '' THEN name ELSE " . $name . " END AS displayName FROM wcf" . WCF_N . "_igdb_integration_game WHERE gameId = ?";
        $statement = WCF::getDB()->prepare($sql);
        $statement->execute([$this->game->gameId]);
        $gameRow = $statement->fetchSingleRow();

        $this->dialog = DialogFormDocument::create('personGameEditDialog' . $this->game->gameId)
            ->appendChildren([
                TitleFormField::create('name')
                    ->label('wcf.IgdbIntegration.game.name')
                    ->value($gameRow['displayName'] ?? '')
                    ->immutable(),
                TextFormField::create('firstReleaseDateYear')
                    ->label('wcf.IgdbIntegration.game.year')
                    ->value($this->game->firstReleaseDateYear)
                    ->immutable(),
                TextFormField::create('platforms')
                    ->label('wcf.IgdbIntegration.game.platforms')
                    ->value($this->game->platforms)
                    ->immutable(),
                DescriptionFormField::create('summary')
                    ->label('wcf.IgdbIntegration.game.summary')
                    ->value($this->game->summary)
                    ->immutable()
            ]);

        // These form fields should only be show to users with the right permission, guests can still see the general game info above.
        try {
            WCF::getSession()->checkPermissions(['user.IgdbIntegration.canManageOwnGames']); // This can throw a PermissionDeniedException

            $this->dialog->appendChildren([
                BooleanFormField::create('isOwned')
                    ->label('wcf.IgdbIntegration.dialog.game_user_edit_isOwned')
                    ->value(!($gameUserRow == NULL))
                    ->required(),
                RatingFormField::create('rating')
                    ->label('wcf.form.field.rating')
                    ->value($gameUserRow['rating'] ?? 0)
            ]);

            EventHandler::getInstance()->fireAction($this, 'getGameUserEditDialog');
        } catch (PermissionDeniedException $ex) {
            $this->dialog->addDefaultButton(false);
        }

        $this->dialog->build();

        return [
            'dialog' => $this->dialog->getHtml(),
            'formId' => $this->dialog->getId(),
        ];
    }

    /**
     * Handles submitting the form.
     */
    public function submitGameUserEditDialog()
    {
        WCF::getSession()->checkPermissions(['user.IgdbIntegration.canManageOwnGames']);

        // If there are any validation errors, show the form again.
        if (!isset($_REQUEST['parameters']['gameId']) || !isset($_REQUEST['parameters']['data']['isOwned'])) {
            return $this->getGameUserEditDialog();
        }

        $gameId = $_REQUEST['parameters']['gameId'];
        $userId = WCF::getUser()->userID;
        $isOwned = boolval($_REQUEST['parameters']['data']['isOwned']);
        $rating = $_REQUEST['parameters']['data']['rating'] ?? 0;

        if ($isOwned) {
            // Insert or update association data

            $sql = "SELECT gameId,userId FROM wcf" . WCF_N . "_igdb_integration_game_user WHERE gameId = ? AND userId = ?";
            $statement = WCF::getDB()->prepare($sql);
            $statement->execute([$gameId, $userId]);
            $row = $statement->fetchSingleRow();

            if ($row != NULL) {
                $sql = "UPDATE wcf" . WCF_N . "_igdb_integration_game_user SET rating = ? WHERE gameId = ? AND userId = ?";
                $statement = WCF::getDB()->prepare($sql);
                $statement->execute([$rating, $gameId, $userId]);
            } else {
                $sql = "INSERT INTO wcf" . WCF_N . "_igdb_integration_game_user SET gameId = ?, userId = ?, rating = ?";
                $statement = WCF::getDB()->prepare($sql);
                $statement->execute([$gameId, $userId, $rating]);
            }
        } else {
            // Remove association data

            $sql = "DELETE FROM wcf" . WCF_N . "_igdb_integration_game_user WHERE gameId = ? AND userId = ?";
            $statement = WCF::getDB()->prepare($sql);
            $statement->execute([$gameId, $userId]);
        }

        // Reload the game <-> user association for the game.
        $sql = "SELECT rating FROM wcf" . WCF_N . "_igdb_integration_game_user WHERE gameId = ?";
        $statement = WCF::getDB()->prepare($sql);
        $statement->execute([$gameId]);
        $rows = $statement->fetchAll(\PDO::FETCH_COLUMN);

        $ratingArray = array_filter($rows, "wcf\util\IgdbIntegrationUtil::validateRating");
        $averageRating = count($ratingArray) ? array_sum($ratingArray) / count($ratingArray) : 0;

        // Get game count for user
        $sql = "SELECT DISTINCT COUNT(*) AS gameCount FROM wcf" . WCF_N . "_igdb_integration_game_user WHERE userId = ?";
        $statement = WCF::getDB()->prepare($sql);
        $statement->execute([$userId]);
        $gameCount = $statement->fetchSingleRow();

        //update game count profile field used for display only
        $userEditor = new UserEditor(WCF::getUser());
        $userEditor->updateUserOptions([
            WCF::getUser()->getUserOptionID('IgdbIntegration_game_count') => $gameCount['gameCount']
        ]);

        // Update user database info used for trophies
        $sql = "UPDATE wcf" . WCF_N . "_user SET IgdbIntegrationGameCount = ? WHERE userID = ?";
        $statement = WCF::getDB()->prepare($sql);
        $statement->execute([$gameCount['gameCount'], $userId]);

        // Return data for displaying in HTML
        return [
            'gameId' => $gameId,
            'isOwned' => $isOwned,
            'playerCount' => count($rows),
            'averageRating' => $averageRating
        ];
    }
}
