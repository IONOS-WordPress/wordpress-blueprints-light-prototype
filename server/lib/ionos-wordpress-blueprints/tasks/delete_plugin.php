<?php

namespace ionos_wordpress_blueprints;

use const ionos_wordpress_blueprints\TASK_EXECUTION_FILTER_PREFIX;

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

\add_filter( TASK_EXECUTION_FILTER_PREFIX . '_delete_plugin', function(array $payload) : array {
  $args = $payload['args'];
  
  $slug = $args['slug'];
  $force = $args['force'] ?? false;

  if(\is_plugin_active($slug)) {
    if($force) {
      \deactivate_plugins($slug);  
    } else {
      return _create_task_error(
        sprintf(
          '%s : task "%s" failed to delete plugin "%s". Plugin is still active.',
          TASK_EXECUTION_FILTER_PREFIX,
          $payload['type'],
          $slug,
        ),
        $payload
      );
    }
  }

  $success = \delete_plugins([ $slug ]);

  if($success !== true && $force===false) {
    return _create_task_error(
      sprintf(
        '%s : task "%s" failed to delete plugin "%s". ',
        TASK_EXECUTION_FILTER_PREFIX,
        $payload['type'],
        $slug,
      \is_wp_error($success) ? $success->get_error_message() : 'unknown error',
      ),
      $payload
    );
  }
    
  return [
    'success' => $force || $success ? true : false,
  ];
});