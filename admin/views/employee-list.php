<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// 編集ページからのリダイレクト後のメッセージ表示
$notice = '';
if ( ! empty( $_GET['emp_saved'] ) ) {
    $notice = '<div class="notice notice-success is-dismissible"><p>社員情報を保存しました。</p></div>';
}
?>
<div class="wrap emp-wrap" id="emp-list-page">
    <h1 class="wp-heading-inline">社員一覧</h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=employee-manager-new' ) ); ?>" class="page-title-action emp-btn-primary">
        ＋ 新規社員登録
    </a>
    <?php echo $notice; // phpcs:ignore ?>

    <!-- 統計カード -->
<div class="emp-stats-row" id="empStatsRow">
    <div class="emp-stat-card emp-stat-active">
        <div class="emp-stat-label">総社員数</div>
        <div class="emp-stat-value" id="statActiveTotal">—</div>
    </div>
</div>

<!-- 統計カード：所属別在籍人数（JS で動的生成） -->
<div class="emp-affil-stats-row" id="empAffilStatsRow">
    <!-- 読み込み中プレースホルダー -->
    <div class="emp-affil-stats-placeholder">読み込み中...</div>
</div>

    <!-- 検索・フィルタツールバー -->
    <div class="emp-toolbar">
        <div class="emp-toolbar-left">
            <div class="emp-search-wrap">
                <span class="emp-search-icon dashicons dashicons-search"></span>
                <input type="text" id="empSearch" class="emp-search-input"
                       placeholder="氏名・社員コード・フリガナで検索...">
            </div>
            <select id="empFilterAffil" class="emp-filter-select">
                <option value="">すべての所属</option>
            </select>
            <select id="empFilterDept" class="emp-filter-select">
                <option value="">すべての部署</option>
            </select>
            <select id="empFilterStatus" class="emp-filter-select">
                <option value="">在籍状況：すべて</option>
                <option value="1">在籍中のみ</option>
                <option value="0">退職のみ</option>
            </select>
        </div>
        <div class="emp-toolbar-right">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=employee-manager-csv' ) ); ?>"
               class="emp-btn emp-btn-success">
                <span class="dashicons dashicons-download"></span> CSV出力
            </a>
        </div>
    </div>

    <!-- テーブル -->
    <div class="emp-table-wrap">
        <div class="emp-table-header">
            <span class="emp-table-title">社員リスト</span>
            <span class="emp-table-info" id="empTableInfo">読み込み中...</span>
        </div>
        <table class="emp-table widefat" id="empTable">
            <thead>
                <tr>
                    <th><input type="checkbox" id="empCheckAll"></th>
                    <th>ID</th>
                    <th class="sortable" data-col="employee_code">社員コード <span class="emp-sort-icon">⇅</span></th>
                    <th class="sortable" data-col="name">氏名 <span class="emp-sort-icon">⇅</span></th>
                    <th>所属</th>
                    <th>部署</th>
                    <th>役職</th>
                    <th class="sortable" data-col="hire_date">入社日 <span class="emp-sort-icon">⇅</span></th>
                    <th>在籍状況</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody id="empTbody">
                <tr><td colspan="10" class="emp-loading">読み込み中...</td></tr>
            </tbody>
        </table>
        <div class="emp-table-footer">
            <div class="emp-page-info">
                全 <strong id="empTotal">0</strong> 件中
                <strong id="empRange">—</strong> 件表示
                <select id="empPerPage" class="emp-per-page">
                    <option value="20">20件/ページ</option>
                    <option value="50">50件/ページ</option>
                    <option value="100">100件/ページ</option>
                </select>
            </div>
            <div id="empPagination" class="emp-pagination"></div>
        </div>
    </div>

    <!-- 一括操作バー -->
    <div class="emp-bulk-bar" id="empBulkBar" style="display:none;">
        <span class="emp-bulk-count"><strong id="empBulkCount">0</strong> 件選択中</span>
        <button class="emp-bulk-btn" id="empBulkActive">一括：在籍に変更</button>
        <button class="emp-bulk-btn" id="empBulkRetire">一括：退職に変更</button>
        <button class="emp-bulk-close" id="empBulkClose">✕</button>
    </div>
</div>
