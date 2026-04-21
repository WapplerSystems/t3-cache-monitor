<?php
declare(strict_types=1);

namespace WapplerSystems\CacheMonitor\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder as BackendUriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\RedirectResponse;
use WapplerSystems\CacheMonitor\Service\CacheStatisticsService;

#[AsController]
class CacheMonitorController
{
    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly CacheStatisticsService $statisticsService,
        private readonly BackendUriBuilder $backendUriBuilder,
    ) {
    }

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $action = $request->getQueryParams()['action'] ?? 'index';

        if ($action === 'flush') {
            return $this->flushAction($request);
        }

        return $this->indexAction($request);
    }

    private function indexAction(ServerRequestInterface $request): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $statistics = $this->statisticsService->getAllCacheStatistics();

        $totalEntries = 0;
        $totalSize = 0;
        foreach ($statistics as $stat) {
            $totalEntries += $stat['entries'] ?? 0;
            $totalSize += $stat['size'] ?? 0;
        }

        // Build flush URLs for each cache
        foreach ($statistics as $identifier => &$stat) {
            $stat['flushUrl'] = (string)$this->backendUriBuilder->buildUriFromRoute(
                'system_cachemonitor',
                ['action' => 'flush', 'identifier' => $identifier]
            );
        }
        unset($stat);

        $flashMessage = $request->getQueryParams()['flushed'] ?? '';

        $moduleTemplate->assignMultiple([
            'statistics' => $statistics,
            'totalEntries' => $totalEntries,
            'totalSize' => CacheStatisticsService::formatBytes($totalSize),
            'totalCaches' => count($statistics),
            'flushedCache' => $flashMessage,
        ]);

        return $moduleTemplate->renderResponse('CacheMonitor/Index');
    }

    private function flushAction(ServerRequestInterface $request): ResponseInterface
    {
        $identifier = $request->getQueryParams()['identifier'] ?? '';
        $flushed = '';

        if ($identifier !== '') {
            try {
                $this->statisticsService->flushCache($identifier);
                $flushed = $identifier;
            } catch (\Throwable) {
            }
        }

        $redirectUri = (string)$this->backendUriBuilder->buildUriFromRoute(
            'system_cachemonitor',
            $flushed !== '' ? ['flushed' => $flushed] : []
        );

        return new RedirectResponse($redirectUri, 303);
    }
}
