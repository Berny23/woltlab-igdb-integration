<?php

namespace wcf\util;

use Exception;
use wcf\system\request\LinkHandler;
use wcf\util\CryptoUtil;
use wcf\system\io\HttpFactory;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Request;
use \wcf\system\WCF;
use \wcf\data\option\OptionEditor;
use \wcf\data\option\Option;

/**
 * A utility class for API interactions with IGDB.
 *
 * @author      Berny23
 * @copyright   2023 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\Util
 * @see         https://api-docs.igdb.com/
 */
class IgdbIntegrationUtil
{
    const URL_BASE = 'https://api.igdb.com/v4/';
    const TWITCH_URL_BASE = 'https://id.twitch.tv/oauth2/token';

    /**
     * @var ClientInterface
     */
    private static $client = null;

    /**
     * Temporary access token to fix exception without reloading page.
     */
    private static $tempAccessToken = null;

    /**
     * Check if all authentication variables are available
     */
    private static function isConnectionDataValid()
    {
        return (!empty(IGDBINTEGRATION_AUTH_CLIENTID) && !empty(IGDBINTEGRATION_AUTH_CLIENTSECRET) && !empty(IGDBINTEGRATION_GENERAL_RESULT_LIMIT));
    }

    /**
     * Saves a new authentication token for the Twitch/IGDB API in the hidden user option.
     */
    private static function saveNewAccessToken(): bool
    {
        if (self::$client === null) {
            self::$client = HttpFactory::getDefaultClient();
        }
        $request = new Request('POST', self::TWITCH_URL_BASE . '?client_id=' . IGDBINTEGRATION_AUTH_CLIENTID . '&client_secret=' . IGDBINTEGRATION_AUTH_CLIENTSECRET . '&grant_type=client_credentials');

        try {
            $response = self::$client->send($request);
            self::$tempAccessToken = json_decode($response->getBody())->access_token;

            // Update the option with the new token.
            $optionId = Option::getOptionByName('IgdbIntegration_auth_accessToken')->getObjectID();
            OptionEditor::updateAll(array(
                $optionId => self::$tempAccessToken
            ));

            return true;
        } catch (Exception $ex) {
            return false;
        }
    }

    /**
     * Returns response with fetched game data from IGDB.
     */
    private static function fetchGameDataByName($name)
    {
        if (self::$client === null) {
            self::$client = HttpFactory::getDefaultClient();
        }

        if (is_null(self::$tempAccessToken)) {
            $accessToken = IGDBINTEGRATION_AUTH_ACCESSTOKEN;
        } else {
            $accessToken = self::$tempAccessToken;
        }

        $headers = [
            'Client-ID' => IGDBINTEGRATION_AUTH_CLIENTID,
            'Authorization' => 'Bearer ' . $accessToken
        ];
        $body = 'search "' . $name . '"; fields id,name,alternative_names.comment,alternative_names.name,first_release_date,platforms.abbreviation,platforms.name,summary,cover.image_id; limit ' . IGDBINTEGRATION_GENERAL_RESULT_LIMIT . ';';
        $request = new Request('POST', self::URL_BASE . 'games', $headers, $body);
        return self::$client->send($request);
    }

    /**
     * Updates the game database with search results, if gameId doesn't already exist.
     */
    public static function updateDatabaseGamesByName($name, $isRetry = false): bool
    {
        if (!self::isConnectionDataValid()) {
            return false;
        }

        try {
            $response = self::fetchGameDataByName($name);
        } catch (Exception $ex) {
            if (self::saveNewAccessToken()) {
                // Retry IGDB request if successfully got new token
                $response = self::fetchGameDataByName($name);
                self::$tempAccessToken = null;
            } else {
                // Failed getting new token
                return false;
            }
        }

        // Insert into games database
        $gamesJson = json_decode($response->getBody());
        $sql = "INSERT INTO wcf" . WCF_N . "_igdb_integration_game SET gameId = ?, name = ?, germanName = ?, firstReleaseDateYear = ?, platforms = ?, summary = ?, coverImageId = ?, coverImageUrl = ? ON DUPLICATE KEY UPDATE name = ?, germanName = ?, firstReleaseDateYear = ?, platforms = ?, summary = ?, coverImageId = ?, coverImageUrl = ?";
        $statement = WCF::getDB()->prepare($sql);
        foreach ($gamesJson as $game) {
            $gamePlatforms = '';
            if (isset($game->platforms)) {
                foreach ($game->platforms as $platform) {
                    if (isset($platform->abbreviation)) {
                        $gamePlatforms .= $platform->abbreviation . ', ';
                    } elseif (isset($platform->name)) {
                        $gamePlatforms .= $platform->name . ', ';
                    }
                }
                $gamePlatforms = substr($gamePlatforms, 0, -2); // Remove last separator
            }

            $gameGermanName = '';
            if (isset($game->alternative_names)) {
                foreach ($game->alternative_names as $altName) {
                    if (isset($altName->comment) && isset($altName->name)) {
                        // Find language name in comment of alternative name
                        if (empty($gameGermanName) && (stripos($altName->comment, 'german') !== false || stripos($altName->comment, 'german') !== false)) {
                            $gameGermanName = $altName->name;
                        }
                    }
                }
            }

            $gameId = $game->id;
            $gameName = $game->name ?? '';
            $gameYear = isset($game->first_release_date) ? date("Y", $game->first_release_date) : 0;
            $gameSummary = $game->summary ?? '';
            $gameCoverId = isset($game->cover) ? $game->cover->image_id : 'nocover';
            $gameCoverUrl = self::getImageProxyLink('https://images.igdb.com/igdb/image/upload/t_cover_med/' . $gameCoverId . '.jpg');

            $statement->execute([$gameId, $gameName, $gameGermanName, $gameYear, $gamePlatforms, $gameSummary, $gameCoverId, $gameCoverUrl, /* UPDATE starts here */ $gameName, $gameGermanName, $gameYear, $gamePlatforms, $gameSummary, $gameCoverId, $gameCoverUrl]);
        }
        WCF::getDB()->commitTransaction();

        return true;
    }

    public static function validateRating($value)
    {
        return $value != 0;
    }

    /**
     * Returns the link to a given image url via image proxy
     * @see https://www.woltlab.com/community/thread/297027-image-proxy-fehlerhaft/?postID=1903894#post1903894
     */
    public static function getImageProxyLink(string $link): string
    {
        // Return normal link if proxy is disabled
        if (!MODULE_IMAGE_PROXY) {
            return $link;
        }

        try {
            return LinkHandler::getInstance()->getLink(
                'ImageProxy',
                ['key' => CryptoUtil::createSignedString($link)]
            );
        } catch (Exception $e) {
            return $link;
        }
    }

    /**
     * Returns the localized game name.
     */
    public static function getLocalizedGameNameColumn(): string
    {
        switch (WCF::getLanguage()->getFixedLanguageCode()) {
            case 'de':
                $localizedNameColumn = 'germanName';
                break;
            default:
                $localizedNameColumn = 'name';
                break;
        }
        return $localizedNameColumn;
    }
}
