<?php

namespace wcf\data\IgdbIntegration;

use wcf\data\DatabaseObjectEditor;

/**
 * Provides functions to edit games.
 *
 * @author      Berny23
 * @copyright   2023 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\Data\IgdbIntegration
 */
class IgdbIntegrationGameEditor extends DatabaseObjectEditor
{
    /**
     * @inheritDoc
     */
    protected static $baseClass = IgdbIntegrationGame::class;
}