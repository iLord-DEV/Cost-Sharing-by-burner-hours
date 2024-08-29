<?php
// Verhindert den direkten Aufruf der Datei
if (!defined('ABSPATH')) exit;

// Create a widget to display the consumption entries table
class CSBH_Consumption_Widget extends WP_Widget
{
    public function __construct()
    {
        parent::__construct(
            'csbh_consumption_widget',
            'Consumption Entries Table',
            array('description' => 'Displays a table of consumption entries.')
        );
    }

    public function widget($args, $instance)
    {
        echo $args['before_widget'];
        echo $args['before_title'] . 'Your Consumption Entries' . $args['after_title'];

        $current_user = wp_get_current_user();

        // Hole alle Jahre, für die der Benutzer Daten hat
        $years = $this->get_user_years($current_user->ID);

        // Wenn keine Daten vorhanden sind, zeigen wir eine Nachricht an
        if (empty($years)) {
            echo '<p>No data available.</p>';
            echo $args['after_widget'];
            return;
        }

        // Bestimme das ausgewählte Jahr
        $selected_year = isset($_GET['csbh_year']) ? intval($_GET['csbh_year']) : max($years);

        // Dropdown zur Jahresauswahl anzeigen
        echo '<form method="GET">';
        echo '<select name="csbh_year" onchange="this.form.submit();">';
        foreach ($years as $year) {
            $selected = $year == $selected_year ? 'selected' : '';
            echo "<option value=\"$year\" $selected>$year</option>";
        }
        echo '</select>';
        echo '</form>';

        // Hole die Daten für das ausgewählte Jahr
        $query_args = array(
            'post_type' => 'consumption_entry',
            'author' => $current_user->ID,
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_query' => array(
                array(
                    'key' => 'arrival_date',
                    'value' => array("01/01/$selected_year", "31/12/$selected_year"),
                    'compare' => 'BETWEEN',
                    'type' => 'DATE'
                )
            )
        );
        $query = new WP_Query($query_args);
        $oil_price = csbh_format_decimal(get_option('csbh_oil_price_' . date('Y')));
        $consumption_rate = csbh_format_decimal(get_option('csbh_consumption_rate'));
        $total_costs = 0;

        // Ausgabe der Tabelle
        echo '<table class="consumption-entries-table">';
        echo '<thead><tr><th>Title</th><th>Arrival</th><th>Departure</th><th>Costs (€)</th></tr></thead>';
        echo '<tbody>';
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $burner_hours = get_field('burner_hours');
                $arrival_date = get_field('arrival_date');
                $departure_date = get_field('departure_date');
                $consumption = $burner_hours * str_replace(',', '.', $consumption_rate);
                $costs = $consumption * str_replace(',', '.', $oil_price);
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
        echo '</table>';

        // Ausgabe der Jahressumme
        echo '<p><strong>Total Costs for ' . $selected_year . ':</strong> ' . csbh_format_decimal($total_costs) . ' €</p>';

        wp_reset_postdata();
        echo $args['after_widget'];
    }

    // Funktion zum Abrufen der Jahre, für die der Benutzer Daten hat
    private function get_user_years($user_id)
    {
        global $wpdb;
        $years = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT YEAR(STR_TO_DATE(meta_value, '%%d/%%m/%%Y')) as year
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
}

// Register the widget
function csbh_register_widget()
{
    register_widget('CSBH_Consumption_Widget');
}
add_action('widgets_init', 'csbh_register_widget');

// Add custom CSS for the widget
function csbh_add_widget_styles()
{
    echo '
    <style>
        .consumption-entries-table {
            width: 100%;
            border-collapse: collapse;
        }
        .consumption-entries-table th, .consumption-entries-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .consumption-entries-table th {
            background-color: #f2f2f2;
        }
    </style>
    ';
}
add_action('wp_head', 'csbh_add_widget_styles');
