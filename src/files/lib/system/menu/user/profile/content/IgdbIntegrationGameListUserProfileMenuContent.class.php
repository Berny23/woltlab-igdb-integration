<?php

namespace wcf\system\menu\user\profile\content;

use wcf\util\IgdbIntegrationUtil;
use wcf\system\SingletonFactory;
use wcf\system\WCF;

/**
 * Shows the list of owned games on user profiles.
 *
 * @author      Berny23
 * @copyright   2023 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\System\Menu\User\Profile\Content
 */
class IgdbIntegrationGameListUserProfileMenuContent extends SingletonFactory implements IUserProfileMenuContent
{
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

        /*$eventList = new ViewableUserActivityEventList();

        // load more items than necessary to avoid empty list if some items are invisible for current user
        $eventList->sqlLimit = 60;

        $eventList->getConditionBuilder()->add("user_activity_event.userID = ?", [$userID]);
        $eventList->readObjects();

        UserActivityEventHandler::validateEvents($eventList);

        // remove unused items
        $eventList->truncate(20);
        */

        $name = IgdbIntegrationUtil::getLocalizedGameNameColumn();
        $sql = "SELECT DISTINCT g.gameId AS gameId, coverImageUrl, firstReleaseDateYear, rating AS ownRating, COUNT(gu.userId) OVER (PARTITION BY gu.gameId) AS playerCount, CASE WHEN EXISTS (SELECT userId FROM wcf" . WCF_N . "_igdb_integration_game_user guTemp WHERE guTemp.gameId = gu.gameId AND guTemp.userId = " . WCF::getUser()->userID . ") THEN 1 ELSE 0 END AS isOwned, CASE WHEN " . $name . " = '' THEN name ELSE " . $name . " END AS displayName FROM wcf" . WCF_N . "_igdb_integration_game g LEFT JOIN wcf" . WCF_N . "_igdb_integration_game_user gu ON gu.gameId = g.gameId WHERE gu.userId = ? ORDER BY ownRating DESC, displayName ASC";
        $statement = WCF::getDB()->prepare($sql);
        $statement->execute([$userID]);
        $userGames = $statement->fetchAll(\PDO::FETCH_ASSOC);

        WCF::getTPL()->assign([
            'userGames' => $userGames,
            'userId' => $userID
        ]);

        return WCF::getTPL()->fetch('igdbIntegrationGameListUserProfile');
    }
}