<?php

namespace ionos_blueprints_light\ionos_blueprints_light\phpunit;

use const ionos_wordpress_blueprints\CRON_JOB_HOOK;
use const ionos_wordpress_blueprints\OPTION_TASKS_SCHEDULED;
use const ionos_blueprints_light\ionos_blueprints_light\SLUG;

require_once __DIR__ . '/../index.php';

class DeleteOptionTaskTest extends \WP_UnitTestCase {

  const OPTION_NAME = 'foo';
  const OPTION_VALUE = 'bar';

  function test_delete_option_task() {
    \add_option(self::OPTION_NAME, 'bar');
    // verify option is set
    $this->assertEquals( self::OPTION_VALUE, \get_option(self::OPTION_NAME), 'option should be set' );

    \update_option(OPTION_TASKS_SCHEDULED, [
      [
        'id' => $uuids[]=\wp_generate_uuid4(),
        'type' => 'delete_option',
        'args' => [
          'name'=> self::OPTION_NAME,
        ]
      ],
    ]);

    // execute cron task the first time
    \do_action(CRON_JOB_HOOK);

    // verify option is now unset
    $this->assertFalse( \get_option(self::OPTION_NAME), 'option should be unset' );
  }
}