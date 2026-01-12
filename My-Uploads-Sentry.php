/**
 * Plugin Name:       My-Uploads-Sentry
 * Plugin URI:        https://wp365.me/
 * Description:       Monitor executable files in static directories. (Admin only)
 * Version:           1.2.4
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Author:            WP 導盲犬
 * Author URI:        https://wp365.me/wp
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       my-uploads-sentry
 */

// 安全性：防止直接透過 URL 存取此檔案
defined( 'ABSPATH' ) || exit;

// --- 版本檢查邏輯 (Runtime Environment Check) ---
if ( ! defined( 'MUS_MIN_WP_VERSION' ) ) {
    define( 'MUS_MIN_WP_VERSION', '6.9' );
}

global $wp_version;
if ( version_compare( $wp_version, MUS_MIN_WP_VERSION, '<' ) ) {
    add_action( 'admin_notices', 'mus_version_error_notice' );
    return;
}

/**
 * 顯示版本錯誤通知
 */
function mus_version_error_notice() {
    $message = sprintf(
        esc_html__( 'My-Uploads-Sentry requires WordPress version %s or higher. You are currently running version %s. Please update your WordPress installation.', 'my-uploads-sentry' ),
        MUS_MIN_WP_VERSION,
        $GLOBALS['wp_version']
    );
    echo '<div class="notice notice-error"><p>' . $message . '</p></div>';
}

