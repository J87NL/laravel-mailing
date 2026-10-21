<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mailing_bounce_events', function (Blueprint $table): void {
            $table->string('source')->change();
        });
    }

    public function down(): void
    {
        DB::table('mailing_bounce_events')
            ->whereRaw('length(source) > 20')
            ->update([
                'source' => DB::raw('substr(source, 1, 20)'),
            ]);

        Schema::table('mailing_bounce_events', function (Blueprint $table): void {
            $table->string('source', 20)->change();
        });
    }
};
