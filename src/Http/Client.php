<?php

namespace BackblazeB2\Http;

use BackblazeB2\ErrorHandler;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

/**
 * Client wrapper around Guzzle.
 */
class Client extends GuzzleClient
{
    protected static $MAX_RETRY = 5;

    /**
     * Sends a response to the B2 API, automatically handling decoding JSON and errors.
     *
     * @param string $method
     * @param null   $uri
     * @param array  $options
     * @param bool   $asJson
     *
     * @throws GuzzleException
     *
     * @return mixed|ResponseInterface|string
     */
    public function request($method, $uri = null, array $options = [], $asJson = true)
    {
        // Retry 500 and 503 responses
        // See https://www.backblaze.com/blog/b2-503-500-server-error/
        // inspired by https://github.com/GiantCowFilms/backblaze-b2/commit/0ea7786ec8b3a9047ae91c51d548a563f06012f2
        $retries = 0;
        do {
            $response = parent::request($method, $uri, $options);
            if ($retries > 1) {
                sleep(1);
            }
            $retries++;
            $code = $response->getStatusCode();
            $shouldRetry = $code == 500 || $code == 503;
        } while ($shouldRetry && $retries < self::$MAX_RETRY);

        if ($response->getStatusCode() !== 200) {
            ErrorHandler::handleErrorResponse($response);
        }

        if ($asJson) {
            return json_decode($response->getBody(), true);
        }

        return $response->getBody()->getContents();
    }
}
