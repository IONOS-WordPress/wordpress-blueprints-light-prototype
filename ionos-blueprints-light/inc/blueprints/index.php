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
      id: "5fd8850f-ddb6-42f4-a63e-8eab98ea7f9b",
      type: "install_plugin",
      args: {
        "url": "https://downloads.wordpress.org/plugin/hello-dolly.zip",
        "slug": "hello-dolly/hello.php"
      }
    },
    { 
      id: "71d59ac9-2de6-4fc2-829f-57179b25ec89",
      type: "set_option",
      args: {
        "name": "foo",
        "value": "bar"
      }
    },
    { 
      id: "1f290990-3a61-4da3-9e5d-4b8581467761",
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

const JOB_VALIDATION_HOOK_PREFIX = 'ionos_blueprints_job_validation_';
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

/**
 * cleanup persisted options and cron jobs
 */ 
\register_deactivation_hook(
  file: FILE, 
  callback: function () {
    \wp_clear_scheduled_hook(CRON_JOB_HOOK);
    \delete_option(OPTION_JOBS_SCHEDULED);
    \delete_option(OPTION_JOBS_DONE);
  }
);

/**
 * register cron job
 */
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

/**
 * validate and sanitize jobs according to their json schema definition
 */

function enqueue_jobs(array $jobs) : bool|\WP_Error {
  foreach ($jobs as $index => $job) {
    if(!is_array($job)) {
      return new \WP_Error(
        'invalid_job',
        sprintf('job(=%s) is not an array', json_encode($job)),
        $job,
      );
    }

    if(!isset($job['type'])) {
      return new \WP_Error(
        'invalid_job_type',
        'job has no "type" property',
        $job,
      );
    }

    $job_type = $job['type'];

    if(!\has_filter(CRON_JOB_HOOK . '_' . $job_type)) {
      return new \WP_Error(
        'invalid_job_type',
        sprintf('job type "%s"(filter=%s) is unknown : No filter registered', $job_type, CRON_JOB_HOOK . '_' . $job_type),
        $job,
      );
    }

    if(!\has_filter(JOB_VALIDATION_HOOK_PREFIX . $job_type)) {
      error_log(sprintf(
        'job(=%s) has no validation filter registered for type "%s"',
        $job_type,
        \wp_json_encode($job),
      ));
      continue;
    }

    // validate filter against json schema
    $result = \apply_filters(
      hook_name: JOB_VALIDATION_HOOK_PREFIX . $job_type,
      value: $job
    );

    if(is_wp_error($result)) {
      return new  \WP_Error(
        'invalid_job',
        sprintf(
          'job(=%s) is not valid according to schema. %s',
          \wp_json_encode($job),
          $result->get_error_message()
        ),
        [
          'job' => $job,
          'error' => $result,
        ]
      );
    }

    $jobs[$index] = $result;
  }

  // merge new jobs and already enqueued jobs
  $jobs_scheduled = \get_option(OPTION_JOBS_SCHEDULED, []);
  \update_option(OPTION_JOBS_SCHEDULED, array_merge($jobs_scheduled, $jobs));

  return true;
}

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

function _get_jobs_done() : array {
  $jobs_done = \get_option(OPTION_JOBS_DONE, []);
  return $jobs_done;
}

function _add_filter_job_validation(string $job_type, array $json_schema) : void {
  \add_filter(
    hook_name: JOB_VALIDATION_HOOK_PREFIX . $job_type,
    callback: function(array $job) use ($json_schema, $job_type) {
      $job = \rest_sanitize_value_from_schema(
        value: $job,
        args: $json_schema,
        param: $job_type
      );
      $result = \rest_validate_value_from_schema(
        value: $job,
        args: $json_schema,
        param: $job_type
      );

      return \is_wp_error($result) ? $result : $job;
    },
  );
}

/**
 * loads job types (each in a separate php file) and registers the json schema for the job 
 * the json schema is later on used to validate the job arguments before job execution
 * 
 * @param $path to load job types
 */
function _load_job_types(string $path) : void {
  # load all job definitions
  foreach (glob($path . '/*.php') as $file) {
    require_once $file;
    
    $schema_file = preg_replace('/\.php$/', '.schema.json', $file);
    if(!file_exists($schema_file)) {
      error_log(sprintf(
        'Schema file "%s" not found for job "%s"',
        $schema_file,
        $file
      ));
      continue;
    }

    $json_schema = \wp_json_file_decode($schema_file, ['associative' => true]);
    if($json_schema === null) {
      error_log(sprintf(
        'Schema file "%s" is not valid json',
        $schema_file
      ));
      continue;
    }

    if(!isset($json_schema['type'])) {
      error_log(sprintf(
        'Schema(file=%s) is not a valid JSON Schema. Missing "type" property in "%s"',
        $schema_file,
        $json_schema,
      ));
      continue;
    }

    $job_type = basename($file, '.php');
    _add_filter_job_validation($job_type, $json_schema);
  }
}

_load_job_types(__DIR__ . '/jobs');