<?php

use wcf\system\database\table\column\NotNullVarchar255DatabaseTableColumn;
use wcf\system\database\table\column\NotNullInt10DatabaseTableColumn;
use wcf\system\database\table\column\ObjectIdDatabaseTableColumn;
use wcf\system\database\table\column\SmallintDatabaseTableColumn;
use wcf\system\database\table\column\YearDatabaseTableColumn;
use wcf\system\database\table\DatabaseTable;
use wcf\system\database\table\PartialDatabaseTable;
use wcf\system\database\table\index\DatabaseTablePrimaryIndex;
use wcf\system\database\table\index\DatabaseTableForeignKey;

return [
    DatabaseTable::create('wcf' . WCF_N . '_igdb_integration_game')
        ->columns([
            ObjectIdDatabaseTableColumn::create('gameId'),
            NotNullVarchar255DatabaseTableColumn::create('name')
                ->length(500),
            NotNullVarchar255DatabaseTableColumn::create('germanName')
                ->length(500),
            YearDatabaseTableColumn::create('firstReleaseDateYear')
                ->notNull(),
            NotNullVarchar255DatabaseTableColumn::create('platforms')
                ->length(500),
            NotNullVarchar255DatabaseTableColumn::create('summary')
                ->length(5000),
            NotNullVarchar255DatabaseTableColumn::create('coverImageId'),
            NotNullVarchar255DatabaseTableColumn::create('coverImageUrl')
                ->length(2000)
        ])
        ->indices([
            DatabaseTablePrimaryIndex::create()
                ->columns(['gameId']),
        ]),
    DatabaseTable::create('wcf' . WCF_N . '_igdb_integration_game_user')
        ->columns([
            NotNullInt10DatabaseTableColumn::create('gameId'),
            NotNullInt10DatabaseTableColumn::create('userId'),
            SmallintDatabaseTableColumn::create('rating')
                ->length(1)
                ->defaultValue(0)
                ->notNull()
        ])
        ->foreignKeys([
            DatabaseTableForeignKey::create()
                ->columns(['gameId'])
                ->referencedTable('wcf' . WCF_N . '_igdb_integration_game')
                ->referencedColumns(['gameId'])
                ->onDelete('CASCADE'),
            DatabaseTableForeignKey::create()
                ->columns(['userId'])
                ->referencedTable('wcf' . WCF_N . '_user')
                ->referencedColumns(['userID'])
                ->onDelete('CASCADE'),
        ]),
    // Add column to default user table
    PartialDatabaseTable::create('wcf' . WCF_N . '_user')
        ->columns([
            NotNullInt10DatabaseTableColumn::create('IgdbIntegrationGameCount')
                ->defaultValue(0)
        ])
];
