<?php
/*
Plugin Name: TavoWeb Analytics
Plugin URI: https://tavoweb.eu
Description: Įtraukia TavoWeb Analytics skriptą į svetainės <head> dalį ir rodo statistiką prietaisų skydelyje.
Version: 2.2
Author: TavoWeb
Author URI: https://tavoweb.eu
*/

if (!defined('ABSPATH')) {
    exit;
}

class TavoWeb_Analytics_Plugin {

    public function __construct() {
        add_action('wp_head', array($this, 'add_analytics_script'));
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_dashboard_scripts'));
    }

    public function enqueue_dashboard_scripts($hook) {
        if ($hook !== 'index.php') {
            return;
        }
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.1', true);
        wp_enqueue_script('tavoweb-analytics-chart', plugin_dir_url(__FILE__) . 'tavoweb-analytics-chart.js', array('chart-js'), '1.1', true);
    }

    public function add_analytics_script() {
        $site_id = get_option('tavoweb_analytics_site_id');
        if (!empty($site_id)) {
            echo '<script data-host="https://analytics.tavoweb.eu" data-dnt="false" src="https://analytics.tavoweb.eu/js/script.js" id="ZwSg9rf6GA" async defer></script>';
        }
    }

    public function add_settings_page() {
        add_options_page('TavoWeb Analytics Nustatymai', 'TavoWeb Analytics', 'manage_options', 'tavoweb-analytics', array($this, 'render_settings_page'));
    }

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

    public function register_settings() {
        register_setting('tavoweb_analytics_options', 'tavoweb_analytics_api_key');
        register_setting('tavoweb_analytics_options', 'tavoweb_analytics_site_id');
        add_settings_section('tavoweb_analytics_main_section', 'API Nustatymai', null, 'tavoweb-analytics');
        add_settings_field('tavoweb_analytics_api_key', 'API Raktas', array($this, 'render_api_key_field'), 'tavoweb-analytics', 'tavoweb_analytics_main_section');
        add_settings_field('tavoweb_analytics_site_id', 'Svetainės ID', array($this, 'render_site_id_field'), 'tavoweb-analytics', 'tavoweb_analytics_main_section');
    }

    public function render_api_key_field() {
        $api_key = get_option('tavoweb_analytics_api_key');
        echo '<input type="text" name="tavoweb_analytics_api_key" value="' . esc_attr($api_key) . '" class="regular-text">';
    }

    public function render_site_id_field() {
        $site_id = get_option('tavoweb_analytics_site_id');
        echo '<input type="text" name="tavoweb_analytics_site_id" value="' . esc_attr($site_id) . '" class="regular-text">';
    }

    public function add_dashboard_widget() {
        wp_add_dashboard_widget('tavoweb_analytics_dashboard_widget', 'TavoWeb Analytics', array($this, 'render_dashboard_widget'));
    }

