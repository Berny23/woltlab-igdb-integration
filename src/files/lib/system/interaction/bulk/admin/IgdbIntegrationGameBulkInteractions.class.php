<?php

namespace wcf\system\interaction\bulk\admin;

use wcf\data\IgdbIntegration\IgdbIntegrationGameList;
use wcf\system\endpoint\controller\core\igdbIntegration\RefreshGames;
use wcf\system\interaction\bulk\AbstractBulkInteractionProvider;
use wcf\system\interaction\bulk\BulkDeleteInteraction;
use wcf\system\interaction\bulk\IgdbIntegrationBulkRefreshInteraction;
use wcf\system\interaction\InteractionConfirmationType;
use wcf\util\IgdbIntegrationUtil;

/**
 * Bulk interaction provider for games.
 *
 * @author      Berny23
 * @copyright   2026 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\System\Interaction\Bulk\Admin
 */
final class IgdbIntegrationGameBulkInteractions extends AbstractBulkInteractionProvider
{
	public function __construct()
	{
		$this->addInteractions([
			new IgdbIntegrationBulkRefreshInteraction(
				'refresh',
				RefreshGames::ENDPOINT,
				'wcf.igdb_integration.game.refresh',
				confirmationType: InteractionConfirmationType::Custom,
				confirmationMessage: 'wcf.igdb_integration.game.refresh.bulkConfirmMessage',
				isAvailableCallback: IgdbIntegrationUtil::isConnectionDataValid(...)
			),
			new BulkDeleteInteraction('core/igdb-integration/games/%s'),
		]);
	}

	/**
	 * @inheritDoc
	 */
	public function getObjectListClassName(): string
	{
		return IgdbIntegrationGameList::class;
	}
}
