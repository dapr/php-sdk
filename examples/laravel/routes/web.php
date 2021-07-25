<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get(
    '/',
    function (\Dapr\State\StateManager $stateManager) {
        $state = new \App\Models\ExampleState();
        $stateManager->load_object($state);
        $state->page_views += 1;
        $stateManager->save_object($state);

        return view('welcome', ['page_views' => $state->page_views, 'last_name_seen' => $state->last_name_seen]);
    }
);

Route::get(
    '/welcome/{name}',
    function (string $name, \Dapr\Client\DaprClient $client) {
        // todo: no security
        return $client->invokeMethod('GET', new \Dapr\Client\AppId('api'), "api/invoke-example/$name")
            ->getBody()
            ->getContents();
    }
);
