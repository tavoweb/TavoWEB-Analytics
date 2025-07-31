<?php
/*
Plugin Name: TavoWeb Analytics
Plugin URI: https://tavoweb.eu
Description: Įtraukia TavoWeb Analytics skriptą į svetainės <head> dalį ir rodo statistiką prietaisų skydelyje.
Version: 2.0
Author: TavoWeb
Author URI: https://tavoweb.eu
*/

// Apsauga nuo tiesioginio failo vykdymo
if (!defined('ABSPATH')) {
    exit;
}

class TavoWeb_Analytics_Plugin {

    public function __construct() {
        // Išsaugome esamą funkcionalumą
        add_action('wp_head', array($this, 'add_analytics_script'));

        // Pridedame nustatymų puslapį
        add_action('admin_menu', array($this, 'add_settings_page'));

        // Registruojame nustatymus
        add_action('admin_init', array($this, 'register_settings'));

        // Pridedame prietaisų skydelio valdiklį
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
    }

    /**
     * Įterpia analizės skriptą į <head>
     */
    public function add_analytics_script() {
        $site_id = get_option('tavoweb_analytics_site_id');
        if (!empty($site_id)) {
            echo '<script data-host="https://analytics.tavoweb.eu" data-dnt="false" src="https://analytics.tavoweb.eu/js/script.js" id="' . esc_attr($site_id) . '" async defer></script>';
        }
    }

    /**
     * Prideda nustatymų puslapį į "Settings" meniu
     */
    public function add_settings_page() {
        add_options_page(
            'TavoWeb Analytics Nustatymai',
            'TavoWeb Analytics',
            'manage_options',
            'tavoweb-analytics',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Atvaizduoja nustatymų puslapio turinį
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>TavoWeb Analytics Nustatymai</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('tavoweb_analytics_options');
                do_settings_sections('tavoweb-analytics');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Registruoja nustatymus, sekcijas ir laukus
     */
    public function register_settings() {
        register_setting('tavoweb_analytics_options', 'tavoweb_analytics_api_key');
        register_setting('tavoweb_analytics_options', 'tavoweb_analytics_site_id');

        add_settings_section(
            'tavoweb_analytics_main_section',
            'API Nustatymai',
            null,
            'tavoweb-analytics'
        );

        add_settings_field(
            'tavoweb_analytics_api_key',
            'API Raktas',
            array($this, 'render_api_key_field'),
            'tavoweb-analytics',
            'tavoweb_analytics_main_section'
        );

        add_settings_field(
            'tavoweb_analytics_site_id',
            'Svetainės ID',
            array($this, 'render_site_id_field'),
            'tavoweb-analytics',
            'tavoweb_analytics_main_section'
        );
    }

    /**
     * Atvaizduoja API rakto įvesties lauką
     */
    public function render_api_key_field() {
        $api_key = get_option('tavoweb_analytics_api_key');
        echo '<input type="text" name="tavoweb_analytics_api_key" value="' . esc_attr($api_key) . '" class="regular-text">';
    }

    /**
     * Atvaizduoja Svetainės ID įvesties lauką
     */
    public function render_site_id_field() {
        $site_id = get_option('tavoweb_analytics_site_id');
        echo '<input type="text" name="tavoweb_analytics_site_id" value="' . esc_attr($site_id) . '" class="regular-text">';
    }

    /**
     * Prideda valdiklį į prietaisų skydelį
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'tavoweb_analytics_dashboard_widget',
            'TavoWeb Analytics',
            array($this, 'render_dashboard_widget')
        );
    }

    /**
     * Atvaizduoja prietaisų skydelio valdiklio turinį
     */
    public function render_dashboard_widget() {
        $api_key = get_option('tavoweb_analytics_api_key');
        $site_id = get_option('tavoweb_analytics_site_id');

        if (empty($api_key) || empty($site_id)) {
            echo '<p>Prašome įvesti savo TavoWeb Analytics API raktą ir Svetainės ID <a href="' . admin_url('options-general.php?page=tavoweb-analytics') . '">nustatymų puslapyje</a>.</p>';
            return;
        }

        $stats_to_fetch = [
            'visitors' => 'Lankytojai (Visitors)',
            'pageviews' => 'Puslapių peržiūros (Pageviews)',
            'bounce_rate' => 'Grįžimo rodiklis (Bounce Rate)',
            'visit_duration' => 'Apsilankymo trukmė (Visit Duration)',
        ];

        $results = [];
        $base_url = 'https://analytics.tavoweb.eu/api/v1/stats/' . $site_id;
        $to_date = date('Y-m-d');
        $from_date = date('Y-m-d', strtotime('-30 days'));

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Accept' => 'application/json',
            ),
        );

        foreach ($stats_to_fetch as $stat_name => $label) {
            $query_params = [
                'name' => $stat_name,
                'from' => $from_date,
                'to' => $to_date,
            ];
            $api_url = add_query_arg($query_params, $base_url);

            $response = wp_remote_get($api_url, $args);
            $value = 'N/A';

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);

                if (in_array($stat_name, ['visitors', 'pageviews']) && isset($data['data']) && is_array($data['data'])) {
                    $total = 0;
                    foreach ($data['data'] as $day) {
                        $total += $day['count'];
                    }
                    $value = $total;
                } elseif (isset($data['value'])) { // For bounce_rate, visit_duration etc. if they return a single value
                    $value = esc_html($data['value']);
                    if ($stat_name === 'bounce_rate') $value .= '%';
                    if ($stat_name === 'visit_duration') $value .= 's';
                }
            }
            $results[$label] = $value;
        }
        ?>
        <ul>
            <?php foreach ($results as $label => $value) : ?>
                <li><strong><?php echo $label; ?>:</strong> <?php echo $value; ?></li>
            <?php endforeach; ?>
        </ul>
        <?php
    }
}

// Inicijuojame įskiepį
new TavoWeb_Analytics_Plugin();
