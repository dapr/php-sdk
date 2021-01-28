<?php

namespace Dapr;

use PHPUnit\Framework\Assert;

class DaprClient
{
    public static array $responses = [];
    public static array $extra_headers = [];

    public static function get_api(string $path, ?array $params = null): string
    {
        $params = $params ? http_build_query($params) : '';
        $params = $params ? '?'.$params : '';

        return DaprClient::get_api_base().$path.$params;
    }

    public static function get_api_base(): string
    {
        return '';
    }

    public static function get(string $url): DaprResponse
    {
        self::validate('GET', $url, '');
        $next = array_shift(self::$responses['GET'][$url]);
        if ($next === null) {
            self::report_unregistered('GET', $url, '');
        }
        self::clean('GET', $url);

        return $next;
    }

    private static function validate(string $method, string $url, string $body)
    {
        if ( ! isset(self::$responses[$method])) {
            self::report_unregistered($method, $url, $body);
        }
        if ( ! isset(self::$responses[$method][$url])) {
            self::report_unregistered($method, $url, $body);
        }
    }

    private static function report_unregistered(string $method, string $url, string $body)
    {
        throw new \Exception('unregistered '.$method.' performed on '.$url."\n$body\n\nExpected:\n".json_encode(self::$responses[$method][0]??null, JSON_PRETTY_PRINT));
    }

    private static function clean(string $method, string $url)
    {
        if (empty(self::$responses[$method][$url])) {
            unset(self::$responses[$method][$url]);
        }
        if (empty(self::$responses[$method])) {
            unset(self::$responses[$method]);
        }
    }

    public static function register_get(string $path, int $code, mixed $data)
    {
        $response                        = new DaprResponse();
        $response->code                  = $code;
        $response->data                  = $data;
        self::$responses['GET'][$path][] = $response;
    }

    public static function post(string $url, mixed $data): DaprResponse
    {
        self::validate('POST', $url, json_encode($data, JSON_PRETTY_PRINT));
        $next = array_shift(self::$responses['POST'][$url]);
        if ($next === null) {
            self::report_unregistered('POST', $url, json_encode($data, JSON_PRETTY_PRINT));
        }
        self::clean('POST', $url);
        if (is_callable($next['cleaner'])) {
            $data = $next['cleaner']($data);
        }
        Assert::assertSame($next['request'], $data);

        return $next['response'];
    }

    public static function register_post(
        string $path,
        int $code,
        mixed $response_data,
        mixed $expected_request,
        ?callable $cleaner = null
    ) {
        $response                         = new DaprResponse();
        $response->code                   = $code;
        $response->data                   = $response_data;
        self::$responses['POST'][$path][] = [
            'request'  => $expected_request,
            'response' => $response,
            'cleaner'  => $cleaner,
        ];
    }

    public static function delete(string $url): DaprResponse
    {
        self::validate('DELETE', $url, '');
        $next = array_shift(self::$responses['DELETE'][$url]);
        if ($next === null) {
            self::report_unregistered('DELETE', $url, '');
        }
        self::clean('DELETE', $url);

        return $next;
    }

    public static function register_delete(string $path, $expected_code)
    {
        $response                           = new DaprResponse();
        $response->code                     = $expected_code;
        self::$responses['DELETE'][$path][] = $response;
    }
}
