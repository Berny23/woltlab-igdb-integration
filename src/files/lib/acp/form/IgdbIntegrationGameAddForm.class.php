<?php

namespace wcf\acp\form;

use wcf\data\IgdbIntegration\IgdbIntegrationGameAction;
use wcf\form\AbstractFormBuilderForm;
use wcf\system\form\builder\container\FormContainer;
use wcf\system\form\builder\field\TextFormField;
use wcf\system\form\builder\field\IntegerFormField;
use wcf\system\form\builder\field\DescriptionFormField;

/**
 * Shows the form to create a new game.
 *
 * @author      Berny23
 * @copyright   2023 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\Acp\Form
 */
class IgdbIntegrationGameAddForm extends AbstractFormBuilderForm
{
    /**
     * @inheritDoc
     */
    public $activeMenuItem = 'wcf.acp.menu.link.IgdbIntegration.add';

    /**
     * @inheritDoc
     */
    public $formAction = 'create';

    /**
     * @inheritDoc
     */
    public $neededPermissions = ['admin.IgdbIntegration.canManageGames'];

    /**
     * @inheritDoc
     */
    public $objectActionClass = IgdbIntegrationGameAction::class;

    /**
     * @inheritDoc
     */
    public $objectEditLinkController = IgdbIntegrationGameEditForm::class;

    /**
     * @inheritDoc
     */
    protected function createForm()
    {
        parent::createForm();

        $this->form->appendChild(
            FormContainer::create('data')
                ->label('wcf.global.form.data')
                ->appendChildren([
                    TextFormField::create('name')
                        ->label('wcf.IgdbIntegration.game.name')
                        ->required()
                        ->maximumLength(500),
                    TextFormField::create('germanName')
                        ->label('wcf.IgdbIntegration.game.germanName')
                        ->maximumLength(500),
                    IntegerFormField::create('firstReleaseDateYear')
                        ->label('wcf.IgdbIntegration.game.year')
                        ->required()
                        ->minimum(0)
                        ->maximum(9999),
                    TextFormField::create('platforms')
                        ->label('wcf.IgdbIntegration.game.platforms')
                        ->maximumLength(500),
                    DescriptionFormField::create('summary')
                        ->label('wcf.IgdbIntegration.game.summary')
                        ->maximumLength(5000),
                    TextFormField::create('coverImageId')
                        ->label('wcf.IgdbIntegration.game.coverImageId')
                        ->maximumLength(255),
                    TextFormField::create('coverImageUrl')
                        ->label('wcf.IgdbIntegration.game.coverImageUrl')
                        ->maximumLength(2000),
                ])
        );
    }
}
