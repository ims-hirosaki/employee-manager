<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$master_types = array(
    'affiliation' => '所属',
    'department'  => '部署',
    'position'    => '役職',
    'job_type'    => '職種',
);
?>
<div class="wrap emp-wrap" id="emp-master-page">
    <h1>マスタ管理</h1>

    <!-- タブ -->
    <div class="emp-tabs">
        <?php foreach ( $master_types as $type => $label ) : ?>
        <button class="emp-tab <?php echo $type === 'affiliation' ? 'active' : ''; ?>"
                data-type="<?php echo esc_attr( $type ); ?>">
            <?php echo esc_html( $label ); ?>
        </button>
        <?php endforeach; ?>
    </div>

    <?php foreach ( $master_types as $type => $label ) : ?>
    <div class="emp-tab-panel <?php echo $type === 'affiliation' ? 'active' : ''; ?>"
         data-panel="<?php echo esc_attr( $type ); ?>">

        <!-- 統計 -->
        <div class="emp-master-stats" id="stats-<?php echo esc_attr( $type ); ?>">
            <span class="emp-stat-badge emp-badge-total">全件：<strong class="js-total">0</strong></span>
            <span class="emp-stat-badge emp-badge-active">有効：<strong class="js-active">0</strong></span>
            <span class="emp-stat-badge emp-badge-inactive">無効：<strong class="js-inactive">0</strong></span>
        </div>

        <!-- 検索 -->
        <div class="emp-master-toolbar">
            <input type="text" class="emp-search-input master-search"
                   data-type="<?php echo esc_attr( $type ); ?>"
                   placeholder="<?php echo esc_attr( $label ); ?>名で絞り込み...">
            <button class="emp-btn emp-btn-primary master-add-btn"
                    data-type="<?php echo esc_attr( $type ); ?>">
                ＋ 新規追加
            </button>
        </div>

        <!-- テーブル -->
        <table class="emp-table widefat">
            <thead>
                <tr>
                    <th style="width:60px;">ID</th>
                    <th><?php echo esc_html( $label ); ?>名</th>
                    <th style="width:80px;">表示順</th>
                    <th style="width:90px;">状態</th>
                    <th style="width:120px;">操作</th>
                </tr>
            </thead>
            <tbody id="masterTbody-<?php echo esc_attr( $type ); ?>">
                <tr><td colspan="5" class="emp-loading">読み込み中...</td></tr>
            </tbody>
        </table>

        <!-- 新規追加フォーム（インライン） -->
        <div class="emp-master-add-form" id="addForm-<?php echo esc_attr( $type ); ?>" style="display:none;">
            <div class="emp-master-add-inner">
                <input type="text" class="emp-input master-new-name"
                       placeholder="<?php echo esc_attr( $label ); ?>名を入力" maxlength="100">
                <input type="number" class="emp-input master-new-order"
                       placeholder="表示順" min="0" value="0" style="width:80px;">
                <button class="emp-btn emp-btn-primary master-save-new"
                        data-type="<?php echo esc_attr( $type ); ?>">登録</button>
                <button class="emp-btn emp-btn-secondary master-cancel-new"
                        data-type="<?php echo esc_attr( $type ); ?>">キャンセル</button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
