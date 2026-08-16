<?php

use	wcf\system\database\table\column\BigintDatabaseTableColumn;
use	wcf\system\database\table\column\NotNullVarchar255DatabaseTableColumn;
use wcf\system\database\table\column\TextDatabaseTableColumn;
use	wcf\system\database\table\column\IntDatabaseTableColumn;
use	wcf\system\database\table\column\NotNullInt10DatabaseTableColumn;
use	wcf\system\database\table\column\ObjectIdDatabaseTableColumn;
use	wcf\system\database\table\column\SmallintDatabaseTableColumn;
use	wcf\system\database\table\column\TinyintDatabaseTableColumn;
use	wcf\system\database\table\column\YearDatabaseTableColumn;
use	wcf\system\database\table\DatabaseTable;
use	wcf\system\database\table\PartialDatabaseTable;
use	wcf\system\database\table\index\DatabaseTablePrimaryIndex;
use	wcf\system\database\table\index\DatabaseTableIndex;
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
			NotNullVarchar255DatabaseTableColumn::create('coverImageId'),
			NotNullVarchar255DatabaseTableColumn::create('slug')
				->defaultValue(''),
			TextDatabaseTableColumn::create('localizedCovers'),
			// Steam app id from IGDB's external game links, NULL if unknown
			IntDatabaseTableColumn::create('steamAppId')
				->length(10),
			NotNullInt10DatabaseTableColumn::create('lastInteractionTime')
				->defaultValue(0),
			NotNullInt10DatabaseTableColumn::create('playerCount')
				->defaultValue(0),
			TinyintDatabaseTableColumn::create('averageRating')
				->length(3)
				->notNull()
				->defaultValue(0)
		])
		->indices([
			DatabaseTablePrimaryIndex::create()
				->columns(['gameId']),
			DatabaseTableIndex::create('releaseYear')
				->columns(['releaseYear']),
			DatabaseTableIndex::create('lastInteractionTime')
				->columns(['lastInteractionTime']),
			DatabaseTableIndex::create('playerCount')
				->columns(['playerCount']),
			DatabaseTableIndex::create('averageRating')
				->columns(['averageRating']),
			DatabaseTableIndex::create('steamAppId')
				->columns(['steamAppId']),
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
	// Sitewide request slots of the used external APIs, see IgdbIntegrationApiRateLimiter
	DatabaseTable::create('wcf1_igdb_integration_api_slot')
		->columns([
			NotNullVarchar255DatabaseTableColumn::create('apiName'),
			BigintDatabaseTableColumn::create('nextSlotTime')
				->length(20)
				->notNull()
				->defaultValue(0)
		])
		->indices([
			DatabaseTablePrimaryIndex::create()
				->columns(['apiName'])
		]),
	// Add column to default user table
	PartialDatabaseTable::create('wcf1_user')
		->columns([
			NotNullInt10DatabaseTableColumn::create('IgdbIntegrationGameCount')
				->defaultValue(0)
		])
];
