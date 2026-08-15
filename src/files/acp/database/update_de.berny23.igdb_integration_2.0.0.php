<?php

use	wcf\system\database\table\column\NotNullVarchar255DatabaseTableColumn;
use	wcf\system\database\table\column\TextDatabaseTableColumn;
use	wcf\system\database\table\PartialDatabaseTable;

return [
	PartialDatabaseTable::create('wcf1_igdb_integration_game')
		->columns([
			NotNullVarchar255DatabaseTableColumn::create('slug')
				->defaultValue(''),
			TextDatabaseTableColumn::create('localizedCovers'),
		])
];
