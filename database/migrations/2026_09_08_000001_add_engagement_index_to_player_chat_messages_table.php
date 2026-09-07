<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_chat_messages', function (Blueprint $table): void {
            $table->index(['player_id', 'deleted_at', 'created_at'], 'player_chat_messages_engagement_index');
        });
    }

    public function down(): void
    {
        Schema::table('player_chat_messages', function (Blueprint $table): void {
            $table->dropIndex('player_chat_messages_engagement_index');
        });
    }
};
