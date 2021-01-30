<?php

namespace Dapr\Mocks;

use Dapr\DaprClient;
use Dapr\DaprResponse;
use PHPUnit\Framework\Assert;

class TestClient extends DaprClient
{
    public array $responses = [];

    public function get(string $url): DaprResponse
    {
        $url = "GET $url";
        $this->validate($url, '');
        $next = array_shift($this->responses[$url]);
        if ($next === null) {
            $this->not_registered($url, '');
        }

        return $next;
    }

    private function validate(string $url, string $body)
    {
        if ( ! isset($this->responses[$url])) {
            $this->not_registered($url, $body);
        }
    }

    /**
     * @param $url
     * @param $body
     *
     * @never-returns
     */
    private function not_registered($url, $body)
    {
        [$method, $url] = explode(' ', $url);
        throw new \Exception(
            'unregistered '.$method.' performed on '.$url."\n$body\n\nExpected:\n".json_encode(
                $this->responses["$method $url"] ?? null,
                JSON_PRETTY_PRINT
            )
        );
    }

    public function register_get(string $path, int $code, mixed $data)
    {
        $response                       = new DaprResponse();
        $response->code                 = $code;
        $response->data                 = $data;
        $this->responses["GET $path"][] = $response;
    }

    public function post(string $url, mixed $data): DaprResponse
    {
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
    ) {
        $response                        = new DaprResponse();
        $response->code                  = $code;
        $response->data                  = $response_data;
        $this->responses["POST $path"][] = [
            'request'  => $expected_request,
            'response' => $response,
            'cleaner'  => $cleaner,
        ];
    }

    public function get_api_path(string $path, ?array $params = null): string
    {
        $params = empty($params) ? '' : '?'.http_build_query($params);

        return $path.$params;
    }

    public function delete(string $url): DaprResponse
    {
        $url = "DELETE $url";
        $this->validate($url, '');
        $next = array_shift($this->responses[$url]);
        if ($next === null) {
            $this->not_registered($url, '');
        }

        return $next;
    }

    public function register_delete(string $path, $expected_code)
    {
        $response                          = new DaprResponse();
        $response->code                    = $expected_code;
        $this->responses["DELETE $path"][] = $response;
    }
}
