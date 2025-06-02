<?php

namespace ionos_blueprints_light\ionos_blueprints_light\phpunit;

use const ionos_wordpress_blueprints\CRON_JOB_HOOK;
use const ionos_wordpress_blueprints\OPTION_TASKS_DONE;
use const ionos_wordpress_blueprints\OPTION_TASKS_SCHEDULED;
use const ionos_blueprints_light\ionos_blueprints_light\SLUG;

require_once __DIR__ . '/../index.php';

class GetOptionTaskTest extends \WP_UnitTestCase {

  const OPTION_NAME = 'foo';
  const OPTION_VALUE = 'bar';

  function test_delete_option_task() {
    \add_option(self::OPTION_NAME, self::OPTION_VALUE);

    \update_option(OPTION_TASKS_SCHEDULED, [
      [
        'id' => $uuids[]=\wp_generate_uuid4(),
        'type' => 'get_option',
        'args' => [
          'name'=> self::OPTION_NAME,
        ]
      ],
    ]);

    // execute cron task the first time
    \do_action(CRON_JOB_HOOK);

    $tasks_done = \get_option(OPTION_TASKS_DONE);

    // verify option value matches preset value
    $this->assertCount(1, $tasks_done);
    $this->assertArrayHasKey('value', $tasks_done[0]);
    

    $this->assertEquals( self::OPTION_VALUE, $tasks_done[0]['value'], 'option should be set' );
  }
}