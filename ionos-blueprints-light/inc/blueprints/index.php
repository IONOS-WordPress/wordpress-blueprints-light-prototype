<?php

/*

# reset instance

pnpm -s run wp-env run cli wp --quiet option delete ionos_blueprints_tasks ionos_blueprints_tasks_done foo 2>/dev/null
pnpm -s run wp-env run cli wp --quiet plugin deactivate hello-dolly 2>/dev/null
pnpm -s run wp-env run cli wp --quiet plugin delete hello-dolly 2>/dev/null

# inject new tasks

tasks="$(pnpm -s run wp-env run cli wp --quiet option get ionos_blueprints_tasks --format=json 2>/dev/null || echo '[]')"
tasks=$(jq '. += [
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
]' <<< "$tasks")
pnpm -s run wp-env run cli wp --quiet option set ionos_blueprints_tasks "$tasks" --format=json

# reset tasks_done
pnpm -s run wp-env run cli wp --quiet option set ionos_blueprints_tasks_done "[]" --format=json

# check tasks done 

echo $(pnpm -s run wp-env run cli wp --quiet option get ionos_blueprints_tasks_done --format=json 2>/dev/null || echo '[]') | jq .

# check enqueued tasks

echo $(pnpm -s run wp-env run cli wp --quiet option get ionos_blueprints_tasks --format=json 2>/dev/null || echo '[]') | jq .

# trigger processing tasks

pnpm -s run wp-env run cli wp --quiet cron event run ionos_blueprints_cron_task

# list active plugins

pnpm -s run wp-env run cli wp --quiet plugin list

# list ionos blueprints options

pnpm -s run wp-env run cli wp --quiet option list --search='ionos_blueprints*' 2>/dev/null

# list foo property

pnpm -s run wp-env run cli wp --quiet option list --search='foo' 2>/dev/null

# list cron tasks

pnpm run wp-env run cli wp --quiet cron event list

*/

namespace ionos_blueprints_light\ionos_blueprints_light\blueprints;

use const ionos_blueprints_light\ionos_blueprints_light\FILE;

const OPTION_TASKS_SCHEDULED = 'ionos_blueprints_tasks';
const OPTION_TASKS_DONE = 'ionos_blueprints_tasks_done';

const JOB_VALIDATION_HOOK_PREFIX = 'ionos_blueprints_task_validation_';
const CRON_JOB_HOOK = 'ionos_blueprints_cron_task';
const CRON_JOB_HOOK_DONE_ACTION = OPTION_TASKS_DONE . '_action';
const CRON_JOB_RECURRENCE = 'ionos_blueprints_cron_task_recurrence';

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

/**
 * this is the time limit for each cron task call
 * by default 10 seconds will be used for each cron task call
 * 
 * You can customize the maximum execution time for each cron task call by defining a constant 
 * 'CRON_JOB_MAX_EXECUTION_TIME'. 
 * 
 * A value of 0 means execute only one task per cron call.
 * A value of -1 means no limit on execution time.
 *
 * @return  int max_execution_time in seconds per cron call
 */
function _get_max_execution_time() {
  if(defined('CRON_JOB_MAX_EXECUTION_TIME')) {
    return constant('CRON_JOB_MAX_EXECUTION_TIME');
  } else {
    return 25;
  }
}

function _cron_task_next_tick_delay() {
  if(defined('CRON_JOB_NEXT_TICK_DELAY')) {
    return constant('CRON_JOB_NEXT_TICK_DELAY');
  } else {
    // default will be 10 seconds
    return 10;
  }
}

/**
 * cleanup persisted options and cron tasks
 */ 
\register_deactivation_hook(
  file: FILE, 
  callback: function () {
    \wp_clear_scheduled_hook(CRON_JOB_HOOK);
    \delete_option(OPTION_TASKS_SCHEDULED);
    \delete_option(OPTION_TASKS_DONE);
  }
);

/**
 * register cron task
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
 * validate and sanitize tasks according to their json schema definition
 */

