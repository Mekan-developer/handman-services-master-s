<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One person, one photo. A master profile always hangs off a client account, so
 * the avatar lives on `clients` and the master profile reads it through the
 * relation — no second upload, no second file to keep in sync.
 *
 * Existing master photos are moved onto their client account first; the files
 * themselves stay where they are, only the owning column changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('masters')
            ->whereNotNull('photo')
            ->orderBy('id')
            ->chunkById(200, function ($masters) {
                foreach ($masters as $master) {
                    // A photo already attached to the client wins: it is the one
                    // the person uploaded themselves from the app.
                    DB::table('clients')
                        ->where('id', $master->client_id)
                        ->whereNull('photo')
                        ->update(['photo' => $master->photo]);
                }
            });

        Schema::table('masters', function (Blueprint $table) {
            $table->dropColumn('photo');
        });
    }

    /**
     * The column comes back empty — photos now belong to the client account and
     * are not copied back, otherwise the same file would be owned twice.
     */
    public function down(): void
    {
        Schema::table('masters', function (Blueprint $table) {
            $table->string('photo')->nullable()->after('is_available');
        });
    }
};
