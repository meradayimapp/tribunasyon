<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('football_competitions', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32);
            $table->string('provider_league_id', 100);
            $table->string('name');
            $table->string('display_name')->nullable();
            $table->string('slug')->unique();
            $table->string('country')->nullable();
            $table->string('provider_logo_url')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('current_season')->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['provider', 'provider_league_id']);
            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('football_competitions');
    }
};