if ( ! class_exists( 'My_Uploads_Sentry' ) ) {

    /**
     * My_Uploads_Sentry 主類別
     */
    class My_Uploads_Sentry {

        /** @var string 外掛版本號 */
        private $version = '1.2.4';

        /** @var string 資料庫設定欄位名稱 */
        private $option_name = 'mus_settings';

        /** @var string 最後掃描時間紀錄欄位名稱 */
        private $option_last_scan = 'mus_last_scan_timestamp';

        /** @var string 掃描結果快取鍵值 */
        private $transient_key = 'mus_scan_results';

        /**
         * 排除目錄清單 (黑名單)
         * @var array
         */
        private $excluded_dirs = [
            'wp-admin',
            'wp-includes',
            'plugins',
            'themes',
            'mu-plugins',
            'cache'
        ];

        public function __construct() {
            if ( is_admin() ) {
                add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
                add_action( 'admin_init', [ $this, 'register_settings' ] );
                add_action( 'wp_dashboard_setup', [ $this, 'register_widget' ] );
                add_action( 'admin_init', [ $this, 'handle_refresh' ] );
                add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), [ $this, 'add_settings_link' ] );
            }
        }

        // =========================================================================
        // 設定頁面與 API
        // =========================================================================

        public function add_settings_page() {
            add_options_page(
                __( 'My-Uploads-Sentry Settings', 'my-uploads-sentry' ),
                __( 'Uploads Sentry', 'my-uploads-sentry' ),
                'manage_options',
                'my-uploads-sentry',
                [ $this, 'render_settings_page' ]
            );
        }

        public function add_settings_link( $links ) {
            $settings_link = '<a href="options-general.php?page=my-uploads-sentry">' . __( 'Settings', 'my-uploads-sentry' ) . '</a>';
            array_unshift( $links, $settings_link );
            return $links;
        }

        public function register_settings() {
            register_setting(
                $this->option_name,
                $this->option_name,
                [ $this, 'sanitize_settings' ]
            );

            add_settings_section(
                'mus_general_section',
                __( 'General Configuration', 'my-uploads-sentry' ),
                null,
                'my-uploads-sentry'
            );

            add_settings_field(
                'cache_time',
                __( 'Scan Cache Duration', 'my-uploads-sentry' ),
                [ $this, 'render_field_cache_time' ],
                'my-uploads-sentry',
                'mus_general_section'
            );

            add_settings_field(
                'target_directories',
                __( 'Monitor Scope (Static Folders Only)', 'my-uploads-sentry' ),
                [ $this, 'render_field_directories' ],
                'my-uploads-sentry',
                'mus_general_section'
            );
        }

        public function sanitize_settings( $input ) {
            $new_input = [];
            $new_input['cache_time'] = isset( $input['cache_time'] ) ? absint( $input['cache_time'] ) : 3600;
            
            // 白名單驗證
            $allowed_dirs = $this->discover_static_directories();
            $allowed_paths = array_keys( $allowed_dirs );
            $valid_dirs = [];

            if ( isset( $input['dirs'] ) && is_array( $input['dirs'] ) ) {
                foreach ( $input['dirs'] as $submitted_dir ) {
                    $clean_dir = sanitize_text_field( $submitted_dir );
                    if ( in_array( $clean_dir, $allowed_paths, true ) ) {
                        $valid_dirs[] = $clean_dir;
                    }
                }
            }

            if ( empty( $valid_dirs ) ) {
                $upload_dir = wp_upload_dir();
                if ( in_array( $upload_dir['basedir'], $allowed_paths, true ) ) {
                    $valid_dirs = [ $upload_dir['basedir'] ];
                }
            }

            $new_input['dirs'] = $valid_dirs;

            // 設定變更後清除快取
            delete_transient( $this->transient_key );

            return $new_input;
        }

        public function render_settings_page() {
            ?>
            <div class="wrap">
                <h1><?php echo esc_html__( 'My-Uploads-Sentry Settings', 'my-uploads-sentry' ); ?></h1>
                <form method="post" action="options.php">
                    <?php
                    settings_fields( $this->option_name );
                    do_settings_sections( 'my-uploads-sentry' );
                    submit_button();
                    ?>
                </form>
            </div>
            <?php
        }

        public function render_field_cache_time() {
            $options = get_option( $this->option_name );
            $value = isset( $options['cache_time'] ) ? $options['cache_time'] : 3600;
            ?>
            <select name="<?php echo esc_attr( $this->option_name ); ?>[cache_time]">
                <option value="900" <?php selected( $value, 900 ); ?>>15 <?php _e('Minutes', 'my-uploads-sentry'); ?> (Debug)</option>
                <option value="3600" <?php selected( $value, 3600 ); ?>>1 <?php _e('Hour', 'my-uploads-sentry'); ?> (Default)</option>
                <option value="43200" <?php selected( $value, 43200 ); ?>>12 <?php _e('Hours', 'my-uploads-sentry'); ?></option>
                <option value="86400" <?php selected( $value, 86400 ); ?>>24 <?php _e('Hours', 'my-uploads-sentry'); ?></option>
            </select>
            <p class="description"><?php _e( 'How long to store scan results to reduce server load.', 'my-uploads-sentry' ); ?></p>
            <?php
        }

        public function render_field_directories() {
            $options = get_option( $this->option_name );
            $selected_dirs = isset( $options['dirs'] ) ? $options['dirs'] : [];

            if ( empty( $selected_dirs ) ) {
                $upload_dir = wp_upload_dir();
                $selected_dirs = [ $upload_dir['basedir'] ];
            }

            $available_dirs = $this->discover_static_directories();

            echo '<fieldset>';
            echo '<p class="description" style="margin-bottom: 10px;">' . __( 'The system has automatically excluded code-heavy directories (Plugins, Themes, Core) to prevent false positives.', 'my-uploads-sentry' ) . '</p>';
            
            foreach ( $available_dirs as $path => $label ) {
                $checked = in_array( $path, $selected_dirs ) ? 'checked="checked"' : '';
                echo '<label style="display:block; margin-bottom: 5px;">';
                echo '<input type="checkbox" name="' . esc_attr( $this->option_name ) . '[dirs][]" value="' . esc_attr( $path ) . '" ' . $checked . '> ';
                echo '<code>' . esc_html( $label ) . '</code>';
                echo '</label>';
            }
            echo '</fieldset>';
        }

        private function discover_static_directories() {
            $candidates = [];
            $upload_info = wp_upload_dir();
            
            $candidates[ $upload_info['basedir'] ] = 'wp-content/uploads (Standard)';

            $content_dirs = glob( WP_CONTENT_DIR . '/*', GLOB_ONLYDIR );
            if ( $content_dirs ) {
                foreach ( $content_dirs as $dir ) {
                    $dirname = basename( $dir );
                    if ( ! in_array( $dirname, $this->excluded_dirs ) && $dir !== $upload_info['basedir'] ) {
                        $candidates[ $dir ] = 'wp-content/' . $dirname;
                    }
                }
            }

            $root_dirs = glob( ABSPATH . '*', GLOB_ONLYDIR );
            if ( $root_dirs ) {
                foreach ( $root_dirs as $dir ) {
                    $dirname = basename( $dir );
                    if ( $dirname !== 'wp-content' && ! in_array( $dirname, $this->excluded_dirs ) ) {
                        $candidates[ $dir ] = '/' . $dirname;
                    }
                }
            }

            return $candidates;
        }

        // =========================================================================
        // 核心掃描與 Dashboard Widget 邏輯
        // =========================================================================

        public function register_widget() {
            if ( current_user_can( 'manage_options' ) ) {
                wp_add_dashboard_widget(
                    'my_uploads_sentry',
                    esc_html__( 'My-Uploads-Sentry', 'my-uploads-sentry' ),
                    [ $this, 'render_widget' ]
                );
            }
        }

        public function handle_refresh() {
            if ( isset( $_GET['mus_action'] ) && $_GET['mus_action'] === 'refresh' && current_user_can( 'manage_options' ) ) {
                check_admin_referer( 'mus_refresh_scan' );
                delete_transient( $this->transient_key );
                wp_safe_redirect( remove_query_arg( [ 'mus_action', '_wpnonce' ] ) );
                exit;
            }
        }

        public function render_widget() {
            if ( ! current_user_can( 'manage_options' ) ) return;

            // --- 1. 定義 CSS (Scope: Widget Internal) ---
            ?>
            <style>
                .mus-widget-container { position: relative; }
                /* Help Icon Styling */
                .mus-help-tip {
                    position: absolute;
                    top: -4px;
                    right: 0;
                    cursor: help;
                    z-index: 99;
                }
                .mus-help-tip .dashicons {
                    color: #b0b0b0;
                    font-size: 18px;
                    width: 18px;
                    height: 18px;
                }
                .mus-help-tip:hover .dashicons { color: #2271b1; }
                
                /* Tooltip Styling */
                .mus-tooltip-content {
                    display: none;
                    position: absolute;
                    top: 25px;
                    right: -5px;
                    width: 260px;
                    background: #1d2327;
                    color: #fff;
                    padding: 10px 12px;
                    border-radius: 4px;
                    font-size: 12px;
                    line-height: 1.5;
                    box-shadow: 0 4px 10px rgba(0,0,0,0.2);
                    z-index: 1000;
                }
                .mus-tooltip-content::after {
                    content: '';
                    position: absolute;
                    top: -6px;
                    right: 8px;
                    border-width: 0 6px 6px;
                    border-style: solid;
                    border-color: #1d2327 transparent;
                }
                .mus-help-tip:hover .mus-tooltip-content { display: block; }
                
                .mus-tooltip-content h4 {
                    margin: 0 0 8px 0;
                    color: #fff;
                    font-size: 13px;
                    border-bottom: 1px solid #3c434a;
                    padding-bottom: 5px;
                }
                .mus-tooltip-content ul { margin: 0; padding-left: 15px; list-style-type: disc; }
                .mus-tooltip-content li { margin-bottom: 4px; color: #f0f0f1; }
            </style>
            <?php

            // --- 2. 輸出 Help Tooltip HTML ---
            ?>
            <div class="mus-widget-container">
                <div class="mus-help-tip">
                    <span class="dashicons dashicons-editor-help"></span>
                    <div class="mus-tooltip-content">
                        <h4><?php esc_html_e( 'How to use', 'my-uploads-sentry' ); ?></h4>
                        <ul>
                            <li><strong><?php esc_html_e( 'Green', 'my-uploads-sentry' ); ?>:</strong> <?php esc_html_e( 'System Safe. No executable files found.', 'my-uploads-sentry' ); ?></li>
                            <li><strong><?php esc_html_e( 'Red', 'my-uploads-sentry' ); ?>:</strong> <?php esc_html_e( 'Warning! Suspicious files detected.', 'my-uploads-sentry' ); ?></li>
                            <li><strong><?php esc_html_e( 'Scope', 'my-uploads-sentry' ); ?>:</strong> <?php esc_html_e( 'Monitoring Uploads & whitelisted static folders.', 'my-uploads-sentry' ); ?></li>
                            <li><strong><?php esc_html_e( 'Scan Now', 'my-uploads-sentry' ); ?>:</strong> <?php esc_html_e( 'Clear cache and force a new scan.', 'my-uploads-sentry' ); ?></li>
                        </ul>
                    </div>
                </div>
            <?php

            // --- 3. 執行掃描邏輯 ---
            $options = get_option( $this->option_name );
            $cache_time = isset( $options['cache_time'] ) ? $options['cache_time'] : 3600;
            
            if ( ! empty( $options['dirs'] ) ) {
                $target_dirs = $options['dirs'];
            } else {
                $upload_dir = wp_upload_dir();
                $target_dirs = [ $upload_dir['basedir'] ];
            }

            $suspicious_files = get_transient( $this->transient_key );
            $is_cached = false;

            if ( false === $suspicious_files ) {
                $suspicious_files = $this->scan_directories( $target_dirs );
                set_transient( $this->transient_key, $suspicious_files, $cache_time );
                update_option( $this->option_last_scan, time() ); 
            } else {
                $is_cached = true;
            }

            $this->display_results( $suspicious_files, $is_cached, count($target_dirs) );
            
            echo '</div>'; // 關閉 .mus-widget-container
        }

        private function scan_directories( $dirs ) {
            $files_found = [];
            $pattern = '/\.(php|php[0-9]|phtml|pl|py|cgi|asp|aspx|exe|sh|bash|cmd)$/i';
            $found_limit = 100;
            $count = 0;

            foreach ( $dirs as $base_path ) {
                if ( ! is_dir( $base_path ) ) continue;

                try {
                    $directory = new RecursiveDirectoryIterator( $base_path, RecursiveDirectoryIterator::SKIP_DOTS );
                    $iterator = new RecursiveIteratorIterator( $directory );
                    $regex = new RegexIterator( $iterator, $pattern, RecursiveRegexIterator::GET_MATCH );

                    foreach ( $regex as $file ) {
                        if ( isset( $file[0] ) ) {
                            $rel_path = str_replace( ABSPATH, '', $file[0] );
                            if ( $rel_path === $file[0] ) {
                                $rel_path = $file[0];
                            }
                            $files_found[] = $rel_path;
                            $count++;
                        }
                        if ( $count >= $found_limit ) break 2;
                    }
                } catch ( Exception $e ) {
                    continue;
                }
            }
            return $files_found;
        }

        private function display_results( $files, $is_cached, $dir_count ) {
            echo '<div style="padding-top: 15px;">'; // 增加頂部間距，避開 Help Icon

            if ( empty( $files ) ) {
                echo '<div style="border-left: 4px solid #46b450; padding: 10px; background: #f9f9f9;">';
                echo '<span class="dashicons dashicons-shield" style="color: #46b450; font-size: 24px; vertical-align: middle;"></span> ';
                echo '<strong style="color: #46b450; vertical-align: middle;">' . esc_html__( 'Secure', 'my-uploads-sentry' ) . '</strong>';
                echo '<p style="margin: 5px 0 0 0; color: #666; font-size: 12px;">';
                echo sprintf( esc_html__( 'Scanned %d directory(s). No executable files found.', 'my-uploads-sentry' ), $dir_count );
                echo '</p></div>';
            } else {
                $count = count( $files );
                echo '<div style="border-left: 4px solid #dc3232; padding: 10px; background: #fff8f8;">';
                echo '<span class="dashicons dashicons-warning" style="color: #dc3232; font-size: 24px; vertical-align: middle;"></span> ';
                echo '<strong style="color: #dc3232; vertical-align: middle;">' . sprintf( esc_html__( 'Warning: %d suspicious files', 'my-uploads-sentry' ), $count ) . '</strong>';
                
                echo '<div style="max-height: 200px; overflow-y: auto; background: #fff; border: 1px solid #ddd; padding: 5px; margin-top: 10px;">';
                echo '<ul style="margin: 0; padding-left: 20px; list-style-type: disc;">';
                foreach ( $files as $path ) {
                    echo '<li style="color: #c00; font-family: monospace; font-size: 11px; margin-bottom: 2px;">' . esc_html( $path ) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
                echo '</div>';
            }

            // Footer
            echo '<div style="margin-top: 8px; border-top: 1px solid #eee; padding-top: 8px; display: flex; justify-content: space-between; align-items: center; color: #a0a5aa; font-size: 11px;">';
            
            $refresh_url = wp_nonce_url( add_query_arg( 'mus_action', 'refresh' ), 'mus_refresh_scan' );
            
            $last_scan_ts = get_option( $this->option_last_scan );
            $time_str = $last_scan_ts ? wp_date( 'Y-m-d H:i:s', $last_scan_ts ) : __( 'Never', 'my-uploads-sentry' );

            echo '<span>';
            if ( $is_cached ) {
                 echo esc_html__( 'Cached.', 'my-uploads-sentry' ) . ' ';
            }
            echo '<a href="' . esc_url( $refresh_url ) . '" style="text-decoration: none;">' . esc_html__( 'Scan Now', 'my-uploads-sentry' ) . '</a>';
            echo ' <span style="color: #ccc;">|</span> <span style="color: #a0a5aa;">' . esc_html( $time_str ) . '</span>';
            echo '</span>';

            echo '<span>My-Uploads-Sentry <span style="font-weight: 600;">v' . esc_html( $this->version ) . '</span></span>';
            echo '</div>'; 
            echo '</div>'; 
        }
    }

    new My_Uploads_Sentry();
}
