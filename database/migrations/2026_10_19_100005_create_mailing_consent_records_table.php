<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailing_consent_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('subscriber_id')->constrained('mailing_subscribers')->cascadeOnDelete();
            $table->foreignId('list_id')->nullable()->constrained('mailing_lists')->nullOnDelete();
            $table->string('action', 32);
            $table->string('source')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailing_consent_records');
    }
};
