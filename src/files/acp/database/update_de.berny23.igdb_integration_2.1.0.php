<?php

use	wcf\system\database\table\column\IntDatabaseTableColumn;
use	wcf\system\database\table\index\DatabaseTableIndex;
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
		])
];
