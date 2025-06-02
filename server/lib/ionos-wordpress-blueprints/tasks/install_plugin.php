<?php

namespace ionos_wordpress_blueprints;

use const ionos_wordpress_blueprints\CRON_JOB_HOOK;

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

\add_filter( CRON_JOB_HOOK . '_install_plugin', function(array $payload) : array {
  $args = $payload['args'];
  
  $slug = $args['slug'];
  $force = $args['force'] ?? false;
  $url = $args['url'] ?? "https://downloads.wordpress.org/plugin/$slug.zip";

  $response = \wp_remote_get($url);

  if (\wp_remote_retrieve_response_code($response) !== 200) {
    return _create_task_error(
      sprintf(
        '%s : task "%s" failed to download plugin "%s". http_status=%s',
        CRON_JOB_HOOK,
        $payload['type'],
        $slug,
        \wp_remote_retrieve_response_code($response),
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
    return _create_task_error(
      sprintf(
        '%s : task "%s" failed to create temporary file for plugin "%s".',
        CRON_JOB_HOOK,
        $payload['type'],
        $slug
      ),
      $payload
    );
  }

  if (!file_put_contents($temp_file, \wp_remote_retrieve_body($response))) {
    return _create_task_error(
      sprintf(
        '%s : task "%s" failed to write downloaded plugin "%s" to temporary file.',
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
        return _create_task_error(
          sprintf(
            '%s : task "%s" aborted because plugin "%s" is already installed.',
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
    return _create_task_error(
      sprintf(
        '%s : %stask "%s" f%sailed to unzip plugin "%s". %s',
        CRON_JOB_HOOK,
        $payload['type'],
        $slug,
        \is_wp_error($success) ? $success->get_error_message() : 'unknown error',
      ),
      $payload
    );
  }
  
  \wp_cache_delete('plugins', 'plugins');
  return [
    'success' => $success
  ];
});