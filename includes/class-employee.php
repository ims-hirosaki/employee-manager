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
        $emp->crew_code_history = self::get_crew_code_history( $id );

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

    /**
     * 乗務員コード履歴を新しい順に取得する。
     */
    public static function get_crew_code_history( $employee_id ) {
        global $wpdb;
        $table = "{$wpdb->prefix}emp_crew_code_history";
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            return array();
        }
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT id, employee_id, crew_code, valid_from, valid_to, is_current, created_at, updated_at
             FROM {$table}
             WHERE employee_id = %d
             ORDER BY is_current DESC, COALESCE(valid_from, '1000-01-01') DESC, id DESC",
            (int) $employee_id
        ) );
    }

    /**
     * 指定期間と重なる乗務員コード履歴を取得する。
     */
    public static function get_crew_codes_for_period( $employee_id, $start_date, $end_date ) {
        global $wpdb;
        $start_date = self::sanitize_date( $start_date );
        $end_date   = self::sanitize_date( $end_date );
        if ( ! $employee_id || ! $start_date || ! $end_date || $start_date > $end_date ) return array();

        $table = "{$wpdb->prefix}emp_crew_code_history";
        $rows = array();
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT crew_code, valid_from, valid_to, is_current
                 FROM {$table}
                 WHERE employee_id = %d
                   AND (valid_from IS NULL OR valid_from <= %s)
                   AND (valid_to IS NULL OR valid_to >= %s)
                 ORDER BY COALESCE(valid_from, '1000-01-01') ASC, id ASC",
                (int) $employee_id,
                $end_date,
                $start_date
            ), ARRAY_A );
        }

        if ( empty( $rows ) ) {
            $crew_code = trim( (string) $wpdb->get_var( $wpdb->prepare(
                "SELECT crew_code FROM {$wpdb->prefix}emp_master WHERE id = %d",
                (int) $employee_id
            ) ) );
            if ( $crew_code !== '' ) {
                $rows[] = array( 'crew_code' => $crew_code, 'valid_from' => null, 'valid_to' => null, 'is_current' => 1 );
            }
        }
        return $rows;
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

        $old_crew_code = '';
        if ( $id > 0 ) {
            $old_crew_code = trim( (string) $wpdb->get_var( $wpdb->prepare(
                "SELECT crew_code FROM {$wpdb->prefix}emp_master WHERE id = %d",
                (int) $id
            ) ) );
        }
        // 既存社員のコード変更は履歴欄の「新規コードを追加」からのみ行う。
        // 新規社員の初回コードは登録日を使用開始日として自動保存する。
        $new_crew_code = $id > 0
            ? $old_crew_code
            : trim( sanitize_text_field( $data['crew_code'] ?? '' ) );
        $crew_valid_from = ( $id === 0 && $new_crew_code !== '' ) ? current_time( 'Y-m-d' ) : null;

        if ( $old_crew_code !== $new_crew_code ) {
            if ( $id > 0 && $old_crew_code !== '' && ! $crew_valid_from ) {
                return new WP_Error( 'crew_date_required', '乗組員コードを変更・終了する場合は、新コードの適用開始日を入力してください' );
            }
            if ( $old_crew_code !== '' && $crew_valid_from ) {
                $old_valid_from = $wpdb->get_var( $wpdb->prepare(
                    "SELECT valid_from FROM {$wpdb->prefix}emp_crew_code_history
                     WHERE employee_id = %d AND crew_code = %s LIMIT 1",
                    (int) $id,
                    $old_crew_code
                ) );
                if ( $old_valid_from && $crew_valid_from <= $old_valid_from ) {
                    return new WP_Error( 'invalid_crew_date', '新コードの適用開始日は、現行コードの使用開始日より後にしてください' );
                }
            }
            $crew_error = self::validate_new_crew_code( (int) $id, $new_crew_code, $crew_valid_from );
            if ( is_wp_error( $crew_error ) ) return $crew_error;
            if ( $new_crew_code !== '' ) {
                $old_history_id = $old_crew_code === '' ? 0 : (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}emp_crew_code_history
                     WHERE employee_id = %d AND crew_code = %s LIMIT 1",
                    (int) $id,
                    $old_crew_code
                ) );
                $period_error = self::validate_history_period(
                    (int) $id,
                    $new_crew_code,
                    $crew_valid_from,
                    null,
                    $old_history_id
                );
                if ( is_wp_error( $period_error ) ) return $period_error;
            }
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
            'crew_code'          => $new_crew_code !== '' ? $new_crew_code : null,
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

        $wpdb->query( 'START TRANSACTION' );

        if ( $id > 0 ) {
            $master['updated_at'] = current_time( 'mysql' );
            $result = $wpdb->update( "{$wpdb->prefix}emp_master", $master, array( 'id' => $id ) );
            if ( $result === false ) {
                error_log( '[EMP] emp_master update failed: ' . $wpdb->last_error );
                $wpdb->query( 'ROLLBACK' );
                return new WP_Error( 'db_error', '社員情報の保存に失敗しました' );
            }
            $employee_id = $id;
        } else {
            $master['created_at'] = current_time( 'mysql' );
            $master['updated_at'] = current_time( 'mysql' );
            $result = $wpdb->insert( "{$wpdb->prefix}emp_master", $master );
            if ( $result === false ) {
                error_log( '[EMP] emp_master insert failed: ' . $wpdb->last_error );
                $wpdb->query( 'ROLLBACK' );
                return new WP_Error( 'db_error', '社員情報の保存に失敗しました' );
            }
            $employee_id = $wpdb->insert_id;
        }

        if ( ! $employee_id ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'db_error', '社員情報の保存に失敗しました' );
        }

        $history_result = self::sync_crew_code_history(
            (int) $employee_id,
            $old_crew_code,
            $new_crew_code,
            $crew_valid_from
        );
        if ( is_wp_error( $history_result ) ) {
            $wpdb->query( 'ROLLBACK' );
            return $history_result;
        }
        $wpdb->query( 'COMMIT' );

        // --- 関連テーブルを Upsert ---
        self::save_insurance(    $employee_id, $data );
        self::save_retirement(   $employee_id, $data );
        self::save_children( 'emp_education',    $employee_id, $data['educations']    ?? array() );
        self::save_children( 'emp_career',       $employee_id, $data['careers']       ?? array() );
        self::save_children( 'emp_qualification',$employee_id, $data['qualifications'] ?? array() );
        self::save_children( 'emp_dependent',    $employee_id, $data['dependents']    ?? array() );

        return $employee_id;
    }

    /**
     * 新しい現行コードが他社員の利用期間と重複しないことを検証する。
     */
    private static function validate_new_crew_code( $employee_id, $crew_code, $valid_from ) {
        global $wpdb;
        if ( $crew_code === '' ) return true;

        $master_conflict = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}emp_master WHERE crew_code = %s AND id <> %d LIMIT 1",
            $crew_code,
            $employee_id
        ) );
        if ( $master_conflict ) {
            return new WP_Error( 'crew_code_conflict', 'この乗組員コードは別の社員の現行コードとして使用されています' );
        }

        $table = "{$wpdb->prefix}emp_crew_code_history";
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            return new WP_Error( 'missing_history_table', '乗組員コード履歴テーブルが未作成です。プラグインを再有効化してください' );
        }

        $same_employee = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} WHERE employee_id = %d AND crew_code = %s LIMIT 1",
            $employee_id,
            $crew_code
        ) );
        if ( $same_employee ) {
            return new WP_Error( 'crew_code_reuse', 'この乗組員コードは当該社員の過去履歴に存在します。再利用する場合は履歴期間の管理機能で調整してください' );
        }

        if ( $valid_from ) {
            $conflict = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table}
                 WHERE crew_code = %s AND employee_id <> %d
                   AND (valid_to IS NULL OR valid_to >= %s)
                 LIMIT 1",
                $crew_code,
                $employee_id,
                $valid_from
            ) );
        } else {
            $conflict = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table} WHERE crew_code = %s AND employee_id <> %d LIMIT 1",
                $crew_code,
                $employee_id
            ) );
        }
        return $conflict
            ? new WP_Error( 'crew_code_conflict', 'この乗組員コードは別の社員の利用期間と重複しています' )
            : true;
    }

    /**
     * master の現行コード変更と履歴の終了・追加を同期する。
     */
    private static function sync_crew_code_history( $employee_id, $old_code, $new_code, $valid_from ) {
        global $wpdb;
        $table = "{$wpdb->prefix}emp_crew_code_history";
        $user_id = get_current_user_id() ?: null;
        $now = current_time( 'mysql' );

        if ( $old_code === $new_code ) {
            if ( $new_code === '' ) return true;
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$table} SET is_current = 0, updated_by = %d, updated_at = %s
                 WHERE employee_id = %d AND crew_code <> %s AND is_current = 1",
                (int) $user_id, $now, $employee_id, $new_code
            ) );
            $existing = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table} WHERE employee_id = %d AND crew_code = %s LIMIT 1",
                $employee_id,
                $new_code
            ) );
            if ( $existing ) {
                $update = array( 'is_current' => 1, 'valid_to' => null, 'updated_by' => $user_id, 'updated_at' => $now );
                if ( $valid_from ) $update['valid_from'] = $valid_from;
                $result = $wpdb->update( $table,
                    $update,
                    array( 'id' => (int) $existing )
                );
            } else {
                $result = $wpdb->insert( $table, array(
                    'employee_id' => $employee_id, 'crew_code' => $new_code,
                    'valid_from' => $valid_from, 'valid_to' => null, 'is_current' => 1,
                    'created_by' => $user_id, 'updated_by' => $user_id,
                    'created_at' => $now, 'updated_at' => $now,
                ) );
            }
            return $result === false ? new WP_Error( 'crew_history_error', '乗組員コード履歴の保存に失敗しました' ) : true;
        }

        $wpdb->update( $table,
            array( 'is_current' => 0, 'updated_by' => $user_id, 'updated_at' => $now ),
            array( 'employee_id' => $employee_id )
        );

        if ( $old_code !== '' ) {
            $valid_to = date( 'Y-m-d', strtotime( $valid_from . ' -1 day' ) );
            $old_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$table} WHERE employee_id = %d AND crew_code = %s LIMIT 1",
                $employee_id,
                $old_code
            ) );
            if ( $old_id ) {
                $result = $wpdb->update( $table,
                    array( 'valid_to' => $valid_to, 'is_current' => 0, 'updated_by' => $user_id, 'updated_at' => $now ),
                    array( 'id' => (int) $old_id )
                );
            } else {
                $result = $wpdb->insert( $table, array(
                    'employee_id' => $employee_id, 'crew_code' => $old_code,
                    'valid_from' => null, 'valid_to' => $valid_to, 'is_current' => 0,
                    'created_by' => $user_id, 'updated_by' => $user_id,
                    'created_at' => $now, 'updated_at' => $now,
                ) );
            }
            if ( $result === false ) return new WP_Error( 'crew_history_error', '旧乗組員コードの終了処理に失敗しました' );
        }

        if ( $new_code !== '' ) {
            $result = $wpdb->insert( $table, array(
                'employee_id' => $employee_id, 'crew_code' => $new_code,
                'valid_from' => $valid_from, 'valid_to' => null, 'is_current' => 1,
                'created_by' => $user_id, 'updated_by' => $user_id,
                'created_at' => $now, 'updated_at' => $now,
            ) );
            if ( $result === false ) return new WP_Error( 'crew_history_error', '新しい乗組員コード履歴の登録に失敗しました' );
        }
        return true;
    }

    /**
     * CSV新規登録など、masterへ直接保存された現行コードを履歴へ補完する。
     */
    public static function ensure_current_crew_code_history( $employee_id ) {
        global $wpdb;
        $code = trim( (string) $wpdb->get_var( $wpdb->prepare(
            "SELECT crew_code FROM {$wpdb->prefix}emp_master WHERE id = %d",
            (int) $employee_id
        ) ) );
        if ( $code === '' ) return true;
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}emp_crew_code_history WHERE employee_id = %d AND crew_code = %s LIMIT 1",
            (int) $employee_id,
            $code
        ) );
        if ( ! $exists ) {
            $validation = self::validate_new_crew_code( (int) $employee_id, $code, null );
            if ( is_wp_error( $validation ) ) return $validation;
        }
        return self::sync_crew_code_history( (int) $employee_id, $code, $code, null );
    }

    private static function validate_history_period( $employee_id, $crew_code, $valid_from, $valid_to, $exclude_id = 0 ) {
        global $wpdb;
        $table = "{$wpdb->prefix}emp_crew_code_history";
        if ( $valid_from && $valid_to && $valid_from > $valid_to ) {
            return new WP_Error( 'invalid_period', '使用開始日は使用終了日以前にしてください' );
        }

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, employee_id, crew_code, valid_from, valid_to FROM {$table}
             WHERE id <> %d AND (employee_id = %d OR crew_code = %s)",
            (int) $exclude_id,
            (int) $employee_id,
            $crew_code
        ), ARRAY_A );
        foreach ( $rows as $row ) {
            $overlaps = ( ! $valid_to || ! $row['valid_from'] || $row['valid_from'] <= $valid_to )
                && ( ! $row['valid_to'] || ! $valid_from || $row['valid_to'] >= $valid_from );
            if ( ! $overlaps ) continue;
            if ( (int) $row['employee_id'] !== (int) $employee_id ) {
                return new WP_Error( 'crew_code_conflict', 'この乗組員コードは同じ期間に別の社員が使用しています' );
            }
            return new WP_Error( 'crew_period_overlap', 'この社員の別の乗組員コード履歴と使用期間が重複しています' );
        }
        return true;
    }

    /**
     * 新しい現行コードを追加し、登録日を境に旧コードを履歴化する。
     */
    private static function add_current_crew_code( $employee_id, $crew_code ) {
        global $wpdb;
        $employee_id = (int) $employee_id;
        $crew_code = trim( sanitize_text_field( $crew_code ) );
        if ( ! $employee_id || $crew_code === '' ) {
            return new WP_Error( 'validation', '新しい乗組員コードを入力してください' );
        }

        $old_code = $wpdb->get_var( $wpdb->prepare(
            "SELECT crew_code FROM {$wpdb->prefix}emp_master WHERE id = %d",
            $employee_id
        ) );
        if ( $old_code === null ) return new WP_Error( 'not_found', '社員が見つかりません' );
        $old_code = trim( (string) $old_code );
        if ( $old_code === $crew_code ) {
            return new WP_Error( 'same_crew_code', 'この乗組員コードはすでに現行コードです' );
        }

        $valid_from = current_time( 'Y-m-d' );
        if ( $old_code !== '' ) {
            $old_valid_from = $wpdb->get_var( $wpdb->prepare(
                "SELECT valid_from FROM {$wpdb->prefix}emp_crew_code_history
                 WHERE employee_id = %d AND crew_code = %s LIMIT 1",
                $employee_id,
                $old_code
            ) );
            if ( $old_valid_from && $valid_from <= $old_valid_from ) {
                return new WP_Error( 'invalid_crew_date', '現行コードと同じ日には新しいコードを追加できません。履歴の日付を確認してください' );
            }
        }

        $crew_error = self::validate_new_crew_code( $employee_id, $crew_code, $valid_from );
        if ( is_wp_error( $crew_error ) ) return $crew_error;

        $old_history_id = $old_code === '' ? 0 : (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}emp_crew_code_history
             WHERE employee_id = %d AND crew_code = %s LIMIT 1",
            $employee_id,
            $old_code
        ) );
        $period_error = self::validate_history_period(
            $employee_id,
            $crew_code,
            $valid_from,
            null,
            $old_history_id
        );
        if ( is_wp_error( $period_error ) ) return $period_error;

        $wpdb->query( 'START TRANSACTION' );
        $updated = $wpdb->update(
            "{$wpdb->prefix}emp_master",
            array( 'crew_code' => $crew_code, 'updated_at' => current_time( 'mysql' ) ),
            array( 'id' => $employee_id )
        );
        if ( $updated === false ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'db_error', '乗組員コードの更新に失敗しました' );
        }

        $history_result = self::sync_crew_code_history( $employee_id, $old_code, $crew_code, $valid_from );
        if ( is_wp_error( $history_result ) ) {
            $wpdb->query( 'ROLLBACK' );
            return $history_result;
        }
        $wpdb->query( 'COMMIT' );
        return $valid_from;
    }

    private static function update_crew_history( $history_id, $employee_id, $crew_code, $valid_from, $valid_to ) {
        global $wpdb;
        $table = "{$wpdb->prefix}emp_crew_code_history";
        $existing = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d AND employee_id = %d",
            $history_id, $employee_id
        ), ARRAY_A );
        if ( ! $existing ) return new WP_Error( 'not_found', '履歴が見つかりません' );

        $crew_code = trim( sanitize_text_field( $crew_code ) );
        $valid_from = self::sanitize_date( $valid_from );
        $valid_to = self::sanitize_date( $valid_to );
        if ( $crew_code === '' ) return new WP_Error( 'validation', '乗組員コードは必須です' );
        if ( ! empty( $existing['is_current'] ) ) {
            $crew_code = $existing['crew_code'];
            $valid_to = null;
        }
        $validation = self::validate_history_period( $employee_id, $crew_code, $valid_from, $valid_to, $history_id );
        if ( is_wp_error( $validation ) ) return $validation;

        $result = $wpdb->update( $table, array(
            'crew_code' => $crew_code, 'valid_from' => $valid_from, 'valid_to' => $valid_to,
            'updated_by' => get_current_user_id() ?: null, 'updated_at' => current_time( 'mysql' ),
        ), array( 'id' => $history_id, 'employee_id' => $employee_id ) );
        return $result === false ? new WP_Error( 'db_error', '履歴の更新に失敗しました' ) : true;
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
            'emp_career', 'emp_qualification', 'emp_dependent', 'emp_crew_code_history',
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

    public static function ajax_crew_history_add() {
        check_ajax_referer( 'emp_employee_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_custom_plugins' ) ) wp_die( -1 );
        $result = self::add_current_crew_code(
            absint( $_POST['employee_id'] ?? 0 ),
            wp_unslash( $_POST['crew_code'] ?? '' )
        );
        if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        wp_send_json_success( array( 'message' => '新しい乗組員コードを追加しました（使用開始日：' . $result . '）' ) );
    }

    public static function ajax_crew_history_update() {
        check_ajax_referer( 'emp_employee_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_custom_plugin_settings' ) ) wp_die( -1 );
        $result = self::update_crew_history(
            absint( $_POST['history_id'] ?? 0 ),
            absint( $_POST['employee_id'] ?? 0 ),
            wp_unslash( $_POST['crew_code'] ?? '' ),
            wp_unslash( $_POST['valid_from'] ?? '' ),
            wp_unslash( $_POST['valid_to'] ?? '' )
        );
        if ( is_wp_error( $result ) ) wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        wp_send_json_success( array( 'message' => '乗組員コード履歴を更新しました' ) );
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
