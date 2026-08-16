<?php

use wcf\util\IgdbIntegrationUtil;

/**
 * Fills the computed playerCount and averageRating columns of all games from
 * the game <-> user association rows.
 *
 * @author      Berny23
 * @copyright   2026 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 */
IgdbIntegrationUtil::updateAllGameStats();