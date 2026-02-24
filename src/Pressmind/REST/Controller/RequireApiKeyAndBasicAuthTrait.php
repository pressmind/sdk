<?php

namespace Pressmind\REST\Controller;

use Pressmind\Registry;

/**
 * Trait for REST controllers that require both API key and Basic Auth.
 * Use for command and redis endpoints; other REST endpoints are unchanged.
 */
trait RequireApiKeyAndBasicAuthTrait
{
    /**
     * Require rest.server.api_key and rest.server.api_user/api_password to be configured
     * and the request to provide valid API key and Basic Auth credentials.
     *
     * @param array $parameters Request parameters (may contain api_key)
     * @throws \Exception When API key or Basic Auth is not configured or invalid
     */
    private function requireApiKeyAndBasicAuth(array $parameters)
    {
        $config = Registry::getInstance()->get('config');
        $server = $config['rest']['server'] ?? [];

        $serverApiKey = $server['api_key'] ?? '';
        if ($serverApiKey === '' || !is_string($serverApiKey)) {
            throw new \Exception('These endpoints require API key. Configure rest.server.api_key (e.g. PM_REST_SERVER_API_KEY).');
        }
        $providedKey = $this->extractApiKeyFromRequest($parameters);
        if ($providedKey === '' || !hash_equals($serverApiKey, $providedKey)) {
            throw new \Exception('These endpoints require API key. Send it in the X-Api-Key header (recommended) or Authorization: Bearer <key>.');
        }

        $apiUser = $server['api_user'] ?? '';
        $apiPassword = $server['api_password'] ?? '';
        if ($apiUser === '' || $apiPassword === '') {
            throw new \Exception('These endpoints require Basic Auth. Configure rest.server.api_user and rest.server.api_password.');
        }
        $basicAuth = $this->getParsedBasicAuthFromRequest();
        if ($basicAuth === false || $basicAuth[0] !== $apiUser || $basicAuth[1] !== $apiPassword) {
            throw new \Exception('These endpoints require Basic Auth. Send Authorization: Basic with valid api_user and api_password.');
        }
    }

    /**
     * Extract API key from X-Api-Key header (recommended), Bearer header, or api_key query/body (fallback).
     *
     * @param array $parameters Request parameters (GET/POST)
     * @return string
     */
    private function extractApiKeyFromRequest(array $parameters)
    {
        $xApiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
        if ($xApiKey !== '' && is_string($xApiKey)) {
            return $xApiKey;
        }
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (stripos($header, 'Bearer ') === 0) {
            return substr($header, 7);
        }
        return isset($parameters['api_key']) && is_string($parameters['api_key']) ? $parameters['api_key'] : '';
    }

    /**
     * Parse Basic Auth from request (Authorization header).
     *
     * @return array{0: string, 1: string}|false [username, password] or false
     */
    private function getParsedBasicAuthFromRequest()
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if ($header === '' || strpos($header, 'Basic ') !== 0) {
            return false;
        }
        $decoded = base64_decode(substr($header, 6), true);
        if ($decoded === false) {
            return false;
        }
        $parts = explode(':', $decoded, 2);
        if (count($parts) !== 2) {
            return false;
        }
        return [$parts[0], $parts[1]];
    }
}
