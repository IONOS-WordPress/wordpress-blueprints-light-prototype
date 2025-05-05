<?php

namespace ionos_blueprints_light\ionos_blueprints_light\phpunit;

use const ionos_blueprints_light\ionos_blueprints_light\blueprints\CRON_JOB_HOOK;
use const ionos_blueprints_light\ionos_blueprints_light\blueprints\OPTION_JOBS_SCHEDULED;
use const ionos_blueprints_light\ionos_blueprints_light\SLUG;

require_once __DIR__ . '/../blueprints-light.php';

class DeactivatePluginJobTest extends \WP_UnitTestCase {

  public function setUp(): void {
    parent::set_up();

    \activate_plugin( SLUG );
  }

  public function tearDown(): void {
    parent::tear_down();

    \deactivate_plugins(SLUG);
  }

  function test_deactivate_plugin_job() {
    \update_option(OPTION_JOBS_SCHEDULED, [
      [
        'id' => $uuids[]=\wp_generate_uuid4(),
        'type' => 'deactivate_plugin',
        'args' => [
          'slug'=> SLUG
        ]
      ],
    ]);

    // trigger init to initialize our plugin 
    \do_action( 'init' );
    // verify our plugin initialized its cron job
    $this->assertTrue( \wp_next_scheduled(CRON_JOB_HOOK) !== false, 'cron job should be cleaned up' );

    // execute cron job the first time
    \do_action(CRON_JOB_HOOK);

    // verify our plugin is no more active
    $this->assertFalse( \is_plugin_active(SLUG), 'plugin should be deactivated' );

    // verify the plugin deactivation hook was properly called
    $this->assertFalse( \wp_next_scheduled(CRON_JOB_HOOK), 'cron job should be cleaned up' );
  }

  function test_deactivate_job_silent() {
    \update_option(OPTION_JOBS_SCHEDULED, [
      [
        'id' => $uuids[]=\wp_generate_uuid4(),
        'type' => 'deactivate_plugin',
        'args' => [
          'slug'=> SLUG,
          'silent' => true,
        ]
      ],
    ]);

    // trigger init to initialize our plugin 
    \do_action( 'init' );
    // verify our plugin initialized its cron job
    $this->assertTrue( \wp_next_scheduled(CRON_JOB_HOOK) !== false, 'cron job should be cleaned up' );

    // execute cron job the first time
    \do_action(CRON_JOB_HOOK);

    // verify our plugin is no more active
    $this->assertFalse( \is_plugin_active(SLUG), 'plugin should be deactivated' );

    // verify the plugin deactivation hook was not called
    $this->assertTrue( \wp_next_scheduled(CRON_JOB_HOOK)!==false, 'cron job should not be be cleaned up' );
  }
}