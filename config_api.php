<?php

if (file_exists(__DIR__ . '/_frontaccounting')) {
	$rootPath = realpath(__DIR__ . '/_frontaccounting');
} else {
	$rootPath = realpath(__DIR__ . '../../');
}
$path_to_root = realpath(__DIR__ . '../../');
define('API_ROOT', $rootPath . '/faapi');
define('FA_ROOT', $rootPath);

