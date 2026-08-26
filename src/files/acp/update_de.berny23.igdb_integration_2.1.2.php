<?php

/**
 * Marks every game as stale, so it is refreshed from IGDB when its details
 * dialog is opened. Version 2.1.2 stores the full platform name instead of
 * the abbreviation, the refresh replaces the old tokens in the platform
 * filter step by step.
 *
 * @author      Berny23
 * @copyright   2026 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 */

use wcf\system\WCF;

$sql = "UPDATE wcf1_igdb_integration_game
		SET lastFetchTime = 0";
WCF::getDB()->prepare($sql)->execute();
