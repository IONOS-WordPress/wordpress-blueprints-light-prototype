<?php

namespace ionos_blueprints_light\ionos_blueprints_light\phpunit;

use function ionos_blueprints_light\ionos_blueprints_light\blueprints\_create_task_error;

use const ionos_blueprints_light\ionos_blueprints_light\blueprints\CRON_JOB_HOOK;
use const ionos_blueprints_light\ionos_blueprints_light\blueprints\CRON_JOB_HOOK_DONE_ACTION;
use const ionos_blueprints_light\ionos_blueprints_light\blueprints\OPTION_TASKS_DONE;
use const ionos_blueprints_light\ionos_blueprints_light\blueprints\OPTION_TASKS_SCHEDULED;
use const ionos_blueprints_light\ionos_blueprints_light\SLUG;

require_once __DIR__ . '/../blueprints-light.php';
class TaskTest extends \WP_UnitTestCase {

  const CUSTOM_JOB_TYPE = 'add_to_option';

  public function setUp(): void {
    parent::set_up();

    \activate_plugin( SLUG );

    static::_register_custom_task_type();
  }

  public function tearDown(): void {
    parent::tear_down();

    \deactivate_plugins(SLUG);
  }

  private function _register_custom_task_type() {
    if(\has_filter(CRON_JOB_HOOK . '_' . self::CUSTOM_JOB_TYPE)) {
      return;
    }

    \add_filter( CRON_JOB_HOOK . '_' . self::CUSTOM_JOB_TYPE, function(array $payload) : array {
      $args = $payload['args'];
      
      $option_name = $args['option'];
      $value = $args['value'];
      
      $current_value = \get_option($option_name, 0);
      $new_value = $current_value + $value;
      $success = \update_option($option_name, $new_value);

      return [
        'success' => $success,
        'value' => $new_value,
      ];
    });
  }

  /*
    test custom task type for adding a value to an option
  */
	function test_custom_task() {
    $tasks = \get_option(OPTION_TASKS_SCHEDULED, []);
    $this->assertEmpty($tasks, 'No tasks should be scheduled');
    
    \update_option(OPTION_TASKS_SCHEDULED, [
      [
        'id' => \wp_generate_uuid4(),
        'type' => self::CUSTOM_JOB_TYPE,
        'args' => [
          'option' => 'foo',
          'value'=> 10
        ]
      ]
    ]);
    $this->assertCount(1, \get_option(OPTION_TASKS_SCHEDULED), 'A single task is scheduled');
    $this->assertFalse( \get_option('foo'), 'option foo should not be set yet');

    \do_action(CRON_JOB_HOOK);
    $this->assertSame( \get_option('foo'), 10, 'option "foo" should be set to 10 after action is triggered');
    
    $this->assertEmpty(\get_option(OPTION_TASKS_SCHEDULED, []), 'tasks should be empty');
    
    \update_option(OPTION_TASKS_SCHEDULED, [
      [
        'id' => \wp_generate_uuid4(),
        'type' => self::CUSTOM_JOB_TYPE,
        'args' => [
          'option' => 'foo',
          'value'=> 10
        ]
      ]
    ]);
    $this->assertCount(1, \get_option(OPTION_TASKS_SCHEDULED), 'A single task is scheduled');

    \do_action(CRON_JOB_HOOK);
    $this->assertSame( \get_option('foo'), 20, 'option "foo" should be set to 20 after action is triggered');
    $this->assertEmpty(\get_option(OPTION_TASKS_SCHEDULED, []), 'tasks should be empty');

    \update_option(OPTION_TASKS_DONE, []);
    $this->assertEquals([], \get_option(OPTION_TASKS_DONE, []));

    \update_option(OPTION_TASKS_SCHEDULED, [
      [
        'id' => \wp_generate_uuid4(),
        'type' => self::CUSTOM_JOB_TYPE,
        'args' => [
          'option' => 'foo',
          'value'=> 20
        ]
        ],
        [
          'id' => \wp_generate_uuid4(),
          'type' => self::CUSTOM_JOB_TYPE,
          'args' => [
            'option' => 'foo',
            'value'=> 30
          ]
        ]
    ]);
    $this->assertCount(2, \get_option(OPTION_TASKS_SCHEDULED), '2 tasks are scheduled');
    \do_action(CRON_JOB_HOOK);
    $this->assertSame( \get_option('foo'), 70, 'option "foo" should be set to 20 after action is triggered');
    $this->assertEmpty(\get_option(OPTION_TASKS_SCHEDULED, []), 'tasks should be empty');
	}

