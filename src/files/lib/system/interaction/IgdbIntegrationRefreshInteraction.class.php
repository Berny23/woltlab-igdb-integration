<?php

namespace wcf\system\interaction;

use wcf\action\ApiAction;
use wcf\system\endpoint\controller\core\igdbIntegration\DeleteGame;
use wcf\system\request\LinkHandler;
use wcf\util\StringUtil;

/**
 * Represents an interaction that refreshes a game from IGDB. Unlike a plain
 * RpcInteraction, it evaluates the response: if IGDB no longer knows the
 * game, a dialog offers to delete it.
 *
 * @author      Berny23
 * @copyright   2026 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\System\Interaction
 */
final class IgdbIntegrationRefreshInteraction extends RpcInteraction
{
	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function renderInitialization(string $containerId): ?string
	{
		$identifier = StringUtil::encodeJS($this->getIdentifier());
		$containerId = StringUtil::encodeJS($containerId);
		$deleteEndpoint = StringUtil::encodeJS(
			LinkHandler::getInstance()->getControllerLink(ApiAction::class, ['id' => 'rpc']) . DeleteGame::ENDPOINT
		);

		return <<<HTML
			<script data-relocate="true">
				require(['WoltLabSuite/Core/Controller/IgdbIntegrationGameRefresh'], ({ setupRefreshInteraction }) => {
					setupRefreshInteraction('{$identifier}', document.getElementById('{$containerId}'), '{$deleteEndpoint}');
				});
			</script>
			HTML;
	}
}
