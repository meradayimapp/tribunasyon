<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('football_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('competition_id')->constrained('football_competitions')->restrictOnDelete();
            $table->foreignId('home_football_team_id')->constrained('football_teams')->restrictOnDelete();
            $table->foreignId('away_football_team_id')->constrained('football_teams')->restrictOnDelete();
            $table->string('provider', 32);
            $table->string('provider_match_id', 100);
            $table->string('season')->nullable();
            $table->string('week')->nullable();
            $table->string('round')->nullable();
            $table->timestamp('kickoff_at');
            $table->string('status', 40);
            $table->string('state', 40)->nullable();
            $table->string('status_display', 80)->nullable();
            $table->boolean('is_live')->default(false);
            $table->unsignedSmallInteger('home_score')->nullable();
            $table->unsignedSmallInteger('away_score')->nullable();
            $table->unsignedSmallInteger('home_halftime_score')->nullable();
            $table->unsignedSmallInteger('away_halftime_score')->nullable();
            $table->unsignedSmallInteger('home_penalty_score')->nullable();
            $table->unsignedSmallInteger('away_penalty_score')->nullable();
            $table->boolean('tv_broadcast')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_match_id']);
            $table->index(['competition_id', 'kickoff_at']);
            $table->index(['kickoff_at', 'is_live']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('football_matches');
    }
};
