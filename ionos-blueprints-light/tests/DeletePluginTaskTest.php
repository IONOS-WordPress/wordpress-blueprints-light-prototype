<?php

namespace ionos_blueprints_light\ionos_blueprints_light\phpunit;

use const ionos_blueprints_light\ionos_blueprints_light\blueprints\CRON_JOB_HOOK;
use const ionos_blueprints_light\ionos_blueprints_light\blueprints\OPTION_TASKS_DONE;
use const ionos_blueprints_light\ionos_blueprints_light\blueprints\OPTION_TASKS_SCHEDULED;
use const ionos_blueprints_light\ionos_blueprints_light\SLUG;

require_once __DIR__ . '/../blueprints-light.php';

class DeletePluginTaskTest extends \WP_UnitTestCase {

  public function setUp(): void {
    parent::set_up();

    \activate_plugin( SLUG );
  }

  public function tearDown(): void {
    parent::tear_down();

    \deactivate_plugins(SLUG);
  }

  function test_delete_plugin_task() {
    $TEST_PLUGIN_SLUG = 'blueprint-test-plugin';

    $code = <<<EOT
<?php
/*
 * Plugin Name: {$TEST_PLUGIN_SLUG}
*/

error_log("Hello from {$TEST_PLUGIN_SLUG} plugin");
EOT;

    file_put_contents(WP_PLUGIN_DIR . "/{$TEST_PLUGIN_SLUG}.php", $code);
    
    \wp_cache_delete('plugins', 'plugins');

    $TEST_PLUGIN_SLUG .= '.php';
    \activate_plugin( $TEST_PLUGIN_SLUG );

    $this->assertTrue( \is_plugin_active( $TEST_PLUGIN_SLUG ) );

    \update_option(OPTION_TASKS_SCHEDULED, [
      [
        'id' => $uuids[]=\wp_generate_uuid4(),
        'type' => 'delete_plugin',
        'args' => [
          'slug'=> $TEST_PLUGIN_SLUG,
          'force' => true,
        ]
      ],
    ]);

    // execute cron task the first time
    \do_action(CRON_JOB_HOOK);

    $tasks_done = \get_option(OPTION_TASKS_DONE);

    // verify option value matches preset value
    $this->assertCount(1, $tasks_done);
    $this->assertTrue($tasks_done[0]['success'], 'task should be successful');
    
    $this->assertFalse( \is_plugin_active( $TEST_PLUGIN_SLUG ), 'plugin should not be active' );
    $this->assertFileDoesNotExist( WP_PLUGIN_DIR . "/{$TEST_PLUGIN_SLUG}", 'plugin should be deleted' );

  }
}