<?php

namespace ionos_blueprints_light\ionos_blueprints_light\blueprints;

use const ionos_blueprints_light\ionos_blueprints_light\blueprints\CRON_JOB_HOOK;

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

\add_filter( CRON_JOB_HOOK . '_set_option', function(array $payload) : array {
  $args = $payload['args'];
  
  if( !isset($args['name']) || !isset($args['value'])) {
    return _create_job_error(
      sprintf(
        '%s : job "%s" requires "name" and "value" in args. payload was %s',
        CRON_JOB_HOOK,
        $payload['type'],
        \wp_json_encode($payload)
      ),
      $payload
    );
  }
  
  $success = \update_option($args['name'], $args['value'], $args['autoload'] ?? false);

  return [
    'success' => $success
  ];
});