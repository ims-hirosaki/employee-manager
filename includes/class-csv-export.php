<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class EMP_CSV_Export
 * CSV出力ロジックを担当する
 */
class EMP_CSV_Export {

    /**
     * 出力可能な全列定義
     * key        : JS側と合わせたキー名
     * label      : ヘッダー行に出力するラベル
     * source     : 'master' | 'insurance' （どのテーブルから取得するか）
     * db_col     : SELECT するカラム名
     */
    public static function column_definitions() {
        return array(
            // 基本情報
            array( 'key'=>'id',               'label'=>'内部ID',          'source'=>'master',    'db_col'=>'m.id' ),
            array( 'key'=>'employee_code',     'label'=>'社員コード',      'source'=>'master',    'db_col'=>'m.employee_code' ),
            array( 'key'=>'name',              'label'=>'氏名',            'source'=>'master',    'db_col'=>'m.name' ),
            array( 'key'=>'name_kana',         'label'=>'フリガナ',        'source'=>'master',    'db_col'=>'m.name_kana' ),
            array( 'key'=>'gender',            'label'=>'性別',            'source'=>'master',    'db_col'=>'m.gender' ),
            array( 'key'=>'birthdate',         'label'=>'生年月日',        'source'=>'master',    'db_col'=>'m.birthdate' ),
            array( 'key'=>'blood_type',        'label'=>'血液型',          'source'=>'master',    'db_col'=>'m.blood_type' ),
            array( 'key'=>'hire_date',         'label'=>'入社日',          'source'=>'master',    'db_col'=>'m.hire_date' ),
            array( 'key'=>'is_active',         'label'=>'在籍区分',        'source'=>'master',    'db_col'=>'m.is_active' ),
            // 所属・役職
            array( 'key'=>'affiliation_name',  'label'=>'所属',            'source'=>'master',    'db_col'=>'a.name' ),
            array( 'key'=>'department_name',   'label'=>'部署',            'source'=>'master',    'db_col'=>'d.name' ),
            array( 'key'=>'position_name',     'label'=>'役職',            'source'=>'master',    'db_col'=>'p.name' ),
            array( 'key'=>'job_type_name',     'label'=>'職種',            'source'=>'master',    'db_col'=>'j.name' ),
            array( 'key'=>'crew_code',         'label'=>'乗組員コード',    'source'=>'master',    'db_col'=>'m.crew_code' ),
            // 連絡先
            array( 'key'=>'zip',               'label'=>'郵便番号',        'source'=>'master',    'db_col'=>'m.zip' ),
            array( 'key'=>'address',           'label'=>'住所',            'source'=>'master',    'db_col'=>'m.address' ),
            array( 'key'=>'tel_home',          'label'=>'自宅電話',        'source'=>'master',    'db_col'=>'m.tel_home' ),
            array( 'key'=>'tel_mobile',        'label'=>'携帯電話',        'source'=>'master',    'db_col'=>'m.tel_mobile' ),
            array( 'key'=>'tel_company',       'label'=>'会社携帯',        'source'=>'master',    'db_col'=>'m.tel_company' ),
            array( 'key'=>'emergency_name',    'label'=>'緊急連絡先氏名',  'source'=>'master',    'db_col'=>'m.emergency_name' ),
            array( 'key'=>'emergency_tel',     'label'=>'緊急連絡先電話',  'source'=>'master',    'db_col'=>'m.emergency_tel' ),
            array( 'key'=>'emergency_relation','label'=>'緊急連絡先続柄',  'source'=>'master',    'db_col'=>'m.emergency_relation' ),
            array( 'key'=>'memo',              'label'=>'備考',            'source'=>'master',    'db_col'=>'m.memo' ),
            // 保険
            array( 'key'=>'health_no',         'label'=>'健康保険番号',    'source'=>'insurance', 'db_col'=>'ins.health_no' ),
            array( 'key'=>'health_date',       'label'=>'健康保険取得日',  'source'=>'insurance', 'db_col'=>'ins.health_date' ),
            array( 'key'=>'pension_no',        'label'=>'厚生年金番号',    'source'=>'insurance', 'db_col'=>'ins.pension_no' ),
            array( 'key'=>'pension_date',      'label'=>'厚生年金取得日',  'source'=>'insurance', 'db_col'=>'ins.pension_date' ),
            array( 'key'=>'employment_no',     'label'=>'雇用保険番号',    'source'=>'insurance', 'db_col'=>'ins.employment_no' ),
            array( 'key'=>'employment_date',   'label'=>'雇用保険取得日',  'source'=>'insurance', 'db_col'=>'ins.employment_date' ),
            array( 'key'=>'accident_no',       'label'=>'労災保険番号',    'source'=>'insurance', 'db_col'=>'ins.accident_no' ),
            array( 'key'=>'accident_date',     'label'=>'労災保険取得日',  'source'=>'insurance', 'db_col'=>'ins.accident_date' ),
        );
    }

