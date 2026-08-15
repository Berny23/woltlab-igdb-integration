<?php

namespace wcf\acp\form;

use wcf\data\IgdbIntegration\IgdbIntegrationGameAction;
use wcf\form\AbstractFormBuilderForm;
use wcf\system\exception\SystemException;
use wcf\system\form\builder\container\FormContainer;
use wcf\system\form\builder\field\TextFormField;
use wcf\system\form\builder\field\IntegerFormField;
use wcf\system\form\builder\field\DescriptionFormField;
use wcf\system\form\builder\field\MultilineTextFormField;
use wcf\system\form\builder\field\validation\FormFieldValidationError;
use wcf\system\form\builder\field\validation\FormFieldValidator;
use wcf\util\JSON;

/**
 * Shows the form to create a new game.
 *
 * @author      Berny23
 * @copyright   2026 Berny23
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
						->minimum(1901)
						->maximum(2155),
					TextFormField::create('platforms')
						->label('wcf.igdb_integration.game.platforms')
						->maximumLength(500),
					DescriptionFormField::create('summary')
						->label('wcf.igdb_integration.game.summary')
						->maximumLength(5000),
					TextFormField::create('coverImageId')
						->label('wcf.igdb_integration.game.cover_image_id')
						->maximumLength(255),
					TextFormField::create('slug')
						->label('wcf.igdb_integration.game.slug')
						->description('wcf.igdb_integration.game.slug.description')
						->maximumLength(255),
					MultilineTextFormField::create('localizedCovers')
						->label('wcf.igdb_integration.game.localized_covers')
						->description('wcf.igdb_integration.game.localized_covers.description')
						->rows(3)
						->maximumLength(5000)
						->addValidator(new FormFieldValidator('jsonFormat', function (MultilineTextFormField $formField) {
							$value = $formField->getSaveValue();
							if ($value === '' || $value === null) {
								return;
							}

							try {
								JSON::decode($value);
							} catch (SystemException $ex) {
								$formField->addValidationError(new FormFieldValidationError(
									'invalid',
									'wcf.igdb_integration.game.localized_covers.error.invalid'
								));
							}
						}))
				])
		);
	}
}
