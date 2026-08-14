<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_master_declines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('master_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            // A master declines a given order at most once.
            $table->unique(['order_id', 'master_id']);
            // Filters the master's available-orders feed.
            $table->index('master_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_master_declines');
    }
};
