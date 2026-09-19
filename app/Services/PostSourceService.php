<?php

namespace App\Services;

use App\Models\Post;
use Closure;

class PostSourceService
{
    public const MAX_SOURCES = 10;

    public const MAX_LABEL_LENGTH = 120;

    public const MAX_URL_LENGTH = 2048;

    public function validationRules(): array
    {
        return [
            'sources' => ['nullable', 'array', 'max:'.self::MAX_SOURCES],
            'sources_editor_present' => ['nullable', 'boolean'],
            'sources.*' => ['array:label,url'],
            'sources.*.label' => ['nullable', 'string', 'max:'.self::MAX_LABEL_LENGTH],
            'sources.*.url' => [
                'nullable',
                'required_with:sources.*.label',
                'string',
                'max:'.self::MAX_URL_LENGTH,
                $this->safeHttpUrlRule(),
            ],
        ];
    }

    public function normalize(array $sources): array
    {
        $normalized = [];
        $seen = [];

        foreach ($sources as $source) {
            if (! is_array($source) || blank($source['url'] ?? null)) {
                continue;
            }

            $url = $this->normalizeUrl((string) $source['url']);
            $fingerprint = hash('sha256', $url);

            if (isset($seen[$fingerprint])) {
                continue;
            }

            $seen[$fingerprint] = true;
            $normalized[] = [
                'url' => $url,
                'label' => filled($source['label'] ?? null) ? trim((string) $source['label']) : null,
                'sort_order' => count($normalized) + 1,
            ];
        }

        return $normalized;
    }

    public function sync(Post $post, array $sources): void
    {
        $post->sources()->delete();

        if ($sources !== []) {
            $post->sources()->createMany($sources);
        }
    }

    private function safeHttpUrlRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            $parts = is_string($value) ? parse_url(trim($value)) : false;
            $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';

            if (! filter_var(trim((string) $value), FILTER_VALIDATE_URL)
                || ! in_array($scheme, ['http', 'https'], true)
                || blank($parts['host'] ?? null)
                || isset($parts['user'])
                || isset($parts['pass'])) {
                $fail('Kaynak bağlantısı geçerli bir http:// veya https:// adresi olmalıdır.');
            }
        };
    }

    private function normalizeUrl(string $url): string
    {
        $parts = parse_url(trim($url));
        $scheme = strtolower((string) $parts['scheme']);
        $host = strtolower(rtrim((string) $parts['host'], '.'));
        $host = str_contains($host, ':') && ! str_starts_with($host, '[') ? "[{$host}]" : $host;
        $port = isset($parts['port']) && ! (($scheme === 'http' && $parts['port'] === 80) || ($scheme === 'https' && $parts['port'] === 443))
            ? ':'.$parts['port']
            : '';
        $path = ($parts['path'] ?? '') === '/' ? '' : ($parts['path'] ?? '');
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return "{$scheme}://{$host}{$port}{$path}{$query}{$fragment}";
    }
}
