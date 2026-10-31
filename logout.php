<?php
// logout.php
declare(strict_types=1);

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/includes/auth.php';

logout_user();
flash_message('success', 'You have been successfully logged out.');
header('Location: ' . BASE_URL . '/index.php');
exit;
