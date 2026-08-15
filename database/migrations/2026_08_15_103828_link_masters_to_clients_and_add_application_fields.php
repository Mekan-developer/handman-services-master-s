<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A master is no longer a standalone account: it is an add-on profile a client
 * applies for from the mobile app. The profile carries what the applicant fills
 * in (categories via the existing pivot, years of experience, a short bio) plus
 * the administrator's review verdict.
 *
 * Masters created by the admin panel before this change have no client to hang
 * off and are dropped — the owner confirmed none of them are worth keeping.
 * `orders.master_id` is nullOnDelete, so order history survives the cleanup.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('masters')->delete();

        Schema::table('masters', function (Blueprint $table) {
            $table->foreignId('client_id')
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('status', 20)->default('pending')->after('phone');
            $table->unsignedTinyInteger('experience_years')->nullable()->after('status');
            $table->text('about')->nullable()->after('experience_years');

            $table->timestamp('reviewed_at')->nullable()->after('about');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->string('rejection_reason')->nullable()->after('reviewed_by');

            $table->unique('client_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('masters', function (Blueprint $table) {
            $table->dropForeign(['client_id']);
            $table->dropUnique(['client_id']);
            $table->dropForeign(['reviewed_by']);
            $table->dropIndex(['status']);

            $table->dropColumn([
                'client_id',
                'status',
                'experience_years',
                'about',
                'reviewed_at',
                'reviewed_by',
                'rejection_reason',
            ]);
        });
    }
};
