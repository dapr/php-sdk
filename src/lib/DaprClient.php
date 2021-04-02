<?php

namespace Dapr;

use Dapr\Deserialization\IDeserializer;
use Dapr\exceptions\DaprException;
use Dapr\Middleware\Defaults\Tracing;
use JetBrains\PhpStorm\Pure;
use Psr\Log\LoggerInterface;

/**
 * Helper functions for accessing the dapr api.
 * @package Dapr
 */
class DaprClient
{
    // temp hack to allow custom headers
    private static DaprClient $client;
    public array $extra_headers = [];

    public function __construct(
        protected LoggerInterface $logger,
        protected IDeserializer $deserializer,
        protected Tracing $trace,
        protected string $port
    ) {
        self::$client = $this;
    }

    public static function get_client(): DaprClient
    {
        return self::$client;
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
        $result          = curl_exec($curl);
        $return          = new DaprResponse();
        $return->data    = json_decode($result, true);
        $return->code    = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $return->headers = explode("\r\n", curl_getinfo($curl, CURLINFO_HEADER_OUT));
        $return->etag    = array_reduce(
            $return->headers,
            fn($carry, $item) => str_starts_with($item, 'etag:') ? str_replace('etag: ', '', $item) : $carry
        );

        $this->logger->debug('Got response: {response}', ['response' => $return]);

        if ($this->deserializer->is_exception($return->data)) {
            /**
             * @var DaprException $ex
             */
            $ex = $this->deserializer->get_exception($return->data);
            throw $ex;
        }

        return $return;
    }

    /**
     * Composes an URI for accessing the API.
     *
     * @param string $path The path to access.
     * @param array|null $params Query params for the API call.
     *
     * @return string The API URI
     */
    #[Pure] protected function get_api_path(string $path, ?array $params = null): string
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
    #[Pure] protected function get_api_base(): string
    {
        return "http://localhost:{$this->port}/v1.0";
    }

    private function get_headers(): array
    {
        $trace = $this->trace ? [
            'tracestate: '.$this->trace->trace_state,
            'traceparent: '.$this->trace->trace_parent,
        ] : [];

        return array_merge(["Accept: application/json"], $trace, $this->extra_headers);
    }

    /**
     * Post a uri.
     *
     * @param string $url The url to post to.
     * @param array $data The data to post as a JSON document.
     * @param array|null $params
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
        $response          = new DaprResponse();
        $response->data    = curl_exec($curl);
        $response->data    = json_decode($response->data, true);
        $response->code    = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $response->headers = explode("\r\n", curl_getinfo($curl, CURLINFO_HEADER_OUT));
        $this->logger->debug('Got response: {r}', ['r' => $response]);

        if ($this->deserializer->is_exception($response->data)) {
            /**
             * @var DaprException $ex
             */
            $ex = $this->deserializer->get_exception($response->data);
            throw $ex;
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
     * @param array|null $params
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
        $response          = new DaprResponse();
        $response->data    = json_decode(curl_exec($curl), true);
        $response->code    = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $response->headers = explode("\r\n", curl_getinfo($curl, CURLINFO_HEADER_OUT));

        $this->logger->debug('Got response: {r}', ['r' => $response]);

        if ($this->deserializer->is_exception($response->data)) {
            /**
             * @var DaprException $ex
             */
            $ex = $this->deserializer->get_exception($response->data);
            throw $ex;
        }

        return $response;
    }

    /**
     * Shutdown the sidecar.
     *
     * @return void
     * @param array $metadata Metadata to pass to the shutdown endpoint
     *
     * @throws DaprException
     */
    public function shutdown(array $metadata = []): void
    {
        $this->post("/shutdown", $metadata);
    }

    /**
     * Shutdown the sidecar at the end of the current request.
     *
     * @param array $metadata Metadata to pass to the shutdown endpoint
     */
    public function schedule_shutdown(array $metadata): void {
        register_shutdown_function(fn() => $this->shutdown($metadata));
    }
}