    private function get_stats_from_api($base_url, $args, $stat_name, $from_date, $to_date) {
        $query_params = ['name' => $stat_name, 'from' => $from_date, 'to' => $to_date];
        $api_url = add_query_arg($query_params, $base_url);
        $response = wp_remote_get($api_url, $args);
        $total = 0;

        if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($data['data']) && is_array($data['data'])) {
                foreach ($data['data'] as $day) {
                    $total += $day['count'];
                }
            }
        }
        return $total;
    }

    private function calculate_percentage_change($current, $previous) {
        if ($previous == 0) {
            return ['value' => '&#8734;', 'class' => 'tavoweb-positive', 'arrow' => '&#8593;']; // Infinite
        }
        $change = (($current - $previous) / $previous) * 100;
        $class = $change >= 0 ? 'tavoweb-positive' : 'tavoweb-negative';
        $arrow = $change >= 0 ? '&#8593;' : '&#8595;';
        return ['value' => round(abs($change), 1) . '%', 'class' => $class, 'arrow' => $arrow];
    }

    public function render_dashboard_widget() {
        $api_key = get_option('tavoweb_analytics_api_key');
        $site_id = get_option('tavoweb_analytics_site_id');

        if (empty($api_key) || empty($site_id)) {
            echo '<p>Prašome įvesti savo TavoWeb Analytics API raktą ir Svetainės ID <a href="' . admin_url('options-general.php?page=tavoweb-analytics') . '">nustatymų puslapyje</a>.</p>';
            return;
        }

        // Date ranges
        $to_current = date('Y-m-d');
        $from_current = date('Y-m-d', strtotime('-29 days'));
        $to_previous = date('Y-m-d', strtotime('-30 days'));
        $from_previous = date('Y-m-d', strtotime('-59 days'));

        $base_url = 'https://analytics.tavoweb.eu/api/v1/stats/' . $site_id;
        $args = ['headers' => ['Authorization' => 'Bearer ' . $api_key, 'Accept' => 'application/json']];

        // Fetch data for both periods
        $total_visitors_current = $this->get_stats_from_api($base_url, $args, 'visitors', $from_current, $to_current);
        $total_visitors_previous = $this->get_stats_from_api($base_url, $args, 'visitors', $from_previous, $to_previous);
        $total_pageviews_current = $this->get_stats_from_api($base_url, $args, 'pageviews', $from_current, $to_current);
        $total_pageviews_previous = $this->get_stats_from_api($base_url, $args, 'pageviews', $from_previous, $to_previous);

        // Calculate percentage changes
        $visitors_change = $this->calculate_percentage_change($total_visitors_current, $total_visitors_previous);
        $pageviews_change = $this->calculate_percentage_change($total_pageviews_current, $total_pageviews_previous);

        // Prepare data for the chart
        $chart_labels = [];
        for ($i = 0; $i < 30; $i++) {
            $chart_labels[] = date('M j', strtotime("-$i days"));
        }
        $chart_labels = array_reverse($chart_labels);

        $chart_visitors = array_fill(0, 30, 0);
        $chart_pageviews = array_fill(0, 30, 0);

        // Re-fetch data for chart to get daily breakdown
        // This is not optimal, but required by the API structure
        $response_visitors = wp_remote_get(add_query_arg(['name' => 'visitors', 'from' => $from_current, 'to' => $to_current], $base_url), $args);
        if (!is_wp_error($response_visitors) && wp_remote_retrieve_response_code($response_visitors) === 200) {
            $data = json_decode(wp_remote_retrieve_body($response_visitors), true);
            if (isset($data['data']) && is_array($data['data'])) {
                $daily_data = array_column($data['data'], 'count', 'value');
                for ($i = 0; $i < 30; $i++) {
                    $day_key = date('Y-m-d', strtotime("-$i days"));
                    if (isset($daily_data[$day_key])) {
                        $chart_visitors[29 - $i] = $daily_data[$day_key];
                    }
                }
            }
        }
        $response_pageviews = wp_remote_get(add_query_arg(['name' => 'pageviews', 'from' => $from_current, 'to' => $to_current], $base_url), $args);
        if (!is_wp_error($response_pageviews) && wp_remote_retrieve_response_code($response_pageviews) === 200) {
            $data = json_decode(wp_remote_retrieve_body($response_pageviews), true);
             if (isset($data['data']) && is_array($data['data'])) {
                $daily_data = array_column($data['data'], 'count', 'value');
                for ($i = 0; $i < 30; $i++) {
                    $day_key = date('Y-m-d', strtotime("-$i days"));
                    if (isset($daily_data[$day_key])) {
                        $chart_pageviews[29 - $i] = $daily_data[$day_key];
                    }
                }
            }
        }

        wp_localize_script('tavoweb-analytics-chart', 'tavoweb_chart_data', [
            'labels' => $chart_labels,
            'visitors' => $chart_visitors,
            'pageviews' => $chart_pageviews,
        ]);
        ?>
        <style>
            .tavoweb-stats-container { display: flex; gap: 20px; margin-bottom: 20px; flex-wrap: wrap; }
            .tavoweb-stat-item { flex: 1; min-width: 200px; }
            .tavoweb-stat-item span.label { font-size: 1em; color: #555; }
            .tavoweb-stat-item strong { display: block; font-size: 2em; color: #23282d; line-height: 1.2; }
            .tavoweb-stat-item .change { font-size: 0.9em; }
            .tavoweb-positive { color: #28a745; }
            .tavoweb-negative { color: #dc3545; }
        </style>
        <div class="tavoweb-stats-container">
            <div class="tavoweb-stat-item">
                <span class="label">Lankytojai (Visitors)</span>
                <strong><?php echo $total_visitors_current; ?></strong>
                <span class="change <?php echo $visitors_change['class']; ?>">
                    <?php echo $visitors_change['arrow']; ?> <?php echo $visitors_change['value']; ?>
                </span>
            </div>
            <div class="tavoweb-stat-item">
                <span class="label">Puslapių peržiūros (Pageviews)</span>
                <strong><?php echo $total_pageviews_current; ?></strong>
                 <span class="change <?php echo $pageviews_change['class']; ?>">
                    <?php echo $pageviews_change['arrow']; ?> <?php echo $pageviews_change['value']; ?>
                </span>
            </div>
        </div>
        <canvas id="tavowebAnalyticsChart"></canvas>
        <?php
    }
}

new TavoWeb_Analytics_Plugin();
