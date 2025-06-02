<?php

require_once __DIR__ . '/../../lib/constants.php';
require_once LIB_PATH . '/bootstrap.php';

use Models\User;
use Illuminate\Support\Carbon;

$activeSince = Carbon::now()->subMinutes(5);
$users = User::where('last_active', '>=', $activeSince)
    ->orderBy('last_active', 'desc')
    ->get(['user_id', 'nickname', 'last_active']);

echo json_encode(['users' => $users]);
