<?php
/**
 * @author Lukas Biermann
 * @author Nina Herrmann
 * @author Wladislaw Iwanzow
 * @author Dennis Meis
 * @author Jonathan Neugebauer
 *
 * @copyright Copyright (c) 2016, Project Seminar "PSSL16" at the University of Muenster.
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 */

namespace OCA\OAuth2\Controller;

use OCA\OAuth2\Db\AccessToken;
use OCA\OAuth2\Db\AccessTokenMapper;
use OCA\OAuth2\Db\AuthorizationCode;
use OCA\OAuth2\Db\AuthorizationCodeMapper;
use OCA\OAuth2\Db\Client;
use OCA\OAuth2\Db\ClientMapper;
use OCA\OAuth2\Db\RefreshToken;
use OCA\OAuth2\Db\RefreshTokenMapper;
use OCA\OAuth2\Utilities;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\AppFramework\ApiController;

use OCA\OAuth2\AppInfo\Application;
use OCP\IUser;
use OCP\IUserManager;

class OAuthApiController extends ApiController {

	/** @var ClientMapper */
	private $clientMapper;

	/** @var AuthorizationCodeMapper */
	private $authorizationCodeMapper;

	/** @var AccessTokenMapper */
	private $accessTokenMapper;

	/** @var RefreshTokenMapper */
	private $refreshTokenMapper;

	/**
	 * OAuthApiController constructor.
	 *
	 * @param string $appName
	 * @param IRequest $request
	 * @param ClientMapper $clientMapper
	 * @param AuthorizationCodeMapper $authorizationCodeMapper
	 * @param AccessTokenMapper $accessTokenMapper
	 * @param RefreshTokenMapper $refreshTokenMapper
	 */
	public function __construct($appName, IRequest $request, ClientMapper $clientMapper, AuthorizationCodeMapper $authorizationCodeMapper, AccessTokenMapper $accessTokenMapper, RefreshTokenMapper $refreshTokenMapper) {
		parent::__construct($appName, $request);
		$this->clientMapper = $clientMapper;
		$this->authorizationCodeMapper = $authorizationCodeMapper;
		$this->accessTokenMapper = $accessTokenMapper;
		$this->refreshTokenMapper = $refreshTokenMapper;
	}

	/**
	 * Implements the OAuth 2.0 Access Token Response.
	 *
	 * @param string $grant_type The authorization grant type.
	 * @param string $code The authorization code.
	 * @param string $redirect_uri The redirect URI.
	 * @param string $refresh_token The refresh token.
	 * @return JSONResponse The Access Token or an empty JSON Object.
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function generateToken($grant_type, $code = null, $redirect_uri = null, $refresh_token = null) {
		if (!is_string($grant_type)) {
			return new JSONResponse(['error' => 'invalid_request'], Http::STATUS_BAD_REQUEST);
		}

		if (is_null($_SERVER['PHP_AUTH_USER']) || is_null($_SERVER['PHP_AUTH_PW'])) {
			return new JSONResponse(['error' => 'invalid_request'], Http::STATUS_BAD_REQUEST);
		}

		try {
			/** @var Client $client */
			$client = $this->clientMapper->findByIdentifier($_SERVER['PHP_AUTH_USER']);
		} catch (DoesNotExistException $exception) {
			return new JSONResponse(['error' => 'invalid_client'], Http::STATUS_BAD_REQUEST);
		}

		if (strcmp($client->getSecret(), $_SERVER['PHP_AUTH_PW']) !== 0) {
			return new JSONResponse(['error' => 'invalid_client'], Http::STATUS_BAD_REQUEST);
		}

		switch ($grant_type) {
			case 'authorization_code':
				if (!is_string($code) || !is_string($redirect_uri)) {
					return new JSONResponse(['error' => 'invalid_request'], Http::STATUS_BAD_REQUEST);
				}

				try {
					/** @var AuthorizationCode $authorizationCode */
					$authorizationCode = $this->authorizationCodeMapper->findByCode($code);
				} catch (DoesNotExistException $exception) {
					return new JSONResponse(['error' => 'invalid_grant'], Http::STATUS_BAD_REQUEST);
				}

				if (strcmp($authorizationCode->getClientId(), $client->getId()) !== 0) {
					return new JSONResponse(['error' => 'invalid_grant'], Http::STATUS_BAD_REQUEST);
				}

				if ($authorizationCode->hasExpired()) {
					return new JSONResponse(['error' => 'invalid_grant'], Http::STATUS_BAD_REQUEST);
				}

				if (!Utilities::validateRedirectUri($client->getRedirectUri(), urldecode($redirect_uri), $client->getAllowSubdomains())) {
					return new JSONResponse(['error' => 'invalid_grant'], Http::STATUS_BAD_REQUEST);
				}

				$userId = $authorizationCode->getUserId();
				break;
			case 'refresh_token':
				if (!is_string($refresh_token)) {
					return new JSONResponse(['error' => 'invalid_request'], Http::STATUS_BAD_REQUEST);
				}

				try {
					/** @var RefreshToken $refreshToken */
					$refreshToken = $this->refreshTokenMapper->findByToken($refresh_token);
				} catch (DoesNotExistException $exception) {
					return new JSONResponse(['error' => 'invalid_grant'], Http::STATUS_BAD_REQUEST);
				}

				if (strcmp($refreshToken->getClientId(), $client->getId()) !== 0) {
					return new JSONResponse(['error' => 'invalid_grant'], Http::STATUS_BAD_REQUEST);
				}

				$userId = $refreshToken->getUserId();
				break;
			default:
				return new JSONResponse(['error' => 'invalid_grant'], Http::STATUS_BAD_REQUEST);
		}

		$this->authorizationCodeMapper->deleteByClientUser($client->getId(), $userId);
		$this->accessTokenMapper->deleteByClientUser($client->getId(), $userId);
		$this->refreshTokenMapper->deleteByClientUser($client->getId(), $userId);

		$token = Utilities::generateRandom();
		$accessToken = new AccessToken();
		$accessToken->setToken($token);
		$accessToken->setClientId($client->getId());
		$accessToken->setUserId($userId);
		$accessToken->resetExpires();
		$this->accessTokenMapper->insert($accessToken);

		$token = Utilities::generateRandom();
		$refreshToken = new RefreshToken();
		$refreshToken->setToken($token);
		$refreshToken->setClientId($client->getId());
		$refreshToken->setUserId($userId);
		$this->refreshTokenMapper->insert($refreshToken);

                $app = new Application();

		$userManager = $app->getContainer()->query('OCP\IUserManager');
		$user = $userManager->get($accessToken->getUserId());



		return new JSONResponse(
			[
				'access_token' => $accessToken->getToken(),
				'token_type' => 'Bearer',
				'expires_in' => 3600,
				'refresh_token' => $refreshToken->getToken(),
				'user_id' => $userId,
                                'user_email' => $user->getEMailAddress()
			]
		);
	}

}
