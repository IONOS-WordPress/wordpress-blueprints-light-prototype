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

namespace ionos_wordpress_blueprints;

const TASK_VALIDATION_FILTER_PREFIX = 'ionos_blueprints_task_validation_';
const TASK_EXECUTION_FILTER_PREFIX = 'ionos_blueprints_task_execution_';

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

/**
 * validate and sanitize tasks according to their json schema definition
 */
function validate_tasks(array $tasks) : array|\WP_Error {
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

    if(!\has_filter(TASK_EXECUTION_FILTER_PREFIX . '_' . $task_type)) {
      return new \WP_Error(
        'invalid_task_type',
        sprintf('task type "%s"(filter=%s) is unknown : No filter registered', $task_type, TASK_EXECUTION_FILTER_PREFIX . '_' . $task_type),
        $task,
      );
    }

    if(!\has_filter(TASK_VALIDATION_FILTER_PREFIX . $task_type)) {
      error_log(sprintf(
        'task(=%s) has no validation filter registered for type "%s"',
        $task_type,
        \wp_json_encode($task),
      ));
      continue;
    }

    // validate filter against json schema
    $result = \apply_filters(
      hook_name: TASK_VALIDATION_FILTER_PREFIX . $task_type,
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

  return $tasks;
}

function execute_tasks(array $tasks) : array|\WP_Error {
  $task_results = [];

  while(($task = array_shift($tasks)) !== null) {
    $result = _execute_task($task);
    if (isset($result['error'])) {
      error_log($result['error']);
    }

    $task_results[] = $result;
  }

  return $task_results;
}

function _create_task_error(string $message, array $task) : array {
  return [
    'error' => $message,
    'id' => $task['id'],
    'args' => $task,
  ];
}

function _execute_task(array $task) : array {
  $HOOK_NAME = TASK_EXECUTION_FILTER_PREFIX . '_' . $task['type'];
  if (!has_filter($HOOK_NAME)) {
    return _create_task_error(
      sprintf(
        '%s : no filter registered for task "%s"(hook_name="%s"). payload was %s',
        TASK_EXECUTION_FILTER_PREFIX,
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
          TASK_EXECUTION_FILTER_PREFIX,
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

function _add_filter_task_validation(string $task_type, array $json_schema) : void {
  \add_filter(
    hook_name: TASK_VALIDATION_FILTER_PREFIX . $task_type,
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
  # @FIXME: https://www.php.net/manual/en/phar.using.stream.php

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