  /*
    test that enqueued tasks are executed
      - task results is stored in the tasks_done option
      - and that the tasks are removed from the tasks_scheduled option
  */
  function test_tasks_done() {
    $tasks_done = [];
    \add_filter(CRON_JOB_HOOK_DONE_ACTION, function(array $tasks) use (&$tasks_done) {
      $tasks_done = array_merge($tasks_done, $tasks);
      \update_option(OPTION_TASKS_DONE, []);
      return $tasks;
    });

    $uuids = [];

    \update_option(OPTION_TASKS_SCHEDULED, [
      [
        'id' => $uuids[]=\wp_generate_uuid4(),
        'type' => self::CUSTOM_JOB_TYPE,
        'args' => [
          'option' => 'my_counter',
          'value'=> 10
        ]
      ]
    ]);
    \do_action(CRON_JOB_HOOK);
    $this->assertCount(1, $tasks_done, '1 task should be done');

    \update_option(OPTION_TASKS_SCHEDULED, [
      [
        'id' => $uuids[]=\wp_generate_uuid4(),
        'type' => self::CUSTOM_JOB_TYPE,
        'args' => [
          'option' => 'my_counter',
          'value'=> 1
        ]
      ],
      [
        'id' => $uuids[]=\wp_generate_uuid4(),
        'type' => self::CUSTOM_JOB_TYPE,
        'args' => [
          'option' => 'my_counter',
          'value'=> 2
        ]
      ]
    ]);
    \do_action(CRON_JOB_HOOK);

    $this->assertEquals(
      [
        [
          'success' => true,
          'value' => 10,
          'id' => $uuids[0],
        ],
        [
          'success' => true,
          'value' => 11,
          'id' => $uuids[1],
        ],
        [
          'success' => true,
          'value' => 13,
          'id' => $uuids[2],
        ]
      ], 
      $tasks_done
    );
  }

  /* 
    test set_option task type
  */
  function test_task_type_set_option() {
    $this->assertTrue(\has_filter(CRON_JOB_HOOK . '_set_option'), '"set_option" task type should be registered');

    $tasks_done = [];
    \add_filter(CRON_JOB_HOOK_DONE_ACTION, function(array $tasks) use (&$tasks_done) {
      $tasks_done = array_merge($tasks_done, $tasks);
      \update_option(OPTION_TASKS_DONE, []);
      return $tasks;
    });

    $uuids = [];
    \update_option(OPTION_TASKS_SCHEDULED, [
      [
        'id' => $uuids[]=\wp_generate_uuid4(),
        'type' => 'set_option',
        'args' => [
          'name' => 'foo',
          'value'=> 'yes'
        ]
      ]
    ]);
    $this->assertFalse(\get_option('foo'), 'option"foo" is not set');
    \do_action(CRON_JOB_HOOK);
    $this->assertEquals('yes', \get_option('foo'), 'option"foo" is set to "yes"');

    \update_option(OPTION_TASKS_SCHEDULED, [
      [
        'id' => $uuids[]=\wp_generate_uuid4(),
        'type' => 'set_option',
        'args' => [
          'name' => 'foo',
          'value'=> 'no'
        ]
      ],
      [
        'id' => $uuids[]=\wp_generate_uuid4(),
        'type' => 'set_option',
        'args' => [
          'name' => 'foo',
          'value'=> 'maybe'
        ]
      ],
    ]);
    \do_action(CRON_JOB_HOOK);
    $this->assertEquals('maybe', \get_option('foo'), 'option "foo" is set to "maybe"');

    $this->assertEquals(
      [
        [
          'success' => true,
          'id' => $uuids[0],
        ],
        [
          'success' => true,
          'id' => $uuids[1],
        ],
        [
          'success' => true,
          'id' => $uuids[2],
        ],
      ], 
      $tasks_done
    );
  }

