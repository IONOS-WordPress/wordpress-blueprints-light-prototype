<?php

use function ionos_blueprints_light\ionos_blueprints_light\blueprints\_create_job_error;

use const ionos_blueprints_light\ionos_blueprints_light\blueprints\CRON_JOB_HOOK;
use const ionos_blueprints_light\ionos_blueprints_light\blueprints\CRON_JOB_HOOK_DONE_ACTION;
use const ionos_blueprints_light\ionos_blueprints_light\blueprints\OPTION_JOBS_DONE;
use const ionos_blueprints_light\ionos_blueprints_light\blueprints\OPTION_JOBS_SCHEDULED;

class JobTest extends \WP_UnitTestCase {

  const CUSTOM_JOB_TYPE = 'add_to_option';

  public function setUp(): void {
    \activate_plugin( 'ionos-blueprints-light/blueprints-light.php' );

    static::_register_custom_job_type();
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
    $this->assertSameSets([], \get_option(OPTION_JOBS_DONE, []));

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

    $this->assertSameSets(
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

  function test_jobs_partial_done() {
    // @TODO: implement
    $this->assertTrue(true);
  }

  function test_job_type_set_option() {
  }

  function test_job_type_install_plugin() {
  }
}