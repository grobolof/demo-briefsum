<?php

use App\Kernel;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return static function (array $context) {
    return new Kernel($context['APP_ENV'], filter_var($context['APP_DEBUG'], FILTER_VALIDATE_BOOL));
};
