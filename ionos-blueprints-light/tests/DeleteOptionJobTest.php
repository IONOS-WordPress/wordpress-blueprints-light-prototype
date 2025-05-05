<?php

namespace ionos_blueprints_light\ionos_blueprints_light\phpunit;

use const ionos_blueprints_light\ionos_blueprints_light\blueprints\CRON_JOB_HOOK;
use const ionos_blueprints_light\ionos_blueprints_light\blueprints\OPTION_JOBS_SCHEDULED;
use const ionos_blueprints_light\ionos_blueprints_light\SLUG;

require_once __DIR__ . '/../blueprints-light.php';

class DeleteOptionJobTest extends \WP_UnitTestCase {

  public function setUp(): void {
    parent::set_up();

    \activate_plugin( SLUG );
  }

  public function tearDown(): void {
    parent::tear_down();

    \deactivate_plugins(SLUG);
  }

  const OPTION_NAME = 'foo';
  const OPTION_VALUE = 'bar';

  function test_delete_option_job() {
    \add_option(self::OPTION_NAME, 'bar');
    // verify option is set
    $this->assertEquals( self::OPTION_VALUE, \get_option(self::OPTION_NAME), 'option should be set' );

    \update_option(OPTION_JOBS_SCHEDULED, [
      [
        'id' => $uuids[]=\wp_generate_uuid4(),
        'type' => 'delete_option',
        'args' => [
          'name'=> self::OPTION_NAME,
        ]
      ],
    ]);

    // execute cron job the first time
    \do_action(CRON_JOB_HOOK);

    // verify option is now unset
    $this->assertFalse( \get_option(self::OPTION_NAME), 'option should be unset' );
  }
}