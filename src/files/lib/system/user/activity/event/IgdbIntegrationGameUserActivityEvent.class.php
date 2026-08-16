<?php

namespace wcf\system\user\activity\event;

use wcf\system\database\util\PreparedStatementConditionBuilder;
use wcf\system\request\LinkHandler;
use wcf\system\SingletonFactory;
use wcf\system\WCF;
use wcf\util\IgdbIntegrationUtil;

/**
 * User activity event implementation for games.
 *
 * @author      Berny23
 * @copyright   2026 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\System\User\Activity\Event
 */
class IgdbIntegrationGameUserActivityEvent extends SingletonFactory implements IUserActivityEvent
{
	/**
	 * @inheritDoc
	 */
	public function prepare(array $events)
	{
		// Hide (but keep) existing events while the user activity is disabled;
		if (!defined('IGDB_INTEGRATION_GENERAL_ENABLE_USER_ACTIVITY') || !IGDB_INTEGRATION_GENERAL_ENABLE_USER_ACTIVITY) {
			return;
		}

		$gameIds = [];
		foreach ($events as $event) {
			$gameIds[] = $event->objectID;
		}

		// Fetch the localized names of the games
		$games = [];
		if (!empty($gameIds)) {
			$conditions = new PreparedStatementConditionBuilder();
			$conditions->add('gameId IN (?)', [$gameIds]);
			$sql = "SELECT gameId, " . IgdbIntegrationUtil::getDisplayNameSql() . " AS displayName
					FROM wcf1_igdb_integration_game
					" . $conditions;
			$statement = WCF::getDB()->prepare($sql);
			$statement->execute($conditions->getParameters());
			$games = $statement->fetchMap('gameId', 'displayName');
		}

		$gameListLink = LinkHandler::getInstance()->getLink('IgdbIntegrationGameList');

		foreach ($events as $event) {
			if (!isset($games[$event->objectID])) {
				// The game no longer exists
				$event->setIsOrphaned();
				continue;
			}

			$event->setIsAccessible();

			$action = $event->additionalData['action'] ?? 'add';
			// Clamp defensively so bad legacy data can never repeat the star
			// icon excessively
			$rating = min(max(intval($event->additionalData['rating'] ?? 0), 0), 5);

			switch ($action) {
				case 'remove':
					$languageItem = 'wcf.user.profile.recentActivity.igdb_integration.game_remove';
					break;
				case 'rating':
					$languageItem = 'wcf.user.profile.recentActivity.igdb_integration.game_rating';
					break;
				default:
					$languageItem = 'wcf.user.profile.recentActivity.igdb_integration.game_add';
					break;
			}

			$event->setTitle(WCF::getLanguage()->getDynamicVariable($languageItem, [
				'author' => $event->getUserProfile(),
				'gameName' => $games[$event->objectID],
			]));

			if ($rating > 0) {
				$event->setDescription(
					'<span class="orange">'
						. str_repeat('<fa-icon size="16" name="star" solid></fa-icon>', $rating)
						. '</span>',
					true
				);
			}

			$event->setLink($gameListLink);
		}
	}
}
