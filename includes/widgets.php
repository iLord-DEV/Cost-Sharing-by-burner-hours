<?php
// Verhindert den direkten Aufruf der Datei
if (!defined('ABSPATH')) exit;

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
        $query_args = array(
            'post_type' => 'consumption_entry',
            'author' => $current_user->ID,
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        $query = new WP_Query($query_args);
        $total_costs = 0;

        echo '<table class="consumption-entries-table">';
        echo '<thead><tr><th>Title</th><th>Arrival</th><th>Departure</th><th>Costs (€)</th></tr></thead>';
        echo '<tbody>';
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $burner_hours = (float) get_field('burner_hours');
                $arrival_date = get_field('arrival_date');
                $year = date('Y', strtotime($arrival_date));
                $yearly_prices = get_option('csbh_yearly_prices', array());
                $oil_price = isset($yearly_prices[$year]) ? $yearly_prices[$year] : get_option('csbh_default_oil_price', 1);
                $consumption_rate = (float) get_option('csbh_consumption_rate');

                $consumption = $burner_hours * $consumption_rate;
                $costs = $consumption * $oil_price;
                $total_costs += $costs;

                echo '<tr>';
                echo '<td>' . get_the_title() . '</td>';
                echo '<td>' . esc_html($arrival_date) . '</td>';
                echo '<td>' . esc_html(get_field('departure_date')) . '</td>';
                echo '<td>' . csbh_format_decimal($costs) . ' €</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="4">No entries found.</td></tr>';
        }
        echo '<tr>';
        echo '<td colspan="3"><strong>Total Costs:</strong></td>';
        echo '<td><strong>' . csbh_format_decimal($total_costs) . ' €</strong></td>';
        echo '</tr>';
        echo '</tbody></table>';

        wp_reset_postdata();

        echo $args['after_widget'];
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
