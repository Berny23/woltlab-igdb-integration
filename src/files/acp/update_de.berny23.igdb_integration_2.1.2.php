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
// whose abbreviation differs from the name. Generated from the IGDB
// "platforms" endpoint (220 platforms)
$platformNames = [
	'3DO' => '3DO Interactive Multiplayer',
	'FireTV' => 'Amazon Fire TV',
	'ACPC' => 'Amstrad CPC',
	'GX4000' => 'Amstrad GX4000',
	'APCW' => 'Amstrad PCW',
	'analogueelectronics' => 'Analogue electronics',
	'Apple][' => 'Apple II',
	'Atari2600' => 'Atari 2600',
	'Atari5200' => 'Atari 5200',
	'Atari7800' => 'Atari 7800',
	'Atari8bit' => 'Atari 8-bit',
	'Jaguar' => 'Atari Jaguar',
	'Lynx' => 'Atari Lynx',
	'Atari-ST' => 'Atari ST/STE',
	'astrocade' => 'Bally Astrocade',
	'bbcmicro' => 'BBC Microcomputer System',
	'blackberry' => 'BlackBerry OS',
	'call-a-computer' => 'Call-A-Computer time-shared mainframe computer system',
	'cdccyber70' => 'CDC Cyber 70',
	'colecovision' => 'ColecoVision',
	'C16' => 'Commodore 16',
	'C64' => 'Commodore C64/128/MAX',
	'cpet' => 'Commodore PET',
	'C+4' => 'Commodore Plus/4',
	'vic-20' => 'Commodore VIC-20',
	'gt40' => 'DEC GT40',
	'donner30' => 'Donner Model 30',
	'DC' => 'Dreamcast',
	'edsac' => 'EDSAC',
	'famicom' => 'Family Computer',
	'fds' => 'Family Computer Disk System',
	'nimrod' => 'Ferranti Nimrod Computer',
	'G&W' => 'Game & Watch',
	'GBA' => 'Game Boy Advance',
	'GBC' => 'Game Boy Color',
	'Stadia' => 'Google Stadia',
	'Handheld' => 'Handheld Electronic LCD',
	'hp2100' => 'HP 2100',
	'hp3000' => 'HP 3000',
	'imlac-pds1' => 'Imlac PDS-1',
	'intellivision' => 'Intellivision',
	'Mobile' => 'Legacy Mobile Device',
	'microcomputer' => 'Microcomputer',
	'microvision' => 'Microvision',
	'NGage' => 'N-Gage',
	'neogeoaes' => 'Neo Geo AES',
	'neogeomvs' => 'Neo Geo MVS',
	'New 3DS' => 'New Nintendo 3DS',
	'3DS' => 'Nintendo 3DS',
	'N64' => 'Nintendo 64',
	'NDS' => 'Nintendo DS',
	'NES' => 'Nintendo Entertainment System',
	'NGC' => 'Nintendo GameCube',
	'Switch' => 'Nintendo Switch',
	'Switch 2' => 'Nintendo Switch 2',
	'odyssey' => 'Odyssey',
	'OnLive' => 'OnLive Game System',
	'PC' => 'PC (Microsoft Windows)',
	'supergrafx' => 'PC Engine SuperGrafx',
	'pdp1' => 'PDP-1',
	'pdp10' => 'PDP-10',
	'pdp11' => 'PDP-11',
	'pdp-7' => 'PDP-7',
	'pdp-8' => 'PDP-8',
	'Philips CDI' => 'Philips CD-i',
	'plato' => 'PLATO',
	'PS1' => 'PlayStation',
	'PS2' => 'PlayStation 2',
	'PS3' => 'PlayStation 3',
	'PS4' => 'PlayStation 4',
	'PS5' => 'PlayStation 5',
	'PSP' => 'PlayStation Portable',
	'Vita' => 'PlayStation Vita',
	'PSVR' => 'PlayStation VR',
	'PSVR2' => 'PlayStation VR2',
	'sdssigma7' => 'SDS Sigma 7',
	'Sega32' => 'Sega 32X',
	'Game Gear' => 'Sega Game Gear',
	'SMS' => 'Sega Master System/Mark III',
	'Genesis/MegaDrive' => 'Sega Mega Drive/Genesis',
	'Saturn' => 'Sega Saturn',
	'sg1000' => 'SG-1000',
	'x1' => 'Sharp X1',
	'Steam VR' => 'SteamVR',
	'SFAM' => 'Super Famicom',
	'SNES' => 'Super Nintendo Entertainment System',
	'zod' => 'Tapwave Zodiac',
	'ti-99' => 'Texas Instruments TI-99',
	'turbografx16' => 'TurboGrafx-16/PC Engine',
	'vectrex' => 'Vectrex',
	'virtualboy' => 'Virtual Boy',
	'VC' => 'Virtual Console',
	'browser' => 'Web browser',
	'WiiU' => 'Wii U',
	'Win Phone' => 'Windows Phone',
	'XBOX' => 'Xbox',
	'X360' => 'Xbox 360',
	'XONE' => 'Xbox One',
	'Series X|S' => 'Xbox Series X|S',
	'ZXS' => 'ZX Spectrum',
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
