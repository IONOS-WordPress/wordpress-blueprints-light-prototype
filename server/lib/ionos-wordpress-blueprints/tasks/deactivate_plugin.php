<?php

namespace ionos_wordpress_blueprints;

use const ionos_wordpress_blueprints\TASK_EXECUTION_FILTER_PREFIX;

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

\add_filter( TASK_EXECUTION_FILTER_PREFIX . '_deactivate_plugin', function(array $payload) : array {
  $args = $payload['args'];
  
  $slug = $args['slug'];
  $silent = $args['silent'] ?? false;

  \deactivate_plugins($slug, $silent);
   
  return [
    'success' => true,
  ];
});