<?php

namespace ionos_blueprints_light\ionos_blueprints_light\phpunit;

use Exception;

use function ionos_blueprints_light\ionos_blueprints_light\blueprints\_create_job_error;

use const ionos_blueprints_light\ionos_blueprints_light\blueprints\CRON_JOB_HOOK;
use const ionos_blueprints_light\ionos_blueprints_light\blueprints\CRON_JOB_HOOK_DONE_ACTION;
use const ionos_blueprints_light\ionos_blueprints_light\blueprints\OPTION_JOBS_DONE;
use const ionos_blueprints_light\ionos_blueprints_light\blueprints\OPTION_JOBS_SCHEDULED;
use const ionos_blueprints_light\ionos_blueprints_light\SLUG;

require_once __DIR__ . '/../blueprints-light.php';
class JobTest extends \WP_UnitTestCase {

  const CUSTOM_JOB_TYPE = 'add_to_option';

  public function setUp(): void {
    parent::set_up();

    \activate_plugin( SLUG );

    static::_register_custom_job_type();
  }

  public function tearDown(): void {
    parent::tear_down();

    \deactivate_plugins(SLUG);
  }

  private function _register_custom_job_type() {
    if(\has_filter(CRON_JOB_HOOK . '_' . self::CUSTOM_JOB_TYPE)) {
      return;
    }

    \add_filter( CRON_JOB_HOOK . '_' . self::CUSTOM_JOB_TYPE, function(array $payload) : array {
      $args = $payload['args'];
      
      if( !isset($args['option']) || !isset($args['value'])) {
        return _create_job_error(
          sprintf(
            '%s : job "%s" requires "option" and "value" in args. payload was %s',
            CRON_JOB_HOOK,
            $payload['type'],
            \wp_json_encode($payload)
          ),
          $payload
        );
      }

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
    test custom job type for adding a value to an option
  */
	function test_custom_job() {
    $jobs = \get_option(OPTION_JOBS_SCHEDULED, []);
    $this->assertEmpty($jobs, 'No jobs should be scheduled');
    
    \update_option(OPTION_JOBS_SCHEDULED, [
      [
        'id' => 1,
        'type' => self::CUSTOM_JOB_TYPE,
        'args' => [
          'option' => 'foo',
          'value'=> 10
        ]
      ]
    ]);
    $this->assertCount(1, \get_option(OPTION_JOBS_SCHEDULED), 'A single job is scheduled');
    $this->assertFalse( \get_option('foo'), 'option foo should not be set yet');

    \do_action(CRON_JOB_HOOK);
    $this->assertSame( \get_option('foo'), 10, 'option "foo" should be set to 10 after action is triggered');
    
    $this->assertEmpty(\get_option(OPTION_JOBS_SCHEDULED, []), 'jobs should be empty');
    
    \update_option(OPTION_JOBS_SCHEDULED, [
      [
        'id' => 1,
        'type' => self::CUSTOM_JOB_TYPE,
        'args' => [
          'option' => 'foo',
          'value'=> 10
        ]
      ]
    ]);
    $this->assertCount(1, \get_option(OPTION_JOBS_SCHEDULED), 'A single job is scheduled');

    \do_action(CRON_JOB_HOOK);
    $this->assertSame( \get_option('foo'), 20, 'option "foo" should be set to 20 after action is triggered');
    $this->assertEmpty(\get_option(OPTION_JOBS_SCHEDULED, []), 'jobs should be empty');
    $this->assertEqualsCanonicalizing([], \get_option(OPTION_JOBS_DONE, []));

    \update_option(OPTION_JOBS_SCHEDULED, [
      [
        'id' => 1,
        'type' => self::CUSTOM_JOB_TYPE,
        'args' => [
          'option' => 'foo',
          'value'=> 20
        ]
        ],
        [
          'id' => 2,
          'type' => self::CUSTOM_JOB_TYPE,
          'args' => [
            'option' => 'foo',
            'value'=> 30
          ]
        ]
    ]);
    $this->assertCount(2, \get_option(OPTION_JOBS_SCHEDULED), '2 jobs are scheduled');
    \do_action(CRON_JOB_HOOK);
    $this->assertSame( \get_option('foo'), 70, 'option "foo" should be set to 20 after action is triggered');
    $this->assertEmpty(\get_option(OPTION_JOBS_SCHEDULED, []), 'jobs should be empty');
	}

  /*
    test that enqueued jobs are executed
      - job results is stored in the jobs_done option
      - and that the jobs are removed from the jobs_scheduled option
  */
  function test_jobs_done() {
    $jobs_done = [];
    \add_filter(CRON_JOB_HOOK_DONE_ACTION, function(array $jobs) use (&$jobs_done) {
      $jobs_done = array_merge($jobs_done, $jobs);
      return $jobs;
    });

    \update_option(OPTION_JOBS_SCHEDULED, [
      [
        'id' => 1,
        'type' => self::CUSTOM_JOB_TYPE,
        'args' => [
          'option' => 'my_counter',
          'value'=> 10
        ]
      ]
    ]);
    \do_action(CRON_JOB_HOOK);
    $this->assertCount(1, $jobs_done, '1 job should be done');

    \update_option(OPTION_JOBS_SCHEDULED, [
      [
        'id' => 1,
        'type' => self::CUSTOM_JOB_TYPE,
        'args' => [
          'option' => 'my_counter',
          'value'=> 1
        ]
      ],
      [
        'id' => 1,
        'type' => self::CUSTOM_JOB_TYPE,
        'args' => [
          'option' => 'my_counter',
          'value'=> 2
        ]
      ]
    ]);
    \do_action(CRON_JOB_HOOK);

    $this->assertEqualsCanonicalizing(
      [
        [
          'success' => true,
          'value' => 10,
          'id' => 1
        ],
        [
          'success' => true,
          'value' => 11,
          'id' => 1
        ],
        [
          'success' => true,
          'value' => 13,
          'id' => 1
        ]
      ], 
      $jobs_done
    );
  }

  /*
    test partial job execution

    using a custom sleep job we force that only the first job  of 2 enqueued jobs is executed at first run og the cron job
    at the second run the second job the rest of jobs qill get executed
  */
  function test_jobs_partial_done() {
    $SLEEP_JOB_TYPE = 'sleep';
    \add_filter( CRON_JOB_HOOK . '_' . $SLEEP_JOB_TYPE, function(array $payload) : array {
      $args = $payload['args'];
      
      if( !isset($args['value'])) {
        return _create_job_error(
          sprintf(
            '%s : job "%s" requires "value" in args. payload was %s',
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
    $jobs_done = [];
    \add_filter(CRON_JOB_HOOK_DONE_ACTION, function(array $jobs) use (&$jobs_done) {
      $jobs_done = array_merge($jobs_done, $jobs);
      return $jobs;
    });

    define('CRON_JOB_MAX_EXECUTION_TIME', 1);
    \update_option(OPTION_JOBS_SCHEDULED, [
      [
        'id' => 1,
        'type' => $SLEEP_JOB_TYPE,
        'args' => [
          'value'=> 2
        ]
      ],
      [
        'id' => 1,
        'type' => 'set_option',
        'args' => [
          'name' => 'foo',
          'value'=> 'bar'
        ]
      ]
    ]);

    // execute cron job the first time
    \do_action(CRON_JOB_HOOK);
    $this->assertFalse( \get_option('foo'), 'option foo should not be set yet');

    // ensure only first job was executed
    $this->assertEqualsCanonicalizing(
      [
        [
          'id' => 1,
          'value' => 2,
        ],
      ], 
      $jobs_done
    );

    // execute cron job the second time
    \do_action(CRON_JOB_HOOK);

    // ensure rest of jobs was executed
    $this->assertEquals( 'bar', \get_option('foo'), 'option foo should not be set to "bar"');
    $this->assertEqualsCanonicalizing(
      [
        [
          'id' => 1,
          'value' => 2,
        ],
        [
          'id' => 1,
          'success' => true,
        ],
      ], 
      $jobs_done
    );
  }

  /* 
    test set_option job type
  */
  function test_job_type_set_option() {
    $this->assertTrue(\has_filter(CRON_JOB_HOOK . '_set_option'), '"set_option" job type should be registered');

    $jobs_done = [];
    \add_filter(CRON_JOB_HOOK_DONE_ACTION, function(array $jobs) use (&$jobs_done) {
      $jobs_done = array_merge($jobs_done, $jobs);
      return $jobs;
    });

    \update_option(OPTION_JOBS_SCHEDULED, [
      [
        'id' => 1,
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

    \update_option(OPTION_JOBS_SCHEDULED, [
      [
        'id' => 2,
        'type' => 'set_option',
        'args' => [
          'name' => 'foo',
          'value'=> 'no'
        ]
      ],
      [
        'id' => 3,
        'type' => 'set_option',
        'args' => [
          'name' => 'foo',
          'value'=> 'maybe'
        ]
      ],
    ]);
    \do_action(CRON_JOB_HOOK);
    $this->assertEquals('maybe', \get_option('foo'), 'option "foo" is set to "maybe"');

    $this->assertEqualsCanonicalizing(
      [
        [
          'success' => true,
          'id' => 1
        ],
        [
          'success' => true,
          'id' => 2
        ],
        [
          'success' => true,
          'id' => 3
        ]
      ], 
      $jobs_done
    );
  }

  /* 
    test install_plugin job type
  */
  function test_job_type_install_plugin() {
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

    $jobs_done = [];
    \add_filter(CRON_JOB_HOOK_DONE_ACTION, function(array $jobs) use (&$jobs_done) {
      $jobs_done = array_merge($jobs_done, $jobs);
      return $jobs;
    });

    # test installing hello-dolly plugin
    \update_option(OPTION_JOBS_SCHEDULED, [
      [
        'id' => 1,
        'type' => 'install_plugin',
        'args' => [
          'url' => 'https://downloads.wordpress.org/plugin/hello-dolly.zip',
          'slug' => 'hello-dolly',
        ]
      ]
    ]);
    \do_action(CRON_JOB_HOOK);
    $this->assertEqualsCanonicalizing(
      [
        [
          'success' => true,
          'id' => 1
        ]
      ], 
      $jobs_done
    );

    # test installing hello-dolly plugin again and firefox-counter plugin
    $jobs_done = [];
    \update_option(OPTION_JOBS_SCHEDULED, [
      [
        'id' => 2,
        'type' => 'install_plugin',
        'args' => [
          'url' => 'https://downloads.wordpress.org/plugin/hello-dolly.zip',
          'slug' => 'hello-dolly',
        ],
      ],
      [
        'id' => 3,
        'type' => 'install_plugin',
        'args' => [
          'url' => 'https://downloads.wordpress.org/plugin/firefox-counter.zip',
          'slug' => 'firefox-counter',
        ]
      ]
    ]);
    \do_action(CRON_JOB_HOOK);

    $this->assertCount(2, $jobs_done, '2 jobs done');
    $this->assertArrayHasKey('error', $jobs_done[0], 'install plugin "hello-dolly" should have an error');
   
    $this->assertEqualsCanonicalizing(
      [
        'success' => true,
        'id' => 3
      ],
      $jobs_done[1]
    );

    # test force installing hello-dolly plugin
    $jobs_done = [];
    \update_option(OPTION_JOBS_SCHEDULED, [
      [
        'id' => 4,
        'type' => 'install_plugin',
        'args' => [
          'url' => 'https://downloads.wordpress.org/plugin/hello-dolly.zip',
          'slug' => 'hello-dolly',
          'force' => true
        ],
      ],
    ]);
    \do_action(CRON_JOB_HOOK);

    $this->assertEqualsCanonicalizing(
      [
        [
          'success' => true,
          'id' => 4
        ]
      ],
      $jobs_done
    );
  }

  /* 
    test complete example
  */
  function test_complex_example() {
    $jobs_done = [];
    \add_filter(CRON_JOB_HOOK_DONE_ACTION, function(array $jobs) use (&$jobs_done) {
      $jobs_done = array_merge($jobs_done, $jobs);
      return $jobs;
    });

    # test installing hello-dolly plugin
    \update_option(OPTION_JOBS_SCHEDULED, [
      [
        'id' => 1,
        'type' => 'install_plugin',
        'args' => [
          'url' => 'https://downloads.wordpress.org/plugin/hello-world.zip',
          'slug' => 'hello-world/hello-world.php',
          'force' => true
        ]
      ],
      [
        'id' => 2,
        'type' => 'activate_plugin',
        'args' => [
          'slug' => 'hello-world/hello-world.php',
          'force' => true
        ]
      ],
      [
        'id' => 3,
        'type' => 'set_option',
        'args' => [
          'name' => 'hello_world_lyrics',
          'value' => 'whoooo!'
        ]
      ],
    ]);
    \do_action(CRON_JOB_HOOK);
    $this->assertEqualsCanonicalizing(
      [
        [
          'success' => true,
          'id' => 1
        ],
        [
          'success' => true,
          'id' => 2
        ],
        [
          'success' => true,
          'id' => 3
        ]
      ], 
      $jobs_done
    );

 }
}