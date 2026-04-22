<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Cache Monitor',
    'description' => 'Cache monitoring dashboard, backend module and CLI command for TYPO3. Provides an overview of all registered caches with statistics.',
    'category' => 'module',
    'author' => 'WapplerSystems',
    'state' => 'stable',
    'version' => '13.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.0.0-13.4.99',
        ],
        'suggests' => [
            'dashboard' => '',
        ],
    ],
];
