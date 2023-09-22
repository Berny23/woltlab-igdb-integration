<?php

namespace wcf\data\IgdbIntegration;

use wcf\data\DatabaseObject;
use wcf\system\request\LinkHandler;
use wcf\system\request\IRouteController;

/**
 * Represents a game.
 *
 * @author      Berny23
 * @copyright   2023 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\Data\IgdbIntegration
 */
class IgdbIntegrationGame extends DatabaseObject implements IRouteController
{
	/**
	 * @inheritDoc
	 */
	protected static $databaseTableIndexName = 'gameId';

	/**
	 * Returns the label's textual representation if a label is treated as a
	 * string.
	 */
	public function __toString(): string
	{
		return $this->getTitle();
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): string
	{
		return $this->name;
	}

	/**
	 * @inheritDoc
	 */
	public function getObjectID() {
		return $this->gameId;
	}
}
