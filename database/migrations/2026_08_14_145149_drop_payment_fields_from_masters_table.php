<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Masters are no longer paid through the platform — the service earns from master
 * subscriptions instead, so the per-master payment model, salary and balance go away.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('masters', function (Blueprint $table) {
            $table->dropColumn(['payment_model', 'payment_value', 'monthly_salary', 'balance']);
        });
    }

    public function down(): void
    {
        Schema::table('masters', function (Blueprint $table) {
            $table->string('payment_model', 30)->default('percentage')->after('phone');
            $table->decimal('payment_value', 10, 2)->default(0)->after('payment_model');
            $table->decimal('monthly_salary', 10, 2)->default(0)->after('payment_value');
            $table->decimal('balance', 10, 2)->default(0)->after('monthly_salary');
        });
    }
};
