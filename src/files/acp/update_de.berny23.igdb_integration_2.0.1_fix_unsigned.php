<?php

use wcf\system\WCF;

/**
 * Converts UNSIGNED integer columns of the plugin tables back to signed ones.
 * The plugin never creates unsigned columns, but external tools (server
 * migrations, backup imports) sometimes add the modifier. WoltLab's database
 * API cannot parse column types like "int unsigned" reported by MySQL 8 and
 * would abort the upgrade during the database instruction otherwise.
 *
 * @author      Berny23
 * @copyright   2026 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 */
$tableNames = ['wcf1_igdb_integration_game', 'wcf1_igdb_integration_game_user'];
$placeholders = implode(',', array_fill(0, count($tableNames), '?'));

// Find all unsigned columns in the plugin tables
$sql = "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
		FROM information_schema.COLUMNS
		WHERE TABLE_SCHEMA = DATABASE()
			AND TABLE_NAME IN ({$placeholders})
			AND COLUMN_TYPE LIKE '%unsigned%'";
$statement = WCF::getDB()->prepare($sql);
$statement->execute($tableNames);
$unsignedColumns = $statement->fetchAll(\PDO::FETCH_ASSOC);

if (empty($unsignedColumns)) {
	return;
}

// Collect the foreign keys of the plugin tables, they have to be dropped
// before the signedness of an involved column can be changed
$sql = "SELECT rc.CONSTRAINT_NAME, rc.TABLE_NAME, rc.REFERENCED_TABLE_NAME, rc.DELETE_RULE,
			kcu.COLUMN_NAME, kcu.REFERENCED_COLUMN_NAME
		FROM information_schema.REFERENTIAL_CONSTRAINTS rc
		INNER JOIN information_schema.KEY_COLUMN_USAGE kcu
			ON kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
			AND kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
			AND kcu.TABLE_NAME = rc.TABLE_NAME
		WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
			AND rc.TABLE_NAME IN ({$placeholders})
		ORDER BY rc.CONSTRAINT_NAME, kcu.ORDINAL_POSITION";
$statement = WCF::getDB()->prepare($sql);
$statement->execute($tableNames);
$foreignKeys = [];
while ($row = $statement->fetchArray()) {
	$key = $row['TABLE_NAME'] . '.' . $row['CONSTRAINT_NAME'];
	if (!isset($foreignKeys[$key])) {
		$foreignKeys[$key] = [
			'name' => $row['CONSTRAINT_NAME'],
			'tableName' => $row['TABLE_NAME'],
			'referencedTable' => $row['REFERENCED_TABLE_NAME'],
			'deleteRule' => $row['DELETE_RULE'],
			'columns' => [],
			'referencedColumns' => [],
		];
	}
	$foreignKeys[$key]['columns'][] = $row['COLUMN_NAME'];
	$foreignKeys[$key]['referencedColumns'][] = $row['REFERENCED_COLUMN_NAME'];
}

// Both sides of a foreign key must have the same signedness, so a column
// that references an unsigned column of a foreign table (e.g. wcf1_user)
// has to stay unsigned as well
$convertColumns = [];
foreach ($unsignedColumns as $column) {
	$isConvertible = true;
	foreach ($foreignKeys as $foreignKey) {
		if ($foreignKey['tableName'] !== $column['TABLE_NAME'] || in_array($foreignKey['referencedTable'], $tableNames)) {
			continue;
		}
		$position = array_search($column['COLUMN_NAME'], $foreignKey['columns']);
		if ($position === false) {
			continue;
		}

		$sql = "SELECT COLUMN_TYPE
				FROM information_schema.COLUMNS
				WHERE TABLE_SCHEMA = DATABASE()
					AND TABLE_NAME = ?
					AND COLUMN_NAME = ?";
		$statement = WCF::getDB()->prepare($sql);
		$statement->execute([$foreignKey['referencedTable'], $foreignKey['referencedColumns'][$position]]);
		$referencedType = $statement->fetchSingleColumn();
		if ($referencedType !== false && stripos($referencedType, 'unsigned') !== false) {
			$isConvertible = false;
		}
	}
	if ($isConvertible) {
		$convertColumns[] = $column;
	}
}

if (empty($convertColumns)) {
	return;
}

// Drop all foreign keys that involve one of the columns, either as the
// referencing or as the referenced side
$droppedForeignKeys = [];
foreach ($foreignKeys as $foreignKey) {
	foreach ($convertColumns as $column) {
		$isReferencingSide = $foreignKey['tableName'] === $column['TABLE_NAME']
			&& in_array($column['COLUMN_NAME'], $foreignKey['columns']);
		$isReferencedSide = $foreignKey['referencedTable'] === $column['TABLE_NAME']
			&& in_array($column['COLUMN_NAME'], $foreignKey['referencedColumns']);
		if ($isReferencingSide || $isReferencedSide) {
			$statement = WCF::getDB()->prepare("ALTER TABLE `{$foreignKey['tableName']}`
				DROP FOREIGN KEY `{$foreignKey['name']}`");
			$statement->execute();
			$droppedForeignKeys[] = $foreignKey;
			break;
		}
	}
}

// Remove the unsigned modifier, keeping all other column properties
foreach ($convertColumns as $column) {
	$definition = trim(preg_replace('~\s*unsigned~i', '', $column['COLUMN_TYPE']));
	if ($column['IS_NULLABLE'] === 'NO') {
		$definition .= ' NOT NULL';
	}
	if ($column['COLUMN_DEFAULT'] !== null) {
		$definition .= " DEFAULT '" . $column['COLUMN_DEFAULT'] . "'";
	}
	if (stripos($column['EXTRA'], 'auto_increment') !== false) {
		$definition .= ' AUTO_INCREMENT';
	}
	$statement = WCF::getDB()->prepare("ALTER TABLE `{$column['TABLE_NAME']}`
		MODIFY COLUMN `{$column['COLUMN_NAME']}` {$definition}");
	$statement->execute();
}

// Restore the dropped foreign keys with their original names
foreach ($droppedForeignKeys as $foreignKey) {
	$columns = '`' . implode('`,`', $foreignKey['columns']) . '`';
	$referencedColumns = '`' . implode('`,`', $foreignKey['referencedColumns']) . '`';
	$statement = WCF::getDB()->prepare("ALTER TABLE `{$foreignKey['tableName']}`
		ADD CONSTRAINT `{$foreignKey['name']}` FOREIGN KEY ({$columns})
		REFERENCES `{$foreignKey['referencedTable']}` ({$referencedColumns})
		ON DELETE {$foreignKey['deleteRule']}");
	$statement->execute();
}
