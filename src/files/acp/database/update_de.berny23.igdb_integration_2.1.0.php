<?php

use	wcf\system\database\table\column\BigintDatabaseTableColumn;
use	wcf\system\database\table\column\IntDatabaseTableColumn;
use	wcf\system\database\table\column\NotNullVarchar255DatabaseTableColumn;
use	wcf\system\database\table\DatabaseTable;
use	wcf\system\database\table\index\DatabaseTableIndex;
use	wcf\system\database\table\index\DatabaseTablePrimaryIndex;
use	wcf\system\database\table\PartialDatabaseTable;

return [
	PartialDatabaseTable::create('wcf1_igdb_integration_game')
		->columns([
			// Steam app id from IGDB's external game links, NULL if unknown
			IntDatabaseTableColumn::create('steamAppId')
				->length(10),
		])
		->indices([
			DatabaseTableIndex::create('steamAppId')
				->columns(['steamAppId']),
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
		])
];
