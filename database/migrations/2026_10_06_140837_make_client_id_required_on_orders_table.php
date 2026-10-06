<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every order belongs to a client — the admin form either picks one or creates
 * one by phone, and the mobile app always has the signed-in client. The column
 * was left nullable from the days before clients existed.
 *
 * Refuses to run while ownerless orders remain instead of silently deleting
 * them: they have to be attached to a client (or removed) by hand first.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ownerless = DB::table('orders')->whereNull('client_id')->count();

        if ($ownerless > 0) {
            throw new RuntimeException(
                "orders.client_id: {$ownerless} order(s) have no client. Attach them to a client before running this migration."
            );
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->nullable()->change();
        });
    }
};
