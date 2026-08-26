<?php

use	wcf\system\database\table\index\DatabaseTableIndex;
use	wcf\system\database\table\PartialDatabaseTable;

return [
	PartialDatabaseTable::create('wcf1_igdb_integration_game')
		->indices([
			// The nightly refresh selects and sorts the stalest games by this
			// column
			DatabaseTableIndex::create('lastFetchTime')
				->columns(['lastFetchTime']),
		])
];
