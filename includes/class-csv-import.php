<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * CSVインポート処理クラス
 *
 * ① 基本情報（emp_master + 保険 + 退職）
 * ② 経歴・資格（学歴 / 職歴 / 資格）
 * ③ 扶養者
 */
class EMP_CSV_Import {

    // =====================================================
    //  列定義
    // =====================================================

    /** ① 基本情報 */
    public static function columns_basic() {
        return array(
            'employee_code'      => '社員コード',
            'name'               => '氏名',
            'name_kana'          => 'フリガナ',
            'gender'             => '性別',
            'birthdate'          => '生年月日',
            'blood_type'         => '血液型',
            'hire_date'          => '入社日',
            'is_active'          => '在籍区分',
            'affiliation_name'   => '所属名',
            'department_name'    => '部署名',
            'position_name'      => '役職名',
            'job_type_name'      => '職種名',
            'crew_code'          => '乗組員コード',
            'employment_type'    => '雇用区分',
            'weekly_work_days'   => '週勤務日数',
            'zip'                => '郵便番号',
            'address'            => '住所',
            'tel_home'           => '自宅電話',
            'tel_mobile'         => '携帯電話',
            'tel_company'        => '会社携帯',
            'emergency_name'     => '緊急連絡先氏名',
            'emergency_tel'      => '緊急連絡先電話',
            'emergency_relation' => '緊急連絡先続柄',
            'memo'               => '備考',
            'health_no'          => '健康保険番号',
            'health_date'        => '健康保険取得日',
            'pension_no'         => '厚生年金番号',
            'pension_date'       => '厚生年金取得日',
            'employment_no'      => '雇用保険番号',
            'employment_date'    => '雇用保険取得日',
            'accident_no'        => '労災保険番号',
            'accident_date'      => '労災保険取得日',
            'retirement_date'    => '退職日',
        );
    }

    /** ② 経歴・資格 */
    public static function columns_career() {
        return array(
            'employee_code' => '社員コード',
            'record_type'   => '種別',
            'date'          => '日付',
            'career_year'   => '年（職歴用）',
            'career_month'  => '月（職歴用）',
            'title'         => '学校名・会社名・資格名',
            'sub1'          => '学科・専攻 / 部署名',
            'sub2'          => '役職名（職歴のみ）',
            'memo'          => '備考（職歴のみ）',
        );
    }

    /** ③ 扶養者 */
    public static function columns_dependent() {
        return array(
            'employee_code' => '社員コード',
            'name'          => '氏名',
            'name_kana'     => 'フリガナ',
            'relation'      => '続柄',
            'birthdate'     => '生年月日',
            'memo'          => '備考',
        );
    }

    // =====================================================
    //  ひな形DL
    // =====================================================

    public static function download_template() {
        check_ajax_referer( 'emp_import_nonce', 'nonce' );
        if ( ! current_user_can( 'access_custom_plugins' ) ) wp_die( 'Permission denied' );

        $type = isset( $_GET['csv_type'] ) ? sanitize_text_field( $_GET['csv_type'] ) : 'basic';
        switch ( $type ) {
            case 'career':    self::output_career_template();    break;
            case 'dependent': self::output_dependent_template(); break;
            default:          self::output_basic_template();     break;
        }
        exit;
    }

    private static function open_csv( $filename ) {
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        $fp = fopen( 'php://output', 'w' );
        fputs( $fp, "\xEF\xBB\xBF" );
        return $fp;
    }

    private static function output_basic_template() {
        $cols = self::columns_basic();
        $fp   = self::open_csv( '①基本情報インポートひな形.csv' );
        fputcsv( $fp, array_values( $cols ) );
        fputcsv( $fp, array(
            '0001','山田 太郎','ヤマダ タロウ','男性','1985-04-01','A','2010-04-01','1',
            '本社','総務部','課長','事務職','','正社員','5','030-0000','青森県弘前市〇〇町1-2-3',
            '0172-XX-XXXX','090-XXXX-XXXX','','山田 花子','0172-YY-YYYY','配偶者','',
            'H123456','2010-04-01','N789012','2010-04-01','E345678','2010-04-01','R000000','2010-04-01','',
        ) );
        fputcsv( $fp, array_merge( array('# ↑サンプル。この行を削除して2行目以降に実データを入力してください。雇用区分は「正社員」「契約社員」「パート・アルバイト」、週勤務日数は1〜6の数値で入力してください。'), array_fill(0, count($cols)-1, '') ) );
        fclose( $fp );
    }

