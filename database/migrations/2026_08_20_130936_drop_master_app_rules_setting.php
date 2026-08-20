<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The two apps share one set of rules now — the master-side copy is gone
     * from the admin panel and from the API, so its row is dead weight.
     */
    public function up(): void
    {
        DB::table('settings')->where('key', 'master_app_rules')->delete();
    }

    public function down(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key' => 'master_app_rules',
            'value' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
