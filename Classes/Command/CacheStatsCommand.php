<?php
declare(strict_types=1);

namespace WapplerSystems\CacheMonitor\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WapplerSystems\CacheMonitor\Service\CacheStatisticsService;

class CacheStatsCommand extends Command
{
    public function __construct(private readonly CacheStatisticsService $statisticsService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Show statistics for all registered TYPO3 caches');
        $this->setHelp('Displays an overview of all TYPO3 caches with their backend type, entry count and size.');
        $this->addOption('group', 'g', InputOption::VALUE_OPTIONAL, 'Filter by cache group (e.g. "pages", "system", "all")');
        $this->addOption('sort', 's', InputOption::VALUE_OPTIONAL, 'Sort by column: name, backend, entries, size', 'name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('TYPO3 Cache Statistics');

        $statistics = $this->statisticsService->getAllCacheStatistics();

        $filterGroup = $input->getOption('group');
        if ($filterGroup) {
            $statistics = array_filter($statistics, function (array $stat) use ($filterGroup) {
                return in_array($filterGroup, $stat['groups'], true);
            });
        }

        $sortBy = $input->getOption('sort');
        $statistics = $this->sortStatistics($statistics, $sortBy);

        if (empty($statistics)) {
            $io->warning('No caches found' . ($filterGroup ? " in group \"{$filterGroup}\"" : '') . '.');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Cache', 'Backend', 'Entries', 'Size', 'Groups']);

        $totalEntries = 0;
        $totalSize = 0;

        foreach ($statistics as $stat) {
            $entries = $stat['entries'];
            $size = $stat['size'];

            if ($entries !== null) {
                $totalEntries += $entries;
            }
            if ($size !== null) {
                $totalSize += $size;
            }

            $table->addRow([
                $stat['identifier'],
                $stat['backend'],
                $entries !== null ? number_format($entries) : '<fg=yellow>n/a</>',
                $size !== null ? CacheStatisticsService::formatBytes($size) : '<fg=yellow>n/a</>',
                implode(', ', $stat['groups']),
            ]);
        }

        $table->addRow(['', '', '', '', '']);
        $table->addRow([
            '<info>Total (' . count($statistics) . ' caches)</info>',
            '',
            '<info>' . number_format($totalEntries) . '</info>',
            '<info>' . CacheStatisticsService::formatBytes($totalSize) . '</info>',
            '',
        ]);

        $table->render();

        return Command::SUCCESS;
    }

    private function sortStatistics(array $statistics, string $sortBy): array
    {
        usort($statistics, function (array $a, array $b) use ($sortBy) {
            return match ($sortBy) {
                'backend' => strcmp($a['backend'], $b['backend']),
                'entries' => ($b['entries'] ?? -1) <=> ($a['entries'] ?? -1),
                'size' => ($b['size'] ?? -1) <=> ($a['size'] ?? -1),
                default => strcmp($a['identifier'], $b['identifier']),
            };
        });
        return $statistics;
    }
}
