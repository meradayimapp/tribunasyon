<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('football_teams', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32);
            $table->string('provider_team_id', 100);
            $table->string('provider_name');
            $table->string('display_name')->nullable();
            $table->string('provider_logo_url')->nullable();
            $table->string('country')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_synced_at')->nullable();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->timestamps();

            $table->unique(['provider', 'provider_team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('football_teams');
    }
};
