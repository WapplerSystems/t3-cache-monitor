<?php

use WapplerSystems\CacheMonitor\Controller\CacheMonitorController;

return [
    'system_cachemonitor' => [
        'parent' => 'system',
        'access' => 'admin',
        'path' => '/module/system/cache-monitor',
        'iconIdentifier' => 'module-cachemonitor',
        'labels' => [
            'title' => 'LLL:EXT:cache_monitor/Resources/Private/Language/locallang_mod.xlf:module.title',
            'shortDescription' => 'LLL:EXT:cache_monitor/Resources/Private/Language/locallang_mod.xlf:module.shortDescription',
        ],
        'routes' => [
            '_default' => [
                'target' => CacheMonitorController::class . '::handleRequest',
            ],
        ],
    ],
];
