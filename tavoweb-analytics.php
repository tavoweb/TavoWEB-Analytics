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
        echo '<script data-host="https://analytics.tavoweb.eu" data-dnt="false" src="https://analytics.tavoweb.eu/js/script.js" id="ZwSg9rf6GA" async defer></script>';
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
    }

    /**
     * Atvaizduoja API rakto įvesties lauką
     */
    public function render_api_key_field() {
        $api_key = get_option('tavoweb_analytics_api_key');
        echo '<input type="text" name="tavoweb_analytics_api_key" value="' . esc_attr($api_key) . '" class="regular-text">';
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

        if (empty($api_key)) {
            echo '<p>Prašome įvesti savo TavoWeb Analytics API raktą <a href="' . admin_url('options-general.php?page=tavoweb-analytics') . '">nustatymų puslapyje</a>.</p>';
            return;
        }

        $api_url = 'https://analytics.tavoweb.eu/api/v1/stats';
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
            ),
        );

        $response = wp_remote_get($api_url, $args);

        if (is_wp_error($response)) {
            echo '<p>Klaida gaunant duomenis: ' . $response->get_error_message() . '</p>';
            return;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $data = json_decode($response_body, true);

        if ($response_code !== 200 || !$data) {
            echo '<p>Nepavyko gauti arba apdoroti statistikos duomenų. Patikrinkite savo API raktą arba bandykite vėliau.</p>';
            // For debugging:
            // echo '<pre>'; print_r($response_body); echo '</pre>';
            return;
        }

        // Darant prielaidą, kad API grąžina tokius duomenis. Tai gali reikėti pakeisti.
        $stats = $data['results'];
        ?>
        <ul>
            <li><strong>Lankytojai (Visitors):</strong> <?php echo isset($stats['visitors']) ? esc_html($stats['visitors']['value']) : 'N/A'; ?></li>
            <li><strong>Puslapių peržiūros (Pageviews):</strong> <?php echo isset($stats['pageviews']) ? esc_html($stats['pageviews']['value']) : 'N/A'; ?></li>
            <li><strong>Grįžimo rodiklis (Bounce Rate):</strong> <?php echo isset($stats['bounce_rate']) ? esc_html($stats['bounce_rate']['value']) . '%' : 'N/A'; ?></li>
            <li><strong>Apsilankymo trukmė (Visit Duration):</strong> <?php echo isset($stats['visit_duration']) ? esc_html($stats['visit_duration']['value']) . 's' : 'N/A'; ?></li>
        </ul>
        <?php
    }
}

// Inicijuojame įskiepį
new TavoWeb_Analytics_Plugin();
