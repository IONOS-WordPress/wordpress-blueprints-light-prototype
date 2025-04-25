<?php

namespace ionos_blueprints_light\ionos_blueprints_light\phpunit;

use function ionos_blueprints_light\ionos_blueprints_light\blueprints\_add_filter_job_validation;
use function ionos_blueprints_light\ionos_blueprints_light\blueprints\enqueue_jobs;

use const ionos_blueprints_light\ionos_blueprints_light\blueprints\CRON_JOB_HOOK;
use const ionos_blueprints_light\ionos_blueprints_light\blueprints\CRON_JOB_HOOK_DONE_ACTION;
use const ionos_blueprints_light\ionos_blueprints_light\blueprints\JOB_VALIDATION_HOOK_PREFIX;
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

  function test_job_args_invalid() {
    $result = enqueue_jobs([ "this is not a valid job" ]);
    $this->assertInstanceOf( \WP_Error::class, $result, 'job is expected to be an associative array' );

    $result = enqueue_jobs(
      [
        [
          'id' => $uuids[]=\wp_generate_uuid4(),
          'type' => 'set_option',
          'args' => [
            'name' => 'foo',
            // 'value'=> 'bar' // required field "value" is missing
          ]
        ]
      ]
    );
    $this->assertInstanceOf( \WP_Error::class, $result, 'job configurations must be valid according to their json schema definition');

    $result = enqueue_jobs(
      [
        [
          // 'id' => $uuids[]=\wp_generate_uuid4(), // required field "id" is missing
          'type' => 'set_option',
          'args' => [
            'name' => 'foo',
            'value'=> 'bar' 
          ]
        ]
      ]
    );
    $this->assertInstanceOf( \WP_Error::class, $result, 'job configurations must be valid according to their json schema definition' );

    $result = enqueue_jobs(
      [
        [
          'id' => $uuids[]=\wp_generate_uuid4(), 
          'type' => 'set_option',
          // 'args' => [   // required field "args" is missing
          //   'name' => 'foo',
          //   'value'=> 'bar' 
          // ]
        ]
      ]
    );
    $this->assertInstanceOf( \WP_Error::class, $result );
  }
  
  function test_job_valid() {
    $JOB = [
      'id' => $uuids[]=\wp_generate_uuid4(), 
      'type' => 'set_option',
      'args' => [   // required field "args" is missing
        'name' => 'foo',
        'value'=> 'bar' 
      ]
    ];

    $result = enqueue_jobs( [$JOB]);
    $this->assertTrue($result, 'job should be valid' );
    $this->assertEqualsCanonicalizing([$JOB], \get_option(OPTION_JOBS_SCHEDULED));
  }  

  function test_manual_job_registration() {
    // declare a simple job adding 2 number arguments left and right
    \add_filter(
      hook_name:CRON_JOB_HOOK . '_' . __FUNCTION__,
      callback: fn(array $payload) : array => [
        'value' => $payload['args']['left'] + $payload['args']['right'],
      ],
    );

    _add_filter_job_validation(
      job_type: __FUNCTION__,
      json_schema: [
        'type' => 'object',
        'properties' => [
          'id' => [
            'type' => 'string',
            'format' => 'uuid',
          ],
          'type' => [
            'type' => 'string',
            'const' => __FUNCTION__,
          ],
          'args' => [
            'type' => 'object',
            'properties' => [
              'left' => [
                'type' => 'integer',
              ],
              'right' => [
                'type' => 'integer',
              ],
            ],
            'required' => ['left', 'right'],
          ],
        ],
        'required' => ['id', 'type', 'args'],
        'additionalProperties' => false,
      ],
    );

    $result = enqueue_jobs([
      [
        'id' => \wp_generate_uuid4(), 
        'type' => __FUNCTION__,
        'args' => [
          'left' => 10,
          'right' => 2,
        ],
        'foo' => 'bar', // additional properties are not allowed
      ]
    ]);
    $this->assertTrue($result, 'job should be sanitized (added defaults for left and right) and valid' );
    $scheduled_jobs = \get_option(OPTION_JOBS_SCHEDULED);
    $this->assertCount(1, $scheduled_jobs);
    $this->assertFalse(isset($scheduled_jobs[0]['foo']), 'foo property should not be present in the scheduled job');
  }
}