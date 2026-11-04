<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class EMP_REST_API
 * Excel Power Query 等の外部ツール向けに、従業員マスタを JSON で提供する。
 *
 * エンドポイント:
 *   GET /wp-json/employee-manager/v1/employees
 *
 * 認証:
 *   WordPress 標準の「アプリケーションパスワード」による Basic 認証。
 *   （Excel: データ → Webから → 基本 → ユーザー名 / アプリケーションパスワード）
 *   閲覧権限 access_custom_plugins を持つユーザーのみ取得できる。
 *
 * 返却項目は外部公開用に限定する（個人情報・連絡先・保険等は含めない）。
 *   employee_code, name, category, job_type_name, affiliation_name
 */
class EMP_REST_API {

    const API_NAMESPACE = 'employee-manager/v1';

    /** 職種名 → category の既定対応。部分一致で判定し、該当なしは DEFAULT_CATEGORY。 */
    const CATEGORY_MAP = array(
        '長距離' => 'chokyo',
    );
    const DEFAULT_CATEGORY = 'jiba';

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes() {
        register_rest_route(
            self::API_NAMESPACE,
            '/employees',
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array( __CLASS__, 'get_employees' ),
                'permission_callback' => array( __CLASS__, 'can_read' ),
                'args'                => array(
                    'status' => array(
                        'description' => '在籍区分: active（既定）| retired | all',
                        'type'        => 'string',
                        'enum'        => array( 'active', 'retired', 'all' ),
                        'default'     => 'active',
                    ),
                ),
            )
        );
    }

    /**
     * 閲覧権限チェック。未ログイン時は 401、権限不足は 403 を返す。
     */
    public static function can_read() {
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'emp_rest_unauthorized',
                '認証が必要です。アプリケーションパスワードで Basic 認証してください。',
                array( 'status' => 401 )
            );
        }
        if ( ! current_user_can( 'access_custom_plugins' ) ) {
            return new WP_Error(
                'emp_rest_forbidden',
                'この API を利用する権限がありません。',
                array( 'status' => 403 )
            );
        }
        return true;
    }

    /**
     * 従業員マスタ一覧を返す。
     */
    public static function get_employees( WP_REST_Request $request ) {
        global $wpdb;

        $where = '';
        switch ( $request->get_param( 'status' ) ) {
            case 'retired':
                $where = 'WHERE m.is_active = 0';
                break;
            case 'all':
                $where = '';
                break;
            default:
                $where = 'WHERE m.is_active = 1';
        }

        $sql = "
            SELECT
                m.employee_code,
                m.name,
                j.name AS job_type_name,
                a.name AS affiliation_name
            FROM {$wpdb->prefix}emp_master m
            LEFT JOIN {$wpdb->prefix}mst_affiliation a ON m.affiliation_id = a.id
            LEFT JOIN {$wpdb->prefix}mst_job_type    j ON m.job_type_id    = j.id
            {$where}
            ORDER BY CAST(m.employee_code AS UNSIGNED) ASC, m.employee_code ASC
        ";

        $rows = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- 可変部分は固定文字列のみ
        if ( $rows === null || $wpdb->last_error ) {
            return new WP_Error(
                'emp_rest_db_error',
                '従業員情報の取得に失敗しました。',
                array( 'status' => 500 )
            );
        }

        $data = array();
        foreach ( $rows as $row ) {
            $job_type = $row->job_type_name !== null ? (string) $row->job_type_name : '';
            $data[]   = array(
                'employee_code'    => (string) $row->employee_code,
                'name'             => (string) $row->name,
                'category'         => self::category_for( $job_type ),
                'job_type_name'    => $job_type,
                'affiliation_name' => $row->affiliation_name !== null ? (string) $row->affiliation_name : '',
            );
        }

        $response = rest_ensure_response( $data );
        $response->header( 'Cache-Control', 'no-store, private' );
        return $response;
    }

    /**
     * 職種名から category を決定する。
     * フィルター emp_rest_category_map / emp_rest_employee_category で上書き可能。
     */
    public static function category_for( $job_type_name ) {
        $map      = apply_filters( 'emp_rest_category_map', self::CATEGORY_MAP );
        $category = self::DEFAULT_CATEGORY;
        foreach ( (array) $map as $needle => $value ) {
            if ( $needle !== '' && $job_type_name !== '' && mb_strpos( $job_type_name, (string) $needle ) !== false ) {
                $category = (string) $value;
                break;
            }
        }
        return (string) apply_filters( 'emp_rest_employee_category', $category, $job_type_name );
    }
}
