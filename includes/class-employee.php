<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class EMP_Employee
 * 社員マスタおよび関連テーブルの CRUD を担当する
 */
class EMP_Employee {

    // =====================================================
    //  READ
    // =====================================================

    /**
     * 在籍中社員一覧（他プラグイン向け公開API）
     *
     * @param array $args
     * @return array
     */
    public static function get_active_employees( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'affiliation_id' => null,
            'department_id'  => null,
            'orderby'        => 'employee_code',
        );
        $args = wp_parse_args( $args, $defaults );

        $allowed_orderby = array( 'employee_code', 'name', 'hire_date' );
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'employee_code';

        $where  = array( 'm.is_active = 1' );
        $params = array();

        if ( ! empty( $args['affiliation_id'] ) ) {
            $where[]  = 'm.affiliation_id = %d';
            $params[] = (int) $args['affiliation_id'];
        }
        if ( ! empty( $args['department_id'] ) ) {
            $where[]  = 'm.department_id = %d';
            $params[] = (int) $args['department_id'];
        }

        $where_sql = 'WHERE ' . implode( ' AND ', $where );

        $sql = "
            SELECT
                m.id, m.employee_code, m.name, m.name_kana,
                m.hire_date, m.is_active, m.crew_code,
                m.employment_type, m.weekly_work_days,
                a.name AS affiliation_name,
                d.name AS department_name,
                p.name AS position_name,
                j.name AS job_type_name
            FROM {$wpdb->prefix}emp_master m
            LEFT JOIN {$wpdb->prefix}mst_affiliation a ON m.affiliation_id = a.id
            LEFT JOIN {$wpdb->prefix}mst_department  d ON m.department_id  = d.id
            LEFT JOIN {$wpdb->prefix}mst_position    p ON m.position_id    = p.id
            LEFT JOIN {$wpdb->prefix}mst_job_type    j ON m.job_type_id    = j.id
            {$where_sql}
            ORDER BY CAST(m.{$orderby} AS UNSIGNED) ASC, m.{$orderby} ASC
        ";

        if ( ! empty( $params ) ) {
            $sql = $wpdb->prepare( $sql, ...$params ); // phpcs:ignore
        }

