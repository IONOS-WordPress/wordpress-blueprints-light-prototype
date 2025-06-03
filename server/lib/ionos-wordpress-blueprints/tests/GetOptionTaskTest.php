<?php

namespace ionos_wordpress_blueprints\phpunit;

require_once __DIR__ . '/../index.php';

use function ionos_wordpress_blueprints\execute_tasks;

class GetOptionTaskTest extends \WP_UnitTestCase {

  const OPTION_NAME = 'foo';
  const OPTION_VALUE = 'bar';

  function test_delete_option_task() {
    \add_option(self::OPTION_NAME, self::OPTION_VALUE);

    $result = execute_tasks([
      [
        'id' => $uuids[]=\wp_generate_uuid4(),
        'type' => 'get_option',
        'args' => [
          'name'=> self::OPTION_NAME,
        ]
      ],
    ]);

    if (is_wp_error($result)) {
      $this->fail('Task execution failed: ' . $result->get_error_message());
    }

    // verify option value matches preset value
    $this->assertCount(1, $result);
    $this->assertArrayHasKey('value', $result[0]);
    

    $this->assertEquals( self::OPTION_VALUE, $result[0]['value'], 'option should be set' );
  }
}