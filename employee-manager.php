<?php
/**
 * Plugin Name: 社員情報管理システム
 * Plugin URI:  https://example.com/employee-manager
 * Description: 社員マスタ・所属・部署・役職・職種・学歴・職歴・資格・扶養者・保険情報を一元管理するプラグイン
 * Version:     1.0.0
 * Author:      Your Name
 * License:     GPL-2.0+
 * Text Domain: employee-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ===== 定数定義 =====
define( 'EMP_VERSION',     '1.1.0' );
define( 'EMP_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'EMP_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );
define( 'EMP_PLUGIN_FILE', __FILE__ );

// ===== 依存ファイルの読み込み =====
require_once EMP_PLUGIN_DIR . 'includes/class-db-install.php';
require_once EMP_PLUGIN_DIR . 'includes/class-master.php';
require_once EMP_PLUGIN_DIR . 'includes/class-employee.php';
require_once EMP_PLUGIN_DIR . 'includes/class-csv-export.php';
require_once EMP_PLUGIN_DIR . 'includes/class-csv-import.php';
require_once EMP_PLUGIN_DIR . 'admin/class-admin-menu.php';

// ===== 有効化フック =====
register_activation_hook( __FILE__, array( 'EMP_DB_Install', 'activate' ) );

// ===== 無効化フック =====
register_deactivation_hook( __FILE__, array( 'EMP_DB_Install', 'deactivate' ) );

// ===== プラグイン初期化 =====
add_action( 'plugins_loaded', 'emp_init' );

function emp_init() {
    // バージョンチェック：DBマイグレーションが必要な場合に対応
    if ( get_option( 'emp_db_version' ) !== EMP_VERSION ) {
        EMP_DB_Install::activate();
    }
}

// ===== 管理画面の初期化 =====
if ( is_admin() ) {
    new EMP_Admin_Menu();
}

// =========================================================
//  他プラグインから呼び出せる公開API関数
// =========================================================

/**
 * 在籍中の社員一覧を取得する
 *
 * @param array $args {
 *   @type int    $affiliation_id  所属IDで絞り込み（省略可）
 *   @type int    $department_id   部署IDで絞り込み（省略可）
 *   @type string $orderby         ソートキー: employee_code | name | hire_date（デフォルト: employee_code）
 * }
 * @return array|null  社員オブジェクトの配列。失敗時はnull。
 *
 * @example
 *   $employees = emp_get_active_employees();
 *   foreach ( $employees as $emp ) {
 *       echo $emp->name . '（' . $emp->employee_code . '）';
 *   }
 */
function emp_get_active_employees( $args = array() ) {
    return EMP_Employee::get_active_employees( $args );
}

/**
 * 社員IDで1件取得する
 *
 * @param int $employee_id  emp_master.id
 * @return object|null  社員オブジェクト。見つからない場合はnull。
 *
 * @example
 *   $emp = emp_get_employee_by_id( 5 );
 *   if ( $emp ) {
 *       echo $emp->name;
 *   }
 */
function emp_get_employee_by_id( $employee_id ) {
    return EMP_Employee::get_by_id( (int) $employee_id );
}

/**
 * 社員コードで1件取得する
 *
 * @param string $employee_code  例: 'E0001'
 * @return object|null
 *
 * @example
 *   $emp = emp_get_employee_by_code( 'E0001' );
 */
function emp_get_employee_by_code( $employee_code ) {
    return EMP_Employee::get_by_code( sanitize_text_field( $employee_code ) );
}

/**
 * 所属マスタの一覧を取得する（有効なもののみ）
 *
 * @return array
 *
 * @example
 *   $affiliations = emp_get_affiliations();
 *   foreach ( $affiliations as $a ) {
 *       echo $a->id . ': ' . $a->name;
 *   }
 */
function emp_get_affiliations() {
    return EMP_Master::get_list( 'affiliation' );
}

/**
 * 部署マスタの一覧を取得する（有効なもののみ）
 *
 * @return array
 */
function emp_get_departments() {
    return EMP_Master::get_list( 'department' );
}

/**
 * 役職マスタの一覧を取得する（有効なもののみ）
 *
 * @return array
 */
function emp_get_positions() {
    return EMP_Master::get_list( 'position' );
}

/**
 * 職種マスタの一覧を取得する（有効なもののみ）
 *
 * @return array
 */
function emp_get_job_types() {
    return EMP_Master::get_list( 'job_type' );
}