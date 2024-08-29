<?php
// Verhindert den direkten Aufruf der Datei
if (!defined('ABSPATH')) exit;

// Admin Settings for Oil Price and Consumption Rate
function csbh_enqueue_admin_scripts($hook)
{
    if ('settings_page_csbh_settings' !== $hook) {
        return;
    }
?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('input[name^="csbh_"]');
            inputs.forEach(input => {
                input.addEventListener('input', function(e) {
                    this.value = this.value.replace('.', ',');
                });
            });
        });
    </script>
<?php
}
add_action('admin_enqueue_scripts', 'csbh_enqueue_admin_scripts');

function csbh_sanitize_decimal($value)
{
    if (get_locale() === 'de_DE') {
        return str_replace(',', '.', $value);
    }
    return $value;
}

function csbh_register_settings()
{
    register_setting('csbh_settings_group', 'csbh_oil_price_' . date('Y'), 'csbh_sanitize_decimal');
    register_setting('csbh_settings_group', 'csbh_consumption_rate', 'csbh_sanitize_decimal');

    add_settings_section(
        'csbh_settings_section',
        'Oil Consumption Settings',
        null,
        'csbh_settings'
    );

    add_settings_field(
        'csbh_oil_price',
        'Oil Price per Liter (€)',
        'csbh_oil_price_callback',
        'csbh_settings',
        'csbh_settings_section'
    );

    add_settings_field(
        'csbh_consumption_rate',
        'Consumption Rate per Burner Hour (Liters)',
        'csbh_consumption_rate_callback',
        'csbh_settings',
        'csbh_settings_section'
    );
}
add_action('admin_init', 'csbh_register_settings');

function csbh_oil_price_callback()
{
    $oil_price = get_option('csbh_oil_price_' . date('Y'));
    echo '<input type="text" name="csbh_oil_price_' . date('Y') . '" value="' . esc_attr(str_replace('.', ',', $oil_price)) . '" />';
}

function csbh_consumption_rate_callback()
{
    $consumption_rate = get_option('csbh_consumption_rate');
    echo '<input type="text" name="csbh_consumption_rate" value="' . esc_attr(str_replace('.', ',', $consumption_rate)) . '" />';
}

// Admin Options Page
function csbh_add_admin_menu()
{
    add_options_page(
        'Cost Sharing Settings',
        'Cost Sharing Settings',
        'manage_options',
        'csbh_settings',
        'csbh_settings_page'
    );
}
add_action('admin_menu', 'csbh_add_admin_menu');

function csbh_settings_page()
{
?>
    <div class="wrap">
        <h1>Cost Sharing by Burner Hours - Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('csbh_settings_group');
            do_settings_sections('csbh_settings');
            submit_button();
            ?>
        </form>
    </div>
<?php
}
