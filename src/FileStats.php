<?php declare(strict_types=1);

namespace SWF;

final readonly class FileStats
{
    public function __construct(
        public string $name,
        public int $size,
        public int $created,
        public int $modified,
        public ?int $width,
        public ?int $height,
        public ?string $type,
    ) {
    }
}