    /**
     * データを取得して CSV を出力（ブラウザに直接送信）
     *
     * @param array $args {
     *   @type array  $column_keys   出力するキー名の配列（順番がそのまま列順）
     *   @type string $is_active     '' | '1' | '0'
     *   @type int    $affiliation_id
     *   @type int    $department_id
     *   @type string $hire_date_from  Y-m-d
     *   @type string $hire_date_to    Y-m-d
     *   @type string $filename       拡張子なしのファイル名
     *   @type string $encoding       'sjis' | 'utf8'
     *   @type string $format         'csv' | 'tsv'
     *   @type bool   $with_header
     * }
     */
    public static function output( $args ) {
        $defaults = array(
            'column_keys'     => array( 'employee_code', 'name' ),
            'is_active'       => '',
            'affiliation_id'  => '',
            'department_id'   => '',
            'hire_date_from'  => '',
            'hire_date_to'    => '',
            'filename'        => '社員情報_出力',
            'encoding'        => 'sjis',
            'format'          => 'csv',
            'with_header'     => true,
        );
        $args = wp_parse_args( $args, $defaults );

        $rows = self::fetch_data( $args );

        // --- HTTPヘッダー ---
        $ext      = $args['format'] === 'tsv' ? 'tsv' : 'csv';
        $filename = sanitize_file_name( $args['filename'] ) . '.' . $ext;
        $charset  = $args['encoding'] === 'utf8' ? 'UTF-8' : 'Shift_JIS';

        header( 'Content-Type: text/csv; charset=' . $charset );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $sep = $args['format'] === 'tsv' ? "\t" : ',';

        // --- 出力用の列定義を絞り込み・並び替え ---
        $all_defs   = self::column_definitions();
        $def_by_key = array();
        foreach ( $all_defs as $def ) {
            $def_by_key[ $def['key'] ] = $def;
        }
        $ordered_defs = array();
        foreach ( $args['column_keys'] as $key ) {
            if ( isset( $def_by_key[ $key ] ) ) {
                $ordered_defs[] = $def_by_key[ $key ];
            }
        }

        $output = fopen( 'php://output', 'w' );

        // UTF-8の場合はBOMを付与（Excelで文字化けしないよう）
        if ( $args['encoding'] === 'utf8' ) {
            fwrite( $output, "\xEF\xBB\xBF" );
        }

        // --- ヘッダー行 ---
        if ( $args['with_header'] ) {
            $header = array_map( fn($d) => $d['label'], $ordered_defs );
            self::write_row( $output, $header, $sep, $args['encoding'] );
        }

        // --- データ行 ---
        foreach ( $rows as $row ) {
            $line = array();
            foreach ( $ordered_defs as $def ) {
                $val = $row->{ $def['key'] } ?? '';
                // 在籍区分は数値→テキスト変換
                if ( $def['key'] === 'is_active' ) {
                    $val = $val ? '在籍中' : '退職';
                }
                $line[] = $val ?? '';
            }
            self::write_row( $output, $line, $sep, $args['encoding'] );
        }

        fclose( $output );
        exit;
    }

