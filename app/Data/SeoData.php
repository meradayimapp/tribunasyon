<?php

namespace App\Data;

final readonly class SeoData
{
    public function __construct(
        public string $title,
        public string $description,
        public string $canonical,
        public string $type = 'website',
        public ?string $image = null,
        public string $robots = 'index, follow',
        public array $structuredData = [],
    ) {}
}
