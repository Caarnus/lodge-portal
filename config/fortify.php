<?php

use Laravel\Fortify\Features;

$config = require base_path('vendor/laravel/fortify/config/fortify.php');
$config['features'] = [Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true])];
$config['home'] = '/dashboard';

return $config;
