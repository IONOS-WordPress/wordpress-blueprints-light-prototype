<?php

namespace ionos_blueprints_light\ionos_blueprints_light\blueprints;

use const ionos_blueprints_light\ionos_blueprints_light\blueprints\CRON_JOB_HOOK;

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

\add_filter( CRON_JOB_HOOK . '_delete_option', function(array $payload) : array {
  $args = $payload['args'];
  
  $success = \delete_option($args['name']);

  return [
    'success' => $success
  ];
});