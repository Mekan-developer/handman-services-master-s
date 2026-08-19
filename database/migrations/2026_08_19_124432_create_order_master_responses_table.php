<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_master_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('master_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->string('rejection_reason', 500)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            // A master responds to a given order at most once.
            $table->unique(['order_id', 'master_id']);
            // The client's response list and the every-tick sweeps both filter by this pair.
            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_master_responses');
    }
};
