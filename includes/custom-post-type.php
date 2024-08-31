<?php
// Verhindert den direkten Aufruf der Datei
if (!defined('ABSPATH')) exit;

// Register Custom Post Type for Consumption Entries
function csbh_register_consumption_cpt()
{
    $labels = array(
        'name'                  => _x('Consumption Entries', 'Post Type General Name', 'csbh'),
        'singular_name'         => _x('Consumption Entry', 'Post Type Singular Name', 'csbh'),
        'menu_name'             => __('Consumption Entries', 'csbh'),
        'name_admin_bar'        => __('Consumption Entry', 'csbh'),
        'archives'              => __('Entry Archives', 'csbh'),
        'attributes'            => __('Entry Attributes', 'csbh'),
        'parent_item_colon'     => __('Parent Entry:', 'csbh'),
        'all_items'             => __('All Entries', 'csbh'),
        'add_new_item'          => __('Add New Entry', 'csbh'),
        'add_new'               => __('Add New', 'csbh'),
        'new_item'              => __('New Entry', 'csbh'),
        'edit_item'             => __('Edit Entry', 'csbh'),
        'update_item'           => __('Update Entry', 'csbh'),
        'view_item'             => __('View Entry', 'csbh'),
        'view_items'            => __('View Entries', 'csbh'),
        'search_items'          => __('Search Entry', 'csbh'),
        'not_found'             => __('Not found', 'csbh'),
        'not_found_in_trash'    => __('Not found in Trash', 'csbh'),
        'featured_image'        => __('Featured Image', 'csbh'),
        'set_featured_image'    => __('Set featured image', 'csbh'),
        'remove_featured_image' => __('Remove featured image', 'csbh'),
        'use_featured_image'    => __('Use as featured image', 'csbh'),
        'insert_into_item'      => __('Insert into entry', 'csbh'),
        'uploaded_to_this_item' => __('Uploaded to this entry', 'csbh'),
        'items_list'            => __('Entries list', 'csbh'),
        'items_list_navigation' => __('Entries list navigation', 'csbh'),
        'filter_items_list'     => __('Filter entries list', 'csbh'),
    );
    $args = array(
        'label'                 => __('Consumption Entry', 'csbh'),
        'description'           => __('A record of oil consumption for a specific period', 'csbh'),
        'labels'                => $labels,
        'supports'              => array('title', 'author', 'custom-fields'),
        'hierarchical'          => false,
        'public'                => false,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 5,
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => false,
        'can_export'            => true,
        'has_archive'           => false,
        'exclude_from_search'   => true,
        'publicly_queryable'    => false,
        'capability_type'       => array('consumption_entry', 'consumption_entries'),
        'map_meta_cap'          => true,
    );
    register_post_type('consumption_entry', $args);
}
add_action('init', 'csbh_register_consumption_cpt', 0);

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
                    'display_format' => 'd/m/Y',
                    'return_format' => 'd/m/Y',
                    'first_day' => 1,
                ),
                array(
                    'key' => 'field_613f3a77c2bb7',
                    'label' => 'Departure Date',
                    'name' => 'departure_date',
                    'type' => 'date_picker',
                    'required' => 1,
                    'display_format' => 'd/m/Y',
                    'return_format' => 'd/m/Y',
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

// Filter posts in admin to show only user's own posts (with option for admins)
function csbh_filter_consumption_entries_for_current_user($query)
{
    global $pagenow, $post_type;

    if (is_admin() && $pagenow == 'edit.php' && $post_type == 'consumption_entry') {
        $show_all_entries = get_option('csbh_show_all_entries', false);

        if (!current_user_can('administrator') || !$show_all_entries) {
            // Nur eigene Einträge anzeigen
            $query->set('author', get_current_user_id());
        }
    }
}
add_action('pre_get_posts', 'csbh_filter_consumption_entries_for_current_user');

// Set post author to current user when creating a new consumption entry
function csbh_set_consumption_entry_author($data)
{
    if ($data['post_type'] == 'consumption_entry') {
        $data['post_author'] = get_current_user_id();
    }
    return $data;
}
add_filter('wp_insert_post_data', 'csbh_set_consumption_entry_author');
