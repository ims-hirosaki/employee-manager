<?php
/**
 * プラグイン削除時に実行されるファイル
 * WordPress管理画面でプラグインを「削除」したときのみ呼ばれる。
 * 「無効化」では呼ばれない。
 */

// WordPressの外から直接呼ばれていないか確認
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// クラスを読み込む
require_once plugin_dir_path( __FILE__ ) . 'includes/class-db-install.php';

// 全テーブルを削除
EMP_DB_Install::drop_tables();

// プラグイン設定をすべて削除
delete_option( 'emp_db_version' );
