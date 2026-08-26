<?php

/**
 * Marks every game as stale, so it is refreshed from IGDB when its details
 * dialog is opened. Version 2.1.2 stores the full platform name instead of
 * the abbreviation, the refresh replaces the old tokens in the platform
 * filter step by step.
 *
 * The saved default platform pre-selection (global option and personal user
 * option) is migrated once from the old abbreviations to the full names,
 * otherwise it would silently stop matching the refreshed games. Unknown
 * tokens are kept as they are.
 *
 * @author      Berny23
 * @copyright   2026 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 */

use wcf\data\option\OptionEditor;
use wcf\system\WCF;
use wcf\util\ArrayUtil;

$sql = "UPDATE wcf1_igdb_integration_game
		SET lastFetchTime = 0";
WCF::getDB()->prepare($sql)->execute();

// IGDB platform abbreviation => full platform name, only for platforms
// whose abbreviation differs from the name
$platformNames = [
	'PC' => 'PC (Microsoft Windows)',
	'browser' => 'Web browser',
	'Mobile' => 'Legacy Mobile Device',
	'Stadia' => 'Google Stadia',
	'winphone' => 'Windows Phone',
	'Steam VR' => 'SteamVR',

	'PS1' => 'PlayStation',
	'PS2' => 'PlayStation 2',
	'PS3' => 'PlayStation 3',
	'PS4' => 'PlayStation 4',
	'PS5' => 'PlayStation 5',
	'PSP' => 'PlayStation Portable',
	'Vita' => 'PlayStation Vita',
	'PSVR' => 'PlayStation VR',
	'PSVR2' => 'PlayStation VR2',

	'XBOX' => 'Xbox',
	'X360' => 'Xbox 360',
	'XONE' => 'Xbox One',
	'Series X|S' => 'Xbox Series X|S',

	'NES' => 'Nintendo Entertainment System',
	'famicom' => 'Family Computer',
	'fds' => 'Family Computer Disk System',
	'SNES' => 'Super Nintendo Entertainment System',
	'SFAM' => 'Super Famicom',
	'N64' => 'Nintendo 64',
	'64DD' => 'Nintendo 64DD',
	'NGC' => 'Nintendo GameCube',
	'WiiU' => 'Wii U',
	'Switch' => 'Nintendo Switch',
	'Switch 2' => 'Nintendo Switch 2',
	'GBC' => 'Game Boy Color',
	'GBA' => 'Game Boy Advance',
	'NDS' => 'Nintendo DS',
	'DSi' => 'Nintendo DSi',
	'3DS' => 'Nintendo 3DS',
	'N3DS' => 'New Nintendo 3DS',
	'virtualboy' => 'Virtual Boy',

	'Genesis/MegaDrive' => 'Sega Mega Drive/Genesis',
	'segacd' => 'Sega CD',
	'sega32' => 'Sega 32X',
	'Saturn' => 'Sega Saturn',
	'DC' => 'Dreamcast',
	'SMS' => 'Sega Master System/Mark III',
	'gamegear' => 'Sega Game Gear',
	'sg1000' => 'SG-1000',

	'Atari2600' => 'Atari 2600',
	'Atari5200' => 'Atari 5200',
	'Atari7800' => 'Atari 7800',
	'Jaguar' => 'Atari Jaguar',
	'Lynx' => 'Atari Lynx',
	'Atari-ST' => 'Atari ST/STE',
	'Atari8bit' => 'Atari 8-bit',

	'C64' => 'Commodore C64/128/MAX',
	'C16' => 'Commodore 16',
	'C+4' => 'Commodore Plus/4',
	'VIC-20' => 'Commodore VIC-20',
	'CPET' => 'Commodore PET',
	'CD32' => 'Amiga CD32',
	'ACPC' => 'Amstrad CPC',
	'ZXS' => 'ZX Spectrum',
	'Apple][' => 'Apple II',
	'bbcmicro' => 'BBC Microcomputer System',
	'ti-99' => 'Texas Instruments TI-99',

	'TG-16' => 'TurboGrafx-16/PC Engine',
	'TG-CD' => 'Turbografx-16/PC Engine CD',
	'neogeoaes' => 'Neo Geo AES',
	'neogeomvs' => 'Neo Geo MVS',
	'neogeocd' => 'Neo Geo CD',
	'NGP' => 'Neo Geo Pocket',
	'NGPC' => 'Neo Geo Pocket Color',
	'3DO' => '3DO Interactive Multiplayer',
];

/**
 * Returns the migrated option value, or null if nothing changed.
 */
$migratePlatformList = static function (?string $value) use ($platformNames): ?string {
	if ($value === null || $value === '') {
		return null;
	}

	$platforms = [];
	foreach (array_filter(ArrayUtil::trim(explode("\n", $value))) as $platform) {
		$platform = $platformNames[$platform] ?? $platform;
		$platforms[$platform] = $platform; // Deduplicate
	}

	$newValue = implode("\n", $platforms);

	return $newValue !== $value ? $newValue : null;
};

// Global default (ACP option)
$sql = "SELECT optionID, optionValue
		FROM wcf1_option
		WHERE optionName = ?";
$statement = WCF::getDB()->prepare($sql);
$statement->execute(['igdb_integration_general_default_platform_filter']);
$option = $statement->fetchArray();
if ($option !== false) {
	$newValue = $migratePlatformList($option['optionValue']);
	if ($newValue !== null) {
		$sql = "UPDATE wcf1_option
				SET optionValue = ?
				WHERE optionID = ?";
		WCF::getDB()->prepare($sql)->execute([$newValue, $option['optionID']]);
		OptionEditor::resetCache();
	}
}

// Personal pre-selection of every user
$sql = "SELECT optionID
		FROM wcf1_user_option
		WHERE optionName = ?";
$statement = WCF::getDB()->prepare($sql);
$statement->execute(['igdb_integration_default_platform_filter']);
$userOptionId = $statement->fetchColumn();
if ($userOptionId !== false) {
	$column = 'userOption' . intval($userOptionId);

	$sql = "SELECT userID, " . $column . " AS platforms
			FROM wcf1_user_option_value
			WHERE " . $column . " <> ''";
	$statement = WCF::getDB()->prepare($sql);
	$statement->execute();
	$userPlatforms = $statement->fetchMap('userID', 'platforms');

	$sql = "UPDATE wcf1_user_option_value
			SET " . $column . " = ?
			WHERE userID = ?";
	$updateStatement = WCF::getDB()->prepare($sql);

	WCF::getDB()->beginTransaction();
	foreach ($userPlatforms as $userId => $platforms) {
		$newValue = $migratePlatformList($platforms);
		if ($newValue !== null) {
			$updateStatement->execute([$newValue, $userId]);
		}
	}
	WCF::getDB()->commitTransaction();
}