    /**
     * データを DB から取得する
     */
    private static function fetch_data( $args ) {
        global $wpdb;

        $need_insurance = false;
        foreach ( $args['column_keys'] as $key ) {
            if ( str_starts_with( $key, 'health_' ) || str_starts_with( $key, 'pension_' ) ||
                 str_starts_with( $key, 'employment_' ) || str_starts_with( $key, 'accident_' ) ) {
                $need_insurance = true;
                break;
            }
        }

        // SELECT句：必要な列のみ（+ key名でエイリアス）
        $all_defs = self::column_definitions();
        $selects  = array();
        foreach ( $all_defs as $def ) {
            $selects[] = "{$def['db_col']} AS `{$def['key']}`";
        }
        $select_sql = implode( ', ', $selects );

        $join_insurance = $need_insurance
            ? "LEFT JOIN {$wpdb->prefix}emp_insurance ins ON m.id = ins.employee_id"
            : '';

        $where  = array( '1=1' );
        $params = array();

        if ( $args['is_active'] !== '' ) {
            $where[]  = 'm.is_active = %d';
            $params[] = (int) $args['is_active'];
        }
        if ( ! empty( $args['affiliation_id'] ) ) {
            $where[]  = 'm.affiliation_id = %d';
            $params[] = (int) $args['affiliation_id'];
        }
        if ( ! empty( $args['department_id'] ) ) {
            $where[]  = 'm.department_id = %d';
            $params[] = (int) $args['department_id'];
        }
        if ( ! empty( $args['hire_date_from'] ) ) {
            $where[]  = 'm.hire_date >= %s';
            $params[] = $args['hire_date_from'];
        }
        if ( ! empty( $args['hire_date_to'] ) ) {
            $where[]  = 'm.hire_date <= %s';
            $params[] = $args['hire_date_to'];
        }

        $where_sql = 'WHERE ' . implode( ' AND ', $where );

        $sql = "
            SELECT {$select_sql}
            FROM {$wpdb->prefix}emp_master m
            LEFT JOIN {$wpdb->prefix}mst_affiliation a ON m.affiliation_id = a.id
            LEFT JOIN {$wpdb->prefix}mst_department  d ON m.department_id  = d.id
            LEFT JOIN {$wpdb->prefix}mst_position    p ON m.position_id    = p.id
            LEFT JOIN {$wpdb->prefix}mst_job_type    j ON m.job_type_id    = j.id
            {$join_insurance}
            {$where_sql}
            ORDER BY m.employee_code ASC
        ";

        if ( ! empty( $params ) ) {
            $sql = $wpdb->prepare( $sql, ...$params ); // phpcs:ignore
        }

        return $wpdb->get_results( $sql ); // phpcs:ignore
    }

    /**
     * 1行書き出し（Shift-JIS変換対応）
     */
    private static function write_row( $handle, $cols, $sep, $encoding ) {
        if ( $sep === ',' ) {
            // CSV: フィールドをダブルクォートで囲む
            $line = implode( ',', array_map( function( $v ) {
                $v = str_replace( '"', '""', (string) $v );
                return '"' . $v . '"';
            }, $cols ) );
        } else {
            $line = implode( "\t", $cols );
        }
        $line .= "\r\n";

        if ( $encoding === 'sjis' ) {
            $line = mb_convert_encoding( $line, 'Shift_JIS', 'UTF-8' );
        }
        fwrite( $handle, $line );
    }

    // =====================================================
    //  CSV テンプレート管理（DBへの保存）
    // =====================================================

