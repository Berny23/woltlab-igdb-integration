<?php

use	wcf\system\database\table\column\NotNullInt10DatabaseTableColumn;
use	wcf\system\database\table\column\NotNullVarchar255DatabaseTableColumn;
use	wcf\system\database\table\column\TinyintDatabaseTableColumn;
use	wcf\system\database\table\column\YearDatabaseTableColumn;
use	wcf\system\database\table\index\DatabaseTableIndex;
use	wcf\system\database\table\PartialDatabaseTable;

return [
	PartialDatabaseTable::create('wcf1_igdb_integration_game')
		->columns([
			NotNullInt10DatabaseTableColumn::create('playerCount')
				->defaultValue(0),
			TinyintDatabaseTableColumn::create('averageRating')
				->length(3)
				->notNull()
				->defaultValue(0),
			// Unused leftovers from version 1.x; they only exist on
			// installations that were upgraded to 2.0.0 from a 1.x version
			YearDatabaseTableColumn::create('firstReleaseDateYear')
				->drop(),
			NotNullVarchar255DatabaseTableColumn::create('coverImageUrl')
				->drop(),
		])
		->indices([
			DatabaseTableIndex::create('releaseYear')
				->columns(['releaseYear']),
			DatabaseTableIndex::create('lastInteractionTime')
				->columns(['lastInteractionTime']),
			DatabaseTableIndex::create('playerCount')
				->columns(['playerCount']),
			DatabaseTableIndex::create('averageRating')
				->columns(['averageRating']),
		])
];
