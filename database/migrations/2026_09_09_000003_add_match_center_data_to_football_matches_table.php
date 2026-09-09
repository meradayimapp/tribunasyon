<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('football_matches', function (Blueprint $table): void {
            $table->json('lineups')->nullable()->after('live_events');
            $table->boolean('lineup_is_projected')->nullable()->after('lineups');
            $table->timestamp('lineup_synced_at')->nullable()->after('lineup_is_projected');
            $table->json('match_stats')->nullable()->after('lineup_synced_at');
            $table->string('venue_name')->nullable()->after('match_stats');
            $table->string('referee_name')->nullable()->after('venue_name');
            $table->json('tv_channels')->nullable()->after('referee_name');
        });
    }

    public function down(): void
    {
        Schema::table('football_matches', function (Blueprint $table): void {
            $table->dropColumn([
                'lineups',
                'lineup_is_projected',
                'lineup_synced_at',
                'match_stats',
                'venue_name',
                'referee_name',
                'tv_channels',
            ]);
        });
    }
};
