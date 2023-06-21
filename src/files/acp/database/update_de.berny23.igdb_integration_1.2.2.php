<?php

use	wcf\system\database\table\column\YearDatabaseTableColumn;
use	wcf\system\database\table\PartialDatabaseTable;

return [
	PartialDatabaseTable::create('wcf1_igdb_integration_game')
		->columns([
			YearDatabaseTableColumn::create('releaseYear')
				->notNull(false)
				->defaultValue(null),
		])
];
