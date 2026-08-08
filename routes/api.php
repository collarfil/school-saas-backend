<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Broadcast;
use App\Events\TestBroadcast;

/*
|--------------------------------------------------------------------------
| Application Core & Health Routes
|--------------------------------------------------------------------------
*/

Route::get('/test-broadcast', function () {
    broadcast(new TestBroadcast("Hello Reverb"));
    return "Event Fired";
});

// Realtime / WebSocket Channel Authorizations
Broadcast::channel('conversation.{id}', function ($user, $id) {
    return \App\Models\ConversationParticipant::where('conversation_id', $id)
        ->where('user_id', $user->id)
        ->exists();
});

Route::post('/broadcasting/auth', function (Illuminate\Http\Request $request) {
    return Broadcast::auth($request);
})->middleware('jwt.auth');


/*
|--------------------------------------------------------------------------
| API Routes - Dynamic Module Loading
|--------------------------------------------------------------------------
| Automatically discovers and loads all module route files inside app/Modules
| to prevent fatal missing-file errors caused by casing or missing modules.
*/

$moduleRoutes = array_unique(array_merge(
    glob(base_path('app/Modules/*/Routes/*.php')),
    glob(base_path('app/Modules/*/routes/*.php'))
));

foreach ($moduleRoutes as $routeFile) {
    require_once $routeFile;
}