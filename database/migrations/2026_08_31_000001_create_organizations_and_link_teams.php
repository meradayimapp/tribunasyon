<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_path')->nullable();
            $table->string('status', 20)->default('active')->index();
            $table->timestamps();
        });

        Schema::table('teams', function (Blueprint $table): void {
            $table->foreignId('organization_id')
                ->nullable()
                ->after('organization_badge')
                ->constrained('organizations')
                ->nullOnDelete();
        });

        $legacyBadges = DB::table('teams')
            ->whereNotNull('organization_badge')
            ->where('organization_badge', '!=', '')
            ->distinct()
            ->pluck('organization_badge');

        foreach ($legacyBadges as $legacyPath) {
            $filename = pathinfo((string) $legacyPath, PATHINFO_FILENAME);
            $name = Str::of($filename)->replace(['-', '_'], ' ')->squish()->title()->toString();
            $name = $name !== '' ? $name : 'Legacy Organizasyon';
            $baseSlug = Str::slug($name) ?: 'legacy-organizasyon';
            $slug = $baseSlug;
            $suffix = 2;

            while (DB::table('organizations')->where('slug', $slug)->exists()) {
                $slug = $baseSlug.'-'.$suffix++;
            }

            $organizationId = DB::table('organizations')->insertGetId([
                'name' => $name,
                'slug' => $slug,
                'logo_path' => $legacyPath,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('teams')
                ->where('organization_badge', $legacyPath)
                ->update(['organization_id' => $organizationId]);
        }
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('organization_id');
        });

        Schema::dropIfExists('organizations');
    }
};
