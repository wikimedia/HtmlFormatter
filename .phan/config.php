<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config-library.php';

// Suppress PHP 8.0+ issue on PHP 7.4 compatibility code
$cfg['suppress_issue_types'][] = 'PhanDeprecatedFunctionInternal';

return $cfg;
