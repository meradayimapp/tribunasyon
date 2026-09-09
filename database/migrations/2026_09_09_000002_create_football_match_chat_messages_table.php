<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('football_match_chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('football_match_id')->constrained('football_matches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('body', 500);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['football_match_id', 'deleted_at', 'id'], 'football_match_chat_room_index');
            $table->index(['football_match_id', 'created_at'], 'football_match_chat_engagement_index');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('football_match_chat_messages');
    }
};
