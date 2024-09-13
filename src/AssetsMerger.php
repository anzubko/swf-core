<?php declare(strict_types=1);

namespace SWF;

use App\Config\SystemConfig;
use Exception;
use JSMin\JSMin;
use LogicException;
use RuntimeException;
use function is_array;

final class AssetsMerger
{
    /**
     * @var string[][][]
     */
    private array $files;

    /**
     * @param string $location Target URL location for merged assets.
     * @param string $dir Target directory for merged assets.
     * @param string $docRoot Web server document root directory.
     * @param string $metricsFile Path to file for internal metrics data.
     * @param string[]|string[][] $assets Assets to merge (targets are just filenames, assets are absolute).
     * @param string $lockKey Key for process locker.
     */
    public function __construct(
        private readonly string $location,
        private readonly string $dir,
        private readonly string $docRoot,
        private readonly string $metricsFile,
        private readonly array $assets = [],
        private readonly string $lockKey = 'assets.merger',
    ) {
    }

    /**
     * Merging if needed and returns merged paths.
     *
     * @return string[]
     *
     * @throws LogicException
     * @throws RuntimeException
     */
    public function merge(): array
    {
        $metrics = $this->getMetrics();
        if (null !== $metrics && !$this->isOutdated($metrics)) {
            return $this->getTargetFiles($metrics);
        }

        i(FileLocker::class)->acquire($this->lockKey);

        $metrics = $this->getMetrics($metrics) ?? $this->rebuild();

        i(FileLocker::class)->release($this->lockKey);

        return $this->getTargetFiles($metrics);
    }

    /**
     * @param mixed[]|null $oldMetrics
     *
     * @return mixed[]|null
     */
    private function getMetrics(?array $oldMetrics = null): ?array
    {
        $metrics = @include $this->metricsFile;
        if (!is_array($metrics) || $metrics === $oldMetrics) {
            return null;
        }

        return $metrics;
    }

    /**
     * @param mixed[] $metrics
     *
     * @return string[]
     */
    private function getTargetFiles(array $metrics): array
    {
        $targetFiles = [];
        foreach ($this->assets as $target => $files) {
            $targetFiles[$target] = sprintf('%s/%s.%s', $this->location, $metrics['modified'], $target);
        }

        return $targetFiles;
    }

    /**
     * @param mixed[] $metrics
     */
    private function isOutdated(array $metrics): bool
    {
        if ($metrics['debug'] !== i(SystemConfig::class)->debug) {
            return true;
        }

        if ('prod' === i(SystemConfig::class)->env) {
            return false;
        }

        $oldTargets = [];
        foreach (DirHandler::scan($this->dir, false, true) as $item) {
            if (!is_file($item) || !preg_match('~/(\d+)\.(.+)$~', $item, $M) || (int) $M[1] !== $metrics['modified']) {
                return true;
            }

            $oldTargets[] = $M[2];
        }

        $this->scanForFiles();

        $newTargets = [];
        foreach (array_keys($this->files) as $type) {
            foreach ($this->files[$type] as $target => $files) {
                foreach ($files as $file) {
                    if ((int) filemtime($file) > $metrics['modified']) {
                        return true;
                    }
                }

                $newTargets[] = $target;
            }
        }

        if ($newTargets !== $oldTargets) {
            return true;
        }

        return false;
    }

    /**
     * @return mixed[]
     *
     * @throws LogicException
     * @throws RuntimeException
     */
    private function rebuild(): array
    {
        DirHandler::clear($this->dir);

        $this->scanForFiles();

        $metrics = [
            'modified' => time(),
            'debug' => i(SystemConfig::class)->debug,
            'hash' => TextHandler::random(),
        ];

        foreach (array_keys($this->files) as $type) {
            foreach ($this->files[$type] as $target => $files) {
                $file = sprintf('%s/%s.%s', $this->dir, $metrics['modified'], $target);

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

        if (!FileHandler::putVar($this->metricsFile, $metrics)) {
            throw new RuntimeException(sprintf('Unable to write file %s', $this->metricsFile));
        }

        return $metrics;
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

    /**
     * @param string[] $files
     *
     * @throws LogicException
     * @throws RuntimeException
     */
    private function mergeJs(array $files): string
    {
        $merged = $this->mergeFiles($files);

        if (i(SystemConfig::class)->debug) {
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
        if (!i(SystemConfig::class)->debug) {
            $merged = TextHandler::fTrim(preg_replace('~/\*(.*?)\*/~us', '', $merged));
        }

        return (string) preg_replace_callback('/url\(\s*(.+?)\s*\)/u',
            function (array $M) {
                $data = $type = null;

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

                if (null !== $data) {
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
            if (null === $contents) {
                throw new RuntimeException(sprintf('Unable to read file %s', $file));
            }

            $merged[] = $contents;
        }

        return implode("\n", $merged);
    }
}
