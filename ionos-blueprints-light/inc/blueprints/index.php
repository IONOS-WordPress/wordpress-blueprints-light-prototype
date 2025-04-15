<?php

namespace ionos_blueprints_light\ionos_blueprints_light\blueprints;

use const ionos_blueprints_light\ionos_blueprints_light\FILE;

const OPTION_JOBS_SCHEDULED = 'ionos_blueprints_jobs';
const OPTION_JOBS_DONE = 'ionos_blueprints_jobs_done';

const CRON_JOB_HOOK = 'ionos_blueprints_cron_job';
const CRON_JOB_RECURRENCE = 'ionos_blueprints_cron_job_recurrence';

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
        timestamp: time() + 10 * MINUTE_IN_SECONDS,
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

    while(($job = array_shift($jobs_scheduled)) !== null) {
      $jobResult = _execute_job($job);

      \update_option(OPTION_JOBS_SCHEDULED, $jobs_scheduled);

      $jobs_done[] = $jobResult;
      \update_option(OPTION_JOBS_DONE, $jobs_done);
    }
  }
);

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

