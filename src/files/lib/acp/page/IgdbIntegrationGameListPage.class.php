<?php

namespace wcf\acp\page;

use wcf\data\IgdbIntegration\IgdbIntegrationGameList;
use wcf\util\IgdbIntegrationUtil;
use wcf\page\SortablePage;
use wcf\system\WCF;

/**
 * Shows the list of games.
 *
 * @author      Berny23
 * @copyright   2023 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\Acp\Page
 */
class IgdbIntegrationGameListPage extends SortablePage
{
    /**
     * @inheritDoc
     */
    public $objectListClassName = IgdbIntegrationGameList::class;

    /**
     * @inheritDoc
     */
    public $activeMenuItem = 'wcf.acp.menu.link.igdb_integration.game_list';

    /**
     * @inheritDoc
     */
    public $neededPermissions = ['admin.igdb_integration.can_manage_games'];

    /**
     * @inheritDoc
     */
    public $defaultSortField = 'gameId';

    /**
     * @inheritDoc
     */
    public $defaultSortOrder = 'ASC';

    /**
     * @inheritDoc
     */
    public $validSortFields = ['gameId', 'name', 'releaseYear', 'platforms'];

    /**
     * @inheritDoc
     */
    public function initObjectList()
    {
        parent::initObjectList();
        
        $name = IgdbIntegrationUtil::getLocalizedGameNameColumn();
        $this->objectList->sqlSelects .= "CASE WHEN " . $name . " = '' THEN name ELSE " . $name . " END AS displayName";
    }
}