    private static function output_career_template() {
        $cols = self::columns_career();
        $fp   = self::open_csv( '②経歴・資格インポートひな形.csv' );
        fputcsv( $fp, array_values( $cols ) );
        fputcsv( $fp, array('0001','学歴','2010-03-31','','','〇〇大学','経営学部 経営学科','','') );
        fputcsv( $fp, array('0001','学歴','2006-03-31','','','〇〇高等学校','普通科','','') );
        fputcsv( $fp, array('0001','職歴','','2010','04','株式会社〇〇','営業部','営業担当','新卒入社') );
        fputcsv( $fp, array('0001','資格','2005-07-20','','','普通自動車第一種運転免許','','','') );
        fputcsv( $fp, array_merge( array('# 種別は「学歴」「職歴」「資格」のいずれか。この行を削除してください。'), array_fill(0, count($cols)-1, '') ) );
        fclose( $fp );
    }

    private static function output_dependent_template() {
        $cols = self::columns_dependent();
        $fp   = self::open_csv( '③扶養者インポートひな形.csv' );
        fputcsv( $fp, array_values( $cols ) );
        fputcsv( $fp, array('0001','山田 花子','ヤマダ ハナコ','配偶者','1987-08-15','') );
        fputcsv( $fp, array('0001','山田 一郎','ヤマダ イチロウ','子','2015-03-20','') );
        fputcsv( $fp, array_merge( array('# 1社員につき複数行登録可。この行を削除してください。'), array_fill(0, count($cols)-1, '') ) );
        fclose( $fp );
    }

    // =====================================================
    //  AJAXディスパッチャ
    // =====================================================

    public static function ajax_import() {
        check_ajax_referer( 'emp_import_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_custom_plugins' ) ) {
            wp_send_json_error( array( 'message' => '権限がありません' ) );
        }

        $csv_type = isset( $_POST['csv_type'] ) ? sanitize_text_field( wp_unslash( $_POST['csv_type'] ) ) : 'basic';
        $rows     = isset( $_POST['rows'] )      ? $_POST['rows']      : array();
        $dup_mode = isset( $_POST['dup_mode'] )  ? sanitize_text_field( wp_unslash( $_POST['dup_mode'] ) ) : 'skip';

        if ( empty( $rows ) || ! is_array( $rows ) ) {
            wp_send_json_error( array( 'message' => 'インポートデータがありません' ) );
        }

        // デバッグ：受信データをログに記録
        error_log( '[EMP_Import] csv_type=' . $csv_type . ' rows_count=' . count($rows) );
        if ( ! empty($rows) ) {
            error_log( '[EMP_Import] first_row=' . json_encode( array_values($rows)[0] ) );
        }

        switch ( $csv_type ) {
            case 'career':    $result = self::import_career( $rows );          break;
            case 'dependent': $result = self::import_dependent( $rows );       break;
            default:          $result = self::import_basic( $rows, $dup_mode ); break;
        }

