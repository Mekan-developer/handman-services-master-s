<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_subscriptions', function (Blueprint $table) {
            $table->timestamp('expiry_reminded_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('master_subscriptions', function (Blueprint $table) {
            $table->dropColumn('expiry_reminded_at');
        });
    }
};
