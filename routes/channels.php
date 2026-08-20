<?php

use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/*
 * Live master positions for the admin map, scoped by city id. Private: a GPS
 * trail is personal data, and the city id is trivially guessable, so a public
 * channel would hand anyone the real-time whereabouts of every master in town.
 * Gated on the same roles that can open the map and order pages.
 */
Broadcast::channel('masters-map.{cityId}', function ($user) {
    return $user instanceof User && $user->role->canAccessAdminSections();
});

/*
 * Public channel signalling the master apps that the pool of claimable orders
 * changed (order.created / order.search.radius.expanded). Payloads carry no
 * client data — the app reloads GET /api/v1/master/orders/available, which
 * filters by the master's own position, categories and the order's radius.
 */
Broadcast::channel('available-orders', function () {
    return true;
});

/*
 * Private channel carrying OTP codes parked for manual delivery. Codes are
 * secrets — only staff who can open the section may subscribe.
 */
Broadcast::channel('admin.pending-otps', function ($user) {
    return $user instanceof User && $user->role->canAccessAdminSections();
});

/*
 * Private channel for a specific client — used by the mobile client app to receive:
 * master.assigned and order.status.changed events scoped to their orders.
 * Auth: Sanctum token issued to the Client model.
 */
Broadcast::channel('client.{clientId}', function ($user, $clientId) {
    return $user instanceof Client && (int) $user->id === (int) $clientId;
});

/*
 * Private channel for a specific master — used by the master side of the mobile
 * app to receive master.assigned (new job) and order.status.changed events.
 *
 * Auth: the client's Sanctum token. A master profile hangs off a client account,
 * so both this channel and `client.{id}` are reachable over the same connection.
 */
Broadcast::channel('master.{masterId}', function ($user, $masterId) {
    return $user instanceof Client
        && $user->master !== null
        && (int) $user->master->id === (int) $masterId;
});
