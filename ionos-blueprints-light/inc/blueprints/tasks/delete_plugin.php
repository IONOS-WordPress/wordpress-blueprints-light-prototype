<?php

namespace ionos_blueprints_light\ionos_blueprints_light\blueprints;

use const ionos_blueprints_light\ionos_blueprints_light\blueprints\CRON_JOB_HOOK;

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

\add_filter( CRON_JOB_HOOK . '_delete_plugin', function(array $payload) : array {
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
          CRON_JOB_HOOK,
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
        CRON_JOB_HOOK,
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