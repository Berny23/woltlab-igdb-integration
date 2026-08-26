<?php

namespace wcf\system\endpoint\controller\core\igdbIntegration;

use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use wcf\http\Helper;
use wcf\system\database\util\PreparedStatementConditionBuilder;
use wcf\system\endpoint\IController;
use wcf\system\endpoint\PostRequest;
use wcf\system\exception\PermissionDeniedException;
use wcf\system\exception\UserInputException;
use wcf\system\WCF;
use wcf\util\IgdbIntegrationUtil;

/**
 * Refreshes the data of all games with the given IDs from IGDB, batching up
 * to 400 ids per IGDB API request. Responds with the games IGDB no longer
 * knows under `missingGames`, so that the caller can offer to delete them.
 *
 * @author      Berny23
 * @copyright   2026 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\System\Endpoint\Controller\Core\IgdbIntegration
 */
#[PostRequest('/core/igdb-integration/games/refresh')]
final class RefreshGames implements IController
{
	/**
	 * Endpoint for interaction providers; must match the route in the attribute above.
	 */
	public const ENDPOINT = 'core/igdb-integration/games/refresh';

	/**
	 * @inheritDoc
	 */
	public function __invoke(ServerRequestInterface $request, array $variables): ResponseInterface
	{
		$parameters = Helper::mapApiParameters($request, RefreshGamesParameters::class);

		WCF::getSession()->checkPermissions(['admin.igdb_integration.can_manage_games']);

		if (!IgdbIntegrationUtil::isConnectionDataValid()) {
			// The refresh interaction is not offered without valid connection data.
			throw new PermissionDeniedException();
		}

		$gameIds = $this->loadExistingGameIds($parameters->gameIds);
		if (empty($gameIds)) {
			throw new UserInputException('gameIds');
		}

		$receivedGameIds = IgdbIntegrationUtil::updateDatabaseGamesByIds($gameIds);
		if ($receivedGameIds === null) {
			throw new \RuntimeException('The IGDB API request failed.');
		}

		// IGDB no longer knows these ids, e.g. because the games were deleted or merged there
		$missingGameIds = array_diff($gameIds, $receivedGameIds);

		return new JsonResponse([
			'missingGames' => IgdbIntegrationUtil::loadGameSummaries($missingGameIds),
		]);
	}

	/**
	 * Returns the subset of the given ids for which games exist in the database.
	 */
	private function loadExistingGameIds(array $gameIds): array
	{
		$existingGameIds = [];
		foreach (array_chunk(array_values($gameIds), 500) as $chunk) {
			$conditions = new PreparedStatementConditionBuilder();
			$conditions->add('gameId IN (?)', [$chunk]);
			$sql = "SELECT gameId
					FROM wcf1_igdb_integration_game
					" . $conditions;
			$statement = WCF::getDB()->prepare($sql);
			$statement->execute($conditions->getParameters());
			while ($gameId = $statement->fetchColumn()) {
				$existingGameIds[] = intval($gameId);
			}
		}

		return $existingGameIds;
	}
}

/** @internal */
final class RefreshGamesParameters
{
	public function __construct(
		/** @var list<positive-int> */
		public readonly array $gameIds,
	) {}
}
