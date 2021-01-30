<?php

use Dapr\Binding;

class BindingTest extends DaprTests
{
    public function testBindingRegistration()
    {
        Binding::register_input_binding(
            'binding',
            function () {
                return ['ok' => 'true'];
            }
        );
        $result = \Dapr\Runtime::get_handler_for_route('OPTIONS', '/binding')();
        $this->assertSame(200, $result['code']);

        $result = \Dapr\Runtime::get_handler_for_route('GET', '/binding')();
        $this->assertSame(200, $result['code']);
        $this->assertSame(['ok' => 'true'], $this->deserialize($result['body']));
    }

    public function testOutputBinding()
    {
        $this->get_client()->register_post(
            '/bindings/name',
            200,
            [],
            [
                'data'      => ['ok'],
                'metadata'  => [],
                'operation' => 'operation',
            ],
            function ($obj) {
                $obj['data']     = (array)$obj['data'];
                $obj['metadata'] = (array)$obj['metadata'];

                return $obj;
            }
        );
        Binding::invoke_output('name', 'operation', data: ['ok']);
    }
}
