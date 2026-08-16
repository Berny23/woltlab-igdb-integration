<?php

namespace wcf\system\event\listener;

use wcf\util\ArrayUtil;
use wcf\util\HeaderUtil;
use wcf\util\IgdbIntegrationUtil;
use wcf\system\menu\user\profile\content\IgdbIntegrationGameListUserProfileMenuContent;
use wcf\system\menu\user\profile\UserProfileMenu;
use wcf\system\WCF;

/**
 * Provides the filtered and paginated game list on user profiles: handles the
 * filter form of the sidebar box and shows the games tab when filter
 * parameters are present.
 *
 * @author      Berny23
 * @copyright   2026 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\System\Event\Listener
 */
class IgdbIntegrationGameListUserPageListener implements IParameterizedEventListener
{
	/**
	 * @inheritDoc
	 */
	public function execute($eventObj, $className, $eventName, array &$parameters)
	{
		$filter = IgdbIntegrationGameListUserProfileMenuContent::getFilterParameters();

		$profileLink = $eventObj->user->getLink();
		$separator = str_contains($profileLink, '?') ? '&' : '?';

		if (isset($_POST['igdbGameListFilter'])) {
			// Redirect the form submit to a GET request so that all filter
			// parameters are visible in the URL
			HeaderUtil::redirect($profileLink . $separator . IgdbIntegrationGameListUserProfileMenuContent::getLinkParameters($filter));

			exit;
		}

		$availablePlatforms = IgdbIntegrationUtil::getAvailablePlatforms($eventObj->userID);

		if (IgdbIntegrationGameListUserProfileMenuContent::hasFilterParameters()) {
			// Render the games tab server-side so the filtered list is shown
			// directly instead of the default tab
			UserProfileMenu::getInstance()->setActiveMenuItem('igdb_integration_game_list');
		} else {
			// The game list itself is unfiltered on a fresh visit, but the
			// default platforms are pre-selected in the filter form for the
			// next submit, limited to the platforms of the profile owner's
			// games
			$defaultPlatformFilter = WCF::getUser()->getUserOption('igdb_integration_default_platform_filter');
			if (empty($defaultPlatformFilter)) {
				$defaultPlatformFilter = defined('IGDB_INTEGRATION_GENERAL_DEFAULT_PLATFORM_FILTER')
					? IGDB_INTEGRATION_GENERAL_DEFAULT_PLATFORM_FILTER
					: '';
			}
			$filter['platforms'] = array_intersect(
				array_filter(ArrayUtil::trim(explode("\n", $defaultPlatformFilter))),
				$availablePlatforms
			);
		}

		// The filter box is pointless on profiles without any owned games
		WCF::getTPL()->assign([
			'igdbGameListShowFilter' => $eventObj->user->IgdbIntegrationGameCount > 0,
			'igdbGameListFilter' => $filter,
			'igdbGameListAvailablePlatforms' => $availablePlatforms,
			'igdbGameListFormUrl' => $profileLink,
			'igdbGameListResetUrl' => $profileLink . '#igdb_integration_game_list'
		]);
	}
}
