<?php

require_once __DIR__ . '/DaprTests.php';

/**
 * Class MetadataTest
 */
class MetadataTest extends DaprTests
{
    public function testSuccessfulMetadata()
    {
        $stack = $this->get_http_client_stack(
            [
                new \GuzzleHttp\Psr7\Response(
                    200, body: json_encode(
                           [
                               'id' => 'demo-actor',
                               'actors' => [
                                   [
                                       'type' => 'DemoActor',
                                       'count' => 1
                                   ]
                               ],
                               'extended' => [
                                   'cliPID' => '12301823',
                                   'appCommand' => 'uvicorn --port 3000 demo_actor_service:app'
                               ],
                               'components' => [
                                   [
                                       'name' => 'pubsub',
                                       'type' => 'pubsub.redis',
                                       'version' => ''
                                   ]
                               ]
                           ]
                       )
                )
            ]
        );
        $client = $this->get_new_client_with_http($stack->client);
        $result = $client->getMetadata();
        $this->assertEquals(
            new \Dapr\Client\MetadataResponse(
                'demo-actor',
                [new \Dapr\Client\RegisteredActor('DemoActor', 1)],
                [
                    'cliPID' => '12301823',
                    'appCommand' => 'uvicorn --port 3000 demo_actor_service:app'
                ],
                [new \Dapr\Client\RegisteredComponent('pubsub', 'pubsub.redis', '')]
            ),
            $result
        );
    }
}
