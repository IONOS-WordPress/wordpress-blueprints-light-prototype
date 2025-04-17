<?php

// $WP_TESTS_DIR = getenv('WP_TESTS_DIR');
// require_once '~/.composer/vendor/autoload.php';
// // Give access to tests_add_filter() function.
// require_once $WP_TESTS_DIR . '/includes/functions.php';

// require $WP_TESTS_DIR . '/includes/bootstrap.php';

use const ionos_blueprints_light\ionos_blueprints_light\blueprints\CRON_JOB_HOOK;
use const ionos_blueprints_light\ionos_blueprints_light\blueprints\OPTION_JOBS_SCHEDULED;

class PluginTest extends \WP_UnitTestCase {

  public function setUp(): void {
    \activate_plugin( 'ionos-blueprints-light/blueprints-light.php' );
  }

  function test_initial_settings_after_installation() {
    $this->assertFalse(\wp_next_scheduled(CRON_JOB_HOOK) !== false, 'The cron job is not yet scheduled.');

    \do_action( 'init' );
    $this->assertTrue(\wp_next_scheduled(CRON_JOB_HOOK) !== false, 'The cron job is scheduled.');
    
    \deactivate_plugins('ionos-blueprints-light/blueprints-light.php');
    $this->assertFalse(\is_plugin_active('ionos-blueprints-light/blueprints-light.php'), 'The plugin is deactivated.');

    $this->assertFalse(\wp_next_scheduled(CRON_JOB_HOOK) !== false, 'The cron job is no more scheduled.');
  }

	function test_no_jobs() {

    // // Replace this with some actual testing code.
    // $siteurl = \get_option('siteurl');
    // $this->assertEquals( 'http://localhost:8889', $siteurl );

    // $this->assertTrue(\is_plugin_active('ionos-blueprints-light/blueprints-light.php'));
    

		$this->assertTrue( true );

    
	}
}