<?php

namespace wcf\system\option;

use wcf\data\option\Option;
use wcf\util\IgdbIntegrationUtil;

/**
 * Option type implementation for selecting multiple game platforms.
 *
 * @author      Berny23
 * @copyright   2026 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\System\Option
 */
class IgdbIntegrationPlatformsOptionType extends MultiSelectOptionType
{
	/**
	 * @inheritDoc
	 */
	protected function getSelectOptions(Option $option)
	{
		return IgdbIntegrationUtil::getAvailablePlatforms();
	}
}
