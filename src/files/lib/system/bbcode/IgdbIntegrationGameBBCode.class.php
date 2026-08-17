<?php

namespace wcf\system\bbcode;

use Exception;
use wcf\data\IgdbIntegration\IgdbIntegrationGame;
use wcf\data\package\PackageCache;
use wcf\system\message\embedded\object\MessageEmbeddedObjectManager;
use wcf\system\view\ContentNotVisibleView;
use wcf\system\WCF;
use wcf\util\IgdbIntegrationUtil;
use wcf\util\StringUtil;

/**
 * Parses the [igdbgame] bbcode tag that embeds a game from the local game
 * database into a message.
 *
 * @author      Berny23
 * @copyright   2026 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\System\Bbcode
 */
final class IgdbIntegrationGameBBCode extends AbstractBBCode
{
	/**
	 * Queries to resolve the author of the message the bbcode is rendered in,
	 * per message object type: required package identifier (or null for core
	 * features) and the query returning the user id of the message author.
	 * Unknown object types (e.g. signatures, whose output is cached across
	 * viewers) show no author-specific content.
	 */
	private const AUTHOR_QUERIES = [
		'com.woltlab.wbb.post' => [
			'com.woltlab.wbb',
			"SELECT userID FROM wbb1_post WHERE postID = ?",
		],
		'com.woltlab.wcf.conversation.message' => [
			'com.woltlab.wcf.conversation',
			"SELECT userID FROM wcf1_conversation_message WHERE messageID = ?",
		],
		'com.woltlab.blog.entry' => [
			'com.woltlab.blog',
			"SELECT userID FROM blog1_entry WHERE entryID = ?",
		],
		'com.woltlab.calendar.event' => [
			'com.woltlab.calendar',
			"SELECT userID FROM calendar1_event WHERE eventID = ?",
		],
		'com.woltlab.wcf.comment' => [
			null,
			"SELECT userID FROM wcf1_comment WHERE commentID = ?",
		],
		'com.woltlab.wcf.comment.response' => [
			null,
			"SELECT userID FROM wcf1_comment_response WHERE responseID = ?",
		],
		'com.woltlab.wcf.article.content' => [
			null,
			"SELECT article.userID
			FROM wcf1_article_content articleContent
			LEFT JOIN wcf1_article article ON article.articleID = articleContent.articleID
			WHERE articleContent.articleContentID = ?",
		],
	];

	/**
	 * Games fetched during this request, indexed by game id. Missing games
	 * are stored as null to avoid repeated queries.
	 * @var (IgdbIntegrationGame|null)[]
	 */
	private static $games = [];

	/**
	 * Whether the current user owns the game, indexed by game id.
	 * @var bool[]
	 */
	private static $isOwned = [];

	/**
	 * Message author user ids resolved during this request, indexed by object
	 * type and message id.
	 * @var int[]
	 */
	private static $authorUserIds = [];

	/**
	 * Game <-> user association rows of message authors resolved during this
	 * request, indexed by game id and user id.
	 * @var (array|null)[]
	 */
	private static $authorGameRows = [];

	/**
	 * @inheritDoc
	 */
	public function getParsedTag(array $openingTag, $content, array $closingTag, BBCodeParser $parser): string
	{
		$gameId = 0;
		if (isset($openingTag['attributes'][0])) {
			$gameId = \intval($openingTag['attributes'][0]);
		}
		if (!$gameId) {
			return '';
		}

		$game = $this->getGame($gameId);
		if ($game === null) {
			return ContentNotVisibleView::forNotAvailable();
		}

		$displayName = $this->getDisplayName($game);
		$gameUrl = $game->slug ? 'https://www.igdb.com/games/' . \rawurlencode($game->slug) : '';

		if ($parser->getOutputType() == 'text/html') {
			$authorUserId = $this->getActiveMessageAuthorId();
			$authorGameRow = $authorUserId ? $this->getAuthorGameRow($gameId, $authorUserId) : null;

			return WCF::getTPL()->render('wcf', 'igdbIntegrationGameBBCode', [
				'game' => $game,
				'displayName' => $displayName,
				'coverImageUrl' => IgdbIntegrationUtil::getCoverImageUrl($game->coverImageId, $game->localizedCovers, true),
				'gameUrl' => $gameUrl,
				'isOwned' => $this->isOwned($gameId),
				'authorOwns' => $authorGameRow !== null,
				'authorRating' => \intval($authorGameRow['rating'] ?? 0),
				'authorIsCurrentUser' => $authorUserId && $authorUserId == WCF::getUser()->userID,
			]);
		}

		if ($gameUrl) {
			return StringUtil::getAnchorTag($gameUrl, $displayName);
		}

		return StringUtil::encodeHTML($displayName);
	}

