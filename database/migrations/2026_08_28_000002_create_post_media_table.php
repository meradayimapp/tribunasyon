<?php

use App\Services\PostMediaBackfillService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('image');
            $table->string('path');
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();
            $table->unique(['post_id', 'sort_order']);
            $table->index(['post_id', 'type']);
        });

        app(PostMediaBackfillService::class)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('post_media');
    }
};
