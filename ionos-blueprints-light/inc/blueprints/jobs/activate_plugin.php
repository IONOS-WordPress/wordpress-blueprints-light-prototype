<?php

namespace ionos_blueprints_light\ionos_blueprints_light\blueprints;

use const ionos_blueprints_light\ionos_blueprints_light\blueprints\CRON_JOB_HOOK;

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

\add_filter( CRON_JOB_HOOK . '_activate_plugin', function(array $payload) : array {
  $args = $payload['args'];
  
  $slug = $args['slug'];
  $force = $args['force'] ?? false;

  $success = \activate_plugin($slug);

  if($success !== null && $force===false) {
    return _create_job_error(
      sprintf(
        '%s : job "%s" failed to activate plugin "%s". ',
        CRON_JOB_HOOK,
        $payload['type'],
        $slug,
      \is_wp_error($success) ? $success->get_error_message() : 'unknown error',
      ),
      $payload
    );
  }
    
  return [
    'success' => $force || $success===false ? true : false,
  ];
});