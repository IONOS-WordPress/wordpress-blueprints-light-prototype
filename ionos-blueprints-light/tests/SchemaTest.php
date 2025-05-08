<?php

namespace ionos_blueprints_light\ionos_blueprints_light\phpunit;

use function ionos_blueprints_light\ionos_blueprints_light\blueprints\_add_filter_task_validation;
use function ionos_blueprints_light\ionos_blueprints_light\blueprints\enqueue_tasks;

use const ionos_blueprints_light\ionos_blueprints_light\blueprints\CRON_JOB_HOOK;
use const ionos_blueprints_light\ionos_blueprints_light\blueprints\OPTION_TASKS_SCHEDULED;
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

  function test_task_args_invalid() {
    $result = enqueue_tasks([ "this is not a valid task" ]);
    $this->assertInstanceOf( \WP_Error::class, $result, 'task is expected to be an associative array' );

    $result = enqueue_tasks(
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
    $this->assertInstanceOf( \WP_Error::class, $result, 'task configurations must be valid according to their json schema definition');

    $result = enqueue_tasks(
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
    $this->assertInstanceOf( \WP_Error::class, $result, 'task configurations must be valid according to their json schema definition' );

    $result = enqueue_tasks(
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
  
  function test_task_valid() {
    $JOB = [
      'id' => $uuids[]=\wp_generate_uuid4(), 
      'type' => 'set_option',
      'args' => [   // required field "args" is missing
        'name' => 'foo',
        'value'=> 'bar' 
      ]
    ];

    $result = enqueue_tasks( [$JOB]);
    $this->assertTrue($result, 'task should be valid' );
    $this->assertEquals([$JOB], \get_option(OPTION_TASKS_SCHEDULED));
  }  

  function test_manual_task_registration() {
    // declare a simple task adding 2 number arguments left and right
    \add_filter(
      hook_name:CRON_JOB_HOOK . '_' . __FUNCTION__,
      callback: fn(array $payload) : array => [
        'value' => $payload['args']['left'] + $payload['args']['right'],
      ],
    );

    _add_filter_task_validation(
      task_type: __FUNCTION__,
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

    $result = enqueue_tasks([
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
    $scheduled_tasks = \get_option(OPTION_TASKS_SCHEDULED);
    $this->assertCount(1, $scheduled_tasks);
    $this->assertFalse(isset($scheduled_tasks[0]['foo']), 'foo property should not be present in the scheduled task');
  }
}