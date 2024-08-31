<?php
// Sicherstellen, dass kein direkter Zugriff auf diese Datei möglich ist
if (!defined('ABSPATH')) exit;

function csbh_add_dashboard_widgets()
{
    wp_add_dashboard_widget(
        'csbh_dashboard_widget',
        __('Oil Consumption Summary', 'csbh'),
        'csbh_display_dashboard_widget'
    );
}
add_action('wp_dashboard_setup', 'csbh_add_dashboard_widgets');

function csbh_display_dashboard_widget()
{
    $current_user = wp_get_current_user();

    echo '<h3 style="margin-block-start:1em; margin-bottom:0;">' . __('Hello', 'csbh') . ' ' . esc_html($current_user->display_name) . '!</h3>';
    echo '<p style="margin-top:0;">' . __('Here you can see an overview of your oil consumption entries.', 'csbh') . '</p>';

    $show_all_users = isset($_GET['csbh_show_all_users']) && sanitize_text_field($_GET['csbh_show_all_users']) === 'on';
    $selected_year = isset($_GET['csbh_year']) ? intval($_GET['csbh_year']) : date('Y');

    echo '<form method="GET">';
    echo '<select name="csbh_year" onchange="this.form.submit();">';
    foreach (csbh_get_years_with_posts($show_all_users ? null : $current_user->ID) as $year) {
        $selected = selected($year, $selected_year, false);
        echo "<option value=\"" . esc_attr($year) . "\" $selected>$year</option>";
    }
    echo '</select>';

    if (current_user_can('administrator')) {
        echo '<label style="margin-left: 10px;"><input type="checkbox" name="csbh_show_all_users" onchange="this.form.submit();" ' . checked($show_all_users, true, false) . '>' . __('Show All Users Data', 'csbh') . '</label>';
    }
    echo '</form>';

    if ($show_all_users && current_user_can('administrator')) {
        echo '<h4 style="margin-top:3em;">' . __('All Users Data', 'csbh') . '</h4>';
        csbh_display_all_users_data($selected_year);
    } else {
        echo '<h4 style="margin-top:3em;">' . __('My Data', 'csbh') . '</h4>';
        csbh_display_user_data($current_user->ID, $selected_year);
    }
}

function csbh_display_user_data($user_id, $year)
{
    global $wpdb;

    // Caching the results to avoid unnecessary database queries
    $cache_key = "csbh_user_data_{$user_id}_{$year}";
    $user_data = wp_cache_get($cache_key);

    if ($user_data === false) {
        $query_args = array(
            'post_type' => 'consumption_entry',
            'author' => $user_id,
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => 'arrival_date',
                    'value' => array($year . '0101', $year . '1231'),
                    'compare' => 'BETWEEN',
                    'type' => 'NUMERIC'
                )
            )
        );

        $user_data = new WP_Query($query_args);
        wp_cache_set($cache_key, $user_data);
    }

    $yearly_prices = get_option('csbh_yearly_prices', array());
    $consumption_rate = get_option('csbh_consumption_rate', '1');
    $price = csbh_get_price_for_year($year, $yearly_prices);
    $rate = (float)$consumption_rate;

    echo '<table class="widefat">';
    echo '<thead><tr><th>' . __('Title', 'csbh') . '</th><th>' . __('Arrival', 'csbh') . '</th><th>' . __('Departure', 'csbh') . '</th><th>' . __('Costs (€)', 'csbh') . '</th></tr></thead>';
    echo '<tbody>';
    $total_costs = 0.0;

    if ($user_data->have_posts()) {
        while ($user_data->have_posts()) {
            $user_data->the_post();

            $burner_hours = get_field('burner_hours');
            $arrival_date_raw = get_field('arrival_date');
            $departure_date_raw = get_field('departure_date');

            $arrival_date = DateTime::createFromFormat('d/m/Y', $arrival_date_raw);
            $departure_date = DateTime::createFromFormat('d/m/Y', $departure_date_raw);

            $formatted_arrival_date = $arrival_date ? $arrival_date->format('d. F Y') : __('Invalid Date', 'csbh');
            $formatted_departure_date = $departure_date ? $departure_date->format('d. F Y') : __('Invalid Date', 'csbh');

            $costs = (float)$burner_hours * $rate * $price;
            $total_costs += $costs;

            echo '<tr>';
            echo '<td>' . get_the_title() . '</td>';
            echo '<td>' . esc_html($formatted_arrival_date) . '</td>';
            echo '<td>' . esc_html($formatted_departure_date) . '</td>';
            echo '<td>' . number_format($costs, 2, ',', '.') . ' €</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="4">' . __('No entries found for this year.', 'csbh') . '</td></tr>';
    }

    echo '</tbody>';
    echo '<tfoot><tr><th colspan="3">' . __('Total Costs', 'csbh') . '</th><th>' . number_format($total_costs, 2, ',', '.') . ' €</th></tr></tfoot>';
    echo '</table>';

    wp_reset_postdata();
}

