<?php

use App\Models\Lead;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Gate;

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

/*
| Lead channel: payment updates of a lead (PaymentSessionUpdated), for the
| users who can see its payments (same rule as the payments list).
*/
Broadcast::channel('leads.{lead}', function (User $user, Lead $lead) {
    return Gate::forUser($user)->allows('viewAny', [Payment::class, $lead]);
});
