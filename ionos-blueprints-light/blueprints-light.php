<?php

/**
 * Plugin Name:       ionos-blueprints-light
 * Description:       plugin prototype implementing wordpress playground blueprints light
 * Requires at least: 6.6
 * Requires Plugins:
 * Requires PHP:      8.3
 * Version:           0.0.1
 * Plugin URI:        https://github.com/IONOS-WordPress/wordpress-blueprints-light-prototype
 * License:           GPL-2.0-or-later
 * Author:            IONOS Group
 * Author URI:        https://www.ionos-group.com/brands.html
 * Domain Path:       /languages
 * Text Domain:       ionos-essentials
 */

namespace ionos_blueprints_light\ionos_blueprints_light;

if ( ! defined( 'ABSPATH' ) ) {
  die();
}

\add_action( "init", function() {
   error_log("hey!");
 });