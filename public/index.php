<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    date_default_timezone_set($context['APP_TIMEZONE'] ?? 'Europe/London');

    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
