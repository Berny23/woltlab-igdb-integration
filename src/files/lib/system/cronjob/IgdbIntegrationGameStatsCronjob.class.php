<?php

namespace wcf\system\cronjob;

use wcf\data\cronjob\Cronjob;
use wcf\util\IgdbIntegrationUtil;

/**
 * Synchronizes the computed playerCount and averageRating columns of all
 * games, fixing drift caused by changes that bypass the game actions, e.g.
 * deleted user accounts whose association rows are removed by the foreign
 * key cascade.
 *
 * @author      Berny23
 * @copyright   2026 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\System\Cronjob
 */
class IgdbIntegrationGameStatsCronjob extends AbstractCronjob
{
	/**
	 * @inheritDoc
	 */
	public function execute(Cronjob $cronjob)
	{
		parent::execute($cronjob);

		IgdbIntegrationUtil::updateAllGameStats();
	}
}
