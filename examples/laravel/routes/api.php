<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/invoke-example/{name}', function(string $name, Request $request, \Dapr\State\StateManager $stateManager, \Psr\Log\LoggerInterface $logger) {
    $state = new \App\Models\ExampleState();
    $stateManager->load_object($state);
    $state->last_name_seen = $name;
    $logger->critical('Setting last seen name to {name}', ['name' => $name]);
    $stateManager->save_object($state);
    return ['setName' => $name];
});
