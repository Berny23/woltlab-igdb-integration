<?php

namespace wcf\util;

/**
 * Maps the system names of OGDB (Online Games-Datenbank) collection exports
 * to the platforms of IGDB.
 *
 * @author      Berny23
 * @copyright   2026 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\Util
 */
final class IgdbIntegrationOgdbUtil
{
	/**
	 * IGDB platform tokens that count as "PC" for the purpose of an OGDB
	 * import. Since 2.1.2 the full platform name is stored, earlier versions
	 * stored the abbreviation if there was one. Both are listed for every
	 * platform, so games that were not refreshed since still match.
	 */
	const PC_PLATFORMS = ['PC', 'PC (Microsoft Windows)', 'DOS', 'Linux', 'Mac'];

	/**
	 * Ordered list of [pattern, IGDB platform tokens] rules. Every rule whose
	 * pattern matches the OGDB system name contributes its tokens, so
	 * multi-system releases like "PC/SEGA Dreamcast - CD-ROM" map to both
	 * platforms. The patterns are matched case-insensitively.
	 *
	 * OGDB names a system once per media type ("PC - CD-ROM", "PC - Download",
	 * "Nintendo Switch - Download"), which is why most patterns only look at
	 * the system part before the media suffix.
	 */
	const RULES = [
		// Computers
		['/(?<![\w.\/-])(?<!Pocket )(?<!Konami )(?<!Bemani )(?<!IBM )PC(?![\w-]| Engine)/', self::PC_PLATFORMS],
		['/(?<![\w.\/-])PC\//', self::PC_PLATFORMS],
		['/Macintosh/', ['Mac']],
		['/Amiga(?! CD)|AmigaCD/', ['Amiga']],
		['/Amiga CD|CD³²|CD32/', ['CD32', 'Amiga CD32']],
		['/Commodore CDTV/', ['Commodore CDTV']],
		['/\bC64\b|Commodore 64|The C64/', ['C64', 'Commodore C64/128/MAX']],
		['/\bC128\b/', ['C64', 'Commodore C64/128/MAX']],
		['/C16|C116|Plus\/4/', ['C16', 'Commodore 16', 'C+4', 'Commodore Plus/4']],
		['/VIC[ -]?20/', ['VIC-20', 'Commodore VIC-20']],
		['/Commodore PET|Commodore Pet|PET\/CBM|PET\/CM|CBM 8000/', ['CPET', 'Commodore PET']],
		['/Atari ST\b|Atari Falcon/', ['Atari-ST', 'Atari ST/STE']],
		['/Atari 800|Atari 400|Atari XE/', ['Atari8bit', 'Atari 8-bit']],
		['/Amstrad CPC|Amstrad GX4000/', ['ACPC', 'Amstrad CPC']],
		['/Amstrad PCW/', ['Amstrad PCW']],
		['/ZX Spectrum|ZX-80|ZX-81|Timex Sinclair/', ['ZXS', 'ZX Spectrum']],
		['/Sinclair QL/', ['Sinclair QL']],
		['/MSX-2|MSX2/', ['MSX2']],
		['/\bMSX\b(?!-2)|MSX turboR/', ['MSX']],
		['/Apple II(?!gs)|Apple II\b/', ['Apple][', 'Apple II']],
		['/Apple IIgs/', ['Apple IIGS']],
		['/Acorn BBC|Acorn BBC\/Electron/', ['bbcmicro', 'BBC Microcomputer System']],
		['/Acorn Electron/', ['Acorn Electron']],
		['/Acorn Archimedes|Acorn Risc PC/', ['Acorn Archimedes']],
		['/TRS-80 CoCo|Color Computer/', ['TRS-80 Color Computer']],
		['/TRS-80(?! CoCo)/', ['TRS-80']],
		['/Dragon 32/', ['Dragon 32/64']],
		['/TI99/', ['ti-99', 'Texas Instruments TI-99']],
		['/Sharp X680x0/', ['Sharp X68000']],
		['/Sharp X-1/', ['Sharp X1']],
		['/Sharp MZ/', ['Sharp MZ-2200']],
		['/NEC PC-88|NEC PC-8001/', ['PC-8800 Series']],
		['/NEC PC-98/', ['PC-9800 Series']],
		['/NEC PC-6001|NEC PC-6601/', ['PC-6000 Series']],
		['/Fujitsu FM-7/', ['FM-7']],
		['/FM-Towns/', ['FM Towns']],
		['/Thomson MO5/', ['Thomson MO5']],
		['/Oric/', ['Tangerine Oric']],
		['/SAM Coupé/', ['SAM Coupé']],
		['/Enterprise 64/', ['Enterprise 64/128']],
		['/Camputers Lynx/', ['Camputers Lynx']],
		['/PDP-11/', ['PDP-11']],
		['/PLATO/', ['PLATO']],
		['/Internet|Browser|Roblox/', ['browser', 'Web browser']],

		// Sony
		['/PlayStation(?! [2-5]| Portable| Vita)/', ['PS1', 'PlayStation']],
		['/PlayStation 2/', ['PS2', 'PlayStation 2']],
		['/PlayStation 3/', ['PS3', 'PlayStation 3']],
		['/PlayStation 4/', ['PS4', 'PlayStation 4']],
		['/PlayStation 5/', ['PS5', 'PlayStation 5']],
		['/PlayStation Portable/', ['PSP', 'PlayStation Portable']],
		['/PlayStation Vita/', ['Vita', 'PlayStation Vita']],

		// Microsoft
		['/Xbox(?! 360| One| Series|\/Xbox 360)/', ['XBOX', 'Xbox']],
		['/Xbox 360|Xbox\/Xbox 360/', ['X360', 'Xbox 360']],
		['/Xbox One/', ['XONE', 'Xbox One']],
		['/Xbox Series/', ['Series X|S', 'Xbox Series X|S']],
		['/Windows Phone/', ['winphone', 'Windows Phone']],
		['/Pocket PC|PalmOS\/Pocket PC/', ['Windows Mobile', 'Pocket PC']],

		// Nintendo
		['/Famicom\/NES|Famicom Disk/', ['NES', 'Nintendo Entertainment System', 'famicom', 'Family Computer', 'fds', 'Family Computer Disk System']],
		['/Super Famicom|SNES/', ['SNES', 'Super Nintendo Entertainment System', 'SFAM', 'Super Famicom']],
		['/Nintendo 64/', ['N64', 'Nintendo 64', 'Nintendo 64DD']],
		['/GameCube/', ['NGC', 'Nintendo GameCube']],
		['/Wii U/', ['WiiU', 'Wii U']],
		['/Wii(?! U)/', ['Wii']],
		['/Switch 2/', ['Switch 2', 'Nintendo Switch 2']],
		['/Switch(?! 2)/', ['Switch', 'Nintendo Switch']],
		['/GameBoy Advance/', ['GBA', 'Game Boy Advance']],
		['/GameBoy Color|GameBoy\/GameBoy Color/', ['GBC', 'Game Boy Color']],
		['/GameBoy(?! Advance| Color)/', ['Game Boy']],
		['/Nintendo DSi/', ['DSi', 'Nintendo DSi']],
		['/Nintendo DS(?!i)/', ['NDS', 'Nintendo DS']],
		['/New 3DS/', ['N3DS', 'New Nintendo 3DS', '3DS', 'Nintendo 3DS']],
		['/Nintendo 3DS/', ['3DS', 'Nintendo 3DS']],
		['/VirtualBoy/', ['virtualboy', 'Virtual Boy']],
		['/Game&Watch/', ['Game & Watch']],
		['/Pokémon mini/', ['Pokémon mini']],

		// Sega
		['/MegaDrive|Mega Drive/', ['Genesis/MegaDrive', 'Sega Mega Drive/Genesis']],
		['/Mega CD|Sega CD|Mega-LD/', ['segacd', 'Sega CD']],
		['/32X/', ['sega32', 'Sega 32X']],
		['/Saturn/', ['Saturn', 'Sega Saturn']],
		['/Dreamcast/', ['DC', 'Dreamcast']],
		['/Master System/', ['SMS', 'Sega Master System/Mark III']],
		['/GameGear/', ['gamegear', 'Sega Game Gear']],
		['/SG-1000|SG1000/', ['sg1000', 'SG-1000']],
		['/Pico/', ['Sega Pico']],

		// Atari
		['/Atari VCS\/2600|Atari VCS(?! 800)/', ['Atari2600', 'Atari 2600']],
		['/Atari 5200/', ['Atari5200', 'Atari 5200']],
		['/Atari 7800/', ['Atari7800', 'Atari 7800']],
		['/Atari Jaguar(?! CD)/', ['Jaguar', 'Atari Jaguar']],
		['/Atari Jaguar CD/', ['Atari Jaguar CD']],
		['/Atari Lynx/', ['Lynx', 'Atari Lynx']],

		// NEC / SNK / others
		['/PC Engine|TurboGrafx/', ['TG-16', 'TurboGrafx-16/PC Engine', 'TG-CD', 'Turbografx-16/PC Engine CD']],
		['/Super Grafx/', ['PC Engine SuperGrafx']],
		['/PC-FX/', ['PC-FX']],
		['/NeoGeo - Home Cart|NeoGeo MVS/', ['neogeoaes', 'Neo Geo AES', 'neogeomvs', 'Neo Geo MVS']],
		['/NeoGeo - CD/', ['neogeocd', 'Neo Geo CD']],
		['/NeoGeo Pocket Color/', ['NGPC', 'Neo Geo Pocket Color']],
		['/NeoGeo Pocket(?! Color)/', ['NGP', 'Neo Geo Pocket']],
		['/^3DO/', ['3DO', '3DO Interactive Multiplayer']],
		['/Philips CD-i/', ['Philips CD-i']],
		['/G7000|Odyssey2/', ['Odyssey 2 / Videopac G7000']],
		['/Magnavox Odyssey$/', ['Odyssey']],
		['/Colecovision|ColecoVision/', ['ColecoVision']],
		['/Intellivision(?! Amico)/', ['Intellivision']],
		['/Intellivision Amico/', ['Intellivision Amico']],
		['/Vectrex/', ['Vectrex']],
		['/Channel F/', ['Fairchild Channel F']],
		['/WonderSwan Color/', ['WonderSwan Color']],
		['/WonderSwan(?! Color)/', ['WonderSwan']],
		['/N-Gage/', ['N-Gage']],
		['/Gizmondo/', ['Gizmondo']],
		['/Game\.com/', ['Game.com']],
		['/Zeebo/', ['Zeebo']],
		['/Nuon/', ['Nuon']],
		['/Tapwave Zodiac/', ['Tapwave Zodiac']],
		['/Evercade/', ['Evercade']],
		['/Playdia/', ['Playdia']],
		['/Casio Loopy/', ['Casio Loopy']],
		['/Microvision/', ['Microvision']],
		['/Supervision/', ['Watara/QuickShot Supervision']],
		['/Super Cassettevision/', ['Epoch Super Cassette Vision']],
		['/Arcadia 2001/', ['Arcadia 2001']],
		['/Interton VC 4000/', ['1292 Advanced Programmable Video System']],
		['/Ouya/', ['Ouya']],
		['/Nvidia Shield/', ['Nvidia Shield']],
		['/^Arcade/', ['Arcade']],

		// Mobile / streaming / VR
		['/Apple iOS|Apple iPod/', ['iOS']],
		['/Google Android|Nvidia Shield|Amazon Kindle/', ['Android']],
		['/Google Stadia/', ['Stadia', 'Google Stadia']],
		['/Amazon Luna/', ['Amazon Luna']],
		['/PalmOS/', ['Palm OS']],
		['/BlackBerry/', ['BlackBerry OS']],
		['/HP WebOS/', ['webOS']],
		['/Mobile - J2ME|Mobile - BREW|Mobile - DoCoMo|Mobile - i-Mode|Symbian/', ['Legacy Mobile Device']],
		['/Oculus Quest|Meta - Oculus Quest/', ['Meta Quest 2', 'Meta Quest 3', 'Oculus Quest']],
		['/Oculus Rift/', ['Oculus Rift']],
		['/Oculus GO/', ['Oculus Go']],
		['/HTC Vive/', ['Steam VR', 'SteamVR']],
		['/Gear VR/', ['Gear VR']],
	];

