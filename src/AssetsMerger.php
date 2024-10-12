<?php
declare(strict_types=1);

namespace SWF;

use Exception;
use JSMin\JSMin;
use LogicException;
use RuntimeException;
use function count;
use function is_array;

final class AssetsMerger
{
    private const MAX_FILESIZE_TO_ENCODE = 32 * 1024;

    /**
     * @var string[][][]
     */
    private array $scannedFiles;

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
     * Merges (if needed) and returns merged paths.
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

        try {
            $metrics = $this->getMetrics($metrics) ?? $this->rebuild();
        } finally {
            i(FileLocker::class)->release($this->lockKey);
        }

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
        if ($metrics['debug'] !== ConfigStorage::$system->debug) {
            return true;
        }

        if ('prod' === ConfigStorage::$system->env) {
            return false;
        }

        $oldTargets = [];
        foreach (DirHandler::scan($this->dir, false, true) as $item) {
            if (!is_file($item) || !preg_match('~/(\d+)\.(.+)$~', $item, $M) || (int) $M[1] !== $metrics['modified']) {
                return true;
            }

            $oldTargets[] = $M[2];
        }

        $count = 0;
        $newTargets = [];
        foreach (array_keys($this->getScannedFiles()) as $type) {
            foreach ($this->getScannedFiles()[$type] as $target => $files) {
                foreach ($files as $file) {
                    if ((int) filemtime($file) > $metrics['modified']) {
                        return true;
                    }
                }

                $count += count($files);
                $newTargets[] = $target;
            }
        }

        if ($count !== $metrics['count'] || $newTargets !== $oldTargets) {
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

        $metrics = ['modified' => time(), 'count' => 0, 'debug' => ConfigStorage::$system->debug, 'hash' => TextHandler::random()];

        foreach (array_keys($this->getScannedFiles()) as $type) {
            foreach ($this->getScannedFiles()[$type] as $target => $files) {
                $file = sprintf('%s/%s.%s', $this->dir, $metrics['modified'], $target);

                $contents = '';
                if ('js' === $type) {
                    $contents = $this->mergeJs($files);
                } elseif ('css' === $type) {
                    $contents = $this->mergeCss($files);
                }

                if (!FileHandler::put($file, $contents)) {
                    throw new RuntimeException(sprintf('Unable to write file: %s', $file));
                }

                $metrics['count'] += count($files);
            }
        }

        if (!FileHandler::putVar($this->metricsFile, $metrics)) {
            throw new RuntimeException(sprintf('Unable to write file: %s', $this->metricsFile));
        }

        return $metrics;
    }

    /**
     * @return string[][][]
     */
    private function getScannedFiles(): array
    {
        return $this->scannedFiles ??= $this->scanForFiles();
    }

    /**
     * @return string[][][]
     */
    private function scanForFiles(): array
    {
        $scannedFiles = [];
        foreach ($this->assets as $target => $files) {
            foreach ((array) $files as $file) {
                if (!preg_match('/\.(css|js)$/', $file, $M)) {
                    continue;
                }

                $scannedFiles[$M[1]][$target] ??= [];
                $items = glob($file);
                if (false !== $items) {
                    foreach ($items as $item) {
                        if (is_file($item)) {
                            $scannedFiles[$M[1]][$target][] = $item;
                        }
                    }
                }
            }
        }

        return $scannedFiles;
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
        if (ConfigStorage::$system->debug) {
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
        if (!ConfigStorage::$system->debug) {
            $merged = TextHandler::fTrim(preg_replace('~/\*(.*?)\*/~us', '', $merged));
        }

        $callback = function (array $M): string {
            if (preg_match('/\.(gif|png|jpg|jpeg|svg|woff|woff2)$/ui', $M[1], $N) && str_starts_with($M[1], '/') && !str_starts_with($M[1], '//') && !str_contains($M[1], '..')) {
                $type = strtolower($N[1]);
                if ('jpg' === $type) {
                    $type = 'jpeg';
                } elseif ('svg' === $type) {
                    $type = 'svg+xml';
                }

                $file = sprintf('%s/%s', $this->docRoot, $M[1]);
                $size = @filesize($file);
                if (false !== $size && $size <= self::MAX_FILESIZE_TO_ENCODE) {
                    $data = FileHandler::get($file);
                    if (null !== $data) {
                        return sprintf('url(data:image/%s;base64,%s)', $type, base64_encode($data));
                    }
                }
            }

            return sprintf('url(%s)', $M[1]);
        };

        return (string) preg_replace_callback('/url\(\s*(.+?)\s*\)/u', $callback, $merged);
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
            $merged[] = FileHandler::get($file) ?? throw new RuntimeException(sprintf('Unable to read file %s', $file));
        }

        return implode("\n\n", $merged);
    }
}
