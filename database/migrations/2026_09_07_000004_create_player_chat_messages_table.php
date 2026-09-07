<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('body', 500);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['player_id', 'deleted_at', 'id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_chat_messages');
    }
};
