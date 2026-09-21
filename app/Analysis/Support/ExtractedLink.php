<?php

namespace App\Analysis\Support;

final readonly class ExtractedLink
{
    public function __construct(
        public string $url,
        public string $text,
        public bool $isInternal,
        public bool $isNofollow,
    ) {}
}
