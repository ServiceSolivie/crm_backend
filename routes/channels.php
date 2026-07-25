<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast channel authorization callbacks
|--------------------------------------------------------------------------
|
| The actual /broadcasting/auth ROUTE (with our custom api/v1 prefix +
| auth:sanctum/active middleware) is registered via ->withBroadcasting(...)
| in bootstrap/app.php, not here — calling Broadcast::routes() again in
| this file would register a second, duplicate auth route.
|
| Private per-user channel: standard Laravel convention consumed by
| Echo.private('App.Models.User.'+id).notification(...) and by
| notification broadcasting automatically.
*/
Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return $user->id === $id;
});
