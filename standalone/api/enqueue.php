<?php

use function ionos_blueprints_light\ionos_blueprints_light\blueprints\_get_jobs_done;
use function ionos_blueprints_light\ionos_blueprints_light\blueprints\enqueue_jobs;

use const ionos_blueprints_light\ionos_blueprints_light\blueprints\CRON_JOB_HOOK;
use const ionos_blueprints_light\ionos_blueprints_light\blueprints\OPTION_JOBS_DONE;

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
require_once __DIR__ . '/../../wp-load.php';
# load our blueprints job scheduler
require_once __DIR__ . '/../../wp-content/plugins/ionos-blueprints-light/inc/blueprints/index.php';

# disable cron job execution time limit since we are running not in the WP context
define('CRON_JOB_MAX_EXECUTION_TIME', -1);
# set_time_limit(0);

$validation_result = enqueue_jobs($payload);

if (\is_wp_error($validation_result)) {
  $payload = $validation_result->get_error_data();
  if (!is_null($payload) && !is_array($payload)) {
    $payload = [ 'data' => $payload ];
  }

  send_error(
    payload : $payload,
    message : $validation_result->get_error_message(), 
    status : 400,
  );
  exit;
}

\do_action(CRON_JOB_HOOK);

$jobs_done = _get_jobs_done();

# cleanup jobs done
\delete_option(OPTION_JOBS_DONE);

send_success(
  payload : $jobs_done, 
  message: 'Jobs processed successfully'
);