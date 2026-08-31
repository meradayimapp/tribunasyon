<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\MediaStorageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class OrganizationController extends Controller
{
    public function index(): View
    {
        return view('admin.organizations.index', [
            'organizations' => Organization::withCount('teams')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.organizations.form', ['organization' => new Organization]);
    }

    public function edit(Organization $organization): View
    {
        return view('admin.organizations.form', compact('organization'));
    }

    public function store(Request $request, MediaStorageService $media): RedirectResponse
    {
        $data = $this->validated($request);
        $logoPath = null;

        try {
            $logoPath = $media->store($request->file('logo_file'), 'organizations/logos');
            $data['logo_path'] = $logoPath;
            unset($data['logo_file'], $data['remove_logo']);

            DB::transaction(fn () => Organization::create($data));
        } catch (Throwable $exception) {
            $media->delete($logoPath);

            throw $exception;
        }

        return redirect()->route('admin.organizations.index')->with('success', 'Organizasyon oluşturuldu.');
    }

    public function update(Request $request, Organization $organization, MediaStorageService $media): RedirectResponse
    {
        $data = $this->validated($request, $organization);
        $oldLogoPath = $organization->logo_path;
        $newLogoPath = null;

        try {
            if ($request->hasFile('logo_file')) {
                $newLogoPath = $media->store($request->file('logo_file'), 'organizations/logos');
                $data['logo_path'] = $newLogoPath;
            } elseif ($request->boolean('remove_logo')) {
                $data['logo_path'] = null;
            }

            unset($data['logo_file'], $data['remove_logo']);

            DB::transaction(fn () => $organization->update($data));
        } catch (Throwable $exception) {
            $media->delete($newLogoPath);

            throw $exception;
        }

        $organization->refresh();

        if ($oldLogoPath !== $organization->logo_path) {
            $this->deleteLogoIfUnused($media, $oldLogoPath);
        }

        return redirect()->route('admin.organizations.index')->with('success', 'Organizasyon güncellendi.');
    }

    public function destroy(Organization $organization, MediaStorageService $media): RedirectResponse
    {
        $logoPath = $organization->logo_path;

        DB::transaction(fn () => $organization->delete());
        $this->deleteLogoIfUnused($media, $logoPath);

        return redirect()->route('admin.organizations.index')->with('success', 'Organizasyon silindi.');
    }

    private function validated(Request $request, ?Organization $organization = null): array
    {
        if (! $request->filled('slug')) {
            $request->merge(['slug' => Str::slug($request->string('name')->toString())]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'alpha_dash', 'max:140', Rule::unique('organizations')->ignore($organization)],
            'status' => ['required', Rule::enum(OrganizationStatus::class)],
            'logo_file' => [
                $organization ? 'nullable' : 'required',
                'file',
                'image',
                'mimes:png,webp',
                'extensions:png,webp',
                'max:5120',
            ],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        return $data;
    }

    private function deleteLogoIfUnused(MediaStorageService $media, ?string $path): void
    {
        if ($path && str_starts_with($path, 'organizations/logos/') && ! Organization::where('logo_path', $path)->exists()) {
            $media->delete($path);
        }
    }
}
