<?php

namespace wcf\system\endpoint\controller\core\igdbIntegration;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use wcf\data\IgdbIntegration\IgdbIntegrationGame;
use wcf\http\Helper;
use wcf\system\endpoint\IController;
use wcf\system\endpoint\PostRequest;
use wcf\system\exception\PermissionDeniedException;
use wcf\system\WCF;
use wcf\util\IgdbIntegrationUtil;

/**
 * Refreshes the data of the game with the given ID from IGDB. Responds with
 * the game under `missingGames` if IGDB no longer knows it, so that the
 * caller can offer to delete it.
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
	 * Endpoint template for interaction providers; must match the route in the attribute above.
	 */
	public const ENDPOINT = 'core/igdb-integration/games/%s/refresh';

	/**
	 * @inheritDoc
	 */
	public function __invoke(ServerRequestInterface $request, array $variables): ResponseInterface
	{
		$game = Helper::fetchObjectFromRequestParameter($variables['id'], IgdbIntegrationGame::class);

		WCF::getSession()->checkPermissions(['admin.igdb_integration.can_manage_games']);

		if (!IgdbIntegrationUtil::isConnectionDataValid()) {
			// The refresh interaction is not offered without valid connection data.
			throw new PermissionDeniedException();
		}

		$receivedGameIds = IgdbIntegrationUtil::updateDatabaseGamesByIds([$game->gameId]);
		if ($receivedGameIds === null) {
			throw new \RuntimeException('The IGDB API request failed.');
		}

		// IGDB no longer knows this id, e.g. because the game was deleted or merged there
		$missingGameIds = empty($receivedGameIds) ? [$game->gameId] : [];

		return new JsonResponse([
			'missingGames' => IgdbIntegrationUtil::loadGameSummaries($missingGameIds),
		]);
	}
}
