<?php

namespace ionos_wordpress_blueprints\phpunit;

require_once __DIR__ . '/../index.php';

use function ionos_wordpress_blueprints\execute_tasks;

class DeletePluginTaskTest extends \WP_UnitTestCase {

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

    $result = execute_tasks([
      [
        'id' => $uuids[]=\wp_generate_uuid4(),
        'type' => 'delete_plugin',
        'args' => [
          'slug'=> $TEST_PLUGIN_SLUG,
          'force' => true,
        ]
      ],
    ]);  

    if (is_wp_error($result)) {
      $this->fail('Task execution failed: ' . $result->get_error_message());
    }

    // verify option value matches preset value
    $this->assertCount(1, $result);
    $this->assertTrue($result[0]['success'], 'task should be successful');
    
    $this->assertFalse( \is_plugin_active( $TEST_PLUGIN_SLUG ), 'plugin should not be active' );
    $this->assertFileDoesNotExist( WP_PLUGIN_DIR . "/{$TEST_PLUGIN_SLUG}", 'plugin should be deleted' );

  }
}