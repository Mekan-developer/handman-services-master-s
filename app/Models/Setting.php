<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    /** Radius the master auto-search starts at, and the step it grows by each minute. */
    public const MASTER_SEARCH_INITIAL_RADIUS_KM = 'master_search_initial_radius_km';

    /** Once the growing radius passes this, the search gives up and admins take over. */
    public const MASTER_SEARCH_MAX_RADIUS_KM = 'master_search_max_radius_km';

    public const DEFAULT_SEARCH_INITIAL_RADIUS_KM = 20;

    public const DEFAULT_SEARCH_MAX_RADIUS_KM = 80;

    protected $fillable = ['key', 'value'];
}
