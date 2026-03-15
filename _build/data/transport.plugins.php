<?php
declare(strict_types=1);

return [
    [
        'name' => 'TwigCacheClear',
        'description' => 'Clears compiled Twig templates when MODX cache is refreshed.',
        'file' => 'elements/plugins/TwigCacheClear.php',
        'events' => [
            [
                'event' => 'OnSiteRefresh',
                'priority' => 0,
                'propertyset' => 0,
            ],
        ],
    ],
    [
        'name' => 'TwigContentBlocks',
        'description' => 'Parses ContentBlocks markup through Twig before ContentBlocks returns it.',
        'file' => 'elements/plugins/TwigContentBlocks.php',
        'events' => [
            [
                'event' => 'ContentBlocks_BeforeParse',
                'priority' => 0,
                'propertyset' => 0,
            ],
        ],
    ],
];

