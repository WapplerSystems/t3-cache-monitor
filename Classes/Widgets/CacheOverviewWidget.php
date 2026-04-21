<?php
declare(strict_types=1);

namespace WapplerSystems\CacheMonitor\Widgets;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Dashboard\Widgets\RequestAwareWidgetInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetConfigurationInterface;
use TYPO3\CMS\Dashboard\Widgets\WidgetInterface;
use TYPO3\CMS\Fluid\View\StandaloneView;
use WapplerSystems\CacheMonitor\Service\CacheStatisticsService;

class CacheOverviewWidget implements WidgetInterface, RequestAwareWidgetInterface
{
    private ServerRequestInterface $request;

    public function __construct(
        private readonly WidgetConfigurationInterface $configuration,
        private readonly CacheStatisticsService $statisticsService,
        private readonly array $options = [],
    ) {
    }

    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function renderWidgetContent(): string
    {
        $statistics = $this->statisticsService->getAllCacheStatistics();

        $totalEntries = 0;
        $totalSize = 0;
        foreach ($statistics as $stat) {
            $totalEntries += $stat['entries'] ?? 0;
            $totalSize += $stat['size'] ?? 0;
        }

        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplatePathAndFilename(
            GeneralUtility::getFileAbsFileName('EXT:cache_monitor/Resources/Private/Templates/Widget/CacheOverviewWidget.html')
        );
        $view->assignMultiple([
            'statistics' => $statistics,
            'totalEntries' => $totalEntries,
            'totalSize' => CacheStatisticsService::formatBytes($totalSize),
            'totalCaches' => count($statistics),
            'configuration' => $this->configuration,
            'options' => $this->options,
        ]);
        return $view->render();
    }

    public function getOptions(): array
    {
        return $this->options;
    }
}
