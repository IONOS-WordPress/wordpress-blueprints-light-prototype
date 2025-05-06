<?php

// const ABSPATH = '/var/www/html/';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$route_php = __DIR__ . '/../api' . $path . '.php';

header('X-Route-File: ' . $route_php);

if($path === '/') {
  index();
} else if ( file_exists( $route_php) ) {
  include $route_php;
} else {
  not_found();
}

function not_found() {
  http_response_code(404);
  echo '404 - Route "' . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) . '" does not exist.' . PHP_EOL;
}

function index() {
  echo "hello from index" . PHP_EOL;
}

//(preg_match('/^\/api\/v1\/.*$/', $path)) {