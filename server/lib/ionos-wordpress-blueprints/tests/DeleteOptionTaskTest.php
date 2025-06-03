<?php

namespace ionos_wordpress_blueprints\phpunit;

require_once __DIR__ . '/../index.php';

use function ionos_wordpress_blueprints\execute_tasks;
use function ionos_wordpress_blueprints\validate_tasks;

class DeleteOptionTaskTest extends \WP_UnitTestCase {

  const OPTION_NAME = 'foo';
  const OPTION_VALUE = 'bar';

  function test_delete_option_task() {
    \add_option(self::OPTION_NAME, self::OPTION_VALUE);
    // verify option is set
    $this->assertEquals( self::OPTION_VALUE, \get_option(self::OPTION_NAME), 'option should be set' );

    $tasks = validate_tasks([
      [
        'id' => \wp_generate_uuid4(),
        'type' => 'delete_option',
        'args' => [
          'name'=> self::OPTION_NAME,
        ]
      ],
    ]);

    if (is_wp_error($tasks)) {
      $this->fail('Task validation failed: ' . $tasks->get_error_message());
    }

    $result = execute_tasks($tasks);

    if (is_wp_error($result)) {
      $this->fail('Task execution failed: ' . $result->get_error_message());
    }

    // verify option is now unset
    $this->assertFalse( \get_option(self::OPTION_NAME), 'option should be unset' );
  }
}