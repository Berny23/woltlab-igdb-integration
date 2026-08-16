<?php

namespace wcf\action;

use Exception;
use GuzzleHttp\Psr7\Request;
use wcf\system\exception\IllegalLinkException;
use wcf\system\exception\PermissionDeniedException;
use wcf\system\io\HttpFactory;
use wcf\system\request\LinkHandler;
use wcf\system\WCF;
use wcf\util\HeaderUtil;
use wcf\util\IgdbIntegrationApiRateLimiter;
use wcf\util\IgdbIntegrationUtil;

/**
 * Verifies the Steam account of the current user via Steam's OpenID 2.0
 * endpoint for the game library import. Only the verified SteamID64 is kept in
 * the session, no permanent account link is stored.
 *
 * @author      Berny23
 * @copyright   2026 Berny23
 * @license     MIT License <https://choosealicense.com/licenses/mit/>
 * @package     WoltLabSuite\Core\Action
 * @see         https://partner.steamgames.com/doc/features/auth
 */
class IgdbIntegrationSteamAuthAction extends AbstractAction
{
	const STEAM_OPENID_ENDPOINT = 'https://steamcommunity.com/openid/login';

	/**
	 * @inheritDoc
	 */
	public $neededPermissions = ['user.igdb_integration.can_import_games'];

	/**
	 * @inheritDoc
	 */
	public function execute()
	{
		parent::execute();

		if (!WCF::getUser()->userID) {
			throw new PermissionDeniedException();
		}
		if (!IgdbIntegrationUtil::isSteamConnectionDataValid()) {
			throw new IllegalLinkException();
		}

		$mode = $_GET['openid_mode'] ?? '';
		if ($mode === 'id_res') {
			$this->processCallback();
		} elseif ($mode === '') {
			$this->redirectToSteam();
		} else {
			// The user canceled the login on the Steam page
			$this->redirectToGameList('');
		}
	}

	/**
	 * Redirects the browser to the Steam login page.
	 */
	private function redirectToSteam()
	{
		// OpenID 2.0 has no state parameter, so bind the flow to the session
		// with a nonce in the return url; return_to is covered by the signature
		$nonce = bin2hex(\random_bytes(16));
		$returnTo = LinkHandler::getInstance()->getLink('IgdbIntegrationSteamAuth', [], 'state=' . $nonce);
		WCF::getSession()->register('igdbSteamAuthNonce', $nonce);
		WCF::getSession()->register('igdbSteamAuthReturnTo', $returnTo);

		$urlParts = \parse_url($returnTo);
		$realm = $urlParts['scheme'] . '://' . $urlParts['host'] . (isset($urlParts['port']) ? ':' . $urlParts['port'] : '');

		$parameters = [
			'openid.ns' => 'http://specs.openid.net/auth/2.0',
			'openid.mode' => 'checkid_setup',
			'openid.claimed_id' => 'http://specs.openid.net/auth/2.0/identifier_select',
			'openid.identity' => 'http://specs.openid.net/auth/2.0/identifier_select',
			'openid.return_to' => $returnTo,
			'openid.realm' => $realm,
		];

		$this->executed();
		HeaderUtil::redirect(self::STEAM_OPENID_ENDPOINT . '?' . \http_build_query($parameters));
		exit;
	}

	/**
	 * Validates the signed response of a Steam login and stores the verified
	 * SteamID64 in the session.
	 */
	private function processCallback()
	{
		$nonce = WCF::getSession()->getVar('igdbSteamAuthNonce');
		$expectedReturnTo = WCF::getSession()->getVar('igdbSteamAuthReturnTo');
		WCF::getSession()->unregister('igdbSteamAuthNonce');
		WCF::getSession()->unregister('igdbSteamAuthReturnTo');

		$state = $_GET['state'] ?? '';
		if (empty($nonce) || !\is_string($state) || !\hash_equals($nonce, $state)) {
			$this->redirectToGameList('steamImportError=1');
		}

		$openidParameters = $this->readOpenidParameters();
		if (empty($openidParameters['openid.signed']) || empty($openidParameters['openid.sig'])
			|| empty($openidParameters['openid.claimed_id'])
			|| ($openidParameters['openid.return_to'] ?? '') !== $expectedReturnTo
			|| !$this->verifyWithSteam($openidParameters)) {
			$this->redirectToGameList('steamImportError=1');
		}

		if (!\preg_match('#^https?://steamcommunity\.com/openid/id/(\d{17})$#', $openidParameters['openid.claimed_id'], $matches)) {
			$this->redirectToGameList('steamImportError=1');
		}

		WCF::getSession()->register('igdbSteamId', $matches[1]);
		$this->redirectToGameList('steamImport=1');
	}

	/**
	 * Returns all openid.* query parameters. The raw query string is parsed
	 * because PHP replaces dots with underscores in $_GET keys.
	 */
	private function readOpenidParameters(): array
	{
		$parameters = [];
		foreach (\explode('&', $_SERVER['QUERY_STRING'] ?? '') as $pair) {
			$parts = \explode('=', $pair, 2);
			if (\count($parts) !== 2) {
				continue;
			}
			$key = \rawurldecode($parts[0]);
			if (\str_starts_with($key, 'openid.')) {
				$parameters[$key] = \rawurldecode($parts[1]);
			}
		}

		return $parameters;
	}

	/**
	 * Asks Steam whether it really issued the received, signed response.
	 */
	private function verifyWithSteam(array $openidParameters): bool
	{
		$openidParameters['openid.mode'] = 'check_authentication';

		$request = new Request(
			'POST',
			self::STEAM_OPENID_ENDPOINT,
			['Content-Type' => 'application/x-www-form-urlencoded'],
			\http_build_query($openidParameters)
		);

		try {
			// Wait for a free slot of the sitewide rate limit queue
			IgdbIntegrationApiRateLimiter::acquireSlot(IgdbIntegrationApiRateLimiter::API_STEAM);

			$response = HttpFactory::getDefaultClient()->send($request);
		} catch (Exception $ex) {
			return false;
		}

		return \str_contains((string)$response->getBody(), 'is_valid:true');
	}

	/**
	 * Redirects to the game list page and ends the request.
	 */
	private function redirectToGameList(string $parameters)
	{
		$this->executed();
		HeaderUtil::redirect(LinkHandler::getInstance()->getLink('IgdbIntegrationGameList', [], $parameters));
		exit;
	}
}