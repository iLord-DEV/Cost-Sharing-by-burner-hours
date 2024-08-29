<?php
// Verhindert den direkten Aufruf der Datei
if (!defined('ABSPATH')) exit;

// Formatieren der Werte für die Ausgabe
function csbh_format_decimal($value)
{
    if (get_locale() === 'de_DE') {
        return number_format($value, 2, ',', '.');
    }
    return number_format($value, 2, '.', ',');
}
