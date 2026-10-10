<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    /** Rules/terms of the mobile app, rendered as HTML before registration. */
    public const CLIENT_APP_RULES = 'client_app_rules';

    /** Radius the master auto-search starts at, and the step it grows by each minute. */
    public const MASTER_SEARCH_INITIAL_RADIUS_KM = 'master_search_initial_radius_km';

    /** Once the growing radius passes this, the search gives up and admins take over. */
    public const MASTER_SEARCH_MAX_RADIUS_KM = 'master_search_max_radius_km';

    public const DEFAULT_SEARCH_INITIAL_RADIUS_KM = 20;

    public const DEFAULT_SEARCH_MAX_RADIUS_KM = 80;

    /** Pending orders no master got approved for are auto-cancelled after this many hours. */
    public const ORDER_AUTO_CANCEL_HOURS = 'order_auto_cancel_hours';

    public const DEFAULT_ORDER_AUTO_CANCEL_HOURS = 48;

    /** How long after declining an order the master may still take the decline back. */
    public const ORDER_DECLINE_RESTORE_MINUTES = 'order_decline_restore_minutes';

    public const DEFAULT_ORDER_DECLINE_RESTORE_MINUTES = 60;

    protected $fillable = ['key', 'value'];
}
