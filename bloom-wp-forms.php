<?php
/**
 * Plugin Name: Bloom Forms
 * Plugin URI: https://github.com/bloom-ux/bloom-wp-forms/
 * Description: Custom forms scaffolding for WordPress
 * Version: 0.1.0
 * Author: bloom.lat
 * Author URI: https://www.bloom.lat/
 * License: GPL-3.0-or-later
 *
 * @package Bloom_UX\WP_Forms
 */

namespace Bloom_UX\WP_Forms;

$bloom_forms = Plugin::get_instance();
register_activation_hook( __FILE__, array( $bloom_forms, 'activation_hook' ) );
