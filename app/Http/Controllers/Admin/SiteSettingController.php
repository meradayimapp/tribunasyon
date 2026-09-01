<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\MediaStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\File;
use Illuminate\View\View;
use Throwable;

class SiteSettingController extends Controller
{
    private const UPLOADS = [
        'site_logo' => 'logo_path',
        'small_logo' => 'small_logo_path',
        'favicon' => 'favicon_path',
        'light_logo' => 'light_logo_path',
        'dark_logo' => 'dark_logo_path',
    ];

    public function edit(): View
    {
        return view('admin.settings.edit', ['settings' => SiteSetting::current()]);
    }

    public function update(Request $request, MediaStorageService $media): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $settings = SiteSetting::current();
        $settings->id = SiteSetting::SINGLETON_ID;
        $oldPaths = collect(SiteSetting::MEDIA_COLUMNS)->mapWithKeys(fn (string $column) => [$column => $settings->{$column}]);
        $storedPaths = [];

        try {
            foreach (self::UPLOADS as $input => $column) {
                if ($request->hasFile($input)) {
                    $data[$column] = $media->store($request->file($input), 'branding');
                    $storedPaths[] = $data[$column];
                } elseif ($request->boolean("remove_{$input}")) {
                    $data[$column] = null;
                }
            }

            $attributes = ['site_name' => $data['site_name']];
            foreach (SiteSetting::MEDIA_COLUMNS as $column) {
                if (array_key_exists($column, $data)) {
                    $attributes[$column] = $data[$column];
                }
            }

            DB::transaction(fn () => $settings->fill($attributes)->save());
        } catch (Throwable $exception) {
            $media->delete($storedPaths);

            throw $exception;
        }

        $settings->refresh();
        foreach ($oldPaths as $column => $oldPath) {
            if ($oldPath !== $settings->{$column}) {
                $this->deleteBrandingIfUnused($media, $oldPath);
            }
        }

        return back()->with('success', 'Site ayarları güncellendi.');
    }

    private function rules(): array
    {
        $rules = ['site_name' => ['required', 'string', 'max:100']];

        foreach (self::UPLOADS as $input => $column) {
            $rules[$input] = ['nullable', File::image()->types(['png', 'webp'])->max(5 * 1024)];
            $rules["remove_{$input}"] = ['nullable', 'boolean'];
        }

        return $rules;
    }

    private function deleteBrandingIfUnused(MediaStorageService $media, ?string $path): void
    {
        if (! $path || ! str_starts_with($path, 'branding/')) {
            return;
        }

        $inUse = SiteSetting::query()->where(function ($query) use ($path): void {
            foreach (SiteSetting::MEDIA_COLUMNS as $index => $column) {
                $index === 0 ? $query->where($column, $path) : $query->orWhere($column, $path);
            }
        })->exists();

        if (! $inUse) {
            $media->delete($path);
        }
    }
}
