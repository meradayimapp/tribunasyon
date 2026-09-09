<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('football_matches', function (Blueprint $table): void {
            $table->unsignedSmallInteger('live_minute')->nullable()->after('is_live');
            $table->json('live_events')->nullable()->after('tv_broadcast');
            $table->timestamp('live_details_synced_at')->nullable()->after('last_synced_at');
            $table->index(['is_live', 'last_synced_at'], 'football_matches_live_sync_index');
        });
    }

    public function down(): void
    {
        Schema::table('football_matches', function (Blueprint $table): void {
            $table->dropIndex('football_matches_live_sync_index');
            $table->dropColumn(['live_minute', 'live_events', 'live_details_synced_at']);
        });
    }
};
