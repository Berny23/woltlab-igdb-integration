<?php

namespace wcf\page;

use wcf\data\IgdbIntegration\IgdbIntegrationGameList;
use wcf\system\WCF;
use wcf\util\IgdbIntegrationUtil;

/**
 * Shows the list of games.
 *
 * @author      Berny23
 * @copyright   2023 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\Page
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
    public $defaultSortField = 'playerCount';

    /**
     * @inheritDoc
     */
    public $defaultSortOrder = 'DESC';

    /**
     * @inheritDoc
     */
    public $validSortFields = ['displayName', 'firstReleaseDateYear', 'playerCount', 'averageRating'];

    /**
     * @inheritDoc
     */
    public $itemsPerPage = 21;

    /**
     * The name search field.
     */
    private $searchField = '';

    /**
     * Show error message if retreiving IGDB data resulted in an error.
     */
    private $showIgdbError = false;

    /**
     * @inheritDoc
     */
    public function readParameters()
    {
        parent::readParameters();

        $this->searchField = '';
        if (isset($_REQUEST['searchField'])) {
            $this->searchField = $_REQUEST['searchField'];

            if (!isset($_REQUEST['pageNo']) && WCF::getSession()->getPermission('user.IgdbIntegration.canSearchIgdb')) {
                // Search for games on IGDB and update local database
                $result = IgdbIntegrationUtil::updateDatabaseGamesByName($this->searchField);
                $this->showIgdbError = !$result;
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function assignVariables()
    {
        parent::assignVariables();

        WCF::getTPL()->assign([
            'searchField' => $this->searchField,
            'showIgdbError' => $this->showIgdbError
        ]);
    }

    /**
     * @inheritDoc
     */
    public function initObjectList()
    {
        parent::initObjectList();

        $name = IgdbIntegrationUtil::getLocalizedGameNameColumn();
        $this->objectList->sqlSelects .= "DISTINCT CASE WHEN " . $name . " = '' THEN name ELSE " . $name . " END AS displayName,";
        $this->objectList->sqlSelects .= "COUNT(gu.userId) OVER (PARTITION BY gu.gameId) AS playerCount,";
        $this->objectList->sqlSelects .= "ROUND(AVG(rating) OVER (PARTITION BY gu.gameId), 0) AS averageRating,";
        $this->objectList->sqlSelects .= "CASE WHEN EXISTS (SELECT userId FROM wcf" . WCF_N . "_igdb_integration_game_user guTemp WHERE guTemp.gameId = gu.gameId AND guTemp.userId = " . WCF::getUser()->userID . ") THEN 1 ELSE 0 END AS isOwned";
        $this->objectList->sqlJoins .= "LEFT JOIN wcf" . WCF_N . "_igdb_integration_game_user gu ON gu.gameId = " . $this->objectList->getDatabaseTableAlias() . ".gameId";

        if (!empty($this->searchField)) {
            // Search for all parts, separated with a space
            $parts = explode(' ', $this->searchField);
            foreach ($parts as $part) {
                $this->objectList->getConditionBuilder()->add("CASE WHEN " . $name . " = '' THEN name ELSE " . $name . " END LIKE ?", ['%' . $part . '%']);
            }
        }
    }
}