        wp_send_json_success( $result );
    }

    // =====================================================
    //  ① 基本情報インポート
    // =====================================================

    private static function import_basic( $rows, $dup_mode ) {
        global $wpdb;
        $col_keys     = array_keys( self::columns_basic() );
        $master_cache = self::build_master_cache();
        $success = $skipped = $updated = 0;
        $errors  = array();

        foreach ( $rows as $idx => $row ) {
            if ( ! is_array( $row ) || self::is_skip_row( $row ) ) continue;
            $line = $idx + 2;
            $d    = self::map_row( $row, $col_keys );

            if ( empty( $d['employee_code'] ) ) { $errors[] = $line.'行目：社員コードが空です'; continue; }
            if ( empty( $d['name'] ) )           { $errors[] = $line.'行目：氏名が空です'; continue; }

            $affil_id = self::resolve_master( $master_cache['affiliation'], $d['affiliation_name'], $line, $errors, '所属' );
            $dept_id  = self::resolve_master( $master_cache['department'],  $d['department_name'],  $line, $errors, '部署' );
            $pos_id   = self::resolve_master( $master_cache['position'],    $d['position_name'],    $line, $errors, '役職' );
            $jt_id    = self::resolve_master( $master_cache['job_type'],    $d['job_type_name'],    $line, $errors, '職種' );

            $existing_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}emp_master WHERE employee_code=%s", $d['employee_code']
            ) );
            if ( $existing_id && $dup_mode === 'skip' ) { $skipped++; continue; }

            $emp = array(
                'employee_code'      => sanitize_text_field( $d['employee_code'] ),
                'name'               => sanitize_text_field( $d['name'] ),
                'name_kana'          => sanitize_text_field( $d['name_kana'] ),
                'gender'             => sanitize_text_field( $d['gender'] ),
                'birthdate'          => self::parse_date( $d['birthdate'] ),
                'blood_type'         => sanitize_text_field( $d['blood_type'] ),
                'hire_date'          => self::parse_date( $d['hire_date'] ),
                'is_active'          => $d['is_active'] === '' ? 1 : (int) $d['is_active'],
                'affiliation_id'     => $affil_id,
                'department_id'      => $dept_id,
                'position_id'        => $pos_id,
                'job_type_id'        => $jt_id,
                'crew_code'          => sanitize_text_field( $d['crew_code'] ),
                'employment_type'    => sanitize_text_field( $d['employment_type'] ) ?: null,
                'weekly_work_days'   => $d['weekly_work_days'] !== '' ? (int) $d['weekly_work_days'] : null,
                'zip'                => sanitize_text_field( $d['zip'] ),
                'address'            => sanitize_text_field( $d['address'] ),
                'tel_home'           => sanitize_text_field( $d['tel_home'] ),
                'tel_mobile'         => sanitize_text_field( $d['tel_mobile'] ),
                'tel_company'        => sanitize_text_field( $d['tel_company'] ),
                'emergency_name'     => sanitize_text_field( $d['emergency_name'] ),
                'emergency_tel'      => sanitize_text_field( $d['emergency_tel'] ),
                'emergency_relation' => sanitize_text_field( $d['emergency_relation'] ),
                'memo'               => sanitize_textarea_field( $d['memo'] ),
                'updated_at'         => current_time('mysql'),
            );

            if ( $existing_id ) {
                $wpdb->update( $wpdb->prefix.'emp_master', $emp, array('id'=>$existing_id) );
                $emp_id = $existing_id; $updated++;
            } else {
                $emp['created_at'] = current_time('mysql');
                $wpdb->insert( $wpdb->prefix.'emp_master', $emp );
                $emp_id = $wpdb->insert_id; $success++;
            }

            // 保険 upsert
            $ins = array(
                'health_no'       => sanitize_text_field( $d['health_no'] ),
                'health_date'     => self::parse_date( $d['health_date'] ),
                'pension_no'      => sanitize_text_field( $d['pension_no'] ),
                'pension_date'    => self::parse_date( $d['pension_date'] ),
                'employment_no'   => sanitize_text_field( $d['employment_no'] ),
                'employment_date' => self::parse_date( $d['employment_date'] ),
                'accident_no'     => sanitize_text_field( $d['accident_no'] ),
                'accident_date'   => self::parse_date( $d['accident_date'] ),
                'updated_at'      => current_time('mysql'),
            );
            $ins_id = $wpdb->get_var( $wpdb->prepare("SELECT id FROM {$wpdb->prefix}emp_insurance WHERE employee_id=%d",$emp_id) );
            if ($ins_id) { $wpdb->update($wpdb->prefix.'emp_insurance',$ins,array('employee_id'=>$emp_id)); }
            else { $ins['employee_id']=$emp_id; $ins['created_at']=current_time('mysql'); $wpdb->insert($wpdb->prefix.'emp_insurance',$ins); }

            // 退職 upsert
            if ( ! empty( $d['retirement_date'] ) ) {
                $ret = array('retirement_date'=>self::parse_date($d['retirement_date']),'updated_at'=>current_time('mysql'));
                $ret_id = $wpdb->get_var( $wpdb->prepare("SELECT id FROM {$wpdb->prefix}emp_retirement WHERE employee_id=%d",$emp_id) );
                if ($ret_id) { $wpdb->update($wpdb->prefix.'emp_retirement',$ret,array('employee_id'=>$emp_id)); }
                else { $ret['employee_id']=$emp_id; $ret['created_at']=current_time('mysql'); $wpdb->insert($wpdb->prefix.'emp_retirement',$ret); }
            }
        }
        return compact('success','updated','skipped','errors');
    }

    // =====================================================
    //  ② 経歴・資格インポート
    // =====================================================

    private static function import_career( $rows ) {
        global $wpdb;
        $col_keys = array_keys( self::columns_career() );
        $success  = 0;
        $errors   = array();

        foreach ( $rows as $idx => $row ) {
            if ( ! is_array( $row ) || self::is_skip_row( $row ) ) continue;
            $line = $idx + 2;
            $d    = self::map_row( $row, $col_keys );

            if ( empty( $d['employee_code'] ) ) { $errors[] = $line.'行目：社員コードが空です'; continue; }

            $emp_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}emp_master WHERE employee_code=%s", $d['employee_code']
            ) );
            if ( ! $emp_id ) { $errors[] = $line.'行目：社員コード「'.esc_html($d['employee_code']).'」が存在しません'; continue; }

            $rtype = trim( $d['record_type'] );

            if ( $rtype === '学歴' ) {
                $wpdb->insert( $wpdb->prefix.'emp_education', array(
                    'employee_id'     => $emp_id,
                    'graduation_date' => self::parse_date($d['date']),
                    'school_name'     => sanitize_text_field($d['title']),
                    'department'      => sanitize_text_field($d['sub1']),
                    'sort_order'      => 0,
                    'created_at'      => current_time('mysql'),
                    'updated_at'      => current_time('mysql'),
                ) );
                $success++;
            } elseif ( $rtype === '職歴' ) {
                $wpdb->insert( $wpdb->prefix.'emp_career', array(
                    'employee_id'  => $emp_id,
                    'career_year'  => sanitize_text_field($d['career_year']),
                    'career_month' => sanitize_text_field($d['career_month']),
                    'company_name' => sanitize_text_field($d['title']),
                    'department'   => sanitize_text_field($d['sub1']),
                    'position'     => sanitize_text_field($d['sub2']),
                    'memo'         => sanitize_textarea_field($d['memo']),
                    'sort_order'   => 0,
                    'created_at'   => current_time('mysql'),
                    'updated_at'   => current_time('mysql'),
                ) );
                $success++;
            } elseif ( $rtype === '資格' ) {
                $wpdb->insert( $wpdb->prefix.'emp_qualification', array(
                    'employee_id'   => $emp_id,
                    'name'          => sanitize_text_field($d['title']),
                    'acquired_date' => self::parse_date($d['date']),
                    'sort_order'    => 0,
                    'created_at'    => current_time('mysql'),
                    'updated_at'    => current_time('mysql'),
                ) );
                $success++;
            } else {
                $errors[] = $line.'行目：種別「'.esc_html($rtype).'」は「学歴」「職歴」「資格」のいずれかにしてください';
            }
        }
        return compact('success','errors');
    }

    // =====================================================
    //  ③ 扶養者インポート
    // =====================================================

    private static function import_dependent( $rows ) {
        global $wpdb;
        $col_keys = array_keys( self::columns_dependent() );
        $success  = 0;
        $errors   = array();

        foreach ( $rows as $idx => $row ) {
            if ( ! is_array( $row ) || self::is_skip_row( $row ) ) continue;
            $line = $idx + 2;
            $d    = self::map_row( $row, $col_keys );

            if ( empty($d['employee_code']) ) { $errors[] = $line.'行目：社員コードが空です'; continue; }
            if ( empty($d['name']) )           { $errors[] = $line.'行目：氏名が空です'; continue; }

            $emp_id = $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}emp_master WHERE employee_code=%s", $d['employee_code']
            ) );
            if ( ! $emp_id ) { $errors[] = $line.'行目：社員コード「'.esc_html($d['employee_code']).'」が存在しません'; continue; }

            $wpdb->insert( $wpdb->prefix.'emp_dependent', array(
                'employee_id' => $emp_id,
                'name'        => sanitize_text_field($d['name']),
                'name_kana'   => sanitize_text_field($d['name_kana']),
                'relation'    => sanitize_text_field($d['relation']),
                'birthdate'   => self::parse_date($d['birthdate']),
                'memo'        => sanitize_textarea_field($d['memo']),
                'sort_order'  => 0,
                'created_at'  => current_time('mysql'),
                'updated_at'  => current_time('mysql'),
            ) );
            $success++;
        }
        return compact('success','errors');
    }

    // =====================================================
    //  共通ヘルパー
    // =====================================================

    private static function build_master_cache() {
        global $wpdb;
        $cache = array('affiliation'=>array(),'department'=>array(),'position'=>array(),'job_type'=>array());
        foreach (array_keys($cache) as $type) {
            $rows = $wpdb->get_results("SELECT id,name FROM {$wpdb->prefix}mst_{$type} WHERE is_active=1");
            foreach ($rows as $r) { $cache[$type][mb_strtolower($r->name)] = $r->id; }
        }
        return $cache;
    }

    private static function map_row( $row, $col_keys ) {
        $d = array();
        foreach ($col_keys as $i => $key) { $d[$key] = trim($row[$i] ?? ''); }
        return $d;
    }

    private static function is_skip_row( $row ) {
        $first = trim($row[0] ?? '');
        if (strpos($first,'#')===0) return true;
        if (empty(array_filter($row, function($v){return trim($v)!=='';}))) return true;
        return false;
    }

    private static function resolve_master( $cache, $name, $line, &$errors, $label ) {
        if (empty($name)) return null;
        $key = mb_strtolower(trim($name));
        if (isset($cache[$key])) return $cache[$key];
        $errors[] = $line.'行目：'.$label.'「'.esc_html($name).'」がマスタに存在しません（空で登録）';
        return null;
    }

    private static function parse_date( $val ) {
        if ( empty( $val ) ) return null;
        // / を - に統一し、YYYY-M-D 形式もゼロ埋めして YYYY-MM-DD に正規化
        $val = str_replace( '/', '-', trim( $val ) );
        if ( preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $val, $m ) ) {
            return sprintf( '%04d-%02d-%02d', $m[1], $m[2], $m[3] );
        }
        return null;
    }
}
