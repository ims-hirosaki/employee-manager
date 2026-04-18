<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class EMP_Master
 * 所属・部署・役職・職種 の CRUD を担当する
 */
class EMP_Master {

    /**
     * マスタ種別 → テーブル名のマッピング
     */
    private static $table_map = array(
        'affiliation' => 'mst_affiliation',
        'department'  => 'mst_department',
        'position'    => 'mst_position',
        'job_type'    => 'mst_job_type',
    );

    /**
     * テーブル名を返す（存在しない種別は例外）
     */
    private static function table( $type ) {
        global $wpdb;
        if ( ! isset( self::$table_map[ $type ] ) ) {
            return null;
        }
        return $wpdb->prefix . self::$table_map[ $type ];
    }

    // ===== READ =====

    /**
     * 一覧取得（有効なもの・表示順ソート）
     *
     * @param  string $type   affiliation | department | position | job_type
     * @return array
     */
    public static function get_list( $type ) {
        global $wpdb;
        $table = self::table( $type );
        if ( ! $table ) return array();

        return $wpdb->get_results(
            "SELECT * FROM `{$table}` WHERE is_active = 1 ORDER BY sort_order ASC, id ASC"
        );
    }

    /**
     * 全件取得（無効含む・管理画面用）
     *
     * @param  string $type
     * @return array
     */
    public static function get_all( $type ) {
        global $wpdb;
        $table = self::table( $type );
        if ( ! $table ) return array();

        return $wpdb->get_results(
            "SELECT * FROM `{$table}` ORDER BY sort_order ASC, id ASC"
        );
    }

    /**
     * 1件取得
     *
     * @param  string $type
     * @param  int    $id
     * @return object|null
     */
    public static function get_by_id( $type, $id ) {
        global $wpdb;
        $table = self::table( $type );
        if ( ! $table ) return null;

        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id )
        );
    }

    // ===== CREATE =====

    /**
     * 新規登録
     *
     * @param  string $type
     * @param  array  $data  { name, sort_order, is_active }
     * @return int|false  挿入されたID、失敗時はfalse
     */
    public static function insert( $type, $data ) {
        global $wpdb;
        $table = self::table( $type );
        if ( ! $table ) return false;

        $result = $wpdb->insert(
            $table,
            array(
                'name'       => sanitize_text_field( $data['name'] ),
                'sort_order' => isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0,
                'is_active'  => isset( $data['is_active'] )  ? (int) $data['is_active']  : 1,
            ),
            array( '%s', '%d', '%d' )
        );

        return $result ? $wpdb->insert_id : false;
    }

    // ===== UPDATE =====

    /**
     * 更新
     *
     * @param  string $type
     * @param  int    $id
     * @param  array  $data  更新するフィールドのみ渡せばOK
     * @return bool
     */
    public static function update( $type, $id, $data ) {
        global $wpdb;
        $table = self::table( $type );
        if ( ! $table ) return false;

        $fields  = array();
        $formats = array();

        if ( isset( $data['name'] ) ) {
            $fields['name']  = sanitize_text_field( $data['name'] );
            $formats[]       = '%s';
        }
        if ( isset( $data['sort_order'] ) ) {
            $fields['sort_order'] = (int) $data['sort_order'];
            $formats[]            = '%d';
        }
        if ( isset( $data['is_active'] ) ) {
            $fields['is_active'] = (int) $data['is_active'];
            $formats[]           = '%d';
        }

        if ( empty( $fields ) ) return false;

        $result = $wpdb->update(
            $table,
            $fields,
            array( 'id' => (int) $id ),
            $formats,
            array( '%d' )
        );

        return $result !== false;
    }

    /**
     * 有効フラグのみ切り替え（トグル用）
     *
     * @param  string $type
     * @param  int    $id
     * @param  bool   $active
     * @return bool
     */
    public static function toggle_active( $type, $id, $active ) {
        return self::update( $type, $id, array( 'is_active' => $active ? 1 : 0 ) );
    }

    // ===== DELETE =====

    /**
     * 削除（物理削除）
     * ※社員マスタで使用中の場合は削除できない
     *
     * @param  string $type
     * @param  int    $id
     * @return true|WP_Error
     */
    public static function delete( $type, $id ) {
        global $wpdb;
        $table = self::table( $type );
        if ( ! $table ) return new WP_Error( 'invalid_type', '不正なマスタ種別です' );

        // 使用中チェック
        $col_map = array(
            'affiliation' => 'affiliation_id',
            'department'  => 'department_id',
            'position'    => 'position_id',
            'job_type'    => 'job_type_id',
        );
        $col = $col_map[ $type ] ?? null;
        if ( $col ) {
            $in_use = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}emp_master WHERE `{$col}` = %d",
                    $id
                )
            );
            if ( $in_use > 0 ) {
                return new WP_Error(
                    'in_use',
                    "この項目は {$in_use} 名の社員に使用されているため削除できません。先に社員情報を変更してください。"
                );
            }
        }

        $result = $wpdb->delete( $table, array( 'id' => (int) $id ), array( '%d' ) );
        return $result !== false ? true : new WP_Error( 'db_error', 'データベースエラーが発生しました' );
    }

    // ===== AJAX HANDLERS =====

    /**
     * AJAX: 一覧取得
     */
    public static function ajax_get_list() {
        check_ajax_referer( 'emp_master_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( -1 );

        $type = sanitize_key( $_POST['master_type'] ?? '' );
        $data = self::get_all( $type );
        wp_send_json_success( $data );
    }

    /**
     * AJAX: 新規登録
     */
    public static function ajax_insert() {
        check_ajax_referer( 'emp_master_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( -1 );

        $type   = sanitize_key( $_POST['master_type'] ?? '' );
        $name   = sanitize_text_field( $_POST['name'] ?? '' );

        if ( empty( $name ) ) {
            wp_send_json_error( array( 'message' => '名称は必須です' ) );
        }

        $id = self::insert( $type, array(
            'name'       => $name,
            'sort_order' => (int) ( $_POST['sort_order'] ?? 0 ),
            'is_active'  => 1,
        ) );

        if ( $id ) {
            wp_send_json_success( array( 'id' => $id, 'message' => '登録しました' ) );
        } else {
            wp_send_json_error( array( 'message' => '登録に失敗しました' ) );
        }
    }

    /**
     * AJAX: 更新
     */
    public static function ajax_update() {
        check_ajax_referer( 'emp_master_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( -1 );

        $type = sanitize_key( $_POST['master_type'] ?? '' );
        $id   = (int) ( $_POST['id'] ?? 0 );
        $data = array();

        if ( isset( $_POST['name'] ) )       $data['name']       = sanitize_text_field( $_POST['name'] );
        if ( isset( $_POST['sort_order'] ) ) $data['sort_order'] = (int) $_POST['sort_order'];
        if ( isset( $_POST['is_active'] ) )  $data['is_active']  = (int) $_POST['is_active'];

        $result = self::update( $type, $id, $data );
        if ( $result ) {
            wp_send_json_success( array( 'message' => '更新しました' ) );
        } else {
            wp_send_json_error( array( 'message' => '更新に失敗しました' ) );
        }
    }

    /**
     * AJAX: 削除
     */
    public static function ajax_delete() {
        check_ajax_referer( 'emp_master_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die( -1 );

        $type   = sanitize_key( $_POST['master_type'] ?? '' );
        $id     = (int) ( $_POST['id'] ?? 0 );
        $result = self::delete( $type, $id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        } else {
            wp_send_json_success( array( 'message' => '削除しました' ) );
        }
    }
}
