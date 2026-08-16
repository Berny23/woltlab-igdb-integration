<?php

namespace wcf\system\menu\user\profile\content;

use wcf\util\IgdbIntegrationUtil;
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
				LEFT JOIN wcf1_igdb_integration_game_user gu
				ON gu.gameId = g.gameId
				WHERE gu.userId = ?
				ORDER BY ownRating DESC, displayName ASC";
        $statement = WCF::getDB()->prepare($sql);
        $statement->execute([WCF::getUser()->userID, $userID]);
        $userGames = $statement->fetchAll(\PDO::FETCH_ASSOC);

		// Generate image proxy links, if enabled
		foreach($userGames as &$game) {
			$game['coverImageUrl'] = IgdbIntegrationUtil::getCoverImageUrl($game['coverImageId'], $game['localizedCovers']);
		}

		$gameCount = count($userGames);

        WCF::getTPL()->assign([
            'userGames' => $userGames,
            'userId' => $userID,
			'gameCount' => $gameCount
        ]);

        return WCF::getTPL()->fetch('igdbIntegrationGameListUserProfile');
    }
}