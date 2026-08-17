<?php

namespace wcf\system\endpoint\controller\core\igdbIntegration;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use wcf\data\IgdbIntegration\IgdbIntegrationGame;
use wcf\http\Helper;
use wcf\system\endpoint\IController;
use wcf\system\endpoint\PostRequest;
use wcf\system\exception\UserInputException;
use wcf\system\WCF;
use wcf\util\IgdbIntegrationUtil;

/**
 * Refreshes the data of the game with the given ID from IGDB.
 *
 * @author      Berny23
 * @copyright   2026 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\System\Endpoint\Controller\Core\IgdbIntegration
 */
#[PostRequest("/core/igdb-integration/games/{id:\d+}/refresh")]
final class RefreshGame implements IController
{
	/**
	 * @inheritDoc
	 */
	public function __invoke(ServerRequestInterface $request, array $variables): ResponseInterface
	{
		$game = Helper::fetchObjectFromRequestParameter($variables['id'], IgdbIntegrationGame::class);

		WCF::getSession()->checkPermissions(['admin.igdb_integration.can_manage_games']);

		if (!IgdbIntegrationUtil::updateDatabaseGamesByIds([$game->gameId])) {
			throw new UserInputException('gameId', 'igdbRequestFailed');
		}

		return new JsonResponse([]);
	}
}
