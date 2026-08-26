<?php

namespace wcf\system\endpoint\controller\core\igdbIntegration;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use wcf\data\IgdbIntegration\IgdbIntegrationGame;
use wcf\data\IgdbIntegration\IgdbIntegrationGameAction;
use wcf\http\Helper;
use wcf\system\endpoint\DeleteRequest;
use wcf\system\endpoint\IController;
use wcf\system\WCF;

/**
 * Deletes the game with the given ID.
 *
 * @author      Berny23
 * @copyright   2026 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\System\Endpoint\Controller\Core\IgdbIntegration
 */
#[DeleteRequest("/core/igdb-integration/games/{id:\d+}")]
final class DeleteGame implements IController
{
	/**
	 * Endpoint template for interaction providers and scripts; must match the
	 * route in the attribute above.
	 */
	public const ENDPOINT = 'core/igdb-integration/games/{id}';

	/**
	 * @inheritDoc
	 */
	public function __invoke(ServerRequestInterface $request, array $variables): ResponseInterface
	{
		$game = Helper::fetchObjectFromRequestParameter($variables['id'], IgdbIntegrationGame::class);

		WCF::getSession()->checkPermissions(['admin.igdb_integration.can_manage_games']);

		(new IgdbIntegrationGameAction([$game], 'delete'))->executeAction();

		return new JsonResponse([]);
	}
}