	/**
	 * Returns the IGDB platform tokens (abbreviations and names) of an OGDB
	 * system name, or null if the system is unknown and the game has to be
	 * matched regardless of its platform.
	 */
	public static function getIgdbPlatforms(string $systemName): ?array
	{
		$systemName = StringUtil::trim($systemName);
		if ($systemName === '') {
			return null;
		}

		$platforms = [];
		foreach (self::RULES as [$pattern, $tokens]) {
			if (preg_match($pattern . 'iu', $systemName)) {
				foreach ($tokens as $token) {
					$platforms[$token] = $token;
				}
			}
		}

		return empty($platforms) ? null : array_values($platforms);
	}

	/**
	 * Returns whether the given IGDB platform tokens contain a PC platform.
	 */
	public static function isPcPlatformList(?array $platforms): bool
	{
		if ($platforms === null) {
			return true;
		}

		foreach ($platforms as $platform) {
			foreach (self::PC_PLATFORMS as $pcPlatform) {
				if (strcasecmp($platform, $pcPlatform) === 0) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Returns whether the comma-separated platform list of a local game
	 * contains at least one of the given IGDB platform tokens. Games without
	 * platform data are accepted, because excluding them would make their
	 * titles unmatchable.
	 */
	public static function gameHasPlatform(string $gamePlatforms, array $platforms): bool
	{
		$gamePlatforms = StringUtil::trim($gamePlatforms);
		if ($gamePlatforms === '') {
			return true;
		}

		foreach (ArrayUtil::trim(explode(',', $gamePlatforms)) as $gamePlatform) {
			foreach ($platforms as $platform) {
				if (strcasecmp($gamePlatform, $platform) === 0) {
					return true;
				}
			}
		}

		return false;
	}
}
