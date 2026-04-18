<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class EMP_DB_Install
 * プラグイン有効化時にテーブルを作成し、無効化時には何もしない。
 * 削除時（uninstall.php）でテーブルを DROP する。
 */
class EMP_DB_Install {

    /**
     * プラグイン有効化時に呼ばれる
     */
    public static function activate() {
        self::create_tables();
        update_option( 'emp_db_version', EMP_VERSION );
    }

    /**
     * プラグイン無効化時に呼ばれる（テーブルは残す）
     */
    public static function deactivate() {
        // 意図的に何もしない
    }

    /**
     * 全テーブルを作成する（既に存在する場合はスキップ）
     */
    public static function create_tables() {
        global $wpdb;

        $charset = $wpdb->get_charset_collate();

        // dbDelta を使うために必要
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sqls = array();

        // =====================================================
        // マスタテーブル群
        // =====================================================

        // 所属マスタ
        $sqls[] = "CREATE TABLE {$wpdb->prefix}mst_affiliation (
            id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
            name        VARCHAR(100)    NOT NULL COMMENT '所属名',
            sort_order  SMALLINT        NOT NULL DEFAULT 0 COMMENT '表示順',
            is_active   TINYINT(1)      NOT NULL DEFAULT 1 COMMENT '有効フラグ 1=有効 0=無効',
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;";

        // 部署マスタ
        $sqls[] = "CREATE TABLE {$wpdb->prefix}mst_department (
            id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
            name        VARCHAR(100)    NOT NULL COMMENT '部署名',
            sort_order  SMALLINT        NOT NULL DEFAULT 0 COMMENT '表示順',
            is_active   TINYINT(1)      NOT NULL DEFAULT 1 COMMENT '有効フラグ',
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;";

        // 役職マスタ
        $sqls[] = "CREATE TABLE {$wpdb->prefix}mst_position (
            id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
            name        VARCHAR(100)    NOT NULL COMMENT '役職名',
            sort_order  SMALLINT        NOT NULL DEFAULT 0 COMMENT '表示順',
            is_active   TINYINT(1)      NOT NULL DEFAULT 1 COMMENT '有効フラグ',
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;";

        // 職種マスタ
        $sqls[] = "CREATE TABLE {$wpdb->prefix}mst_job_type (
            id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
            name        VARCHAR(100)    NOT NULL COMMENT '職種名',
            sort_order  SMALLINT        NOT NULL DEFAULT 0 COMMENT '表示順',
            is_active   TINYINT(1)      NOT NULL DEFAULT 1 COMMENT '有効フラグ',
            created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;";

        // =====================================================
        // 社員マスタ（メインテーブル）
        // =====================================================
        $sqls[] = "CREATE TABLE {$wpdb->prefix}emp_master (
            id                      INT UNSIGNED    NOT NULL AUTO_INCREMENT  COMMENT '内部ID（サロゲートキー）',
            employee_code           VARCHAR(20)     NOT NULL                 COMMENT '社員コード（ユニーク・人が入力）',
            affiliation_id          INT UNSIGNED        NULL DEFAULT NULL     COMMENT 'FK: mst_affiliation.id',
            department_id           INT UNSIGNED        NULL DEFAULT NULL     COMMENT 'FK: mst_department.id',
            position_id             INT UNSIGNED        NULL DEFAULT NULL     COMMENT 'FK: mst_position.id',
            job_type_id             INT UNSIGNED        NULL DEFAULT NULL     COMMENT 'FK: mst_job_type.id',
            employment_type         VARCHAR(20)         NULL DEFAULT NULL     COMMENT '雇用区分（正社員/契約社員/パート・アルバイト）',
            weekly_work_days        TINYINT             NULL DEFAULT NULL     COMMENT '週勤務日数（1〜6）',
            crew_code               VARCHAR(20)         NULL DEFAULT NULL     COMMENT '乗組員コード（NULLは乗組員ではない）',
            name                    VARCHAR(100)    NOT NULL                 COMMENT '氏名',
            name_kana               VARCHAR(100)        NULL DEFAULT NULL     COMMENT 'フリガナ',
            gender                  VARCHAR(10)         NULL DEFAULT NULL     COMMENT '性別',
            birthdate               DATE                NULL DEFAULT NULL     COMMENT '生年月日',
            blood_type              VARCHAR(5)          NULL DEFAULT NULL     COMMENT '血液型',
            my_number               VARCHAR(255)        NULL DEFAULT NULL     COMMENT '個人番号（暗号化推奨）',
            hire_date               DATE                NULL DEFAULT NULL     COMMENT '入社日',
            zip                     VARCHAR(10)         NULL DEFAULT NULL     COMMENT '郵便番号',
            address                 VARCHAR(255)        NULL DEFAULT NULL     COMMENT '住所',
            tel_home                VARCHAR(20)         NULL DEFAULT NULL     COMMENT '自宅電話',
            tel_mobile              VARCHAR(20)         NULL DEFAULT NULL     COMMENT '携帯電話',
            tel_company             VARCHAR(20)         NULL DEFAULT NULL     COMMENT '会社携帯',
            emergency_name          VARCHAR(100)        NULL DEFAULT NULL     COMMENT '緊急連絡先氏名',
            emergency_tel           VARCHAR(20)         NULL DEFAULT NULL     COMMENT '緊急連絡先電話',
            emergency_relation      VARCHAR(50)         NULL DEFAULT NULL     COMMENT '緊急連絡先続柄',
            memo                    TEXT                NULL DEFAULT NULL     COMMENT '総合備考',
            is_active               TINYINT(1)      NOT NULL DEFAULT 1       COMMENT '在籍フラグ 1=在籍 0=退職',
            created_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at              DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE  KEY  uq_employee_code (employee_code),
            KEY          idx_affiliation  (affiliation_id),
            KEY          idx_department   (department_id),
            KEY          idx_is_active    (is_active)
        ) $charset;";

