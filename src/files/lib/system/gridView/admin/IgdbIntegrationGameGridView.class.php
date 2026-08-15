<?php

namespace wcf\system\gridView\admin;

use wcf\acp\form\IgdbIntegrationGameEditForm;
use wcf\data\IgdbIntegration\IgdbIntegrationGame;
use wcf\data\IgdbIntegration\IgdbIntegrationGameList;
use wcf\system\gridView\AbstractGridView;
use wcf\system\gridView\GridViewColumn;
use wcf\system\gridView\GridViewRowLink;
use wcf\system\gridView\renderer\ObjectIdColumnRenderer;
use wcf\system\interaction\admin\IgdbIntegrationGameInteractions;
use wcf\system\interaction\bulk\admin\IgdbIntegrationGameBulkInteractions;
use wcf\system\view\filter\IntegerFilter;
use wcf\system\view\filter\ObjectIdFilter;
use wcf\system\view\filter\TextFilter;
use wcf\system\WCF;

/**
 * Grid view for the ACP list of games.
 *
 * @author      Berny23
 * @copyright   2026 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\System\GridView\Admin
 *
 * @extends AbstractGridView<IgdbIntegrationGame, IgdbIntegrationGameList>
 */
final class IgdbIntegrationGameGridView extends AbstractGridView
{
	public function __construct()
	{
		$this->addColumns([
			GridViewColumn::for('gameId')
				->label('wcf.global.objectID')
				->renderer(new ObjectIdColumnRenderer())
				->filter(ObjectIdFilter::class)
				->sortable(),
			GridViewColumn::for('name')
				->label('wcf.igdb_integration.game.name')
				->titleColumn()
				->filter(TextFilter::class)
				->sortable(),
			GridViewColumn::for('germanName')
				->label('wcf.igdb_integration.game.german_name')
				->filter(TextFilter::class)
				->sortable(),
			GridViewColumn::for('releaseYear')
				->label('wcf.igdb_integration.game.year')
				->filter(IntegerFilter::class)
				->sortable(),
			GridViewColumn::for('platforms')
				->label('wcf.igdb_integration.game.platforms')
				->filter(TextFilter::class)
				->sortable(),
		]);

		$this->setInteractionProvider(new IgdbIntegrationGameInteractions());
		$this->setBulkInteractionProvider(new IgdbIntegrationGameBulkInteractions());

		$this->setDefaultSortField('gameId');
		$this->addRowLink(new GridViewRowLink(IgdbIntegrationGameEditForm::class));
	}

	/**
	 * @inheritDoc
	 */
	public function isAccessible(): bool
	{
		return WCF::getSession()->getPermission('admin.igdb_integration.can_manage_games');
	}

	/**
	 * @inheritDoc
	 */
	protected function createObjectList(): IgdbIntegrationGameList
	{
		return new IgdbIntegrationGameList();
	}
}
