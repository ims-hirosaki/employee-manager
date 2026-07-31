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
        $args['column_keys'] = self::valid_column_keys( $args['column_keys'] );

        if ( empty( $args['column_keys'] ) ) {
            self::abort_export( '出力する項目を1つ以上選択してください。' );
        }

        $ordered_defs = self::get_ordered_definitions( $args['column_keys'] );
        $rows         = self::fetch_data( $args, $ordered_defs );

        // --- HTTPヘッダー ---
        $ext      = $args['format'] === 'tsv' ? 'tsv' : 'csv';
        $basename = sanitize_file_name( $args['filename'] );
        $basename = $basename !== '' ? $basename : 'employee-export';
        $filename = $basename . '.' . $ext;
        $charset  = $args['encoding'] === 'utf8' ? 'UTF-8' : 'Shift_JIS';
        $mime     = $args['format'] === 'tsv' ? 'text/tab-separated-values' : 'text/csv';

        header( 'Content-Type: ' . $mime . '; charset=' . $charset );
        header(
            'Content-Disposition: attachment; filename="employee-export.' . $ext .
            '"; filename*=UTF-8\'\'' . rawurlencode( $filename )
        );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $sep = $args['format'] === 'tsv' ? "\t" : ',';

        $output = fopen( 'php://output', 'w' );
        if ( $output === false ) {
            self::abort_export( '出力ストリームを開けませんでした。' );
        }

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
    private static function fetch_data( $args, $ordered_defs ) {
        global $wpdb;

        $need_insurance = false;
        foreach ( $ordered_defs as $def ) {
            if ( $def['source'] === 'insurance' ) {
                $need_insurance = true;
                break;
            }
        }

        // 選択された列だけを SELECT し、必要なテーブルだけを JOIN する。
        $selects = array();
        foreach ( $ordered_defs as $def ) {
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

        $rows = $wpdb->get_results( $sql ); // phpcs:ignore
        if ( $rows === null ) {
            self::abort_export( '社員情報の取得に失敗しました。' );
        }

        return $rows;
    }

    /**
     * 指定順に有効な列定義を返す。
     */
    private static function get_ordered_definitions( $column_keys ) {
        $definitions = array();
        foreach ( self::column_definitions() as $definition ) {
            $definitions[ $definition['key'] ] = $definition;
        }

        $ordered = array();
        foreach ( $column_keys as $key ) {
            if ( isset( $definitions[ $key ] ) ) {
                $ordered[] = $definitions[ $key ];
            }
        }

        return $ordered;
    }

    /**
     * 未知の列と重複列を除外する。
     */
    private static function valid_column_keys( $column_keys ) {
        $valid_keys = array_column( self::column_definitions(), 'key' );
        $keys       = array_map( 'sanitize_key', (array) $column_keys );
        $keys       = array_values( array_unique( $keys ) );

        return array_values( array_intersect( $keys, $valid_keys ) );
    }

    /**
     * 1行書き出し（CSV/TSVの引用符処理・Shift-JIS変換対応）。
     */
    private static function write_row( $handle, $cols, $sep, $encoding ) {
        $buffer = fopen( 'php://temp', 'w+' );
        if ( $buffer === false ) {
            self::abort_export( 'CSV行の生成に失敗しました。' );
        }

        $cols = array_map( array( __CLASS__, 'protect_spreadsheet_value' ), $cols );
        if ( fputcsv( $buffer, $cols, $sep, '"', '' ) === false ) {
            fclose( $buffer );
            self::abort_export( 'CSV行の生成に失敗しました。' );
        }

        rewind( $buffer );
        $line = stream_get_contents( $buffer );
        fclose( $buffer );

        // fputcsv() の行末を、Excelとの互換性が高いCRLFへ統一する。
        $line = preg_replace( "/\r?\n\z/", "\r\n", $line );

        if ( $encoding === 'sjis' ) {
            $line = mb_convert_encoding( $line, 'SJIS-win', 'UTF-8' );
        }

        if ( fwrite( $handle, $line ) === false ) {
            self::abort_export( 'CSVデータの書き込みに失敗しました。' );
        }
    }

    /**
     * Excel等でセル値が数式として実行されることを防ぐ。
     */
    private static function protect_spreadsheet_value( $value ) {
        $value = (string) ( $value ?? '' );
        if ( preg_match( '/^[\x00-\x20]*[=+\-@]/u', $value ) ) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * CSVレスポンスを開始する前にエラーとして終了する。
     */
    private static function abort_export( $message ) {
        wp_die(
            esc_html( $message ),
            'CSV出力エラー',
            array( 'response' => 400 )
        );
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

        $is_active = sanitize_text_field( wp_unslash( $_POST['is_active'] ?? '' ) );
        if ( ! in_array( $is_active, array( '', '0', '1' ), true ) ) {
            self::abort_export( '在籍区分の指定が正しくありません。' );
        }

        $hire_date_from = self::sanitize_export_date( $_POST['hire_date_from'] ?? '' );
        $hire_date_to   = self::sanitize_export_date( $_POST['hire_date_to'] ?? '' );
        if ( $hire_date_from === false || $hire_date_to === false ) {
            self::abort_export( '入社日は正しい日付形式で指定してください。' );
        }
        if ( $hire_date_from !== '' && $hire_date_to !== '' && $hire_date_from > $hire_date_to ) {
            self::abort_export( '入社日の開始日は終了日以前に指定してください。' );
        }

        $args = array(
            'column_keys'    => self::valid_column_keys( wp_unslash( $_POST['column_keys'] ?? array() ) ),
            'is_active'      => $is_active,
            'affiliation_id' => absint( $_POST['affiliation_id'] ?? 0 ),
            'department_id'  => absint( $_POST['department_id'] ?? 0 ),
            'hire_date_from' => $hire_date_from,
            'hire_date_to'   => $hire_date_to,
            'filename'       => sanitize_text_field( wp_unslash( $_POST['filename'] ?? '社員情報_出力' ) ),
            'encoding'       => in_array( $_POST['encoding'] ?? '', array( 'sjis', 'utf8' ), true )
                                    ? $_POST['encoding'] : 'sjis',
            'format'         => in_array( $_POST['format'] ?? '', array( 'csv', 'tsv' ), true )
                                    ? $_POST['format'] : 'csv',
            'with_header'    => ! isset( $_POST['with_header'] ) || $_POST['with_header'] === '1',
        );

        self::output( $args );
    }

    /**
     * 空文字または厳密なY-m-d形式の日付を返す。
     *
     * @return string|false
     */
    private static function sanitize_export_date( $value ) {
        $value = sanitize_text_field( wp_unslash( $value ) );
        if ( $value === '' ) {
            return '';
        }

        $date   = DateTime::createFromFormat( '!Y-m-d', $value );
        $errors = DateTime::getLastErrors();
        if (
            $date === false ||
            ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) ) ||
            $date->format( 'Y-m-d' ) !== $value
        ) {
            return false;
        }

        return $value;
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
