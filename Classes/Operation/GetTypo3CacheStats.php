<?php
declare(strict_types=1);

namespace WapplerSystems\CacheMonitor\Operation;

/*
 * This file is part of the "cache_monitor" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

// Soft dependency on wapplersystems/zabbix_client. If the attribute or
// interface is missing, this file is a no-op and Symfony's DI compiler
// will not see the class.
if (!\class_exists(\WapplerSystems\ZabbixClient\Attribute\MonitoringOperation::class)
    || !\interface_exists(\WapplerSystems\ZabbixClient\Operation\IOperation::class)) {
    return;
}

use TYPO3\CMS\Core\SingletonInterface;
use WapplerSystems\CacheMonitor\Service\CacheStatisticsService;
use WapplerSystems\ZabbixClient\Attribute\MonitoringOperation;
use WapplerSystems\ZabbixClient\Operation\IOperation;
use WapplerSystems\ZabbixClient\OperationResult;

/**
 * Returns size + entry-count for every registered TYPO3 cache so Zabbix
 * can graph cache growth and alert on runaway caches (e.g. proxy_responses
 * growing without bound, OPcache full, etc.).
 *
 * Backends supported by entry/size: Database, File, SimpleFile, Apcu (entries),
 * Redis (entries). NullBackend reports 0/0. TransientMemory returns null/null.
 *
 * Output shape:
 *   {
 *     "totals": {"caches": int, "entries": int, "size_bytes": int},
 *     "caches": [
 *       {"identifier": "proxy_responses", "backend": "FileBackend",
 *        "entries": 440, "size_bytes": 33518592, "groups": ["all"]},
 *       ...
 *     ]
 *   }
 */
#[MonitoringOperation('GetTypo3CacheStats')]
class GetTypo3CacheStats implements IOperation, SingletonInterface
{
    public function __construct(
        private readonly CacheStatisticsService $statisticsService,
    ) {
    }

    public function execute(array $parameter = []): OperationResult
    {
        $statistics = $this->statisticsService->getAllCacheStatistics();

        $totalEntries = 0;
        $totalSize = 0;
        $caches = [];

        foreach ($statistics as $stat) {
            $entries = $stat['entries'];
            $size = $stat['size'];

            if ($entries !== null) {
                $totalEntries += $entries;
            }
            if ($size !== null) {
                $totalSize += $size;
            }

            $caches[] = [
                'identifier' => $stat['identifier'],
                'backend' => $stat['backend'],
                'entries' => $entries,
                'size_bytes' => $size,
                'groups' => array_values($stat['groups']),
            ];
        }

        return new OperationResult(true, [
            'totals' => [
                'caches' => count($caches),
                'entries' => $totalEntries,
                'size_bytes' => $totalSize,
            ],
            'caches' => $caches,
        ]);
    }
}
