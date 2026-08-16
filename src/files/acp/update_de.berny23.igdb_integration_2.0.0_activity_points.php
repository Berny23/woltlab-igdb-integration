<?php

use wcf\system\user\activity\point\UserActivityPointHandler;
use wcf\system\WCF;
use wcf\util\IgdbIntegrationUtil;

/**
 * Synchronizes the activity points of all users with their owned game counts.
 *
 * @author      Berny23
 * @copyright   2026 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 */
$handler = UserActivityPointHandler::getInstance();
$objectType = $handler->getObjectTypeByName(IgdbIntegrationUtil::ACTIVITY_POINT_OBJECT_TYPE);
if ($objectType === null) {
	return;
}

$handler->reset(IgdbIntegrationUtil::ACTIVITY_POINT_OBJECT_TYPE);

$sql = "INSERT INTO wcf1_user_activity_point
				(userID, objectTypeID, activityPoints, items)
		SELECT userId, ?, COUNT(*) * ?, COUNT(*)
			FROM wcf1_igdb_integration_game_user
			GROUP BY userId
		ON DUPLICATE KEY UPDATE activityPoints = VALUES(activityPoints),
				items = VALUES(items)";
$statement = WCF::getDB()->prepare($sql);
$statement->execute([$objectType->objectTypeID, $objectType->points]);

$sql = "SELECT DISTINCT userId
		FROM wcf1_igdb_integration_game_user";
$statement = WCF::getDB()->prepare($sql);
$statement->execute();
$userIds = $statement->fetchAll(\PDO::FETCH_COLUMN);

if (!empty($userIds)) {
	$handler->updateUsers($userIds);
}