  /* 
    test install_plugin task type
  */
  function test_task_type_install_plugin() {
    $PLUGINS = [
      'hello-dolly/hello.php',
      'firefox-counter/firefox-counter.php'
    ];

    # reset plugins to be installed in this test
    \deactivate_plugins($PLUGINS);
    foreach($PLUGINS as $file) {
      $plugin_dir = WP_PLUGIN_DIR . '/' . dirname( $file );
      if( file_exists( $plugin_dir) ) {
        $r = exec("rm -rf $plugin_dir");
      }
    };
    \wp_cache_delete('plugins', 'plugins');

    $tasks_done = [];
    \add_filter(CRON_JOB_HOOK_DONE_ACTION, function(array $tasks) use (&$tasks_done) {
      $tasks_done = array_merge($tasks_done, $tasks);
      \update_option(OPTION_TASKS_DONE, []);
      return $tasks;
    });

    $uuids = [];
    # test installing hello-dolly plugin
    \update_option(OPTION_TASKS_SCHEDULED, [
      [
        'id' => $uuids[]=\wp_generate_uuid4(),
        'type' => 'install_plugin',
        'args' => [
          'url' => 'https://downloads.wordpress.org/plugin/hello-dolly.zip',
          'slug' => 'hello-dolly',
        ],
      ],
    ]);
    \do_action(CRON_JOB_HOOK);
    $this->assertEquals(
      [
        [
          'success' => true,
          'id' => $uuids[0],
        ],
      ], 
      $tasks_done
    );

    # test installing hello-dolly plugin again and firefox-counter plugin
    $tasks_done = [];
    $uuids = [];
    \update_option(OPTION_TASKS_DONE, []);
    \update_option(OPTION_TASKS_SCHEDULED, [
      [
        'id' => $uuids[]=\wp_generate_uuid4(),
        'type' => 'install_plugin',
        'args' => [
          'url' => 'https://downloads.wordpress.org/plugin/hello-dolly.zip',
          'slug' => 'hello-dolly',
        ],
      ],
      [
        'id' => $uuids[]=\wp_generate_uuid4(),
        'type' => 'install_plugin',
        'args' => [
          'url' => 'https://downloads.wordpress.org/plugin/firefox-counter.zip',
          'slug' => 'firefox-counter',
        ]
      ]
    ]);
    \do_action(CRON_JOB_HOOK);

    $this->assertCount(2, $tasks_done, '2 tasks done');
    $this->assertArrayHasKey('error', $tasks_done[0], 'install plugin "hello-dolly" should have an error');
   
    $this->assertEquals(
      [
        'success' => true,
        'id' => $uuids[1],
      ],
      $tasks_done[1]
    );

    # test force installing hello-dolly plugin
    $tasks_done = [];
    $uuids=[];
    \update_option(OPTION_TASKS_DONE, []);
    \update_option(OPTION_TASKS_SCHEDULED, [
      [
        'id' => $uuids[]=\wp_generate_uuid4(),
        'type' => 'install_plugin',
        'args' => [
          'url' => 'https://downloads.wordpress.org/plugin/hello-dolly.zip',
          'slug' => 'hello-dolly',
          'force' => true
        ],
      ],
    ]);
    \do_action(CRON_JOB_HOOK);

    $this->assertEquals(
      [
        [
          'success' => true,
          'id' => $uuids[0],
        ],
      ],
      $tasks_done
    );
  }

