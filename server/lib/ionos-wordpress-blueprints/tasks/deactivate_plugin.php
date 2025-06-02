<?php

namespace ionos_wordpress_blueprints;

use const ionos_wordpress_blueprints\CRON_JOB_HOOK;

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

\add_filter( CRON_JOB_HOOK . '_deactivate_plugin', function(array $payload) : array {
  $args = $payload['args'];
  
  $slug = $args['slug'];
  $silent = $args['silent'] ?? false;

  \deactivate_plugins($slug, $silent);
   
  return [
    'success' => true,
  ];
});