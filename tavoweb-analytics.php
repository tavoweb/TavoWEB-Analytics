<?php
/*
Plugin Name: TavoWeb Analytics
Plugin URI: https://tavoweb.eu
Description: Įtraukia TavoWeb Analytics skriptą į svetainės <head> dalį.
Version: 1.0
Author: TavoWeb
Author URI: https://tavoweb.eu
*/

// Apsauga nuo tiesioginio failo vykdymo
if (!defined('ABSPATH')) {
    exit;
}

// Funkcija, kuri įterpia skriptą į <head>
function tavoweb_add_analytics_script() {
    echo '<script data-host="https://analytics.tavoweb.eu" data-dnt="false" src="https://analytics.tavoweb.eu/js/script.js" id="ZwSg9rf6GA" async defer></script>';
}
add_action('wp_head', 'tavoweb_add_analytics_script');
