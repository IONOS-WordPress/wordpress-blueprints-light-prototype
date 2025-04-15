<?php

/*

  pnpm run wp-env run cli wp cron event list

  pnpm run wp-env run cli wp cron event run ionos_blueprints_cron_job

*/

namespace ionos_blueprints_light\ionos_blueprints_light\blueprints;

use const ionos_blueprints_light\ionos_blueprints_light\FILE;

const OPTION_JOBS_SCHEDULED = 'ionos_blueprints_jobs';
const OPTION_JOBS_DONE = 'ionos_blueprints_jobs_done';

const CRON_JOB_HOOK = 'ionos_blueprints_cron_job';
const CRON_JOB_RECURRENCE = 'ionos_blueprints_cron_job_recurrence';

const CRON_JOB_MAX_EXECUTION_TIME = 25; // in seconds

\register_deactivation_hook(
  file: FILE, 
  callback: function () {
    \wp_clear_scheduled_hook(CRON_JOB_HOOK);
    \delete_option(OPTION_JOBS_SCHEDULED);
    \delete_option(OPTION_JOBS_DONE);
  }
);

\register_activation_hook(
  file: FILE, 
  callback: function() {
    if (!\wp_next_scheduled(CRON_JOB_HOOK)) {
      \wp_schedule_event(
        timestamp: time() + 10 * MINUTE_IN_SECONDS, // dont start immediately but after 10 minutes
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
  callback: function () {
    $jobs_scheduled = _get_jobs();
    $jobs_done = _get_jobs_done();
    $current_time = time();
    
    while(($job = array_shift($jobs_scheduled)) !== null) {
      $jobResult = _execute_job($job);

      \update_option(OPTION_JOBS_SCHEDULED, $jobs_scheduled);

      $jobs_done[] = $jobResult;
      \update_option(OPTION_JOBS_DONE, $jobs_done);

      if (time() - $current_time > CRON_JOB_MAX_EXECUTION_TIME) {
        break;
      }
    }

    _send_jobs_done();
  }
);

function _send_jobs_done() {
  $jobs_done = _get_jobs_done();

  if (!empty($jobs_done)) {
    // @FIXME: send proceeded job results back to hosting platform
    
    // // reset jobs done
    // \update_option(OPTION_JOBS_DONE, []);
  }
}

function _execute_job(array $job) : array {
  // @TODO: Implement the logic to execute the job
  return $job;
}

function _get_jobs() {
  $jobs_scheduled = \get_option(OPTION_JOBS_SCHEDULED, []);
  return $jobs_scheduled;
}

function _get_jobs_done() {
  $jobs_done = \get_option(OPTION_JOBS_DONE, []);
  return $jobs_done;
}


