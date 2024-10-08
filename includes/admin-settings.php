<?php
// Verhindert den direkten Aufruf der Datei
if (!defined('ABSPATH')) exit;

function csbh_burner_hours_admin_menu()
{
    add_menu_page(
        __('Burner Hours Settings', 'csbh'),         // Seitentitel
        __('Burner Hours Settings', 'csbh'),         // Menütext
        'manage_options',                            // Berechtigung
        'csbh_burner_hours_settings',                // Slug der Seite
        'csbh_burner_hours_settings_page',           // Callback-Funktion
        'dashicons-admin-generic'                    // Symbol
    );
}
add_action('admin_menu', 'csbh_burner_hours_admin_menu');

function csbh_burner_hours_settings_page()
{
    // Nonce für die Sicherheit
    $nonce_action = 'csbh_burner_hours_save_settings';
    $nonce_name = 'csbh_nonce';

    // Werte aus der Datenbank abrufen und sicherstellen, dass es ein Array ist
    $yearly_prices = get_option('csbh_yearly_prices', array());
    $consumption_rate = get_option('csbh_consumption_rate', '1');
    $show_all_entries = get_option('csbh_show_all_entries', false);

    if (!is_array($yearly_prices)) {
        $yearly_prices = array();
    }

    // Jahre ohne Preis abrufen
    $years_without_price = csbh_get_years_without_price($yearly_prices);

?>
    <div class="wrap">
        <h1>Manage Yearly Oil Prices</h1>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field($nonce_action, $nonce_name); ?>
            <input type="hidden" name="action" value="csbh_burner_hours_save_settings">

            <h2>Global Settings</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Consumption Rate (Liters per Burner Hour)</th>
                    <td><input type="text" name="csbh_consumption_rate" value="<?php echo esc_attr($consumption_rate); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Show All Entries</th>
                    <td><input type="checkbox" name="csbh_show_all_entries" <?php checked($show_all_entries, true); ?> /> Show all consumption entries in the admin area</td>
                </tr>
            </table>

            <?php if (!empty($years_without_price)) : ?>
                <h2>Add Price for Year</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Year</th>
                        <td>
                            <select name="new_year">
                                <option value="">Select Year</option>
                                <?php foreach ($years_without_price as $year) : ?>
                                    <option value="<?php echo esc_attr($year); ?>"><?php echo esc_html($year); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Price (€ per Liter)</th>
                        <td><input type="text" name="new_price" value="" /></td>
                    </tr>
                </table>
            <?php endif; ?>

            <?php if (!empty($yearly_prices)) : ?>
                <h2>Existing Prices</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th scope="col">Year</th>
                            <th scope="col">Price (€ per Liter)</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($yearly_prices as $year => $price) : ?>
                            <tr>
                                <td>
                                    <input type="text" name="csbh_years[<?php echo esc_attr($year); ?>]" value="<?php echo esc_attr($year); ?>" readonly />
                                </td>
                                <td>
                                    <input type="text" name="csbh_prices[<?php echo esc_attr($year); ?>]" value="<?php echo esc_attr($price); ?>" />
                                </td>
                                <td>
                                    <button type="submit" name="csbh_remove_year" value="<?php echo esc_attr($year); ?>" class="button button-secondary">Remove</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php submit_button('Save Changes'); ?>
        </form>
    </div>
<?php
}

function csbh_burner_hours_save_settings()
{
    // Überprüfen der Nonce
    if (!isset($_POST['csbh_nonce']) || !wp_verify_nonce($_POST['csbh_nonce'], 'csbh_burner_hours_save_settings')) {
        wp_die('Nonce verification failed.');
    }

    $yearly_prices = get_option('csbh_yearly_prices', array());

    if (!is_array($yearly_prices)) {
        $yearly_prices = array();
    }

    // Verbrauchsrate speichern
    if (isset($_POST['csbh_consumption_rate'])) {
        $consumption_rate = sanitize_text_field($_POST['csbh_consumption_rate']);
        update_option('csbh_consumption_rate', $consumption_rate);
    }

    // Option für das Anzeigen aller Einträge speichern
    $show_all_entries = isset($_POST['csbh_show_all_entries']) ? true : false;
    update_option('csbh_show_all_entries', $show_all_entries);

    // Jahr und Preis hinzufügen, nur wenn beide Felder ausgefüllt sind
    if (!empty($_POST['new_year']) && !empty($_POST['new_price'])) {
        $new_year = sanitize_text_field($_POST['new_year']);
        $new_price = sanitize_text_field($_POST['new_price']);

        // Fehlerüberprüfung
        if (ctype_digit($new_year) && is_numeric($new_price)) {
            // Jahr hinzufügen, wenn es noch nicht existiert
            if (!isset($yearly_prices[$new_year])) {
                $yearly_prices[$new_year] = $new_price;
                update_option('csbh_yearly_prices', $yearly_prices);
            } else {
                wp_die('Error: Year already exists.');
            }
        } else {
            wp_die('Error: Year and Price must be numeric.');
        }
    }

    // Entfernen eines Jahres
    if (isset($_POST['csbh_remove_year'])) {
        $remove_year = sanitize_text_field($_POST['csbh_remove_year']);
        if (isset($yearly_prices[$remove_year])) {
            unset($yearly_prices[$remove_year]);
            update_option('csbh_yearly_prices', $yearly_prices);

            wp_redirect(admin_url('admin.php?page=csbh_burner_hours_settings&removed=true'));
            exit;
        } else {
            wp_die('Error: Year to remove not found.');
        }
    }

    // Speichern der Änderungen
    if (isset($_POST['csbh_prices'])) {
        foreach ($_POST['csbh_prices'] as $year => $price) {
            $year = sanitize_text_field($year);
            $price = sanitize_text_field($price);

            if (isset($yearly_prices[$year])) {
                $yearly_prices[$year] = $price;
            } else {
                wp_die('Error: Year for price update not found.');
            }
        }

        update_option('csbh_yearly_prices', $yearly_prices);
    }

    wp_redirect(admin_url('admin.php?page=csbh_burner_hours_settings&updated=true'));
    exit;
}
add_action('admin_post_csbh_burner_hours_save_settings', 'csbh_burner_hours_save_settings');

function csbh_get_years_without_price($yearly_prices)
{
    global $wpdb;

    // Caching der Jahre ohne Preis
    $cache_key = 'csbh_years_without_price';
    $years_without_price = wp_cache_get($cache_key);

    if ($years_without_price === false) {
        $years = $wpdb->get_col("
            SELECT DISTINCT YEAR(meta_value) 
            FROM $wpdb->postmeta 
            WHERE meta_key = 'arrival_date' 
            AND post_id IN (
                SELECT ID FROM $wpdb->posts WHERE post_type = 'consumption_entry'
            )
            ORDER BY meta_value ASC
        ");

        $years_without_price = array_diff($years, array_keys($yearly_prices));
        wp_cache_set($cache_key, $years_without_price);
    }

    return $years_without_price;
}
