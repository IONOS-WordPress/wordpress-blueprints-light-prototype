<?php

namespace ionos_wordpress_blueprints;

use const ionos_wordpress_blueprints\TASK_EXECUTION_FILTER_PREFIX;

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

\add_filter( TASK_EXECUTION_FILTER_PREFIX . '_get_option', function(array $payload) : array {
  return [
    'value' => \get_option($payload['args']['name']),
    'success' => true
  ];
});