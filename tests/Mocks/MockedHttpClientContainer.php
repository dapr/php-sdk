<?php

namespace Dapr\Mocks;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;

/**
 * Class MockedHttpClientContainer
 * @package Dapr\Mocks
 */
class MockedHttpClientContainer
{
    public array $history = [];
    public HandlerStack $handlerStack;
    public MockHandler $mock;
    public Client $client;
}
