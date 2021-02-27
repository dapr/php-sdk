<?php

return [
    \Dapr\Client\V1\GrpcClient::class => \DI\autowire(),
    \Dapr\Client\V1\RestClient::class => \DI\autowire()
];
