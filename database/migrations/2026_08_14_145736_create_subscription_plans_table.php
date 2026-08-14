<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name_ru');
            $table->string('name_tk');
            $table->text('description_ru')->nullable();
            $table->text('description_tk')->nullable();
            $table->unsignedInteger('duration_days');
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Soft deletes keep the plan row around so purchased subscriptions
            // that reference it never lose their link.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
