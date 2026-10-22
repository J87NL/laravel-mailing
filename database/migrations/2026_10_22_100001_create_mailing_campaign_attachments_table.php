<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailing_campaign_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('mailing_campaigns')->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->string('disk', 64);
            $table->string('path');
            $table->string('name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->boolean('as_link')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailing_campaign_attachments');
    }
};
