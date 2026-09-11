<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->string('provider_player_id', 100)->nullable()->unique();
            $table->timestamp('provider_last_synced_at')->nullable();
            $table->string('nationality', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('players', function (Blueprint $table): void {
            $table->dropUnique(['provider_player_id']);
            $table->dropColumn(['provider_player_id', 'provider_last_synced_at', 'nationality']);
        });
    }
};
