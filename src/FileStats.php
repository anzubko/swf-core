<?php declare(strict_types=1);

namespace SWF;

final readonly class FileStats
{
    public function __construct(
        private string $name,
        private string $dirname,
        private string $basename,
        private ?string $extension,
        private int $size,
        private int $created,
        private int $modified,
        private ?int $width,
        private ?int $height,
        private ?string $type,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDirname(): string
    {
        return $this->dirname;
    }

    public function getBasename(): string
    {
        return $this->basename;
    }

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getCreated(): int
    {
        return $this->created;
    }

    public function getModified(): int
    {
        return $this->modified;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function getType(): ?string
    {
        return $this->type;
    }
}
