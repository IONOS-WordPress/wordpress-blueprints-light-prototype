<?php

namespace ionos_wordpress_blueprints;

use const ionos_wordpress_blueprints\TASK_EXECUTION_FILTER_PREFIX;

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

\add_filter( TASK_EXECUTION_FILTER_PREFIX . '_delete_option', function(array $payload) : array {
  $args = $payload['args'];
  
  $success = \delete_option($args['name']);

  return [
    'success' => $success
  ];
});