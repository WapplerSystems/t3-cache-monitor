<?php
declare(strict_types=1);

namespace WapplerSystems\CacheMonitor\Service;

use TYPO3\CMS\Core\Cache\Backend\ApcuBackend;
use TYPO3\CMS\Core\Cache\Backend\FileBackend;
use TYPO3\CMS\Core\Cache\Backend\NullBackend;
use TYPO3\CMS\Core\Cache\Backend\RedisBackend;
use TYPO3\CMS\Core\Cache\Backend\SimpleFileBackend;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Database\ConnectionPool;

class CacheStatisticsService
{
    public function __construct(
        private readonly CacheManager $cacheManager,
        private readonly ConnectionPool $connectionPool,
    ) {
    }

    /**
     * @return array<string, array{identifier: string, backend: string, entries: int|null, size: int|null, groups: array}>
     */
    public function getAllCacheStatistics(): array
    {
        $statistics = [];
        $cacheConfigurations = $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations'] ?? [];

        foreach (array_keys($cacheConfigurations) as $identifier) {
            try {
                $cache = $this->cacheManager->getCache($identifier);
            } catch (\Exception) {
                continue;
            }

            $backend = $cache->getBackend();
            $backendClass = get_class($backend);
            $shortBackend = $this->getShortBackendName($backendClass);
            $groups = $cacheConfigurations[$identifier]['groups'] ?? ['all'];

            $entries = null;
            $size = null;

            try {
                if ($backend instanceof NullBackend) {
                    $entries = 0;
                    $size = 0;
                } elseif ($backend instanceof Typo3DatabaseBackend) {
                    [$entries, $size] = $this->getDatabaseBackendStats($backend);
                } elseif ($backend instanceof SimpleFileBackend) {
                    [$entries, $size] = $this->getFileBackendStats($backend->getCacheDirectory());
                } elseif ($backend instanceof FileBackend) {
                    [$entries, $size] = $this->getFileBackendStats($backend->getCacheDirectory());
                } elseif ($backend instanceof RedisBackend) {
                    $entries = $this->getRedisBackendEntries($backend, $identifier);
                } elseif ($backend instanceof ApcuBackend) {
                    $entries = $this->getApcuBackendEntries($identifier);
                }
            } catch (\Throwable) {
                // Stats collection failed - leave as null
            }

            $statistics[$identifier] = [
                'identifier' => $identifier,
                'backend' => $shortBackend,
                'backendClass' => $backendClass,
                'entries' => $entries,
                'size' => $size,
                'groups' => $groups,
            ];
        }

        ksort($statistics);
        return $statistics;
    }

    /**
     * @return array{int, int}
     */
    private function getDatabaseBackendStats(Typo3DatabaseBackend $backend): array
    {
        $tableName = $backend->getCacheTable();
        $connection = $this->connectionPool->getConnectionForTable($tableName);

        try {
            $count = (int)$connection->count('*', $tableName, []);
        } catch (\Throwable) {
            return [0, 0];
        }

        $size = 0;
        try {
            $result = $connection->executeQuery(
                'SELECT SUM(LENGTH(content)) as total_size FROM ' . $connection->quoteIdentifier($tableName)
            );
            $row = $result->fetchAssociative();
            $size = (int)($row['total_size'] ?? 0);
        } catch (\Throwable) {
            // Size calculation not available
        }

        return [$count, $size];
    }

    /**
     * @return array{int, int}
     */
    private function getFileBackendStats(string $cacheDirectory): array
    {
        if (!is_dir($cacheDirectory)) {
            return [0, 0];
        }

        $entries = 0;
        $size = 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($cacheDirectory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $entries++;
                $size += $file->getSize();
            }
        }

        return [$entries, $size];
    }

    private function getRedisBackendEntries(RedisBackend $backend, string $identifier): ?int
    {
        // Redis backend doesn't expose the connection directly,
        // so we try to use reflection to access the redis instance
        try {
            $reflection = new \ReflectionClass($backend);
            $property = $reflection->getProperty('redis');
            $property->setAccessible(true);
            $redis = $property->getValue($backend);
            if ($redis instanceof \Redis) {
                return $redis->dbSize();
            }
        } catch (\Throwable) {
        }
        return null;
    }

    private function getApcuBackendEntries(string $identifier): ?int
    {
        if (!function_exists('apcu_cache_info')) {
            return null;
        }
        try {
            $info = apcu_cache_info(true);
            return $info['num_entries'] ?? null;
        } catch (\Throwable) {
        }
        return null;
    }

    public function flushCache(string $identifier): void
    {
        $cache = $this->cacheManager->getCache($identifier);
        $cache->flush();
    }

    public static function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = (int)floor(log($bytes, 1024));
        $i = min($i, count($units) - 1);
        return round($bytes / (1024 ** $i), 2) . ' ' . $units[$i];
    }

    private function getShortBackendName(string $className): string
    {
        $parts = explode('\\', $className);
        return end($parts);
    }
}
