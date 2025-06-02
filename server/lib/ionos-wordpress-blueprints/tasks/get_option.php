<?php

namespace ionos_wordpress_blueprints;

use const ionos_wordpress_blueprints\CRON_JOB_HOOK;

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

\add_filter( CRON_JOB_HOOK . '_get_option', function(array $payload) : array {
  return [
    'value' => \get_option($payload['args']['name']),
    'success' => true
  ];
});