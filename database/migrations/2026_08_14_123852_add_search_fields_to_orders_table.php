<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('search_started_at')->nullable()->after('assigned_at');
            $table->unsignedSmallInteger('search_radius_km')->nullable()->after('search_started_at');
            $table->timestamp('search_expired_at')->nullable()->after('search_radius_km');

            // Drives the every-minute scheduler sweep: pending, unclaimed, still searching.
            $table->index(['status', 'search_expired_at'], 'orders_search_sweep_index');
            // Narrows the bounding-box scan for the master's available-orders feed.
            $table->index('client_lat', 'orders_client_lat_index');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex('orders_search_sweep_index');
            $table->dropIndex('orders_client_lat_index');
            $table->dropColumn(['search_started_at', 'search_radius_km', 'search_expired_at']);
        });
    }
};
