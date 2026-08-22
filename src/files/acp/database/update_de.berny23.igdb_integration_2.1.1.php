<?php

use	wcf\system\database\table\column\NotNullVarchar255DatabaseTableColumn;
use	wcf\system\database\table\PartialDatabaseTable;

return [
	PartialDatabaseTable::create('wcf1_igdb_integration_game')
		->columns([
			// GOG store slug from IGDB's external game links, empty if unknown.
			// Needed for the store link, GOG has no URL that takes the product id.
			NotNullVarchar255DatabaseTableColumn::create('gogSlug')
				->defaultValue(''),
		])
];
