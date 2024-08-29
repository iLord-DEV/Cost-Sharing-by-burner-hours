<?php
/*
Plugin Name: Cost Sharing by Burner Hours
Description: A plugin to manage oil consumption based on burner operating hours. Allows users to enter multiple consumption records per year and view them in a table.
Version: 1.0
Author: Christoph Heim
*/

// Verhindert den direkten Aufruf der Datei
if (!defined('ABSPATH')) exit;

// Inkludiere die Hilfsfunktionen
require_once plugin_dir_path(__FILE__) . 'includes/helpers.php';


// Inkludiere die verschiedenen Moduldateien
require_once plugin_dir_path(__FILE__) . 'includes/custom-post-type.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/widgets.php';
require_once plugin_dir_path(__FILE__) . 'includes/dashboard-widget.php';
