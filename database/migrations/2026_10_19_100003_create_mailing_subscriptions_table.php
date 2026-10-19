<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailing_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscriber_id')->constrained('mailing_subscribers')->cascadeOnDelete();
            $table->foreignId('list_id')->constrained('mailing_lists')->restrictOnDelete();
            $table->string('status', 20)->index();
            $table->string('unsubscribe_token', 64)->unique();
            $table->timestamp('consent_at')->nullable();
            $table->string('consent_ip', 45)->nullable();
            $table->string('consent_source')->nullable();
            $table->timestamp('confirmation_sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();

            $table->unique([
                'subscriber_id',
                'list_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailing_subscriptions');
    }
};
