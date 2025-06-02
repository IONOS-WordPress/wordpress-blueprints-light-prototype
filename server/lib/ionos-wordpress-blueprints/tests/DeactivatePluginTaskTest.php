<?php

namespace ionos_blueprints_light\ionos_blueprints_light\phpunit;

use const ionos_wordpress_blueprints\CRON_JOB_HOOK;
use const ionos_wordpress_blueprints\OPTION_TASKS_SCHEDULED;
use const ionos_blueprints_light\ionos_blueprints_light\SLUG;

require_once __DIR__ . '/../index.php';

class DeactivatePluginTaskTest extends \WP_UnitTestCase {

  function test_deactivate_plugin_task() {
    \update_option(OPTION_TASKS_SCHEDULED, [
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
    // verify our plugin initialized its cron task
    $this->assertTrue( \wp_next_scheduled(CRON_JOB_HOOK) !== false, 'cron task should be cleaned up' );

    // execute cron task the first time
    \do_action(CRON_JOB_HOOK);

    // verify our plugin is no more active
    $this->assertFalse( \is_plugin_active(SLUG), 'plugin should be deactivated' );

    // verify the plugin deactivation hook was properly called
    $this->assertFalse( \wp_next_scheduled(CRON_JOB_HOOK), 'cron task should be cleaned up' );
  }

  function test_deactivate_task_silent() {
    \update_option(OPTION_TASKS_SCHEDULED, [
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
    // verify our plugin initialized its cron task
    $this->assertTrue( \wp_next_scheduled(CRON_JOB_HOOK) !== false, 'cron task should be cleaned up' );

    // execute cron task the first time
    \do_action(CRON_JOB_HOOK);

    // verify our plugin is no more active
    $this->assertFalse( \is_plugin_active(SLUG), 'plugin should be deactivated' );

    // verify the plugin deactivation hook was not called
    $this->assertTrue( \wp_next_scheduled(CRON_JOB_HOOK)!==false, 'cron task should not be be cleaned up' );
  }
}