<?php

namespace ionos_wordpress_blueprints\phpunit;

use const ionos_wordpress_blueprints\TASK_EXECUTION_FILTER_PREFIX;
use const ionos_wordpress_blueprints\TASK_VALIDATION_FILTER_PREFIX;

use function ionos_wordpress_blueprints\_add_filter_task_validation;
use function ionos_wordpress_blueprints\validate_tasks;

require_once __DIR__ . '/../index.php';

class SchemaTest extends \WP_UnitTestCase {

  function test_task_args_invalid() {
    $result = validate_tasks([ "this is not a valid task" ]);
    $this->assertInstanceOf( \WP_Error::class, $result, 'task is expected to be an associative array' );

    $result = validate_tasks(
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

    $result = validate_tasks(
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

    $result = validate_tasks(
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

    $result = validate_tasks( [$JOB]);
    $this->assertIsArray($result, 'task should be valid' );
    $this->assertEquals([$JOB], $result, 'task should be returned as is');
  }  

  function test_manual_task_registration() {
    // declare a simple task adding 2 number arguments left and right
    \add_filter(
      hook_name:TASK_EXECUTION_FILTER_PREFIX . '_' . __FUNCTION__,
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

    $validated_tasks = validate_tasks([
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
    $this->assertCount(1, $validated_tasks);
    $this->assertFalse(isset($validated_tasks[0]['foo']), 'foo property should not be present in the scheduled task');
  }
}