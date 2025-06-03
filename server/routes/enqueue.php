<?php

use function ionos_wordpress_blueprints\execute_tasks;
use function ionos_wordpress_blueprints\validate_tasks;

function send_success(array|null $payload=null, string $message='success', int $status=200) : void {
  header('Content-Type: application/json');
  http_response_code($status);
  echo json_encode([
    'success' => $message,
    'payload' => $payload,
  ]);
}

function send_error(array|null $payload=null, string $message='Error occured', int $status=500) : void {
  header('Content-Type: application/json');
  http_response_code($status);
  echo json_encode([
    "error" => $message,
    "payload" => $payload,
  ]);
}

// Ensure the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  send_error(message : 'Method Not Allowed', status : 405);
  exit;
}

// get json payload
$input = file_get_contents('php://input');
if ($input === false) {
  send_error(message : "Failed to read input");
  exit;
}

$payload = $input==='' ? [] : json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
  send_error(message : 'Failed to decode JSON: ' . json_last_error_msg(),);
  exit;
}

# load wordpress
require_once '/var/www/html/wp-load.php';
# load our blueprints task engine
require_once __DIR__ . '/../lib/ionos-wordpress-blueprints/index.php';

$validated_tasks = validate_tasks($payload);

if (\is_wp_error($validated_tasks)) {
  $payload = $validated_tasks->get_error_data();
  if (!is_null($payload) && !is_array($payload)) {
    $payload = [ 'data' => $payload ];
  }

  send_error(
    payload : $payload,
    message : $validated_tasks->get_error_message(), 
    status : 400,
  );
  exit;
}

$task_results = execute_tasks($validated_tasks);

send_success(
  payload : $task_results, 
  message: 'Tasks processed successfully'
);