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
 *
 * 権限（社内ツール共通 API 発行ルールに準拠）:
 *   - 共通ロール「API取得」（api_reader）に、本プラグイン専用ケイパビリティ
 *     emp_export_data を追加する。ロールが無ければ作成し、あれば作り直さない。
 *   - emp_export_data または access_custom_plugins のどちらかを持つユーザーが取得できる。
 *
 * 返却項目は外部公開用に限定する（個人情報・連絡先・保険等は含めない）。
 *   employee_code, name, category, job_type_name, affiliation_name
 */
class EMP_REST_API {

    const API_NAMESPACE = 'employee-manager/v1';

    /** 社内ツール共通のAPI取得ロール（他プラグインと共有） */
    const ROLE_API   = 'api_reader';
    /** 本プラグイン専用のAPI取得ケイパビリティ */
    const CAP_EXPORT = 'emp_export_data';

    /** 職種名 → category の既定対応。部分一致で判定し、該当なしは DEFAULT_CATEGORY。 */
    const CATEGORY_MAP = array(
        '長距離' => 'chokyo',
    );
    const DEFAULT_CATEGORY = 'jiba';

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
        // FTPデプロイでは有効化フックが走らないため init で確認する。
        add_action( 'init', array( __CLASS__, 'ensure_role' ) );
    }

    /**
     * 「API取得」ロールを用意する。
     * 他プラグインと共有するため、既に存在する場合は作り直さず、
     * 本プラグイン用のケイパビリティだけを追加する。
     */
    public static function ensure_role() {
        $role = get_role( self::ROLE_API );
        if ( ! $role ) {
            add_role( self::ROLE_API, 'API取得', array( 'read' => true ) );
            $role = get_role( self::ROLE_API );
        }
        if ( $role && ! $role->has_cap( self::CAP_EXPORT ) ) {
            $role->add_cap( self::CAP_EXPORT );
        }
    }

    /**
     * プラグイン削除時：共有ロールは残し、本プラグインのケイパビリティだけ外す。
     */
    public static function remove_capability() {
        $role = get_role( self::ROLE_API );
        if ( $role && $role->has_cap( self::CAP_EXPORT ) ) {
            $role->remove_cap( self::CAP_EXPORT );
        }
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
     * 権限チェック。emp_export_data または access_custom_plugins が必要。
     * 未ログインは 401、権限不足は 403。
     */
    public static function can_read() {
        if ( current_user_can( self::CAP_EXPORT ) || current_user_can( 'access_custom_plugins' ) ) {
            return true;
        }
        return new WP_Error(
            'emp_forbidden',
            '権限がありません。',
            array( 'status' => is_user_logged_in() ? 403 : 401 )
        );
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