function enqueue_tasks(array $tasks) : bool|\WP_Error {
  foreach ($tasks as $index => $task) {
    if(!is_array($task)) {
      return new \WP_Error(
        'invalid_task',
        sprintf('task(=%s) is not an array', json_encode($task)),
        $task,
      );
    }

    if(!isset($task['type'])) {
      return new \WP_Error(
        'invalid_task_type',
        'task has no "type" property',
        $task,
      );
    }

    $task_type = $task['type'];

    if(!\has_filter(CRON_JOB_HOOK . '_' . $task_type)) {
      return new \WP_Error(
        'invalid_task_type',
        sprintf('task type "%s"(filter=%s) is unknown : No filter registered', $task_type, CRON_JOB_HOOK . '_' . $task_type),
        $task,
      );
    }

    if(!\has_filter(JOB_VALIDATION_HOOK_PREFIX . $task_type)) {
      error_log(sprintf(
        'task(=%s) has no validation filter registered for type "%s"',
        $task_type,
        \wp_json_encode($task),
      ));
      continue;
    }

    // validate filter against json schema
    $result = \apply_filters(
      hook_name: JOB_VALIDATION_HOOK_PREFIX . $task_type,
      value: $task
    );

    if(is_wp_error($result)) {
      return new  \WP_Error(
        'invalid_task',
        sprintf(
          'task(=%s) is not valid according to schema. %s',
          \wp_json_encode($task),
          $result->get_error_message()
        ),
        [
          'task' => $task,
          'error' => $result,
        ]
      );
    }

    $tasks[$index] = $result;
  }

  // merge new tasks and already enqueued tasks
  $tasks_scheduled = \get_option(OPTION_TASKS_SCHEDULED, []);
  \update_option(OPTION_TASKS_SCHEDULED, array_merge($tasks_scheduled, $tasks));

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
    $tasks_scheduled = _get_tasks();
    $tasks_done = _get_tasks_done();

    $current_time = time();
    
    while(($task = array_shift($tasks_scheduled)) !== null) {
      $result = _execute_task($task);
      if (isset($result['error'])) {
        error_log($result['error']);
      }

      \update_option(OPTION_TASKS_SCHEDULED, $tasks_scheduled);

      $tasks_done[] = $result;
      \update_option(OPTION_TASKS_DONE, $tasks_done);

      $max_execution_time = _get_max_execution_time();
      if($max_execution_time === 0) {
        // abort after one task
        break;
      } else if($max_execution_time === -1) {
        // no limit
        continue;
      } else if (time() - $current_time > $max_execution_time) {
        // if there are still tasks scheduled, reschedule the cron task
        // to run again in 10 seconds
        if(count($tasks_scheduled) > 0) {
          \wp_schedule_single_event(
            timestamp: time() + _cron_task_next_tick_delay(), 
            hook: CRON_JOB_HOOK
          );
        }
        break;
      }
    }

    \do_action( CRON_JOB_HOOK_DONE_ACTION, $tasks_done);
  }
);

\add_action( CRON_JOB_HOOK_DONE_ACTION, function(array $tasks_done) : void {
  $tasks_done = _get_tasks_done();
  
  if (!empty($tasks_done)) {
    // @FIXME: send proceeded task results back to hosting platform

    // reset tasks done
    // \update_option(OPTION_TASKS_DONE, []);
  }
});

function _create_task_error(string $message, array $task) : array {
  return [
    'error' => $message,
    'id' => $task['id'],
    'args' => $task,
  ];
}

function _execute_task(array $task) : array {
  $HOOK_NAME = CRON_JOB_HOOK . '_' . $task['type'];
  if (!has_filter($HOOK_NAME)) {
    return _create_task_error(
      sprintf(
        '%s : no filter registered for task "%s"(hook_name="%s"). payload was %s',
        CRON_JOB_HOOK,
        $task['type'],
        $HOOK_NAME,
        \wp_json_encode($task)
      ),      
      $task
    );
  } else {
    $result = \apply_filters(
      hook_name: $HOOK_NAME,
      value: $task
    );

    if(!is_array($result)) {
      $result = _create_task_error(
        sprintf(
          '%s : filter "%s"(hook_name="%s") returned a non array result. result was %s',
          CRON_JOB_HOOK,
          $task['type'],
          $HOOK_NAME,
          \wp_json_encode($result)
        ),
        $task
      );
    }
  }

  $result['id'] = $task['id'];

  return $result;
}

function _get_tasks() : array {
  $tasks_scheduled = \get_option(OPTION_TASKS_SCHEDULED, []);
  return $tasks_scheduled;
}

function _get_tasks_done() : array {
  $tasks_done = \get_option(OPTION_TASKS_DONE, []);
  return $tasks_done;
}

function _add_filter_task_validation(string $task_type, array $json_schema) : void {
  \add_filter(
    hook_name: JOB_VALIDATION_HOOK_PREFIX . $task_type,
    callback: function(array $task) use ($json_schema, $task_type) {
      $task = \rest_sanitize_value_from_schema(
        value: $task,
        args: $json_schema,
        param: $task_type
      );
      $result = \rest_validate_value_from_schema(
        value: $task,
        args: $json_schema,
        param: $task_type
      );

      return \is_wp_error($result) ? $result : $task;
    },
  );
}

/**
 * loads task types (each in a separate php file) and registers the json schema for the task 
 * the json schema is later on used to validate the task arguments before task execution
 * 
 * @param $path to load task types
 */
function _load_task_types(string $path) : void {
  # load all task definitions
  foreach (glob($path . '/*.php') as $file) {
    require_once $file;
    
    $schema_file = preg_replace('/\.php$/', '.schema.json', $file);
    if(!file_exists($schema_file)) {
      error_log(sprintf(
        'Schema file "%s" not found for task "%s"',
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

    $task_type = basename($file, '.php');
    _add_filter_task_validation($task_type, $json_schema);
  }
}

_load_task_types(__DIR__ . '/tasks');