  /* 
    test complete example
  */
  function test_complex_example() {
    $tasks_done = [];
    $uuids = [];
    \update_option(OPTION_TASKS_DONE, []);
    \add_filter(CRON_JOB_HOOK_DONE_ACTION, function(array $tasks) use (&$tasks_done) {
      $tasks_done = array_merge($tasks_done, $tasks);
      \update_option(OPTION_TASKS_DONE, []);
      return $tasks;
    });

    # test installing hello-dolly plugin
    \update_option(OPTION_TASKS_SCHEDULED, [
      [
        'id' => $uuids[]=\wp_generate_uuid4(),
        'type' => 'install_plugin',
        'args' => [
          'url' => 'https://downloads.wordpress.org/plugin/hello-world.zip',
          'slug' => 'hello-world/hello-world.php',
          'force' => true
        ]
      ],
      [
        'id' => $uuids[]=\wp_generate_uuid4(),
        'type' => 'activate_plugin',
        'args' => [
          'slug' => 'hello-world/hello-world.php',
          'force' => true
        ]
      ],
      [
        'id' => $uuids[]=\wp_generate_uuid4(),
        'type' => 'set_option',
        'args' => [
          'name' => 'hello_world_lyrics',
          'value' => 'whoooo!'
        ]
      ],
    ]);

    \do_action(CRON_JOB_HOOK);

    $this->assertEquals(
      [
        [
          'success' => true,
          'id' => $uuids[0],
        ],
        [
          'success' => true,
          'id' => $uuids[1],
        ],
        [
          'success' => true,
          'id' => $uuids[2],
        ]
      ], 
      $tasks_done
    );
  }

  /*
   * test partial task execution
   * using a custom sleep task we force that only the first task  of 2 enqueued tasks is executed at first run og the cron task
   * at the second run the second task the rest of tasks qill get executed
   * 
   * this testcase should be called as last one since it modifies the cron execution time for all follow up tests 
  */
  function test_tasks_partial_done() {
    $SLEEP_JOB_TYPE = 'sleep';
    \add_filter( CRON_JOB_HOOK . '_' . $SLEEP_JOB_TYPE, function(array $payload) : array {
      $args = $payload['args'];
      
      if( !isset($args['value'])) {
        return _create_task_error(
          sprintf(
            '%s : task "%s" requires "value" in args. payload was %s',
            CRON_JOB_HOOK,
            $payload['type'],
            \wp_json_encode($payload)
          ),
          $payload
        );
      }

      $value = $args['value'];
      
      sleep($value);

      return [
        'value' => $value,
      ];
    });
    $this->assertFalse( \get_option('foo'), 'option foo should not be set yet');

    $tasks_done = [];
    \add_filter(CRON_JOB_HOOK_DONE_ACTION, function(array $tasks) use (&$tasks_done) {
      $tasks_done = array_merge($tasks_done, $tasks);
      \update_option(OPTION_TASKS_DONE, []);
      return $tasks;
    });

    // setting CRON_JOB_MAX_EXECUTION_TIME to 0 means 
    // skip the execution time check and execute only one task per cron call 
    define('CRON_JOB_MAX_EXECUTION_TIME', 0);
    $uuids = [];
    \update_option(OPTION_TASKS_SCHEDULED, [
      [
        'id' => $uuids[]=\wp_generate_uuid4(),
        'type' => $SLEEP_JOB_TYPE,
        'args' => [
          'value'=> 2
        ]
      ],
      [
        'id' => $uuids[]=\wp_generate_uuid4(),
        'type' => 'set_option',
        'args' => [
          'name' => 'foo',
          'value'=> 'bar'
        ]
      ]
    ]);

    // execute cron task the first time
    \do_action(CRON_JOB_HOOK);
    $this->assertFalse( \get_option('foo'), 'option foo should not be set yet');

    // ensure only first task was executed
    $this->assertEquals(
      [
        [
          'id' => $uuids[0],
          'value' => 2,
        ],
      ], 
      $tasks_done
    );

    // execute cron task the second time
    \do_action(CRON_JOB_HOOK);

    // ensure rest of tasks was executed
    $this->assertEquals( 'bar', \get_option('foo'), 'option foo should be set to "bar"');

    $this->assertEquals(
      [
        [
          'id' => $uuids[0],
          'value' => 2,
        ],
        [
          'id' => $uuids[1],
          'success' => true,
        ],
      ],
      $tasks_done
    );
  }
}