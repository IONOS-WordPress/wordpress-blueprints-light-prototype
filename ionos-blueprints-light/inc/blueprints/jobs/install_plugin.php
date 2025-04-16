<?php

namespace ionos_blueprints_light\ionos_blueprints_light\blueprints;

use const ionos_blueprints_light\ionos_blueprints_light\blueprints\CRON_JOB_HOOK;

\add_filter( CRON_JOB_HOOK . '_install_plugin', function(array $payload) : array {
  $args = $payload['args'];
  
  if( !isset($args['slug'])) {
    return _create_job_error(
      sprintf(
        '%s : job "%s" requires "slug" in args. payload was %s',
        CRON_JOB_HOOK,
        $payload['type'],
        \wp_json_encode($payload)
      ),
      $payload
    );
  }
  
  $slug = $args['slug'];
  $force = $args['force'] ?? false;
  $url = $args['url'] ?? "https://downloads.wordpress.org/plugin/$slug.zip";

  $response = \wp_remote_get($url);

  if (\wp_remote_retrieve_response_code($response) !== 200) {
    return _create_job_error(
      sprintf(
        '%s : job "%s" failed to download plugin "%s". http_status=%s',
        CRON_JOB_HOOK,
        $payload['type'],
        \wp_remote_retrieve_response_code($response),
        $slug
      ),
      $payload
    );
  }

  global $wp_filesystem;
  if ( is_null( $wp_filesystem ) ) {
      require_once ABSPATH . '/wp-admin/includes/file.php';
      WP_Filesystem();
  }

  $temp_file = \wp_tempnam($url);
  if (!$temp_file) {
    return _create_job_error(
      sprintf(
        '%s : job "%s" failed to create temporary file for plugin "%s".',
        CRON_JOB_HOOK,
        $payload['type'],
        $slug
      ),
      $payload
    );
  }

  if (!file_put_contents($temp_file, \wp_remote_retrieve_body($response))) {
    return _create_job_error(
      sprintf(
        '%s : job "%s" failed to write plugin "%s" to temporary file.',
        CRON_JOB_HOOK,
        $payload['type'],
        $slug
      ),
      $payload
    );
  }

  $installed_plugins = \get_plugins();
  foreach ($installed_plugins as $plugin_file => $plugin_data) {
    if (strpos($plugin_file, $slug) !== false) {
      if($force) {
        \deactivate_plugins($plugin_file);
        \delete_plugins([$plugin_file]);
        break;
      } else{
        return _create_job_error(
          sprintf(
            '%s : job "%s" aborted because plugin "%s" is already installed.',
            CRON_JOB_HOOK,
            $payload['type'],
            $slug
          ),
          $payload
        );
      }
    }
  }

  $success = \unzip_file($temp_file, WP_PLUGIN_DIR);
  @unlink($temp_file); // Clean up the temporary file

  if($success !== true) {
    return _create_job_error(
      sprintf(
        '%s : job "%s" failed to unzip plugin "%s". ',
        CRON_JOB_HOOK,
        $payload['type'],
        $slug,
      \is_wp_error($success) ? $success->get_error_message() : 'unknown error',
      ),
      $payload
    );
  }

  return [
    'success' => $success
  ];
});