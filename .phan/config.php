<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config-library.php';

// Suppress PHP 8.0+ issue on PHP 7.4 compatibility code
$cfg['suppress_issue_types'][] = 'PhanDeprecatedFunctionInternal';

// T311928 - ReturnTypeWillChange only exists in PHP >= 8.1; seen as a comment on PHP < 8.0
$cfg['file_list'] = array_merge(
	$cfg['file_list'],
	class_exists( ReturnTypeWillChange::class ) ? [] : [ '.phan/stubs/ReturnTypeWillChange.php' ]
);

return $cfg;
