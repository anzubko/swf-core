<?php declare(strict_types=1);

namespace SWF;

use Exception;
use JSMin\JSMin;
use LogicException;
use RuntimeException;
use function is_array;

final class AssetsMerger
{
    /** @var array<int|string,mixed> */
    private array $cache;
    /** @var array<string,mixed> */
    private array $files;

    /**
     * @param string $location Target URL location for merged assets.
     * @param string $dir Target directory for merged assets.
     * @param string $docRoot Web server document root directory.
     * @param string $cacheFile Cache file for internal data.
     * @param array<string, string|string[]> $assets Assets to merge (targets are just filenames, assets are absolute).
     * @param string $lockKey Key for process locker.
     */
    public function __construct(
        private readonly string $location,
        private readonly string $dir,
        private readonly string $docRoot,
        private readonly string $cacheFile,
        private readonly array $assets = [],
        private readonly string $lockKey = 'merger',
    ) {
    }

    /**
     * Merging if needed and returns merged paths.
     *
     * @return array<int|string,string>
     *
     * @throws LogicException
     * @throws RuntimeException
     */
    public function merge(): array
    {
        $cache = @include $this->cacheFile;
        if (is_array($cache)) {
            $this->cache = $cache;
            if ('prod' === ConfigHolder::get()->env && $this->cache['debug'] === ConfigHolder::get()->debug) {
                return $this->getPaths();
            }
        }

        if (!ProcessLocker::getInstance()->lock($this->lockKey)) {
            return $this->getPaths();
        }

        if ($this->isOutdated()) {
            $this->recombine();
        }

        ProcessLocker::getInstance()->unlock($this->lockKey);

        return $this->getPaths();
    }

    /**
     * @return array<int|string,string>
     */
    private function getPaths(): array
    {
        $time = isset($this->cache) ? $this->cache['time'] : 0;

        $paths = [];
        foreach ($this->assets as $target => $files) {
            $paths[$target] = sprintf('%s/%s.%s', $this->location, $time, $target);
        }

        return $paths;
    }

    private function scanForFiles(): void
    {
        if (isset($this->files)) {
            return;
        }

        $this->files = [];
        foreach ($this->assets as $target => $files) {
            foreach ((array) $files as $file) {
                if (!preg_match('/\.(css|js)$/', $file, $M)) {
                    continue;
                }

                $this->files[$M[1]][$target] ??= [];
                foreach (glob($file) ?: [] as $item) {
                    if (is_file($item)) {
                        $this->files[$M[1]][$target][] = $item;
                    }
                }
            }
        }
    }

    private function isOutdated(): bool
    {
        if (!isset($this->cache) || ConfigHolder::get()->debug !== $this->cache['debug']) {
            return true;
        }

        $targets = [];
        foreach (DirHandler::scan($this->dir, false, true) as $item) {
            if (is_file($item) && preg_match('~/(\d+)\.(.+)$~', $item, $M) && (int) $M[1] === $this->cache['time']) {
                $targets[] = $M[2];
            } else {
                return true;
            }
        }

        $this->scanForFiles();

        if (array_diff(array_keys(array_merge(...array_values($this->files))), $targets)) {
            return true;
        }

        foreach (array_keys($this->files) as $type) {
            foreach ($this->files[$type] as $files) {
                foreach ($files as $file) {
                    if ((int) filemtime($file) > $this->cache['time']) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @throws LogicException
     * @throws RuntimeException
     */
    private function recombine(): void
    {
        DirHandler::clear($this->dir);

        $this->cache = ['time' => time(), 'debug' => ConfigHolder::get()->debug];

        $this->scanForFiles();

        foreach (array_keys($this->files) as $type) {
            foreach ($this->files[$type] as $target => $files) {
                $file = sprintf('%s/%s.%s', $this->dir, $this->cache['time'], $target);

                if ('js' === $type) {
                    $contents = $this->mergeJs($files);
                } else {
                    $contents = $this->mergeCss($files);
                }

                if (!FileHandler::put($file, $contents)) {
                    throw new RuntimeException(sprintf('Unable to write file %s', $file));
                }
            }
        }

        if (!FileHandler::putVar($this->cacheFile, $this->cache)) {
            throw new RuntimeException(sprintf('Unable to write file %s', $this->cacheFile));
        }
    }

    /**
     * @param string[] $files
     *
     * @throws LogicException
     * @throws RuntimeException
     */
    private function mergeJs(array $files): string
    {
        $merged = $this->mergeFiles($files);

        if (ConfigHolder::get()->debug) {
            return $merged;
        }

        try {
            return (new JSMin($merged))->min();
        } catch (Exception $e) {
            throw new LogicException($e->getMessage());
        }
    }

    /**
     * @param string[] $files
     *
     * @throws RuntimeException
     */
    private function mergeCss(array $files): string
    {
        $merged = $this->mergeFiles($files);
        if (!ConfigHolder::get()->debug) {
            $merged = TextHandler::fTrim(preg_replace('~/\*(.*?)\*/~us', '', $merged));
        }

        return (string) preg_replace_callback('/url\(\s*(.+?)\s*\)/u',
            function (array $M) {
                $data = $type = false;

                if (
                    preg_match('/\.(gif|png|jpg|jpeg|svg|woff|woff2)$/ui', $M[1], $N)
                    && str_starts_with($M[1], '/')
                    && !str_starts_with($M[1], '//')
                    && !str_contains($M[1], '..')
                ) {
                    $type = strtolower($N[1]);
                    if ('jpg' === $type) {
                        $type = 'jpeg';
                    } elseif ('svg' === $type) {
                        $type = 'svg+xml';
                    }

                    $file = sprintf('%s/%s', $this->docRoot, $M[1]);
                    $size = @filesize($file);
                    if (false !== $size && $size <= 32 * 1024) {
                        $data = FileHandler::get($file);
                    }
                }

                if (false !== $data) {
                    return sprintf('url(data:image/%s;base64,%s)', $type, base64_encode($data));
                } else {
                    return sprintf('url(%s)', $M[1]);
                }
            },
            $merged,
        );
    }

    /**
     * @param string[] $files
     *
     * @throws RuntimeException
     */
    private function mergeFiles(array $files): string
    {
        $merged = [];
        foreach ($files as $file) {
            $contents = FileHandler::get($file);
            if (false === $contents) {
                throw new RuntimeException(sprintf('Unable to read file %s', $file));
            }

            $merged[] = $contents;
        }

        return implode("\n", $merged);
    }
}
