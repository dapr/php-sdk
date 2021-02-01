<?php

namespace Dapr;

use Dapr\Deserialization\IDeserializer;
use Dapr\exceptions\DaprException;
use JetBrains\PhpStorm\Pure;
use Psr\Log\LoggerInterface;

/**
 * Helper functions for accessing the dapr api.
 * @package Dapr
 */
class DaprClient
{
    private static array $trace;
    private static bool $added_trace = false;
    // temp hack to allow custom headers
    public array $extra_headers = [];
    private static DaprClient $client;

    public static function get_client() {
        return self::$client;
    }

    public function __construct(protected LoggerInterface $logger, protected IDeserializer $deserializer)
    {
        self::$client = $this;
    }

    /**
     * Composes an URI for accessing the API.
     *
     * @param string $path The path to access.
     * @param array|null $params Query params for the API call.
     *
     * @return string The API URI
     */
    protected function get_api_path(string $path, ?array $params = null): string
    {
        $params = $params ? http_build_query($params) : '';
        $params = $params ? '?'.$params : '';

        return DaprClient::get_api_base().$path.$params;
    }

    /**
     * Get the API base uri
     *
     * @return string The base uri
     */
    protected function get_api_base(): string
    {
        $port = getenv('DAPR_HTTP_PORT') ?: 3500;

        return "http://localhost:$port/v1.0";
    }

    /**
     * Get a uri.
     *
     * @param string $url The URL to get.
     * @param array|null $params
     *
     * @return DaprResponse The parsed response.
     * @throws DaprException
     */
    public function get(string $url, ?array $params = null): DaprResponse
    {
        $url = $this->get_api_path($url, $params);
        $this->logger->debug('Calling GET {url}', ['url' => $url]);
        $curl = curl_init($url);
        curl_setopt_array(
            $curl,
            [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => self::get_headers(),
                CURLINFO_HEADER_OUT    => true,
            ]
        );
        $result       = curl_exec($curl);
        $return       = new DaprResponse();
        $return->data = json_decode($result, true);
        $return->code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $return->etag = array_reduce(
            explode("\r\n", curl_getinfo($curl, CURLINFO_HEADER_OUT)),
            fn($carry, $item) => str_starts_with($item, 'etag:') ? str_replace('etag: ', '', $item) : $carry
        );
        self::detect_trace_from_response($curl);

        $this->logger->debug('Got response: {response}', ['response' => $return]);

        if ($this->deserializer->is_exception($return->data)) {
            throw $this->deserializer->get_exception($return->data);
        }

        return $return;
    }

    private function get_headers(): array
    {
        return array_merge(["Accept: application/json"], self::detect_trace(), $this->extra_headers);
    }

    private function detect_trace(): array
    {
        if (isset(self::$trace)) {
            return self::$trace;
        }

        $existing_headers = getallheaders();
        self::$trace      = isset($existing_headers['Traceparent']) ? ['Traceparent: '.$existing_headers['Traceparent']] : [];

        if ( ! self::$added_trace && isset($existing_headers['Traceparent'])) {
            header('Traceparent: '.$existing_headers['Traceparent']);
            self::$added_trace = true;
        }

        return self::$trace;
    }

    /**
     * @param \CurlHandle|false $curl
     */
    private function detect_trace_from_response(mixed $curl): void
    {
        if (isset(self::$trace)) {
            return;
        }

        if ($curl === false) {
            return;
        }

        $header = curl_getinfo($curl, CURLINFO_HEADER_OUT);
        $header = array_filter(
            explode("\r\n", $header),
            function ($ii) {
                return str_starts_with($ii, 'Traceparent:');
            }
        );
        if ( ! empty($header)) {
            self::$trace = $header;
            if ( ! self::$added_trace) {
                header('Traceparent: '.$header[0]);
                self::$added_trace = true;
            }
        }
    }

    /**
     * Post a uri.
     *
     * @param string $url The url to post to.
     * @param array $data The data to post as a JSON document.
     *
     * @return DaprResponse The parsed response.
     * @throws DaprException
     */
    public function post(string $url, mixed $data, ?array $params = null): DaprResponse
    {
        $url = $this->get_api_path($url, $params);
        $this->logger->debug('Calling POST {url} with data: {data}', ['url' => $url, 'data' => $data]);
        $curl = curl_init($url);
        curl_setopt_array(
            $curl,
            [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_HTTPHEADER     => self::as_json(self::get_headers()),
                CURLOPT_POSTFIELDS     => json_encode($data),
                CURLINFO_HEADER_OUT    => true,
            ]
        );
        $response       = new DaprResponse();
        $response->data = curl_exec($curl);
        $response->data = json_decode($response->data, true);
        $response->code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        self::detect_trace_from_response($curl);
        $this->logger->debug('Got response: {r}', ['r' => $response]);

        if ($this->deserializer->is_exception($response->data)) {
            throw $this->deserializer->get_exception($response->data);
        }

        return $response;
    }

    #[Pure] private function as_json(array $headers): array
    {
        return array_merge($headers, ["Content-type: application/json"], $this->extra_headers);
    }

    /**
     * Delete a uri
     *
     * @param string $url The url to delete
     *
     * @return DaprResponse The response
     * @throws DaprException
     */
    public function delete(string $url, ?array $params = []): DaprResponse
    {
        $url = $this->get_api_path($url, $params);
        $this->logger->debug('Calling DELETE {url}', ['url' => $url]);
        $curl = curl_init($url);
        curl_setopt_array(
            $curl,
            [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => 'DELETE',
                CURLOPT_HTTPHEADER     => self::as_json(self::get_headers()),
                CURLINFO_HEADER_OUT    => true,
            ]
        );
        $response       = new DaprResponse();
        $response->data = json_decode(curl_exec($curl), true);
        $response->code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        self::detect_trace_from_response($curl);

        $this->logger->debug('Got response: {r}', ['r' => $response]);

        if ($this->deserializer->is_exception($response->data)) {
            throw $this->deserializer->get_exception($response->data);
        }

        return $response;
    }
}
