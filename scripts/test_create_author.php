<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

try {
    $data = [
        'firstname' => 'TestAuth',
        'lastname' => 'User',
        'email' => 'testauthor+' . time() . '@example.com',
        'password' => 'Password123!',
        'role_id' => 2,
        'is_active' => false,
        'status' => 'in_review',
    ];

    $user = User::create($data);
    echo "Created user ID: " . $user->id . PHP_EOL;
    echo json_encode($user->toArray(), JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . PHP_EOL;
}
