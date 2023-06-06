<?php

namespace wcf\acp\form;

use wcf\data\IgdbIntegration\IgdbIntegrationGame;
use wcf\system\exception\IllegalLinkException;

/**
 * Shows the form to edit an existing game.
 *
 * @author      Berny23
 * @copyright   2023 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\Acp\Form
 */
class IgdbIntegrationGameEditForm extends IgdbIntegrationGameAddForm
{
    /**
     * @inheritDoc
     */
    public $activeMenuItem = 'wcf.acp.menu.link.IgdbIntegration.game_list';

    /**
     * @inheritDoc
     */
    public $formAction = 'update';

    /**
     * @inheritDoc
     */
    public function readParameters()
    {
        parent::readParameters();

        if (isset($_REQUEST['id'])) {
            $this->formObject = new IgdbIntegrationGame($_REQUEST['id']);

            if (!$this->formObject->getObjectID()) {
                throw new IllegalLinkException();
            }
        }
    }
}