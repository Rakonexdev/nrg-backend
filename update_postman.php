<?php

$collection = json_decode(file_get_contents('postman_collection_full.json'), true);

$payloads = [
    'Auth - Login' => ['email' => 'admin@ggcs.com', 'password' => 'password'],
    'POST users' => ['name' => 'API Tester', 'email' => 'apitest@ggcs.com', 'password' => 'password', 'password_confirmation' => 'password'],
    'PUT users/{user}' => ['name' => 'API Tester Updated'],
    'PATCH users/{user}/status' => ['status' => 'active', 'withdrawal_reason' => 'testing'],
    'PUT roles/{role}/permissions' => ['permissions' => ['view-users']],
    'PUT settings/menus' => ['menus' => []],
    'POST companies' => ['name' => 'Test Company LLC'],
    'POST persons' => ['name' => 'Test Person', 'phone' => '1234567890', 'qatar_id' => '09876543210', 'id_expiration_date' => '2026-12-31', 'company_id' => 1],
    'PUT persons/{person}' => ['phone' => '0987654321'],
    'POST projects' => ['person_id' => 1, 'type' => 'fixed', 'status' => 'active', 'fixed_total_amount' => 50000],
    'PUT projects/{project}' => ['fixed_total_amount' => 60000],
    'PATCH projects/{project}/status' => ['status' => 'on_hold', 'withdrawal_reason' => 'Client paused'],
    'POST projects/{project}/professions' => ['profession_name' => 'Surveyor', 'hourly_rate' => 100, 'no_of_persons' => 2],
    'PUT projects/{project}/professions/{profession}' => ['hourly_rate' => 120],
    'POST timesheets' => ['project_id' => 1, 'date' => '2026-03-17', 'project_profession_id' => 1, 'type' => 'regular', 'regular_hours' => 8, 'persons_count' => 2],
    'PUT timesheets/{timesheet}' => ['regular_hours' => 10],
    'POST invoices' => ['project_id' => 1, 'invoice_date' => '2026-03-17', 'due_date' => '2026-04-17', 'total_amount' => 5000, 'status' => 'issued'],
    'POST collections' => ['invoice_id' => 1, 'collection_date' => '2026-03-18', 'amount_collected' => 5000, 'status' => 'verified'],
    'PATCH collections/{collection}/verify' => ['status' => 'verified'],
    'POST expense-categories' => ['name' => 'Equipment'],
    'PUT expense-categories/{category}' => ['name' => 'Equipment Rental'],
    'POST expenses' => ['expense_category_id' => 1, 'amount' => 500, 'expense_date' => '2026-03-17', 'description' => 'Tested equipment'],
];

$file_uploads = [
    'POST uploads/id-photo',
    'POST uploads/lpo',
    'POST uploads/invoice-copy',
    'POST uploads/expense-document'
];

$items_ref = &$collection['item'];
if (isset($collection['collection']['item'])) {
    $items_ref = &$collection['collection']['item'];
}

foreach ($items_ref as $index => &$item) {
    if ($item['name'] === 'POST logout') {
        unset($items_ref[$index]);
        continue;
    }

    if (isset($item['request']['url'])) {
        // Keep baseUrl intact
    }

    $name = $item['name'];
    
    if (in_array($name, $file_uploads)) {
        $item['request']['body'] = [
            'mode' => 'formdata',
            'formdata' => [
                [
                    'key' => 'file',
                    'type' => 'file',
                    'src' => 'dummy.pdf'
                ]
            ]
        ];
    } elseif (isset($payloads[$name])) {
        if (!isset($item['request']['body'])) {
            $item['request']['body'] = ['mode' => 'raw', 'raw' => ''];
        }
        $item['request']['body']['mode'] = 'raw';
        $item['request']['body']['raw'] = json_encode($payloads[$name], JSON_PRETTY_PRINT);
    }
}

$items_ref = array_values($items_ref);

// ensure variables are correct
$collection['variable'] = [
    [
        "key" => "baseUrl",
        "value" => "http://127.0.0.1:8000/api"
    ],
    [
        "key" => "token",
        "value" => ""
    ]
];

file_put_contents('postman_with_data.json', json_encode($collection, JSON_PRETTY_PRINT));
echo "Dummy data generated successfully.\n";
