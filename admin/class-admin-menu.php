<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class EMP_Admin_Menu
 * 管理画面メニューの登録・アセット読み込み・AJAXフック登録を担当する
 */
class EMP_Admin_Menu {

    public function __construct() {
        add_action( 'admin_menu',            array( $this, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        $this->register_ajax_hooks();
    }

    // =====================================================
    //  メニュー登録
    // =====================================================

    public function register_menu() {
        // トップメニュー
        add_menu_page(
            '社員情報管理',
            '社員情報管理',
            'manage_options',
            'employee-manager',
            array( $this, 'page_employee_list' ),
            'dashicons-groups',
            30
        );

        // 社員一覧（トップと同じ）
        add_submenu_page(
            'employee-manager',
            '社員一覧',
            '社員一覧',
            'manage_options',
            'employee-manager',
            array( $this, 'page_employee_list' )
        );

        // 新規社員登録
        add_submenu_page(
            'employee-manager',
            '新規社員登録',
            '新規社員登録',
            'manage_options',
            'employee-manager-new',
            array( $this, 'page_employee_form' )
        );

        // マスタ管理
        add_submenu_page(
            'employee-manager',
            'マスタ管理',
            'マスタ管理',
            'manage_options',
            'employee-manager-master',
            array( $this, 'page_master' )
        );

        // CSV出力
        add_submenu_page(
            'employee-manager',
            'CSV出力',
            'CSV出力',
            'manage_options',
            'employee-manager-csv',
            array( $this, 'page_csv' )
        );

        // CSVインポート
        add_submenu_page(
            'employee-manager',
            'CSVインポート',
            'CSVインポート',
            'manage_options',
            'employee-manager-import',
            array( $this, 'page_import' )
        );
    }

    // =====================================================
    //  アセット読み込み
    // =====================================================

    public function enqueue_assets( $hook ) {
        // $hook はサブメニューで長い文字列になるため、$_GET['page'] で判定する
        $page = $_GET['page'] ?? ''; // phpcs:ignore WordPress.Security.NonceVerification
        $allowed_pages = array(
            'employee-manager',
            'employee-manager-new',
            'employee-manager-master',
            'employee-manager-csv',
            'employee-manager-import',
        );
        if ( ! in_array( $page, $allowed_pages, true ) ) {
            return;
        }

        wp_enqueue_style(
            'emp-admin',
            EMP_PLUGIN_URL . 'admin/assets/admin.css',
            array(),
            EMP_VERSION
        );

        wp_enqueue_script(
            'emp-admin',
            EMP_PLUGIN_URL . 'admin/assets/admin.js',
            array( 'jquery' ),
            EMP_VERSION,
            true   // フッターに読み込む
        );

        // PHP → JS へのデータ受け渡し
        wp_localize_script( 'emp-admin', 'empData', array(
            'ajaxUrl'          => admin_url( 'admin-ajax.php' ),
            'masterNonce'      => wp_create_nonce( 'emp_master_nonce' ),
            'employeeNonce'    => wp_create_nonce( 'emp_employee_nonce' ),
            'csvNonce'         => wp_create_nonce( 'emp_csv_nonce' ),
            'importNonce'      => wp_create_nonce( 'emp_import_nonce' ),
            'currentPage'      => $hook,
            'pluginUrl'        => EMP_PLUGIN_URL,
        ) );
    }

    // =====================================================
    //  AJAXフック登録
    // =====================================================

    private function register_ajax_hooks() {
        // ---- マスタ ----
        add_action( 'wp_ajax_emp_master_get_list', array( 'EMP_Master', 'ajax_get_list' ) );
        add_action( 'wp_ajax_emp_master_insert',   array( 'EMP_Master', 'ajax_insert' ) );
        add_action( 'wp_ajax_emp_master_update',   array( 'EMP_Master', 'ajax_update' ) );
        add_action( 'wp_ajax_emp_master_delete',   array( 'EMP_Master', 'ajax_delete' ) );

        // ---- 社員 ----
        add_action( 'wp_ajax_emp_employee_get_list',    array( 'EMP_Employee', 'ajax_get_list' ) );
        add_action( 'wp_ajax_emp_employee_get_one',     array( 'EMP_Employee', 'ajax_get_one' ) );
        add_action( 'wp_ajax_emp_employee_save',        array( 'EMP_Employee', 'ajax_save' ) );
        add_action( 'wp_ajax_emp_employee_toggle',      array( 'EMP_Employee', 'ajax_toggle_active' ) );

        // ---- CSV ----
        add_action( 'wp_ajax_emp_csv_export',           array( 'EMP_CSV_Export', 'ajax_export' ) );
        add_action( 'wp_ajax_emp_csv_get_templates',    array( 'EMP_CSV_Export', 'ajax_get_templates' ) );
        add_action( 'wp_ajax_emp_csv_save_template',    array( 'EMP_CSV_Export', 'ajax_save_template' ) );
        add_action( 'wp_ajax_emp_csv_update_template',  array( 'EMP_CSV_Export', 'ajax_update_template' ) );
        add_action( 'wp_ajax_emp_csv_delete_template',  array( 'EMP_CSV_Export', 'ajax_delete_template' ) );

        // ---- CSVインポート ----
        add_action( 'wp_ajax_emp_csv_template_download', array( 'EMP_CSV_Import', 'download_template' ) );
        add_action( 'wp_ajax_emp_csv_import',            array( 'EMP_CSV_Import', 'ajax_import' ) );

        add_action( 'wp_ajax_emp_get_stats', array( 'EMP_Employee', 'ajax_get_stats' ) );
    }

    // =====================================================
    //  ページ描画
    // =====================================================

    public function page_employee_list() {
        require_once EMP_PLUGIN_DIR . 'admin/views/employee-list.php';
    }

    public function page_employee_form() {
        require_once EMP_PLUGIN_DIR . 'admin/views/employee-form.php';
    }

    public function page_master() {
        require_once EMP_PLUGIN_DIR . 'admin/views/master.php';
    }

    public function page_csv() {
        require_once EMP_PLUGIN_DIR . 'admin/views/csv.php';
    }

    public function page_import() {
        require_once EMP_PLUGIN_DIR . 'admin/views/employee-import.php';
    }
}
