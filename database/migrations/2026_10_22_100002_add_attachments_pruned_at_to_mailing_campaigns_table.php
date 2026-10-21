<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailing_campaigns', function (Blueprint $table): void {
            $table->timestamp('attachments_pruned_at')->nullable()->after('finished_at');
        });
    }

    public function down(): void
    {
        Schema::table('mailing_campaigns', function (Blueprint $table): void {
            $table->dropColumn('attachments_pruned_at');
        });
    }
};
