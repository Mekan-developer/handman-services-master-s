<?php

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->nullable()->constrained()->nullOnDelete();

            // Snapshot of the plan at purchase time — later price or name edits
            // must never rewrite history.
            $table->string('plan_name');
            $table->decimal('price_paid', 10, 2);
            $table->unsignedInteger('duration_days');

            $table->string('status', 20)->default(SubscriptionStatus::Pending->value);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['master_id', 'status']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_subscriptions');
    }
};