function csbh_display_all_users_data($year)
{
    global $wpdb;

    // Caching the results to avoid unnecessary database queries
    $cache_key = "csbh_all_users_data_{$year}";
    $all_users_data = wp_cache_get($cache_key);

    if ($all_users_data === false) {
        $query_args = array(
            'post_type' => 'consumption_entry',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => 'arrival_date',
                    'value' => array($year . '0101', $year . '1231'),
                    'compare' => 'BETWEEN',
                    'type' => 'NUMERIC'
                )
            )
        );

        $all_users_data = new WP_Query($query_args);
        wp_cache_set($cache_key, $all_users_data);
    }

    $yearly_prices = get_option('csbh_yearly_prices', array());
    $consumption_rate = get_option('csbh_consumption_rate', '1');
    $price = csbh_get_price_for_year($year, $yearly_prices);
    $rate = (float)$consumption_rate;

    echo '<table class="widefat">';
    echo '<thead><tr><th>' . __('User', 'csbh') . '</th><th>' . __('Title', 'csbh') . '</th><th>' . __('Arrival', 'csbh') . '</th><th>' . __('Departure', 'csbh') . '</th><th>' . __('Costs (€)', 'csbh') . '</th></tr></thead>';
    echo '<tbody>';
    $total_costs = 0.0;

    if ($all_users_data->have_posts()) {
        while ($all_users_data->have_posts()) {
            $all_users_data->the_post();

            $user = get_the_author();
            $burner_hours = get_field('burner_hours');
            $arrival_date_raw = get_field('arrival_date');
            $departure_date_raw = get_field('departure_date');

            $arrival_date = DateTime::createFromFormat('d/m/Y', $arrival_date_raw);
            $departure_date = DateTime::createFromFormat('d/m/Y', $departure_date_raw);

            $formatted_arrival_date = $arrival_date ? $arrival_date->format('d. F Y') : __('Invalid Date', 'csbh');
            $formatted_departure_date = $departure_date ? $departure_date->format('d. F Y') : __('Invalid Date', 'csbh');

            $costs = (float)$burner_hours * $rate * $price;
            $total_costs += $costs;

            echo '<tr>';
            echo '<td>' . esc_html($user) . '</td>';
            echo '<td>' . get_the_title() . '</td>';
            echo '<td>' . esc_html($formatted_arrival_date) . '</td>';
            echo '<td>' . esc_html($formatted_departure_date) . '</td>';
            echo '<td>' . number_format($costs, 2, ',', '.') . ' €</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="5">' . __('No entries found for this year.', 'csbh') . '</td></tr>';
    }

    echo '</tbody>';
    echo '<tfoot><tr><th colspan="4">' . __('Total Costs', 'csbh') . '</th><th>' . number_format($total_costs, 2, ',', '.') . ' €</th></tr></tfoot>';
    echo '</table>';

    wp_reset_postdata();
}

function csbh_get_years_with_posts($user_id = null)
{
    global $wpdb;

    $user_clause = '';
    if ($user_id !== null) {
        $user_clause = $wpdb->prepare("AND post_author = %d", $user_id);
    }

    // Caching the years with posts to avoid unnecessary database queries
    $cache_key = "csbh_years_with_posts_{$user_id}";
    $years = wp_cache_get($cache_key);

    if ($years === false) {
        $years = $wpdb->get_col("
            SELECT DISTINCT YEAR(meta_value) 
            FROM $wpdb->postmeta 
            WHERE meta_key = 'arrival_date' 
            AND post_id IN (
                SELECT ID FROM $wpdb->posts WHERE post_type = 'consumption_entry' $user_clause
            )
            ORDER BY meta_value ASC
        ");

        wp_cache_set($cache_key, $years);
    }

    return $years;
}

function csbh_get_price_for_year($year, $yearly_prices)
{
    $default_price = 1.0;

    // Caching the price for the year to avoid unnecessary calculations
    $cache_key = "csbh_price_for_year_{$year}";
    $price = wp_cache_get($cache_key);

    if ($price === false) {
        if (empty($yearly_prices)) {
            $price = $default_price;
        } else {
            while ($year > 0) {
                if (isset($yearly_prices[$year])) {
                    $price = (float)$yearly_prices[$year];
                    break;
                }
                $year--;
            }
            $price = $price ?? $default_price;
        }

        wp_cache_set($cache_key, $price);
    }

    return $price;
}
