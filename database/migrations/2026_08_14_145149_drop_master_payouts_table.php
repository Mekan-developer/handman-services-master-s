<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The payout ledger tracked money the platform owed masters. That direction of
 * payment no longer exists — masters pay the platform via subscriptions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('master_payouts');
    }

    public function down(): void
    {
        Schema::create('master_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_id')->nullable()->constrained()->nullOnDelete();
            $table->string('master_name');
            $table->decimal('amount', 10, 2);
            $table->string('payment_model', 30);
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }
};
