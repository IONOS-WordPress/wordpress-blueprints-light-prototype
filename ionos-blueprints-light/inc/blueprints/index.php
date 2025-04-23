<?php

/*

# reset instance

pnpm -s run wp-env run cli wp --quiet option delete ionos_blueprints_jobs ionos_blueprints_jobs_done foo 2>/dev/null
pnpm -s run wp-env run cli wp --quiet plugin deactivate hello-dolly 2>/dev/null
pnpm -s run wp-env run cli wp --quiet plugin delete hello-dolly 2>/dev/null

# inject new jobs

jobs="$(pnpm -s run wp-env run cli wp --quiet option get ionos_blueprints_jobs --format=json 2>/dev/null || echo '[]')"
jobs=$(jq '. += [
    { 
      id: 100,
      type: "install_plugin",
      args: {
        "url": "https://downloads.wordpress.org/plugin/hello-dolly.zip",
        "slug": "hello-dolly/hello.php"
      }
    },
    { 
      id: 101,
      type: "set_option",
      args: {
        "name": "foo",
        "value": "bar"
      }
    },
    { 
      id: 102,
      type: "activate_plugin",
      args: {
        "slug": "hello-dolly/hello.php"
      }
    }
]' <<< "$jobs")
pnpm -s run wp-env run cli wp --quiet option set ionos_blueprints_jobs "$jobs" --format=json

# reset jobs_done
pnpm -s run wp-env run cli wp --quiet option set ionos_blueprints_jobs_done "[]" --format=json

# check jobs done 

echo $(pnpm -s run wp-env run cli wp --quiet option get ionos_blueprints_jobs_done --format=json 2>/dev/null || echo '[]') | jq .

# check enqueued jobs

echo $(pnpm -s run wp-env run cli wp --quiet option get ionos_blueprints_jobs --format=json 2>/dev/null || echo '[]') | jq .

# trigger processing jobs

pnpm -s run wp-env run cli wp --quiet cron event run ionos_blueprints_cron_job

# list active plugins

pnpm -s run wp-env run cli wp --quiet plugin list

# list ionos blueprints options

pnpm -s run wp-env run cli wp --quiet option list --search='ionos_blueprints*' 2>/dev/null

# list foo property

pnpm -s run wp-env run cli wp --quiet option list --search='foo' 2>/dev/null

# list cron jobs

pnpm run wp-env run cli wp --quiet cron event list

*/

namespace ionos_blueprints_light\ionos_blueprints_light\blueprints;

use const ionos_blueprints_light\ionos_blueprints_light\FILE;

const OPTION_JOBS_SCHEDULED = 'ionos_blueprints_jobs';
const OPTION_JOBS_DONE = 'ionos_blueprints_jobs_done';

const CRON_JOB_HOOK = 'ionos_blueprints_cron_job';
const CRON_JOB_HOOK_DONE_ACTION = OPTION_JOBS_DONE . '_action';
const CRON_JOB_RECURRENCE = 'ionos_blueprints_cron_job_recurrence';

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

function _get_max_execution_time() {
  if(defined('CRON_JOB_MAX_EXECUTION_TIME')) {
    return constant('CRON_JOB_MAX_EXECUTION_TIME');
  } else {
    return 25;
  }
}

function _cron_job_next_tick_delay() {
  if(defined('CRON_JOB_NEXT_TICK_DELAY')) {
    return constant('CRON_JOB_NEXT_TICK_DELAY');
  } else {
    // default will be 10 seconds
    return 10;
  }
}

\register_deactivation_hook(
  file: FILE, 
  callback: function () {
    \wp_clear_scheduled_hook(CRON_JOB_HOOK);
    \delete_option(OPTION_JOBS_SCHEDULED);
    \delete_option(OPTION_JOBS_DONE);
  }
);

\add_action(
  hook_name: 'init', 
  callback: function() : void {
    if (\wp_next_scheduled(CRON_JOB_HOOK)===false) {
      $success = \wp_schedule_event(
        timestamp: time() + 10 * MINUTE_IN_SECONDS, // start first 10 minutes after first scheduling
        recurrence: CRON_JOB_RECURRENCE, 
        hook: CRON_JOB_HOOK
      );
    }
  }
);

\add_filter(
  hook_name: 'cron_schedules', 
  callback: function(array $schedules) : array {
    $schedules[CRON_JOB_RECURRENCE] = [
      'interval' => 10 * MINUTE_IN_SECONDS,
      'display'  => __('Every 10 Minutes')
    ];
    return $schedules;
  }
);

\add_action(
  hook_name: CRON_JOB_HOOK, 
  callback: function () : void {
    $jobs_scheduled = _get_jobs();
    $jobs_done = _get_jobs_done();

    $current_time = time();
    
    while(($job = array_shift($jobs_scheduled)) !== null) {
      $result = _execute_job($job);
      if (isset($result['error'])) {
        error_log($result['error']);
      }

      \update_option(OPTION_JOBS_SCHEDULED, $jobs_scheduled);

      $jobs_done[] = $result;
      \update_option(OPTION_JOBS_DONE, $jobs_done);

      if (time() - $current_time > _get_max_execution_time()) {
        // if there are still jobs scheduled, reschedule the cron job
        // to run again in 10 seconds
        if(count($jobs_scheduled) > 0) {
          \wp_schedule_single_event(
            timestamp: time() + _cron_job_next_tick_delay(), 
            hook: CRON_JOB_HOOK
          );
        }
        break;
      }
    }

    \do_action( CRON_JOB_HOOK_DONE_ACTION, $jobs_done);
  }
);

\add_action( CRON_JOB_HOOK_DONE_ACTION, function(array $jobs_done) : void {
  $jobs_done = _get_jobs_done();
  
  if (!empty($jobs_done)) {
    // @FIXME: send proceeded job results back to hosting platform

    // reset jobs done
    // \update_option(OPTION_JOBS_DONE, []);
  }
});

function _create_job_error(string $message, array $job) : array {
  return [
    'error' => $message,
    'id' => $job['id'],
    'args' => $job,
  ];
}

function _execute_job(array $job) : array {
  $HOOK_NAME = CRON_JOB_HOOK . '_' . $job['type'];
  if (!has_filter($HOOK_NAME)) {
    return _create_job_error(
      sprintf(
        '%s : no filter registered for job "%s"(hook_name="%s"). payload was %s',
        CRON_JOB_HOOK,
        $job['type'],
        $HOOK_NAME,
        \wp_json_encode($job)
      ),      
      $job
    );
  } else {
    $result = \apply_filters(
      hook_name: $HOOK_NAME,
      value: $job
    );

    if(!is_array($result)) {
      $result = _create_job_error(
        sprintf(
          '%s : filter "%s"(hook_name="%s") returned a non array result. result was %s',
          CRON_JOB_HOOK,
          $job['type'],
          $HOOK_NAME,
          \wp_json_encode($result)
        ),
        $job
      );
    }
  }

  $result['id'] = $job['id'];

  return $result;
}

function _get_jobs() : array {
  $jobs_scheduled = \get_option(OPTION_JOBS_SCHEDULED, []);
  return $jobs_scheduled;
}

function _get_jobs_done() {
  $jobs_done = \get_option(OPTION_JOBS_DONE, []);
  return $jobs_done;
}

# load all job definitions
foreach (glob(__DIR__ . '/jobs/*.php') as $file) {
  require_once $file;
}

