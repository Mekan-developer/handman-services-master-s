<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting a client now takes their orders with them. `nullOnDelete` left
 * ownerless rows behind — an order nobody can open, nobody can review, and that
 * still counted in every report.
 *
 * `master_id` deliberately keeps `nullOnDelete`: an order belongs to the client
 * who placed it, so losing the assigned master must not destroy someone else's
 * order history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->foreign('client_id')->references('id')->on('clients')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
        });
    }
};
