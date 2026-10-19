<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailing_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('list_id')->constrained('mailing_lists')->cascadeOnDelete();
            $table->string('filename');
            $table->string('consent_source');
            $table->text('consent_note')->nullable();
            $table->unsignedInteger('imported')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->unsignedInteger('confirmations_sent')->default(0);
            $table->json('errors')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailing_imports');
    }
};
