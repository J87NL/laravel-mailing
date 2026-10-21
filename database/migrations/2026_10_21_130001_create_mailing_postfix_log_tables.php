<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mailing_postfix_log_cursors', function (Blueprint $table): void {
            $table->id();
            $table->string('path')->unique();
            $table->unsignedBigInteger('inode');
            $table->unsignedBigInteger('position');
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('mailing_postfix_queue_ids', function (Blueprint $table): void {
            $table->id();
            $table->string('queue_id', 32)->unique();
            $table->string('message_id');
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mailing_postfix_queue_ids');
        Schema::dropIfExists('mailing_postfix_log_cursors');
    }
};
