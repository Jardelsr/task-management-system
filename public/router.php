<?php

/**
 * Router script for PHP built-in server
 * 
 * This script handles routing for the PHP built-in development server,
 * specifically to fix issues with .json file extensions being treated
 * as static files instead of being routed through the application.
 */

$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = $_SERVER['SCRIPT_NAME'];

// Remove query string from request URI
$path = parse_url($requestUri, PHP_URL_PATH);

// Check if the request is for a static file that actually exists
$publicPath = __DIR__ . $path;
if ($path !== '/' && file_exists($publicPath) && is_file($publicPath)) {
    // Let the built-in server handle actual static files
    return false;
}

// For all other requests (including .json routes), pass to index.php
$_SERVER['SCRIPT_NAME'] = '/index.php';

// Include the main application entry point
require __DIR__ . '/index.php';