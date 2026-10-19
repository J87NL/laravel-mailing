<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailing_bounce_events', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->index();
            $table->foreignId('send_id')->nullable()->constrained('mailing_sends')->nullOnDelete();
            $table->string('type', 10);
            $table->string('source', 20);
            $table->text('diagnostic')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('mailing_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('driver', 40);
            $table->string('event_id');
            $table->string('event', 60);
            $table->timestamp('created_at')->nullable()->index();

            $table->unique([
                'driver',
                'event_id',
            ]);
        });

        Schema::create('mailing_rate_counters', function (Blueprint $table): void {
            $table->id();
            $table->string('key');
            $table->timestamp('window_starts_at');
            $table->unsignedInteger('hits')->default(0);

            $table->unique([
                'key',
                'window_starts_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailing_rate_counters');
        Schema::dropIfExists('mailing_webhook_events');
        Schema::dropIfExists('mailing_bounce_events');
    }
};
