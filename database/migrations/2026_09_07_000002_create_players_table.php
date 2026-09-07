<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('photo_path')->nullable();
            $table->string('cover_image_path')->nullable();
            $table->string('position', 80)->nullable();
            $table->unsignedTinyInteger('shirt_number')->nullable();
            $table->foreignId('current_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('national_team_name', 100)->nullable();
            $table->char('national_team_code', 2)->nullable();
            $table->date('birth_date')->nullable();
            $table->unsignedBigInteger('market_value_amount')->nullable();
            $table->char('market_value_currency', 3)->nullable();
            $table->text('bio')->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'sort_order']);
            $table->index('current_team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
