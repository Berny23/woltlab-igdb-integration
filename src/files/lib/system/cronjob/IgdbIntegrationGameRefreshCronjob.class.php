<?php

namespace wcf\system\cronjob;

use wcf\data\cronjob\Cronjob;
use wcf\util\IgdbIntegrationUtil;

/**
 * Refreshes the IGDB records of the stalest games in the database, so that
 * the whole library is renewed over time regardless of which games are viewed.
 * Never fetched games come first. The nightly amount is limited by an option.
 *
 * @author      Berny23
 * @copyright   2026 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\System\Cronjob
 */
class IgdbIntegrationGameRefreshCronjob extends AbstractCronjob
{
	/**
	 * @inheritDoc
	 */
	public function execute(Cronjob $cronjob)
	{
		parent::execute($cronjob);

		$limit = defined('IGDB_INTEGRATION_GENERAL_NIGHTLY_REFRESH_LIMIT') ? intval(IGDB_INTEGRATION_GENERAL_NIGHTLY_REFRESH_LIMIT) : 0;
		IgdbIntegrationUtil::refreshStalestGames($limit);
	}
}