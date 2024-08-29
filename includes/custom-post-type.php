<?php
// Verhindert den direkten Aufruf der Datei
if (!defined('ABSPATH')) exit;

// Register Custom Post Type for Consumption Entries
function csbh_register_consumption_cpt()
{
    $labels = array(
        'name' => _x('Consumption Entries', 'Post Type General Name', 'text_domain'),
        'singular_name' => _x('Consumption Entry', 'Post Type Singular Name', 'text_domain'),
        'menu_name' => __('Consumption Entries', 'text_domain'),
        'name_admin_bar' => __('Consumption Entry', 'text_domain'),
        'archives' => __('Entry Archives', 'text_domain'),
        'attributes' => __('Entry Attributes', 'text_domain'),
        'parent_item_colon' => __('Parent Entry:', 'text_domain'),
        'all_items' => __('All Entries', 'text_domain'),
        'add_new_item' => __('Add New Entry', 'text_domain'),
        'add_new' => __('Add New', 'text_domain'),
        'new_item' => __('New Entry', 'text_domain'),
        'edit_item' => __('Edit Entry', 'text_domain'),
        'update_item' => __('Update Entry', 'text_domain'),
        'view_item' => __('View Entry', 'text_domain'),
        'view_items' => __('View Entries', 'text_domain'),
        'search_items' => __('Search Entry', 'text_domain'),
        'not_found' => __('Not found', 'text_domain'),
        'not_found_in_trash' => __('Not found in Trash', 'text_domain'),
        'featured_image' => __('Featured Image', 'text_domain'),
        'set_featured_image' => __('Set featured image', 'text_domain'),
        'remove_featured_image' => __('Remove featured image', 'text_domain'),
        'use_featured_image' => __('Use as featured image', 'text_domain'),
        'insert_into_item' => __('Insert into entry', 'text_domain'),
        'uploaded_to_this_item' => __('Uploaded to this entry', 'text_domain'),
        'items_list' => __('Entries list', 'text_domain'),
        'items_list_navigation' => __('Entries list navigation', 'text_domain'),
        'filter_items_list' => __('Filter entries list', 'text_domain'),
    );
    $args = array(
        'label' => __('Consumption Entry', 'text_domain'),
        'description' => __('A record of oil consumption for a specific period', 'text_domain'),
        'labels' => $labels,
        'supports' => array('title', 'author', 'custom-fields'),
        'hierarchical' => false,
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'menu_position' => 5,
        'show_in_admin_bar' => true,
        'show_in_nav_menus' => false,
        'can_export' => true,
        'has_archive' => false,
        'exclude_from_search' => true,
        'publicly_queryable' => false,
        'capability_type' => array('consumption_entry', 'consumption_entries'),
        'map_meta_cap' => true,
    );
    register_post_type('consumption_entry', $args);
}
add_action('init', 'csbh_register_consumption_cpt', 0);

// Add Custom Fields to the CPT using ACF
function csbh_add_consumption_fields()
{
    if (function_exists('acf_add_local_field_group')):
        acf_add_local_field_group(array(
            'key' => 'group_613f3a4b8f3e4',
            'title' => 'Consumption Details',
            'fields' => array(
                array(
                    'key' => 'field_613f3a54c2bb5',
                    'label' => 'Burner Operating Hours',
                    'name' => 'burner_hours',
                    'type' => 'number',
                    'required' => 1,
                    'min' => 0,
                ),
                array(
                    'key' => 'field_613f3a65c2bb6',
                    'label' => 'Arrival Date',
                    'name' => 'arrival_date',
                    'type' => 'date_picker',
                    'required' => 1,
                    'display_format' => 'd/m/Y', // Anzeigeformat im Admin-Panel
                    'return_format' => 'd/m/Y', // Speichern im d/m/Y Format
                    'first_day' => 1,
                ),
                array(
                    'key' => 'field_613f3a77c2bb7',
                    'label' => 'Departure Date',
                    'name' => 'departure_date',
                    'type' => 'date_picker',
                    'required' => 1,
                    'display_format' => 'd/m/Y', // Anzeigeformat im Admin-Panel
                    // 'return_format' => 'd/m/Y', // Speichern im d/m/Y Format
                    'return_format' => 'Y-m-d', // Speichern im Y-m-d Format

                    'first_day' => 1,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'consumption_entry',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
            'active' => true,
            'description' => '',
        ));
    endif;
}
add_action('acf/init', 'csbh_add_consumption_fields');

// Add role capabilities
function csbh_add_role_caps()
{
    $roles = array('administrator', 'editor', 'author', 'contributor', 'subscriber');
    foreach ($roles as $role) {
        $role_obj = get_role($role);
        if (!$role_obj) continue;
        $role_obj->add_cap('read_consumption_entry');
        $role_obj->add_cap('edit_consumption_entry');
        $role_obj->add_cap('edit_consumption_entries');
        $role_obj->add_cap('delete_consumption_entry');
        $role_obj->add_cap('delete_consumption_entries');
        $role_obj->add_cap('publish_consumption_entries');
        $role_obj->add_cap('create_consumption_entries');
    }
}
register_activation_hook(__FILE__, 'csbh_add_role_caps');
