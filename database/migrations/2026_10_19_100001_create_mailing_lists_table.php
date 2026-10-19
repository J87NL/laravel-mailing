<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailing_lists', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('mailer', 64)->nullable();
            $table->string('sender_identity', 64)->nullable();
            $table->string('reply_to')->nullable();
            $table->boolean('is_public')->default(true);
            $table->unsignedSmallInteger('throttle_per_minute')->default(20);
            $table->unsignedInteger('daily_cap')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailing_lists');
    }
};
