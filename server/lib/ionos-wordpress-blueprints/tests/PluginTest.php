<?php

namespace ionos_blueprints_light\ionos_blueprints_light\phpunit;

// $WP_TESTS_DIR = getenv('WP_TESTS_DIR');
// require_once '~/.composer/vendor/autoload.php';
// // Give access to tests_add_filter() function.
// require_once $WP_TESTS_DIR . '/includes/functions.php';

// require $WP_TESTS_DIR . '/includes/bootstrap.php';

use const ionos_wordpress_blueprints\CRON_JOB_HOOK;
use const ionos_blueprints_light\ionos_blueprints_light\SLUG;

require_once __DIR__ . '/../index.php';

class PluginTest extends \WP_UnitTestCase {
  /*
    basic cron functionality checks 
  */
  function test_initial_settings_after_installation() {   
    $this->assertFalse(\wp_next_scheduled(CRON_JOB_HOOK) !== false, 'The cron task is not yet scheduled before "init" action.');

    \do_action( 'init' );
    $this->assertTrue(\wp_next_scheduled(CRON_JOB_HOOK) !== false, 'The cron task is scheduled after "init" action.');
    
    \deactivate_plugins(SLUG);
    $this->assertFalse(\is_plugin_active(SLUG), 'The plugin is deactivated when plugin was deactivated.');

    $this->assertFalse(\wp_next_scheduled(CRON_JOB_HOOK) !== false, 'The cron task is no more scheduled.');
  }
}