<?php

namespace wcf\system\interaction\admin;

use wcf\acp\form\IgdbIntegrationGameEditForm;
use wcf\data\IgdbIntegration\IgdbIntegrationGame;
use wcf\system\interaction\AbstractInteractionProvider;
use wcf\system\interaction\DeleteInteraction;
use wcf\system\interaction\EditInteraction;
use wcf\system\interaction\RpcInteraction;
use wcf\util\IgdbIntegrationUtil;

/**
 * Interaction provider for games.
 *
 * @author      Berny23
 * @copyright   2026 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\System\Interaction\Admin
 */
final class IgdbIntegrationGameInteractions extends AbstractInteractionProvider
{
	public function __construct()
	{
		$this->addInteractions([
			new EditInteraction(IgdbIntegrationGameEditForm::class),
			new RpcInteraction(
				'refresh',
				'core/igdb-integration/games/%s/refresh',
				'wcf.igdb_integration.game.refresh',
				isAvailableCallback: static fn () => IgdbIntegrationUtil::isConnectionDataValid()
			),
			new DeleteInteraction('core/igdb-integration/games/%s'),
		]);
	}

	/**
	 * @inheritDoc
	 */
	public function getObjectClassName(): string
	{
		return IgdbIntegrationGame::class;
	}
}
