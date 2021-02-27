<?php

use Dapr\App;
use Dapr\Client\V1\GrpcClient;
use Dapr\Client\V1\RestClient;
use Dapr\Proto\Common\V1\StateItem;
use Dapr\Proto\Runtime\V1\GetStateRequest;
use Dapr\Proto\Runtime\V1\GetStateResponse;
use Dapr\Proto\Runtime\V1\SaveStateRequest;
use DI\ContainerBuilder;

require_once __DIR__ .'/vendor/autoload.php';

$test_results = [];
define('STORE', 'statestore');

/**
 * @param $expected
 * @param $actual
 * @param string $message
 */
function assert_equals($expected, $actual, string $message): void
{
    global $test_results;
    if ($expected == $actual) {
        $test_results[$message] = '✔';
    } else {
        $test_results[$message] = '❌';
    }
}

$app = App::create(configure: fn(ContainerBuilder $builder) => $builder->addDefinitions(__DIR__.'/config.php'));
$app->get(
    '/test/state',
    function (GrpcClient $grpcClient, RestClient $restClient) {
        global $test_results;
        /*[$result, $status] = $grpcClient->SaveState(
            (new SaveStateRequest())->setStoreName(STORE)->setStates(
                [(new StateItem())->setKey('test')->setValue('test')]
            )
        );
        assert_equals(\Grpc\STATUS_OK, $status->ok, 'status should be ok');
        [$result, $status] = $grpcClient->GetState((new GetStateRequest())->setStoreName(STORE)->setKey('test'));
        assert_equals(true, $result instanceof GetStateResponse, 'Expect a state response');
        if($result instanceof GetStateResponse) {
            assert_equals('test', $result->getData(), 'Message should match');
        }*/

        return $test_results;
    }
);
$app->start();