        return $wpdb->get_results( $sql ); // phpcs:ignore
    }

    /**
     * 管理画面用一覧（絞り込み・ページング・検索対応）
     *
     * @param array $args {
     *   @type string $search          氏名・コード・フリガナの部分一致
     *   @type int    $affiliation_id
     *   @type int    $department_id
     *   @type int    $is_active       1=在籍 0=退職 ''=全件
     *   @type int    $per_page
     *   @type int    $page
     *   @type string $orderby
     *   @type string $order           ASC | DESC
     * }
     * @return array { items: array, total: int }
     */
    public static function get_list( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'search'         => '',
            'affiliation_id' => '',
            'department_id'  => '',
            'is_active'      => '',
            'per_page'       => 20,
            'page'           => 1,
            'orderby'        => 'employee_code',
            'order'          => 'ASC',
        );
        $args = wp_parse_args( $args, $defaults );

        $where  = array( '1=1' );
        $params = array();

        if ( $args['search'] !== '' ) {
            $like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
            $where[] = '( m.name LIKE %s OR m.name_kana LIKE %s OR m.employee_code LIKE %s )';
            $params  = array_merge( $params, array( $like, $like, $like ) );
        }
        if ( $args['affiliation_id'] !== '' ) {
            $where[]  = 'm.affiliation_id = %d';
            $params[] = (int) $args['affiliation_id'];
        }
        if ( $args['department_id'] !== '' ) {
            $where[]  = 'm.department_id = %d';
            $params[] = (int) $args['department_id'];
        }
        if ( $args['is_active'] !== '' ) {
            $where[]  = 'm.is_active = %d';
            $params[] = (int) $args['is_active'];
        }

        $where_sql   = 'WHERE ' . implode( ' AND ', $where );
        $allowed_ob  = array( 'employee_code', 'name', 'hire_date', 'id' );
        $orderby     = in_array( $args['orderby'], $allowed_ob, true ) ? $args['orderby'] : 'employee_code';
        $order       = strtoupper( $args['order'] ) === 'DESC' ? 'DESC' : 'ASC';
        $offset      = ( max( 1, (int) $args['page'] ) - 1 ) * (int) $args['per_page'];
        $per_page    = (int) $args['per_page'];

        $base_sql = "
            FROM {$wpdb->prefix}emp_master m
            LEFT JOIN {$wpdb->prefix}mst_affiliation a ON m.affiliation_id = a.id
            LEFT JOIN {$wpdb->prefix}mst_department  d ON m.department_id  = d.id
            LEFT JOIN {$wpdb->prefix}mst_position    p ON m.position_id    = p.id
            LEFT JOIN {$wpdb->prefix}mst_job_type    j ON m.job_type_id    = j.id
            {$where_sql}
        ";

        // 件数取得
        $count_sql = "SELECT COUNT(*) {$base_sql}";
        // データ取得
        $data_sql  = "
            SELECT
                m.id, m.employee_code, m.name, m.name_kana,
                m.gender, m.birthdate, m.hire_date, m.is_active, m.crew_code,
                m.employment_type, m.weekly_work_days,
                a.name AS affiliation_name,
                d.name AS department_name,
                p.name AS position_name,
                j.name AS job_type_name
            {$base_sql}
            ORDER BY CAST(m.{$orderby} AS UNSIGNED) {$order}, m.{$orderby} {$order}
            LIMIT %d OFFSET %d
        ";

        if ( ! empty( $params ) ) {
            $prepared_count = $wpdb->prepare( $count_sql, ...$params ); // phpcs:ignore
            $data_params    = array_merge( $params, array( $per_page, $offset ) );
            $prepared_data  = $wpdb->prepare( $data_sql, ...$data_params ); // phpcs:ignore
        } else {
            $prepared_count = $count_sql;
            $prepared_data  = $wpdb->prepare( $data_sql, $per_page, $offset );
        }

        return array(
            'items' => $wpdb->get_results( $prepared_data ), // phpcs:ignore
            'total' => (int) $wpdb->get_var( $prepared_count ), // phpcs:ignore
        );
    }

    /**
     * 1件取得（関連テーブルをすべて含む）
     */
    public static function get_by_id( $id ) {
        global $wpdb;

        $emp = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT m.*,
                    a.name AS affiliation_name,
                    d.name AS department_name,
                    p.name AS position_name,
                    j.name AS job_type_name
                 FROM {$wpdb->prefix}emp_master m
                 LEFT JOIN {$wpdb->prefix}mst_affiliation a ON m.affiliation_id = a.id
                 LEFT JOIN {$wpdb->prefix}mst_department  d ON m.department_id  = d.id
                 LEFT JOIN {$wpdb->prefix}mst_position    p ON m.position_id    = p.id
                 LEFT JOIN {$wpdb->prefix}mst_job_type    j ON m.job_type_id    = j.id
                 WHERE m.id = %d",
                $id
            )
        );

        if ( ! $emp ) return null;

        // 関連テーブルも取得
        $emp->insurance    = self::get_insurance( $id );
        $emp->retirement   = self::get_retirement( $id );
        $emp->educations   = self::get_children( 'emp_education',    $id );
        $emp->careers      = self::get_children( 'emp_career',       $id );
        $emp->qualifications = self::get_children( 'emp_qualification', $id );
        $emp->dependents   = self::get_children( 'emp_dependent',    $id );

        return $emp;
    }

    /**
     * 社員コードで1件取得
     */
    public static function get_by_code( $code ) {
        global $wpdb;
        $id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}emp_master WHERE employee_code = %s",
                $code
            )
        );
        return $id ? self::get_by_id( (int) $id ) : null;
    }

    private static function get_insurance( $employee_id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}emp_insurance WHERE employee_id = %d",
                $employee_id
            )
        );
    }

    private static function get_retirement( $employee_id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}emp_retirement WHERE employee_id = %d",
                $employee_id
            )
        );
    }

    private static function get_children( $table_suffix, $employee_id ) {
        global $wpdb;
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}{$table_suffix} WHERE employee_id = %d ORDER BY sort_order ASC, id ASC",
                $employee_id
            )
        );
    }

    // =====================================================
    //  CREATE / UPDATE
    // =====================================================

    /**
     * 社員を新規登録または更新する（Upsert）
     *
     * @param  array $data  フォームデータ
     * @param  int   $id    0なら新規、>0なら更新
     * @return int|WP_Error  社員ID
     */
    public static function save( $data, $id = 0 ) {
        global $wpdb;

        // --- バリデーション ---
        if ( empty( $data['employee_code'] ) ) {
            return new WP_Error( 'validation', '社員コードは必須です' );
        }
        if ( empty( $data['name'] ) ) {
            return new WP_Error( 'validation', '氏名は必須です' );
        }

        // 社員コードの重複チェック
        $existing_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}emp_master WHERE employee_code = %s AND id != %d",
                sanitize_text_field( $data['employee_code'] ),
                (int) $id
            )
        );
        if ( $existing_id ) {
            return new WP_Error( 'duplicate', 'この社員コードはすでに使用されています' );
        }

        // --- emp_master ---
        $master = array(
            'employee_code'      => sanitize_text_field( $data['employee_code'] ),
            'affiliation_id'     => ! empty( $data['affiliation_id'] ) ? (int) $data['affiliation_id'] : null,
            'department_id'      => ! empty( $data['department_id'] )  ? (int) $data['department_id']  : null,
            'position_id'        => ! empty( $data['position_id'] )    ? (int) $data['position_id']    : null,
            'job_type_id'        => ! empty( $data['job_type_id'] )    ? (int) $data['job_type_id']    : null,
            'employment_type'    => sanitize_text_field( $data['employment_type'] ?? '' ) ?: null,
            'weekly_work_days'   => ! empty( $data['weekly_work_days'] ) ? (int) $data['weekly_work_days'] : null,
            'crew_code'          => ! empty( $data['crew_code'] )      ? sanitize_text_field( $data['crew_code'] ) : null,
            'name'               => sanitize_text_field( $data['name'] ),
            'name_kana'          => sanitize_text_field( $data['name_kana'] ?? '' ) ?: null,
            'gender'             => sanitize_text_field( $data['gender'] ?? '' ) ?: null,
            'birthdate'          => self::sanitize_date( $data['birthdate'] ?? '' ),
            'blood_type'         => sanitize_text_field( $data['blood_type'] ?? '' ) ?: null,
            'my_number'          => ! empty( $data['my_number'] ) ? sanitize_text_field( $data['my_number'] ) : null,
            'hire_date'          => self::sanitize_date( $data['hire_date'] ?? '' ),
            'zip'                => sanitize_text_field( $data['zip'] ?? '' ) ?: null,
            'address'            => sanitize_text_field( $data['address'] ?? '' ) ?: null,
            'tel_home'           => sanitize_text_field( $data['tel_home'] ?? '' ) ?: null,
            'tel_mobile'         => sanitize_text_field( $data['tel_mobile'] ?? '' ) ?: null,
            'tel_company'        => sanitize_text_field( $data['tel_company'] ?? '' ) ?: null,
            'emergency_name'     => sanitize_text_field( $data['emergency_name'] ?? '' ) ?: null,
            'emergency_tel'      => sanitize_text_field( $data['emergency_tel'] ?? '' ) ?: null,
            'emergency_relation' => sanitize_text_field( $data['emergency_relation'] ?? '' ) ?: null,
            'memo'               => sanitize_textarea_field( $data['memo'] ?? '' ) ?: null,
            'is_active'          => isset( $data['is_active'] ) ? (int) $data['is_active'] : 1,
        );

        if ( $id > 0 ) {
            $master['updated_at'] = current_time( 'mysql' );
            $result = $wpdb->update( "{$wpdb->prefix}emp_master", $master, array( 'id' => $id ) );
            if ( $result === false ) {
                error_log( '[EMP] emp_master update failed: ' . $wpdb->last_error );
            }
            $employee_id = $id;
        } else {
            $master['created_at'] = current_time( 'mysql' );
            $master['updated_at'] = current_time( 'mysql' );
            $result = $wpdb->insert( "{$wpdb->prefix}emp_master", $master );
            if ( $result === false ) {
                error_log( '[EMP] emp_master insert failed: ' . $wpdb->last_error );
            }
            $employee_id = $wpdb->insert_id;
        }

        if ( ! $employee_id ) {
            return new WP_Error( 'db_error', '社員情報の保存に失敗しました' );
        }

        // --- 関連テーブルを Upsert ---
        self::save_insurance(    $employee_id, $data );
        self::save_retirement(   $employee_id, $data );
        self::save_children( 'emp_education',    $employee_id, $data['educations']    ?? array() );
        self::save_children( 'emp_career',       $employee_id, $data['careers']       ?? array() );
        self::save_children( 'emp_qualification',$employee_id, $data['qualifications'] ?? array() );
        self::save_children( 'emp_dependent',    $employee_id, $data['dependents']    ?? array() );

        return $employee_id;
    }

    private static function save_insurance( $employee_id, $data ) {
        global $wpdb;
        $row = array(
            'employee_id'     => $employee_id,
            'health_no'       => sanitize_text_field( wp_unslash( $data['health_no']       ?? '' ) ) ?: null,
            'health_date'     => self::sanitize_date( $data['health_date']     ?? '' ),
            'pension_no'      => sanitize_text_field( wp_unslash( $data['pension_no']      ?? '' ) ) ?: null,
            'pension_date'    => self::sanitize_date( $data['pension_date']    ?? '' ),
            'employment_no'   => sanitize_text_field( wp_unslash( $data['employment_no']   ?? '' ) ) ?: null,
            'employment_date' => self::sanitize_date( $data['employment_date'] ?? '' ),
            'accident_no'     => sanitize_text_field( wp_unslash( $data['accident_no']     ?? '' ) ) ?: null,
            'accident_date'   => self::sanitize_date( $data['accident_date']   ?? '' ),
            'updated_at'      => current_time( 'mysql' ),
        );
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}emp_insurance WHERE employee_id = %d", $employee_id
        ) );
        if ( $exists ) {
            $wpdb->update( "{$wpdb->prefix}emp_insurance", $row, array( 'employee_id' => $employee_id ) );
        } else {
            $row['created_at'] = current_time( 'mysql' );
            $result = $wpdb->insert( "{$wpdb->prefix}emp_insurance", $row );
            if ( $result === false ) {
                error_log( '[EMP] save_insurance insert failed: ' . $wpdb->last_error );
            }
        }
    }

    private static function save_retirement( $employee_id, $data ) {
        global $wpdb;
        $row = array(
            'employee_id'     => $employee_id,
            'retirement_date' => self::sanitize_date( $data['retirement_date'] ?? '' ),
            'updated_at'      => current_time( 'mysql' ),
        );
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}emp_retirement WHERE employee_id = %d", $employee_id
        ) );
        if ( $exists ) {
            $wpdb->update( "{$wpdb->prefix}emp_retirement", $row, array( 'employee_id' => $employee_id ) );
        } else {
            $row['created_at'] = current_time( 'mysql' );
            $result = $wpdb->insert( "{$wpdb->prefix}emp_retirement", $row );
            if ( $result === false ) {
                error_log( '[EMP] save_retirement insert failed: ' . $wpdb->last_error );
            }
        }
    }

    /**
     * 1対多テーブルの保存（全削除→再挿入）
     */
    private static function save_children( $table_suffix, $employee_id, $rows ) {
        global $wpdb;
        $table = "{$wpdb->prefix}{$table_suffix}";

        // 既存レコードを全削除
        $wpdb->delete( $table, array( 'employee_id' => $employee_id ), array( '%d' ) );

        if ( empty( $rows ) || ! is_array( $rows ) ) return;

        foreach ( $rows as $i => $row ) {
            if ( ! is_array( $row ) ) continue;
            $sanitized = array();
            foreach ( $row as $k => $v ) {
                $sanitized[ $k ] = sanitize_text_field( wp_unslash( (string) $v ) );
            }
            $sanitized['employee_id'] = $employee_id;
            $sanitized['sort_order']  = (int) $i;
            $sanitized['created_at']  = current_time( 'mysql' );
            $sanitized['updated_at']  = current_time( 'mysql' );
            $result = $wpdb->insert( $table, $sanitized );
            if ( $result === false ) {
                error_log( '[EMP] save_children insert failed: ' . $wpdb->last_error . ' | table: ' . $table . ' | row: ' . wp_json_encode( $sanitized ) );
            }
        }
    }

    // =====================================================
    //  DELETE
    // =====================================================

    /**
     * 社員を物理削除（関連テーブルも全削除）
     */
    public static function delete( $id ) {
        global $wpdb;

        $related = array(
            'emp_insurance', 'emp_retirement', 'emp_education',
            'emp_career', 'emp_qualification', 'emp_dependent',
        );
        foreach ( $related as $t ) {
            $wpdb->delete( "{$wpdb->prefix}{$t}", array( 'employee_id' => (int) $id ), array( '%d' ) );
        }

        return $wpdb->delete( "{$wpdb->prefix}emp_master", array( 'id' => (int) $id ), array( '%d' ) ) !== false;
    }

    /**
     * 在籍フラグのみ切り替え（トグル）
     */
    public static function toggle_active( $id, $active ) {
        global $wpdb;
        return $wpdb->update(
            "{$wpdb->prefix}emp_master",
            array( 'is_active' => $active ? 1 : 0 ),
            array( 'id' => (int) $id ),
            array( '%d' ),
            array( '%d' )
        ) !== false;
    }

    // =====================================================
    //  UTILITIES
    // =====================================================

    private static function sanitize_date( $val ) {
        if ( empty( $val ) ) return null;
        $d = DateTime::createFromFormat( 'Y-m-d', $val );
        return ( $d && $d->format( 'Y-m-d' ) === $val ) ? $val : null;
    }

    // =====================================================
    //  AJAX HANDLERS
    // =====================================================

    public static function ajax_get_list() {
        check_ajax_referer( 'emp_employee_nonce', 'nonce' );
        if ( ! current_user_can( 'access_custom_plugins' ) ) wp_die( -1 );

        $args = array(
            'search'         => sanitize_text_field( $_POST['search']         ?? '' ),
            'affiliation_id' => sanitize_text_field( $_POST['affiliation_id'] ?? '' ),
            'department_id'  => sanitize_text_field( $_POST['department_id']  ?? '' ),
            'is_active'      => $_POST['is_active'] !== '' ? (int) $_POST['is_active'] : '',
            'per_page'       => (int) ( $_POST['per_page'] ?? 20 ),
            'page'           => (int) ( $_POST['page']     ?? 1  ),
            'orderby'        => sanitize_key( $_POST['orderby'] ?? 'employee_code' ),
            'order'          => sanitize_text_field( $_POST['order'] ?? 'ASC' ),
        );

        wp_send_json_success( self::get_list( $args ) );
    }



    public static function ajax_get_one() {
        check_ajax_referer( 'emp_employee_nonce', 'nonce' );
        if ( ! current_user_can( 'access_custom_plugins' ) ) wp_die( -1 );

        $id  = (int) ( $_POST['id'] ?? 0 );
        $emp = self::get_by_id( $id );
        if ( $emp ) {
            wp_send_json_success( $emp );
        } else {
            wp_send_json_error( array( 'message' => '社員が見つかりません' ) );
        }
    }

    public static function ajax_save() {
        check_ajax_referer( 'emp_employee_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_custom_plugins' ) ) wp_die( -1 );

        $id     = (int) ( $_POST['id'] ?? 0 );
        $data   = wp_unslash( $_POST['data'] ?? array() ); // magic quotes対策
        $result = self::save( $data, $id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        } else {
            wp_send_json_success( array( 'id' => $result, 'message' => $id > 0 ? '更新しました' : '登録しました' ) );
        }
    }

    public static function ajax_toggle_active() {
        check_ajax_referer( 'emp_employee_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_custom_plugins' ) ) wp_die( -1 );

        $id     = (int) ( $_POST['id']        ?? 0 );
        $active = (int) ( $_POST['is_active'] ?? 1 );
        $result = self::toggle_active( $id, $active );

        if ( $result ) {
            wp_send_json_success( array( 'message' => $active ? '在籍中に変更しました' : '退職に変更しました' ) );
        } else {
            wp_send_json_error( array( 'message' => '更新に失敗しました' ) );
        }
    }

    /**
 * 統計情報を返す（フィルター無関係の全体集計）
 * 所属別在籍人数を含む
 */
