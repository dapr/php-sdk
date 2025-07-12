<?php

namespace Dapr\Mocks;

use Dapr\DaprClient;
use Dapr\DaprResponse;
use Exception;
use JetBrains\PhpStorm\Pure;
use PHPUnit\Framework\Assert;

/**
 * Class TestClient
 *
 * An implementation of DaprClient that provides mocking abilities
 *
 * @package Dapr\Mocks
 * @codeCoverageIgnore not desired
 */
class TestClient extends DaprClient
{
    public array $responses = [];
    private bool $is_shutdown = false;

    /**
     * Asserts that the sidecar isn't shutdown
     */
    private function fail_if_shutdown(): void {
        Assert::assertFalse($this->is_shutdown, 'The sidecar was previously shutdown.');
    }

    /**
     * @param string $url
     * @param array|null $params
     *
     * @return DaprResponse
     * @throws Exception
     */
    public function get(string $url, ?array $params = null): DaprResponse
    {
        $this->fail_if_shutdown();
        $url = $this->get_api_path($url, $params);
        $url = "GET $url";
        $this->validate($url, '');
        $next = array_shift($this->responses[$url]);
        if ($next === null) {
            $this->not_registered($url, '');
        }

        return $next;
    }

    /**
     * @param string $path
     * @param array|null $params
     *
     * @return string
     */
    #[Pure] protected function get_api_path(string $path, ?array $params = null): string
    {
        $params = empty($params) ? '' : '?'.http_build_query($params);

        return $path.$params;
    }

    /**
     * @param string $url
     * @param string $body
     *
     * @throws Exception
     */
    private function validate(string $url, string $body): void
    {
        if ( ! isset($this->responses[$url])) {
            $this->not_registered($url, $body);
        }
    }

    /**
     * @param $url
     * @param false|string $body
     *
     * @never-returns
     *
     * @throws Exception
     */
    private function not_registered(string $url, string|false $body): void
    {
        [$method, $url] = explode(' ', $url);
        throw new Exception(
            'unregistered '.$method.' performed on '.$url."\n$body\n\nExpected:\n".json_encode(
                $this->responses["$method $url"] ?? null,
                JSON_PRETTY_PRINT
            )
        );
    }

    public function register_get(string $path, int $code, mixed $data): void
    {
        $response                       = new DaprResponse();
        $response->code                 = $code;
        $response->data                 = $data;
        $this->responses["GET $path"][] = $response;
    }

    /**
     * @param string $url
     * @param mixed $data
     * @param array|null $params
     *
     * @return DaprResponse
     * @throws Exception
     */
    public function post(string $url, mixed $data, ?array $params = null): DaprResponse
    {
        $this->fail_if_shutdown();
        $url = $this->get_api_path($url, $params);
        $url = "POST $url";
        $this->validate($url, json_encode($data, JSON_PRETTY_PRINT));
        $next = array_shift($this->responses[$url]);
        if ($next === null) {
            $this->not_registered($url, json_encode($data, JSON_PRETTY_PRINT));
        }
        if (is_callable($next['cleaner'])) {
            $data = $next['cleaner']($data);
        }
        Assert::AssertSame($next['request'], $data);

        return $next['response'];
    }

    public function register_post(
        string $path,
        int $code,
        mixed $response_data,
        mixed $expected_request,
        ?callable $cleaner = null
    ): void {
        $response                        = new DaprResponse();
        $response->code                  = $code;
        $response->data                  = $response_data;
        $this->responses["POST $path"][] = [
            'request'  => $expected_request,
            'response' => $response,
            'cleaner'  => $cleaner,
        ];
    }

    /**
     * @param string $url
     * @param array|null $params
     *
     * @return DaprResponse
     * @throws Exception
     */
    public function delete(string $url, ?array $params = null): DaprResponse
    {
        $this->fail_if_shutdown();
        $url = $this->get_api_path($url, $params);
        $url = "DELETE $url";
        $this->validate($url, '');
        $next = array_shift($this->responses[$url]);
        if ($next === null) {
            $this->not_registered($url, '');
        }

        return $next;
    }

    public function register_delete(string $path, $expected_code): void
    {
        $response                          = new DaprResponse();
        $response->code                    = $expected_code;
        $this->responses["DELETE $path"][] = $response;
    }

    public function shutdown(array $metadata = []): void
    {
        parent::shutdown($metadata);
        $this->is_shutdown = true;
    }

    public function schedule_shutdown(array $metadata): void
    {
        // noop because a unit test will never hit it
    }
}
