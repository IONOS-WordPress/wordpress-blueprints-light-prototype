<?php

namespace ionos_blueprints_light\ionos_blueprints_light\blueprints;

use const ionos_blueprints_light\ionos_blueprints_light\blueprints\CRON_JOB_HOOK;

\add_filter( CRON_JOB_HOOK . '_activate_plugin', function(array $payload) : array {
  $args = $payload['args'];
  
  if( !isset($args['slug'])) {
    return _create_job_error(
      sprintf(
        '%s : job "%s" requires "slug" in args. payload was %s',
        CRON_JOB_HOOK,
        $payload['type'],
        \wp_json_encode($payload)
      ),
      $payload
    );
  }
  
  $slug = $args['slug'];
  $force = $args['force'] ?? false;

  $success = \activate_plugin($slug); 

  if($success !== true && $force===false) {
    return _create_job_error(
      sprintf(
        '%s : job "%s" failed to unzip plugin "%s". ',
        CRON_JOB_HOOK,
        $payload['type'],
        $slug,
      \is_wp_error($success) ? $success->get_error_message() : 'unknown error',
      ),
      $payload
    );
  }
    
  return [
    'success' => $force ? true : $success,
  ];
});