<?php

namespace App\Services;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PostEditorInput
{
    public function __construct(private readonly PostSourceService $sources) {}

    public function validated(Request $request, ?Post $post = null): array
    {
        $validator = validator($request->all(), [
            'team_id' => ['required', 'exists:teams,id'],
            'body' => ['required', 'string', 'max:5000'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:160'],
            'status' => ['required', Rule::enum(PostStatus::class)],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],
            'media_order' => ['nullable', 'array', 'max:10'],
            'media_order.*' => ['required', 'string', 'distinct', 'regex:/^(existing|new):[0-9]+$/'],
            'media_editor_present' => ['nullable', 'boolean'],
            ...$this->sources->validationRules(),
        ]);
        $validator->after(function (Validator $validator) use ($request): void {
            $imageFiles = $request->file('images', []);
            $multiple = is_array($imageFiles) ? count($imageFiles) : ($imageFiles ? 1 : 0);
            $legacy = $request->hasFile('image') ? 1 : 0;

            if ($multiple + $legacy > PostMediaService::MAX_MEDIA) {
                $validator->errors()->add('images', 'Bir gönderide en fazla 10 görsel olabilir.');
            }
        });
        $data = $validator->validate();
        $data['seo_title'] = filled($data['seo_title'] ?? null) ? trim($data['seo_title']) : null;
        $data['seo_description'] = filled($data['seo_description'] ?? null) ? trim($data['seo_description']) : null;
        $uploads = array_values($request->file('images', []));

        if ($request->hasFile('image')) {
            $uploads[] = $request->file('image');
        }

        $data['published_at'] = $data['status'] === PostStatus::Published->value ? ($post?->published_at ?? now()) : null;
        $order = $request->boolean('media_editor_present') ? array_values($data['media_order'] ?? []) : null;
        $sourceRows = $request->boolean('sources_editor_present') || $request->exists('sources')
            ? $this->sources->normalize($data['sources'] ?? [])
            : null;
        unset($data['image'], $data['images'], $data['media_order'], $data['media_editor_present'], $data['sources'], $data['sources_editor_present']);

        return [$data, $uploads, $order, $sourceRows];
    }
}