	/**
	 * Returns the game with the given id, or null if it does not exist.
	 */
	private function getGame(int $gameId): ?IgdbIntegrationGame
	{
		if (!\array_key_exists($gameId, self::$games)) {
			$game = new IgdbIntegrationGame($gameId);
			self::$games[$gameId] = $game->getObjectID() ? $game : null;
		}

		return self::$games[$gameId];
	}

	/**
	 * Returns the localized name of the given game.
	 */
	private function getDisplayName(IgdbIntegrationGame $game): string
	{
		if (IgdbIntegrationUtil::getLocalizedGameNameColumn() === 'germanName' && $game->germanName) {
			return $game->germanName;
		}

		return $game->name;
	}

	/**
	 * Returns whether the current user owns the game with the given id.
	 */
	private function isOwned(int $gameId): bool
	{
		if (!WCF::getUser()->userID) {
			return false;
		}

		if (!isset(self::$isOwned[$gameId])) {
			$sql = "SELECT COUNT(*)
					FROM wcf1_igdb_integration_game_user
					WHERE gameId = ? AND userId = ?";
			$statement = WCF::getDB()->prepare($sql);
			$statement->execute([$gameId, WCF::getUser()->userID]);
			self::$isOwned[$gameId] = $statement->fetchSingleColumn() > 0;
		}

		return self::$isOwned[$gameId];
	}

	/**
	 * Returns the user id of the author of the message the bbcode is rendered
	 * in, or 0 if there is no active message or its type is not supported.
	 */
	private function getActiveMessageAuthorId(): int
	{
		$manager = MessageEmbeddedObjectManager::getInstance();
		$objectType = $manager->getActiveMessageObjectType();
		$messageId = $manager->getActiveMessageID();
		if (!$objectType || !$messageId || !isset(self::AUTHOR_QUERIES[$objectType])) {
			return 0;
		}

		$cacheKey = $objectType . '-' . $messageId;
		if (!isset(self::$authorUserIds[$cacheKey])) {
			[$package, $sql] = self::AUTHOR_QUERIES[$objectType];

			$authorUserId = 0;
			if ($package === null || PackageCache::getInstance()->getPackageID($package)) {
				try {
					$statement = WCF::getDB()->prepare($sql);
					$statement->execute([$messageId]);
					$authorUserId = \intval($statement->fetchSingleColumn());
				} catch (Exception $e) {
					// The author is optional content, a lookup failure must
					// not break the message output
				}
			}
			self::$authorUserIds[$cacheKey] = $authorUserId;
		}

		return self::$authorUserIds[$cacheKey];
	}

	/**
	 * Returns the game <-> user association row of the given message author,
	 * or null if the author does not own the game.
	 */
	private function getAuthorGameRow(int $gameId, int $userId): ?array
	{
		$cacheKey = $gameId . '-' . $userId;
		if (!\array_key_exists($cacheKey, self::$authorGameRows)) {
			$sql = "SELECT rating
					FROM wcf1_igdb_integration_game_user
					WHERE gameId = ? AND userId = ?";
			$statement = WCF::getDB()->prepare($sql);
			$statement->execute([$gameId, $userId]);
			$row = $statement->fetchSingleRow();
			self::$authorGameRows[$cacheKey] = $row !== false ? $row : null;
		}

		return self::$authorGameRows[$cacheKey];
	}
}
