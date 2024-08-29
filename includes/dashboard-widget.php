<?php
// Verhindert den direkten Aufruf der Datei
if (!defined('ABSPATH')) exit;

// Dashboard Widget hinzufügen
function csbh_add_dashboard_widget()
{
    wp_add_dashboard_widget(
        'csbh_dashboard_widget',
        'Your Consumption Entries',
        'csbh_display_dashboard_widget'
    );
}
add_action('wp_dashboard_setup', 'csbh_add_dashboard_widget');

// Funktion zum Abrufen der Jahre, für die der Benutzer Daten hat
function csbh_get_user_years($user_id)
{
    global $wpdb;
    $years = $wpdb->get_col($wpdb->prepare("
        SELECT DISTINCT YEAR(STR_TO_DATE(meta_value, '%%Y%%m%%d')) as year
        FROM {$wpdb->prefix}postmeta pm
        JOIN {$wpdb->prefix}posts p ON p.ID = pm.post_id
        WHERE pm.meta_key = 'arrival_date'
        AND p.post_type = 'consumption_entry'
        AND p.post_author = %d
        AND p.post_status = 'publish'
        ORDER BY year DESC
    ", $user_id));

    return $years;
}


// Funktion zum Abrufen aller Jahre, für die Daten vorhanden sind
function csbh_get_all_years()
{
    global $wpdb;
    $years = $wpdb->get_col("
        SELECT DISTINCT YEAR(STR_TO_DATE(meta_value, '%Y%m%d')) as year
        FROM {$wpdb->prefix}postmeta pm
        JOIN {$wpdb->prefix}posts p ON p.ID = pm.post_id
        WHERE pm.meta_key = 'arrival_date'
        AND p.post_type = 'consumption_entry'
        AND p.post_status = 'publish'
        ORDER BY year DESC
    ");

    return $years;
}

function csbh_display_dashboard_widget()
{
    $current_user = wp_get_current_user();
    $is_admin = current_user_can('manage_options');

    echo '<h3>Hello, ' . esc_html($current_user->display_name) . '!</h3>';

    // Auswahl zwischen eigenen Daten und allen Benutzerdaten
    if ($is_admin) {
        echo '<form method="GET">';
        echo '<input type="radio" name="csbh_scope" value="self" ' . (isset($_GET['csbh_scope']) && $_GET['csbh_scope'] == 'self' ? 'checked' : '') . ' onchange="this.form.submit();"> My Data ';
        echo '<input type="radio" name="csbh_scope" value="all" ' . (isset($_GET['csbh_scope']) && $_GET['csbh_scope'] == 'all' ? 'checked' : '') . ' onchange="this.form.submit();"> All Users Data ';
        echo '</form>';
    }

    $scope = isset($_GET['csbh_scope']) ? $_GET['csbh_scope'] : 'self';

    // Hole alle Jahre, für die der Benutzer Daten hat
    $years = $scope === 'all' ? csbh_get_all_years() : csbh_get_user_years($current_user->ID);

    // Wenn keine Daten vorhanden sind, zeigen wir eine Nachricht an
    if (empty($years)) {
        echo '<p>No data available.</p>';
        return;
    }

    // Bestimme das ausgewählte Jahr
    $selected_year = isset($_GET['csbh_year']) ? intval($_GET['csbh_year']) : max($years);

    // Dropdown zur Jahresauswahl anzeigen
    echo '<form method="GET">';
    echo '<input type="hidden" name="csbh_scope" value="' . esc_attr($scope) . '">';
    echo '<select name="csbh_year" onchange="this.form.submit();">';
    foreach ($years as $year) {
        $selected = $year == $selected_year ? 'selected' : '';
        echo "<option value=\"$year\" $selected>$year</option>";
    }
    echo '</select>';
    echo '</form>';

    if ($scope === 'self') {
        csbh_display_user_data($current_user->ID, $selected_year);
    } elseif ($scope === 'all') {
        csbh_display_all_users_data($selected_year);
    }
}

function csbh_display_user_data($user_id, $selected_year)
{
    // Hole den Ölpreis und die Verbrauchsrate aus den Einstellungen
    $oil_price = get_option('csbh_oil_price_' . date('Y'));
    $consumption_rate = get_option('csbh_consumption_rate');

    if (!is_numeric($oil_price) || !is_numeric($consumption_rate)) {
        echo '<p>Invalid configuration. Please check the settings.</p>';
        return;
    }

    $oil_price = (float) $oil_price;
    $consumption_rate = (float) $consumption_rate;

    // Debugging: Prüfen, ob user_id korrekt ist
    error_log("Fetching data for user_id: $user_id for year: $selected_year");

    $query_args = array(
        'post_type' => 'consumption_entry',
        'author' => $user_id,
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
        'meta_query' => array(
            array(
                'key' => 'arrival_date',
                'value' => array("{$selected_year}0101", "{$selected_year}1231"),
                'compare' => 'BETWEEN',
                'type' => 'NUMERIC'
            )
        )
    );

    // Debugging: Prüfen der Query-Argumente
    error_log(print_r($query_args, true));

    $query = new WP_Query($query_args);
    $total_costs = 0;

    echo '<table class="widefat">';
    echo '<thead><tr><th>Title</th><th>Arrival</th><th>Departure</th><th>Costs (€)</th></tr></thead>';
    echo '<tbody>';
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $burner_hours = (float) get_field('burner_hours');
            $arrival_date = get_field('arrival_date');
            $departure_date = get_field('departure_date');
            $consumption = $burner_hours * $consumption_rate;
            $costs = $consumption * $oil_price;
            $total_costs += $costs;
            echo '<tr>';
            echo '<td>' . get_the_title() . '</td>';
            echo '<td>' . esc_html($arrival_date) . '</td>';
            echo '<td>' . esc_html($departure_date) . '</td>';
            echo '<td>' . csbh_format_decimal($costs) . ' €</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="4">No entries found.</td></tr>';
    }
    echo '</tbody>';
    echo '<tfoot>';
    echo '<tr><td colspan="3"><strong>Total Costs:</strong></td><td><strong>' . csbh_format_decimal($total_costs) . ' €</strong></td></tr>';
    echo '</tfoot>';
    echo '</table>';

    wp_reset_postdata();
}

function csbh_display_all_users_data($selected_year)
{
    // Hole den Ölpreis und die Verbrauchsrate aus den Einstellungen
    $oil_price = get_option('csbh_oil_price_' . date('Y'));
    $consumption_rate = get_option('csbh_consumption_rate');

    if (!is_numeric($oil_price) || !is_numeric($consumption_rate)) {
        echo '<p>Invalid configuration. Please check the settings.</p>';
        return;
    }

    $oil_price = (float) $oil_price;
    $consumption_rate = (float) $consumption_rate;

    $users = get_users(array('fields' => array('ID', 'display_name')));
    $total_costs_all_users = 0;

    echo '<table class="widefat">';
    echo '<thead><tr><th>User</th><th>Title</th><th>Arrival</th><th>Departure</th><th>Costs (€)</th></tr></thead>';
    echo '<tbody>';
    foreach ($users as $user) {
        $query_args = array(
            'post_type' => 'consumption_entry',
            'author' => $user->ID,
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => 'arrival_date',
                    'value' => array("{$selected_year}0101", "{$selected_year}1231"),
                    'compare' => 'BETWEEN',
                    'type' => 'NUMERIC'
                )
            )
        );

        // Debugging: Prüfen der Query-Argumente
        error_log(print_r($query_args, true));

        $query = new WP_Query($query_args);
        $total_costs_user = 0;

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $burner_hours = (float) get_field('burner_hours');
                $arrival_date = get_field('arrival_date');
                $departure_date = get_field('departure_date');
                $consumption = $burner_hours * $consumption_rate;
                $costs = $consumption * $oil_price;
                $total_costs_user += $costs;
                echo '<tr>';
                echo '<td>' . esc_html($user->display_name) . '</td>';
                echo '<td>' . get_the_title() . '</td>';
                echo '<td>' . esc_html($arrival_date) . '</td>';
                echo '<td>' . esc_html($departure_date) . '</td>';
                echo '<td>' . csbh_format_decimal($costs) . ' €</td>';
                echo '</tr>';
            }
            echo '<tr>';
            echo '<td colspan="4"><strong>Total for ' . esc_html($user->display_name) . ':</strong></td>';
            echo '<td><strong>' . csbh_format_decimal($total_costs_user) . ' €</strong></td>';
            echo '</tr>';
        }
        $total_costs_all_users += $total_costs_user;
    }
    echo '</tbody>';
    echo '<tfoot>';
    echo '<tr><td colspan="4"><strong>Total Costs for All Users:</strong></td><td><strong>' . csbh_format_decimal($total_costs_all_users) . ' €</strong></td></tr>';
    echo '</tfoot>';
    echo '</table>';
}

// Add custom CSS for the dashboard widget
function csbh_add_dashboard_widget_styles()
{
    echo '
    <style>
        .widefat th, .widefat td {
            padding: 8px;
        }
        .widefat th {
            background-color: #f1f1f1;
        }
    </style>
    ';
}
add_action('admin_head', 'csbh_add_dashboard_widget_styles');
