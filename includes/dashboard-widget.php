<?php
// Sicherstellen, dass kein direkter Zugriff auf diese Datei möglich ist
if (!defined('ABSPATH')) exit;

function csbh_add_dashboard_widgets()
{
    wp_add_dashboard_widget(
        'csbh_dashboard_widget',        // Widget-Slug
        'Oil Consumption Summary',      // Titel
        'csbh_display_dashboard_widget' // Callback-Funktion
    );
}
add_action('wp_dashboard_setup', 'csbh_add_dashboard_widgets');

function csbh_display_dashboard_widget()
{
    $current_user = wp_get_current_user();

    // Begrüßungstext
    echo '<h3>Hello, ' . esc_html($current_user->display_name) . '!</h3>';

    // Überprüfen, ob die Checkbox für "All Users Data" gesetzt ist
    $show_all_users = isset($_GET['csbh_show_all_users']) && $_GET['csbh_show_all_users'] === 'on';

    // Dropdown für die Auswahl des Jahres
    $selected_year = isset($_GET['csbh_year']) ? intval($_GET['csbh_year']) : date('Y');
    echo '<form method="GET">';
    echo '<select name="csbh_year" onchange="this.form.submit();">';
    foreach (csbh_get_years_with_posts($show_all_users ? null : $current_user->ID) as $year) {
        $selected = $year == $selected_year ? 'selected' : '';
        echo "<option value=\"$year\" $selected>$year</option>";
    }
    echo '</select>';

    // Checkbox für "All Users Data"
    if (current_user_can('administrator')) {
        echo '<label style="margin-left: 10px;"><input type="checkbox" name="csbh_show_all_users" onchange="this.form.submit();" ' . checked($show_all_users, true, false) . '> Show All Users Data</label>';
    }
    echo '</form>';

    // Anzeige der Einträge basierend auf der Auswahl der Checkbox
    if ($show_all_users && current_user_can('administrator')) {
        echo '<h4>All Users Data</h4>';
        csbh_display_all_users_data($selected_year);
    } else {
        echo '<h4>My Data</h4>';
        csbh_display_user_data($current_user->ID, $selected_year);
    }
}

function csbh_display_user_data($user_id, $year)
{
    global $wpdb;

    // Abfrage für die Beiträge des Benutzers für das ausgewählte Jahr
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

    $query = new WP_Query($query_args);

    $yearly_prices = get_option('csbh_yearly_prices', array());
    $consumption_rate = get_option('csbh_consumption_rate', '1');

    // Debugging-Ausgabe
    error_log("User ID: $user_id, Year: $year");

    // Fallback-Mechanismus: Suche nach dem Preis für das Jahr, oder den Vorjahren
    $price = csbh_get_price_for_year($year, $yearly_prices);
    $rate = (float)$consumption_rate;

    echo '<table class="widefat">';
    echo '<thead><tr><th>Title</th><th>Arrival</th><th>Departure</th><th>Costs (€)</th></tr></thead>';
    echo '<tbody>';
    $total_costs = 0.0;

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();

            $burner_hours = get_field('burner_hours');
            $arrival_date = get_field('arrival_date');
            $departure_date = get_field('departure_date');

            // Berechne die Gesamtkosten
            $costs = (float)$burner_hours * $rate * $price;
            $total_costs += $costs;

            echo '<tr>';
            echo '<td>' . get_the_title() . '</td>';
            echo '<td>' . esc_html($arrival_date) . '</td>';
            echo '<td>' . esc_html($departure_date) . '</td>';
            echo '<td>' . number_format($costs, 2, ',', '.') . ' €</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="4">No entries found for this year.</td></tr>';
    }

    echo '</tbody>';
    echo '<tfoot><tr><th colspan="3">Total Costs</th><th>' . number_format($total_costs, 2, ',', '.') . ' €</th></tr></tfoot>';
    echo '</table>';

    wp_reset_postdata();
}

function csbh_display_all_users_data($year)
{
    global $wpdb;

    $yearly_prices = get_option('csbh_yearly_prices', array());
    $consumption_rate = get_option('csbh_consumption_rate', '1');

    // Debugging-Ausgabe
    error_log("All Users Data for Year: $year");

    // Fallback-Mechanismus: Suche nach dem Preis für das Jahr, oder den Vorjahren
    $price = csbh_get_price_for_year($year, $yearly_prices);
    $rate = (float)$consumption_rate;

    echo '<table class="widefat">';
    echo '<thead><tr><th>User</th><th>Title</th><th>Arrival</th><th>Departure</th><th>Costs (€)</th></tr></thead>';
    echo '<tbody>';
    $total_costs = 0.0;

    // Abfrage für die Beiträge aller Benutzer für das ausgewählte Jahr
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

    $query = new WP_Query($query_args);

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();

            $user = get_the_author();
            $burner_hours = get_field('burner_hours');
            $arrival_date = get_field('arrival_date');
            $departure_date = get_field('departure_date');

            // Berechne die Gesamtkosten
            $costs = (float)$burner_hours * $rate * $price;
            $total_costs += $costs;

            echo '<tr>';
            echo '<td>' . esc_html($user) . '</td>';
            echo '<td>' . get_the_title() . '</td>';
            echo '<td>' . esc_html($arrival_date) . '</td>';
            echo '<td>' . esc_html($departure_date) . '</td>';
            echo '<td>' . number_format($costs, 2, ',', '.') . ' €</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="5">No entries found for this year.</td></tr>';
    }

    echo '</tbody>';
    echo '<tfoot><tr><th colspan="4">Total Costs</th><th>' . number_format($total_costs, 2, ',', '.') . ' €</th></tr></tfoot>';
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

    $years = $wpdb->get_col("
        SELECT DISTINCT YEAR(meta_value) 
        FROM $wpdb->postmeta 
        WHERE meta_key = 'arrival_date' 
        AND post_id IN (
            SELECT ID FROM $wpdb->posts WHERE post_type = 'consumption_entry' $user_clause
        )
        ORDER BY meta_value ASC
    ");

    error_log("Years with posts: " . implode(", ", $years));

    return $years;
}

function csbh_get_price_for_year($year, $yearly_prices)
{
    $default_price = 1.0; // Beispiel für einen Standardpreis

    // Debugging-Ausgabe
    error_log("Getting price for year: " . $year);

    // Wenn keine Preise vorhanden sind, gib den Standardpreis zurück
    if (empty($yearly_prices)) {
        error_log("No yearly prices available, using default price");
        return $default_price;
    }

    // Fallback-Mechanismus: Suche nach dem Preis für das Jahr, oder den Vorjahren
    while ($year > 0) {
        if (isset($yearly_prices[$year])) {
            error_log("Using price from year: " . $year);
            return (float)$yearly_prices[$year];
        }
        $year--;
    }

    // Fallback, wenn kein Preis gefunden wurde, auf den Standardpreis
    error_log("No valid price found, returning default price");
    return $default_price;
}
