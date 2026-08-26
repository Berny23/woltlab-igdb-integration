<?php

namespace wcf\system\interaction\bulk;

use wcf\action\ApiAction;
use wcf\data\DatabaseObject;
use wcf\system\endpoint\controller\core\igdbIntegration\DeleteGame;
use wcf\system\interaction\InteractionConfirmationType;
use wcf\system\request\LinkHandler;
use wcf\system\WCF;
use wcf\util\JSON;
use wcf\util\StringUtil;

/**
 * Represents a bulk interaction that refreshes all selected games from IGDB
 * with a single request to a batch RPC endpoint, unlike BulkRpcInteraction,
 * which sends one request per selected object. Games IGDB no longer knows
 * are listed in a dialog that offers to delete them.
 *
 * @author      Berny23
 * @copyright   2026 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\System\Interaction\Bulk
 */
final class IgdbIntegrationBulkRefreshInteraction extends AbstractBulkInteraction
{
	public function __construct(
		string $identifier,
		protected readonly string $endpoint,
		protected readonly string $languageItem,
		protected readonly InteractionConfirmationType $confirmationType = InteractionConfirmationType::None,
		protected readonly string $confirmationMessage = '',
		?\Closure $isAvailableCallback = null
	) {
		parent::__construct($identifier, $isAvailableCallback);
	}

	/**
	 * @inheritDoc
	 */
	#[\Override]
	public function render(array $objects): string
	{
		$identifier = StringUtil::encodeHTML($this->getIdentifier());
		$label = WCF::getLanguage()->get($this->languageItem);
		$confirmationMessage = WCF::getLanguage()->getDynamicVariable($this->confirmationMessage);
		$endpoint = StringUtil::encodeHTML(
			LinkHandler::getInstance()->getControllerLink(ApiAction::class, ['id' => 'rpc']) . $this->endpoint
		);
		$objectIDs = StringUtil::encodeHTML(
			JSON::encode(
				\array_values(\array_map(fn (DatabaseObject $object) => $object->getObjectID(), $objects))
			)
		);

		return <<<HTML
			<button
				type="button"
				data-bulk-interaction="{$identifier}"
				data-endpoint="{$endpoint}"
				data-object-ids="{$objectIDs}"
				data-confirmation-type="{$this->confirmationType->toString()}"
				data-confirmation-message="{$confirmationMessage}"
			>
				{$label}
			</button>
			HTML;
	}

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
				require(['WoltLabSuite/Core/Controller/IgdbIntegrationGameRefresh'], ({ setupBulkRefreshInteraction }) => {
					setupBulkRefreshInteraction('{$identifier}', document.getElementById('{$containerId}'), '{$deleteEndpoint}');
				});
			</script>
			HTML;
	}
}
