<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailing_sends', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('mailing_campaigns')->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('mailing_subscriptions')->nullOnDelete();
            $table->string('email');
            $table->string('status', 20);
            $table->string('message_id')->nullable()->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('sending_started_at')->nullable();
            $table->text('last_response')->nullable();
            $table->timestamp('handed_over_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->string('bounce_type', 10)->nullable();
            $table->text('bounce_diagnostic')->nullable();
            $table->timestamps();

            $table->unique([
                'campaign_id',
                'subscription_id',
            ]);
            $table->index([
                'campaign_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailing_sends');
    }
};
