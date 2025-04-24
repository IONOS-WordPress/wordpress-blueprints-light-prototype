<?php

namespace ionos_blueprints_light\ionos_blueprints_light\phpunit;

use const ionos_blueprints_light\ionos_blueprints_light\blueprints\CRON_JOB_HOOK;
use const ionos_blueprints_light\ionos_blueprints_light\blueprints\CRON_JOB_HOOK_DONE_ACTION;
use const ionos_blueprints_light\ionos_blueprints_light\blueprints\OPTION_JOBS_DONE;
use const ionos_blueprints_light\ionos_blueprints_light\blueprints\OPTION_JOBS_SCHEDULED;
use const ionos_blueprints_light\ionos_blueprints_light\SLUG;

require_once __DIR__ . '/../blueprints-light.php';

class SchemaTest extends \WP_UnitTestCase {

  public function setUp(): void {
    parent::set_up();

    \activate_plugin( SLUG );
  }

  public function tearDown(): void {
    parent::tear_down();
    
    \deactivate_plugins(SLUG);
  }

  function test_job_args_validity() {
    $jobs_done = [];
    \add_filter(CRON_JOB_HOOK_DONE_ACTION, function(array $jobs) use (&$jobs_done) {
      $jobs_done = array_merge($jobs_done, $jobs);
      \update_option(OPTION_JOBS_DONE, []);
      return $jobs;
    });
    $uuids = [];

    $this->assertTrue(function_exists('rest_validate_value_from_schema'));
    // \rest_validate_value_from_schema(
    //   // @TODO: add schema validation using a wp filter 
    // );

    \update_option(OPTION_JOBS_SCHEDULED, [
      [
        'id' => $uuids[]=\wp_generate_uuid4(),
        'type' => 'set_option',
        'args' => [
          'name' => 'foo',
          'value'=> 'bar'
        ]
      ]
    ]);

    \do_action(CRON_JOB_HOOK);
    $this->assertEqualsCanonicalizing(
      [
        [
          'id' => $uuids[0],
          'success' => true,
        ],
      ], 
      $jobs_done
    );
  }
}