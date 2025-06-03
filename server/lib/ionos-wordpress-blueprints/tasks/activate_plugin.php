<?php

namespace ionos_wordpress_blueprints;

use const ionos_wordpress_blueprints\TASK_EXECUTION_FILTER_PREFIX;

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

\add_filter( TASK_EXECUTION_FILTER_PREFIX . '_activate_plugin', function(array $payload) : array {
  $args = $payload['args'];
  
  $slug = $args['slug'];
  $force = $args['force'] ?? false;

  $success = \activate_plugin($slug);

  if($success !== null && $force===false) {
    return _create_task_error(
      sprintf(
        '%s : task "%s" failed to activate plugin "%s". ',
        TASK_EXECUTION_FILTER_PREFIX,
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