    /**
     * テンプレート一覧取得（ログインユーザー）
     */
    public static function get_templates() {
        global $wpdb;
        $user_id = get_current_user_id();
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}emp_csv_template WHERE wp_user_id = %d ORDER BY sort_order ASC, id ASC",
                $user_id
            )
        );
    }

    /**
     * テンプレート保存
     */
    public static function save_template( $name, $column_keys ) {
        global $wpdb;
        $user_id = get_current_user_id();
        if ( ! $user_id ) return false;

        return $wpdb->insert(
            "{$wpdb->prefix}emp_csv_template",
            array(
                'wp_user_id'  => $user_id,
                'name'        => sanitize_text_field( $name ),
                'column_keys' => wp_json_encode( $column_keys ),
                'sort_order'  => 0,
            ),
            array( '%d', '%s', '%s', '%d' )
        ) ? $wpdb->insert_id : false;
    }

    /**
     * テンプレート更新（名前変更）
     */
    public static function update_template( $id, $name ) {
        global $wpdb;
        $user_id = get_current_user_id();
        return $wpdb->update(
            "{$wpdb->prefix}emp_csv_template",
            array( 'name' => sanitize_text_field( $name ) ),
            array( 'id' => (int) $id, 'wp_user_id' => $user_id ),
            array( '%s' ),
            array( '%d', '%d' )
        ) !== false;
    }

    /**
     * テンプレート削除
     */
    public static function delete_template( $id ) {
        global $wpdb;
        $user_id = get_current_user_id();
        return $wpdb->delete(
            "{$wpdb->prefix}emp_csv_template",
            array( 'id' => (int) $id, 'wp_user_id' => $user_id ),
            array( '%d', '%d' )
        ) !== false;
    }

    // =====================================================
    //  AJAX HANDLERS
    // =====================================================

    public static function ajax_export() {
        check_ajax_referer( 'emp_csv_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( -1 );

        $args = array(
            'column_keys'    => array_map( 'sanitize_key', (array) ( $_POST['column_keys']    ?? array() ) ),
            'is_active'      => sanitize_text_field( $_POST['is_active']      ?? '' ),
            'affiliation_id' => sanitize_text_field( $_POST['affiliation_id'] ?? '' ),
            'department_id'  => sanitize_text_field( $_POST['department_id']  ?? '' ),
            'hire_date_from' => sanitize_text_field( $_POST['hire_date_from'] ?? '' ),
            'hire_date_to'   => sanitize_text_field( $_POST['hire_date_to']   ?? '' ),
            'filename'       => sanitize_text_field( $_POST['filename']        ?? '社員情報_出力' ),
            'encoding'       => in_array( $_POST['encoding'] ?? '', array( 'sjis', 'utf8' ), true )
                                    ? $_POST['encoding'] : 'sjis',
            'format'         => in_array( $_POST['format'] ?? '', array( 'csv', 'tsv' ), true )
                                    ? $_POST['format'] : 'csv',
            'with_header'    => (bool) ( $_POST['with_header'] ?? true ),
        );

        self::output( $args );
    }

    public static function ajax_get_templates() {
        check_ajax_referer( 'emp_csv_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( -1 );
        wp_send_json_success( self::get_templates() );
    }

    public static function ajax_save_template() {
        check_ajax_referer( 'emp_csv_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( -1 );

        $name        = sanitize_text_field( $_POST['name']        ?? '' );
        $column_keys = array_map( 'sanitize_key', (array) ( $_POST['column_keys'] ?? array() ) );

        if ( empty( $name ) ) {
            wp_send_json_error( array( 'message' => 'テンプレート名は必須です' ) );
        }
        $id = self::save_template( $name, $column_keys );
        if ( $id ) {
            wp_send_json_success( array( 'id' => $id, 'message' => '保存しました' ) );
        } else {
            wp_send_json_error( array( 'message' => '保存に失敗しました' ) );
        }
    }

    public static function ajax_update_template() {
        check_ajax_referer( 'emp_csv_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( -1 );

        $id   = (int) ( $_POST['id']   ?? 0 );
        $name = sanitize_text_field( $_POST['name'] ?? '' );
        if ( self::update_template( $id, $name ) ) {
            wp_send_json_success( array( 'message' => '更新しました' ) );
        } else {
            wp_send_json_error( array( 'message' => '更新に失敗しました' ) );
        }
    }

    public static function ajax_delete_template() {
        check_ajax_referer( 'emp_csv_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( -1 );

        $id = (int) ( $_POST['id'] ?? 0 );
        if ( self::delete_template( $id ) ) {
            wp_send_json_success( array( 'message' => '削除しました' ) );
        } else {
            wp_send_json_error( array( 'message' => '削除に失敗しました' ) );
        }
    }
}
