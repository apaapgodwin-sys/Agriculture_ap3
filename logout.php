<?php
declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

logout();
flash('info', 'You have been successfully signed out.');
redirect('login.php');
