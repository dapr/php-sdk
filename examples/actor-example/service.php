<?php

require_once __DIR__."/vendor/autoload.php";

error_reporting(E_ALL);
ini_set('display_errors', 0);
set_error_handler(
    function ($err_no, $err_str, $err_file, $err_line, $err_context = null) {
        http_response_code(500);
        echo json_encode(
            [
                'errorCode' => 'Exception',
                'message'   => $err_str,
                'file'      => $err_file,
                'line'      => $err_line,
            ]
        );
    }
);

\Dapr\Actors\ActorRuntime::set_scan_interval(new DateInterval('PT10S'));
\Dapr\Actors\ActorRuntime::set_idle_timeout(new DateInterval('PT5S'));
\Dapr\Actors\ActorRuntime::do_drain_actors(false);

\Dapr\Actors\ActorRuntime::register_actor(\Actor\Counter::class);

header('Content-Type: application/json');
$result = \Dapr\Runtime::get_handler_for_route($_SERVER['REQUEST_METHOD'], strtok($_SERVER['REQUEST_URI'], '?'))();
http_response_code($result['code']);
if (isset($result['body'])) {
    echo $result['body'];
}
