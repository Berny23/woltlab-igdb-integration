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
    public $activeMenuItem = 'wcf.acp.menu.link.igdb_integration.add';

    /**
     * @inheritDoc
     */
    public $formAction = 'create';

    /**
     * @inheritDoc
     */
    public $neededPermissions = ['admin.igdb_integration.can_manage_games'];

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
                        ->label('wcf.igdb_integration.game.name')
                        ->required()
                        ->maximumLength(500),
                    TextFormField::create('germanName')
                        ->label('wcf.igdb_integration.game.german_name')
                        ->maximumLength(500),
                    IntegerFormField::create('releaseYear')
                        ->label('wcf.igdb_integration.game.year')
                        ->required()
                        ->minimum(0)
                        ->maximum(9999),
                    TextFormField::create('platforms')
                        ->label('wcf.igdb_integration.game.platforms')
                        ->maximumLength(500),
                    DescriptionFormField::create('summary')
                        ->label('wcf.igdb_integration.game.summary')
                        ->maximumLength(5000),
                    TextFormField::create('coverImageId')
                        ->label('wcf.igdb_integration.game.cover_image_id')
                        ->maximumLength(255)
                ])
        );
    }
}
