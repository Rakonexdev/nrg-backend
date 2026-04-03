<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$routes = app('router')->getRoutes()->getRoutes();

$items = [];

// Login item
$items[] = [
    'name' => 'Auth - Login',
    'event' => [
        [
            'listen' => 'test',
            'script' => [
                'type' => 'text/javascript',
                'exec' => [
                    "var jsonData = pm.response.json();",
                    "if(jsonData.token) { pm.collectionVariables.set('token', jsonData.token); }"
                ]
            ]
        ]
    ],
    'request' => [
        'method' => 'POST',
        'header' => [
            ['key' => 'Accept', 'value' => 'application/json'],
            ['key' => 'Content-Type', 'value' => 'application/json']
        ],
        'body' => [
            'mode' => 'raw',
            'raw' => json_encode(['email' => 'admin@ggcs.com', 'password' => 'password'])
        ],
        'url' => [
            'raw' => '{{baseUrl}}/login',
            'host' => ['{{baseUrl}}'],
            'path' => ['login']
        ]
    ]
];

foreach ($routes as $route) {
    if (!str_starts_with($route->uri(), 'api/')) continue;
    if ($route->uri() === 'api/login') continue; // already added

    $method = $route->methods()[0];
    if ($method === 'HEAD' || $method === 'OPTIONS') continue;

    $path = str_replace('api/', '', $route->uri());
    $name = strtoupper($method) . ' ' . $path;

    // Convert parameter syntax from Laravel {param} to Postman :param
    $postmanPath = preg_replace('/\{([^\}]+)\}/', ':$1', $path);
    $pathParts = explode('/', $postmanPath);
    
    $variables = [];
    foreach ($pathParts as $part) {
        if (str_starts_with($part, ':')) {
            $variables[] = [
                'key' => substr($part, 1),
                'value' => '1' // placeholder
            ];
        }
    }

    $item = [
        'name' => $name,
        'request' => [
            'auth' => [
                'type' => 'bearer',
                'bearer' => [
                    ['key' => 'token', 'value' => '{{token}}', 'type' => 'string']
                ]
            ],
            'method' => $method,
            'header' => [
                ['key' => 'Accept', 'value' => 'application/json']
            ],
            'url' => [
                'raw' => '{{baseUrl}}/' . $postmanPath,
                'host' => ['{{baseUrl}}'],
                'path' => array_map(function($p) { return str_starts_with($p, ':') ? $p : $p; }, $pathParts)
            ]
        ]
    ];

    if (!empty($variables)) {
        $item['request']['url']['variable'] = $variables;
    }

    if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
        $item['request']['header'][] = ['key' => 'Content-Type', 'value' => 'application/json'];
        $item['request']['body'] = [
            'mode' => 'raw',
            'raw' => '{}'
        ];
    }

    $items[] = $item;
}

$collection = [
    'info' => [
        'name' => 'GGCS API Tests - Full',
        'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json'
    ],
    'item' => $items,
    'variable' => [
        ['key' => 'baseUrl', 'value' => 'http://localhost:8000/api'],
        ['key' => 'token', 'value' => '']
    ]
];

file_put_contents(__DIR__.'/postman_collection_full.json', json_encode(['collection' => $collection], JSON_PRETTY_PRINT));
echo "Done\n";
