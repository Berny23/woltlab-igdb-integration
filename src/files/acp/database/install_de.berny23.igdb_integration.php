<?php

use	wcf\system\database\table\column\NotNullVarchar255DatabaseTableColumn;
use wcf\system\database\table\column\TextDatabaseTableColumn;
use	wcf\system\database\table\column\NotNullInt10DatabaseTableColumn;
use	wcf\system\database\table\column\ObjectIdDatabaseTableColumn;
use	wcf\system\database\table\column\SmallintDatabaseTableColumn;
use	wcf\system\database\table\column\YearDatabaseTableColumn;
use	wcf\system\database\table\DatabaseTable;
use	wcf\system\database\table\PartialDatabaseTable;
use	wcf\system\database\table\index\DatabaseTablePrimaryIndex;
use	wcf\system\database\table\index\DatabaseTableForeignKey;

return [
	DatabaseTable::create('wcf1_igdb_integration_game')
		->columns([
			ObjectIdDatabaseTableColumn::create('gameId'),
			TextDatabaseTableColumn::create('name')
				->notNull(),
			TextDatabaseTableColumn::create('germanName')
				->notNull(),
			YearDatabaseTableColumn::create('releaseYear')
				->defaultValue(null),
			TextDatabaseTableColumn::create('platforms')
				->notNull(),
			TextDatabaseTableColumn::create('summary')
				->notNull(),
			NotNullVarchar255DatabaseTableColumn::create('coverImageId')
		])
		->indices([
			DatabaseTablePrimaryIndex::create()
				->columns(['gameId']),
		]),
	DatabaseTable::create('wcf1_igdb_integration_game_user')
		->columns([
			NotNullInt10DatabaseTableColumn::create('gameId'),
			NotNullInt10DatabaseTableColumn::create('userId'),
			SmallintDatabaseTableColumn::create('rating')
				->length(1)
				->defaultValue(0)
				->notNull()
		])
		->indices([
			DatabaseTablePrimaryIndex::create()
				->columns(['gameId', 'userId'])
		])
		->foreignKeys([
			DatabaseTableForeignKey::create()
				->columns(['gameId'])
				->referencedTable('wcf1_igdb_integration_game')
				->referencedColumns(['gameId'])
				->onDelete('CASCADE'),
			DatabaseTableForeignKey::create()
				->columns(['userId'])
				->referencedTable('wcf1_user')
				->referencedColumns(['userID'])
				->onDelete('CASCADE'),
		]),
	// Add column to default user table
	PartialDatabaseTable::create('wcf1_user')
		->columns([
			NotNullInt10DatabaseTableColumn::create('IgdbIntegrationGameCount')
				->defaultValue(0)
		])
];
