<?php

namespace wcf\acp\page;

use wcf\action\ApiAction;
use wcf\page\AbstractGridViewPage;
use wcf\system\endpoint\controller\core\igdbIntegration\DeleteGame;
use wcf\system\gridView\admin\IgdbIntegrationGameGridView;
use wcf\system\request\LinkHandler;
use wcf\system\WCF;
use wcf\util\IgdbIntegrationUtil;

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

    /**
     * @inheritDoc
     */
    public function assignVariables()
    {
        parent::assignVariables();

        WCF::getTPL()->assign([
            // The refresh button is only offered with valid connection data,
            // like the refresh interactions of the grid
            'canRefreshGames' => IgdbIntegrationUtil::isConnectionDataValid(),
            'gameDeleteEndpoint' => LinkHandler::getInstance()->getControllerLink(ApiAction::class, ['id' => 'rpc'])
                . DeleteGame::ENDPOINT,
        ]);
    }
}
