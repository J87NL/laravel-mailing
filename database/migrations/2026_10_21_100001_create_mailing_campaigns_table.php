<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailing_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('list_id')->constrained('mailing_lists')->restrictOnDelete();
            $table->string('subject');
            $table->string('preheader')->nullable();
            $table->longText('body_html');
            $table->longText('body_text');
            $table->string('status', 20)->index();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailing_campaigns');
    }
};
