<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Spatie\Permission\Models\Role;

try {
    $role = Role::firstOrCreate(['name' => 'Collector', 'guard_name' => 'web']);
    
    $user = User::firstOrCreate(
        ['email' => 'collector@nrg.com'],
        [
            'name' => 'Collector User',
            'password' => bcrypt('collector123'),
        ]
    );

    if (! \Hash::check('collector123', $user->password)) {
        $user->password = bcrypt('collector123');
        $user->save();
    }
    
    $user->assignRole($role);
    echo "User collector@nrg.com created successfully with password 'collector123'.\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
