<?php
/*
Plugin Name: Cost Sharing by Burner Hours
PLUGIN URI: https://christoph-heim.de
Description: A plugin to manage oil consumption based on burner operating hours. Allows users to enter multiple consumption records per year and view them in a table.
Version: 1.0
Author: Christoph Heim
Author URI: https://christoph-heim.de
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /languages/
Text Domain: csbh
*/

// Verhindert den direkten Aufruf der Datei
if (!defined('ABSPATH')) exit;

// Inkludiere die Hilfsfunktionen
require_once plugin_dir_path(__FILE__) . 'includes/helpers.php';

// textdomain für Übersetzungen
function csbh_load_textdomain_manual()
{
    $result = load_plugin_textdomain('csbh', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('plugins_loaded', 'csbh_load_textdomain_manual');

// Inkludiere die verschiedenen Moduldateien
require_once plugin_dir_path(__FILE__) . 'includes/custom-post-type.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/widgets.php';
require_once plugin_dir_path(__FILE__) . 'includes/dashboard-widget.php';