public static function ajax_get_stats() {
    check_ajax_referer( 'emp_employee_nonce', 'nonce' );
    if ( ! current_user_can( 'access_custom_plugins' ) ) wp_die( -1 );

    global $wpdb;

    // 総社員数（在籍・退職含む全員）
    $total = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}emp_master"
    );

    // 在籍中の合計
    $active_total = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}emp_master WHERE is_active = 1"
    );

    // 所属別在籍人数（有効な所属マスタに LEFT JOIN）
    $rows = $wpdb->get_results(
        "SELECT a.id, a.name, COUNT(m.id) AS active_count
         FROM {$wpdb->prefix}mst_affiliation a
         LEFT JOIN {$wpdb->prefix}emp_master m
             ON m.affiliation_id = a.id AND m.is_active = 1
         WHERE a.is_active = 1
         GROUP BY a.id, a.name
         ORDER BY a.id ASC"
    );

    // 所属未設定の在籍者（affiliation_id が NULL または 0）
    $no_affil = (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->prefix}emp_master
         WHERE is_active = 1
           AND (affiliation_id IS NULL OR affiliation_id = 0)"
    );

    $affiliations = array();
    foreach ( $rows as $row ) {
        $affiliations[] = array(
            'id'           => (int) $row->id,
            'name'         => $row->name,
            'active_count' => (int) $row->active_count,
        );
    }
    if ( $no_affil > 0 ) {
        $affiliations[] = array(
            'id'           => 0,
            'name'         => '未所属',
            'active_count' => $no_affil,
        );
    }

    wp_send_json_success( array(
        'total'        => $total,
        'active_total' => $active_total,
        'affiliations' => $affiliations,
    ) );
}

}