        // =====================================================
        // 加入保険テーブル（1対1）
        // =====================================================
        $sqls[] = "CREATE TABLE {$wpdb->prefix}emp_insurance (
            id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
            employee_id     INT UNSIGNED    NOT NULL                COMMENT 'FK: emp_master.id',
            health_no       VARCHAR(50)         NULL DEFAULT NULL   COMMENT '健康保険番号',
            health_date     DATE                NULL DEFAULT NULL   COMMENT '健康保険取得日',
            pension_no      VARCHAR(50)         NULL DEFAULT NULL   COMMENT '厚生年金番号',
            pension_date    DATE                NULL DEFAULT NULL   COMMENT '厚生年金取得日',
            employment_no   VARCHAR(50)         NULL DEFAULT NULL   COMMENT '雇用保険番号',
            employment_date DATE                NULL DEFAULT NULL   COMMENT '雇用保険取得日',
            accident_no     VARCHAR(50)         NULL DEFAULT NULL   COMMENT '労災保険番号',
            accident_date   DATE                NULL DEFAULT NULL   COMMENT '労災保険取得日',
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE  KEY uq_employee (employee_id)
        ) $charset;";

        // =====================================================
        // 退職情報テーブル（1対1）
        // =====================================================
        $sqls[] = "CREATE TABLE {$wpdb->prefix}emp_retirement (
            id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
            employee_id     INT UNSIGNED    NOT NULL                COMMENT 'FK: emp_master.id',
            retirement_date DATE                NULL DEFAULT NULL   COMMENT '退職日',
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE  KEY uq_employee (employee_id)
        ) $charset;";

        // =====================================================
        // 学歴テーブル（1対多）
        // =====================================================
        $sqls[] = "CREATE TABLE {$wpdb->prefix}emp_education (
            id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
            employee_id     INT UNSIGNED    NOT NULL                COMMENT 'FK: emp_master.id',
            sort_order      SMALLINT        NOT NULL DEFAULT 0      COMMENT '表示順（連番）',
            graduation_date DATE                NULL DEFAULT NULL   COMMENT '卒業年月日',
            school_name     VARCHAR(200)        NULL DEFAULT NULL   COMMENT '学校名',
            department      VARCHAR(200)        NULL DEFAULT NULL   COMMENT '学科・専攻',
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_employee (employee_id)
        ) $charset;";

        // =====================================================
        // 職歴テーブル（1対多）
        // =====================================================
        $sqls[] = "CREATE TABLE {$wpdb->prefix}emp_career (
            id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
            employee_id     INT UNSIGNED    NOT NULL                COMMENT 'FK: emp_master.id',
            sort_order      SMALLINT        NOT NULL DEFAULT 0      COMMENT '表示順',
            career_year     CHAR(4)             NULL DEFAULT NULL   COMMENT '年（YYYY）',
            career_month    CHAR(2)             NULL DEFAULT NULL   COMMENT '月（MM）',
            company_name    VARCHAR(200)        NULL DEFAULT NULL   COMMENT '会社名',
            department      VARCHAR(200)        NULL DEFAULT NULL   COMMENT '部署名',
            position        VARCHAR(200)        NULL DEFAULT NULL   COMMENT '役職名',
            memo            TEXT                NULL DEFAULT NULL   COMMENT '備考',
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_employee (employee_id)
        ) $charset;";

        // =====================================================
        // 免許・資格テーブル（1対多）
        // =====================================================
        $sqls[] = "CREATE TABLE {$wpdb->prefix}emp_qualification (
            id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
            employee_id     INT UNSIGNED    NOT NULL                COMMENT 'FK: emp_master.id',
            sort_order      SMALLINT        NOT NULL DEFAULT 0      COMMENT '表示順',
            name            VARCHAR(200)        NULL DEFAULT NULL   COMMENT '資格・免許名',
            acquired_date   DATE                NULL DEFAULT NULL   COMMENT '取得日',
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_employee (employee_id)
        ) $charset;";

        // =====================================================
        // 扶養者テーブル（1対多）
        // =====================================================
        $sqls[] = "CREATE TABLE {$wpdb->prefix}emp_dependent (
            id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
            employee_id     INT UNSIGNED    NOT NULL                COMMENT 'FK: emp_master.id',
            sort_order      SMALLINT        NOT NULL DEFAULT 0      COMMENT '表示順',
            name            VARCHAR(100)        NULL DEFAULT NULL   COMMENT '扶養者氏名',
            name_kana       VARCHAR(100)        NULL DEFAULT NULL   COMMENT '扶養者フリガナ',
            relation        VARCHAR(50)         NULL DEFAULT NULL   COMMENT '続柄',
            birthdate       DATE                NULL DEFAULT NULL   COMMENT '生年月日',
            memo            TEXT                NULL DEFAULT NULL   COMMENT '備考',
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_employee (employee_id)
        ) $charset;";

        // =====================================================
        // CSV出力テンプレートテーブル（ユーザーごとに保存）
        // =====================================================
        $sqls[] = "CREATE TABLE {$wpdb->prefix}emp_csv_template (
            id              INT UNSIGNED    NOT NULL AUTO_INCREMENT,
            wp_user_id      BIGINT UNSIGNED NOT NULL                COMMENT 'WordPressユーザーID',
            name            VARCHAR(100)    NOT NULL                COMMENT 'テンプレート名',
            column_keys     TEXT            NOT NULL                COMMENT '選択列キーのJSON配列',
            sort_order      SMALLINT        NOT NULL DEFAULT 0      COMMENT '表示順',
            created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (wp_user_id)
        ) $charset;";

        // 全テーブルを作成
        foreach ( $sqls as $sql ) {
            dbDelta( $sql );
        }
    }

    /**
     * 全テーブルを削除する（uninstall.phpから呼ぶ）
     */
    public static function drop_tables() {
        global $wpdb;

        $tables = array(
            "{$wpdb->prefix}emp_csv_template",
            "{$wpdb->prefix}emp_dependent",
            "{$wpdb->prefix}emp_qualification",
            "{$wpdb->prefix}emp_career",
            "{$wpdb->prefix}emp_education",
            "{$wpdb->prefix}emp_retirement",
            "{$wpdb->prefix}emp_insurance",
            "{$wpdb->prefix}emp_master",
            "{$wpdb->prefix}mst_job_type",
            "{$wpdb->prefix}mst_position",
            "{$wpdb->prefix}mst_department",
            "{$wpdb->prefix}mst_affiliation",
        );

        foreach ( $tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore
        }

        delete_option( 'emp_db_version' );
    }
}