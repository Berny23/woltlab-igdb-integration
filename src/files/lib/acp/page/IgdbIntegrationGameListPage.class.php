<?php

namespace wcf\acp\page;

use wcf\page\AbstractGridViewPage;
use wcf\system\gridView\admin\IgdbIntegrationGameGridView;

/**
 * Shows the list of games.
 *
 * @author      Berny23
 * @copyright   2026 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\Acp\Page
 *
 * @extends AbstractGridViewPage<IgdbIntegrationGameGridView>
 */
class IgdbIntegrationGameListPage extends AbstractGridViewPage
{
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
    protected function createGridView(): IgdbIntegrationGameGridView
    {
        return new IgdbIntegrationGameGridView();
    }
}
