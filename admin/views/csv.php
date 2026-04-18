<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$col_groups = array(
    'basic'     => array( 'label' => '基本情報', 'keys' => array( 'id','employee_code','name','name_kana','gender','birthdate','blood_type','hire_date','is_active' ) ),
    'org'       => array( 'label' => '所属・役職', 'keys' => array( 'affiliation_name','department_name','position_name','job_type_name','crew_code' ) ),
    'contact'   => array( 'label' => '連絡先', 'keys' => array( 'zip','address','tel_home','tel_mobile','tel_company','emergency_name','emergency_tel','emergency_relation','memo' ) ),
    'insurance' => array( 'label' => '加入保険', 'keys' => array( 'health_no','health_date','pension_no','pension_date','employment_no','employment_date','accident_no','accident_date' ) ),
);

// 列定義のラベルマップ
$col_labels = array();
foreach ( EMP_CSV_Export::column_definitions() as $def ) {
    $col_labels[ $def['key'] ] = $def['label'];
}

// 保存済みテンプレート
$templates = EMP_CSV_Export::get_templates();
?>
<div class="wrap emp-wrap" id="emp-csv-page">
    <h1>CSV出力</h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=employee-manager' ) ); ?>"
       class="emp-back-link">← 社員一覧に戻る</a>

    <div class="emp-csv-layout">

        <!-- ===== 左：項目選択 ===== -->
        <div class="emp-csv-left">

            <!-- テンプレート -->
            <div class="emp-card">
                <div class="emp-card-head">
                    <div class="emp-card-title">STEP 0 ── 保存済みテンプレート</div>
                    <span class="emp-selected-badge" id="csvTplCount"><?php echo count( $templates ); ?> 件</span>
                    <button type="button" class="emp-btn emp-btn-success emp-btn-sm" id="csvSaveTpl">
                        ＋ 現在の選択を保存
                    </button>
                </div>
                <div class="emp-tpl-grid" id="csvTplGrid">
                    <?php if ( empty( $templates ) ) : ?>
                        <div class="emp-tpl-empty" id="csvTplEmpty">
                            テンプレートがありません。項目を選択して「現在の選択を保存」から登録できます。
                        </div>
                    <?php else : ?>
                        <?php foreach ( $templates as $tpl ) :
                            $keys  = json_decode( $tpl->column_keys, true );
                            $count = is_array( $keys ) ? count( $keys ) : 0;
                            $previews = array_slice( is_array( $keys ) ? $keys : array(), 0, 4 );
                        ?>
                        <div class="emp-tpl-card" data-tpl-id="<?php echo esc_attr( $tpl->id ); ?>"
                             data-tpl-keys="<?php echo esc_attr( $tpl->column_keys ); ?>">
                            <div class="emp-tpl-top">
                                <div class="emp-tpl-name"><?php echo esc_html( $tpl->name ); ?></div>
                                <div class="emp-tpl-actions">
                                    <button class="emp-tpl-icon-btn emp-tpl-rename" data-id="<?php echo esc_attr( $tpl->id ); ?>" title="名前変更">✏</button>
                                    <button class="emp-tpl-icon-btn emp-tpl-delete"  data-id="<?php echo esc_attr( $tpl->id ); ?>" title="削除">✕</button>
                                </div>
                            </div>
                            <div class="emp-tpl-meta">
                                <span class="emp-tpl-count"><?php echo esc_html( $count ); ?> 列</span>
                            </div>
                            <div class="emp-tpl-tags">
                                <?php foreach ( $previews as $k ) : ?>
                                    <span class="emp-tpl-tag"><?php echo esc_html( $col_labels[ $k ] ?? $k ); ?></span>
                                <?php endforeach; ?>
                                <?php if ( $count > 4 ) : ?>
                                    <span class="emp-tpl-tag">+<?php echo esc_html( $count - 4 ); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- プリセット -->
            <div class="emp-card">
                <div class="emp-card-head">
                    <div class="emp-card-title">STEP 1 ── プリセットから選ぶ</div>
                    <span class="emp-selected-badge" id="csvSelectedCount">0 項目</span>
                </div>
                <div class="emp-preset-row">
                    <button type="button" class="emp-preset-btn" data-preset="basic">基本情報のみ</button>
                    <button type="button" class="emp-preset-btn" data-preset="hr">人事用</button>
                    <button type="button" class="emp-preset-btn" data-preset="insurance">保険情報</button>
                    <button type="button" class="emp-preset-btn" data-preset="contact">連絡先</button>
                    <button type="button" class="emp-preset-btn" data-preset="all">すべて選択</button>
                    <button type="button" class="emp-preset-btn" data-preset="none">クリア</button>
                </div>
            </div>

            <!-- 項目選択 -->
            <div class="emp-card">
                <div class="emp-card-head">
                    <div class="emp-card-title">STEP 2 ── 出力項目を選択</div>
                </div>
                <?php foreach ( $col_groups as $group_key => $group ) : ?>
                <div class="emp-col-group">
                    <div class="emp-col-group-head">
                        <span class="emp-col-group-label"><?php echo esc_html( $group['label'] ); ?></span>
                        <button type="button" class="emp-group-toggle" data-group="<?php echo esc_attr( $group_key ); ?>">
                            一括切替
                        </button>
                    </div>
                    <div class="emp-col-items" data-group-el="<?php echo esc_attr( $group_key ); ?>">
                        <?php foreach ( $group['keys'] as $key ) : ?>
                        <label class="emp-col-item" data-key="<?php echo esc_attr( $key ); ?>">
                            <input type="checkbox" class="emp-col-check" value="<?php echo esc_attr( $key ); ?>">
                            <span class="emp-col-check-box"></span>
                            <span class="emp-col-item-label"><?php echo esc_html( $col_labels[ $key ] ?? $key ); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php if ( $group_key !== 'insurance' ) : ?><hr class="emp-divider"><?php endif; ?>
                <?php endforeach; ?>
            </div>

            <!-- 列順 -->
            <div class="emp-card">
                <div class="emp-card-head">
                    <div class="emp-card-title">STEP 3 ── 列の並び順を調整</div>
                </div>
                <p class="emp-hint" style="margin:0 0 .75rem;">ドラッグして並び替え、✕ で除外できます</p>
                <div id="csvOrderList" class="emp-order-list">
                    <div class="emp-order-empty">項目が選択されていません</div>
                </div>
            </div>

        </div><!-- /left -->

        <!-- ===== 右：設定・出力 ===== -->
        <div class="emp-csv-right">

            <!-- 絞り込み -->
            <div class="emp-card">
                <div class="emp-card-title">絞り込み条件</div>
                <div class="emp-card-body">
                    <div class="emp-field">
                        <label class="emp-label">在籍状況</label>
                        <select name="is_active" id="csvFilterStatus" class="emp-select">
                            <option value="">すべて</option>
                            <option value="1">在籍中のみ</option>
                            <option value="0">退職のみ</option>
                        </select>
                    </div>
                    <div class="emp-field">
                        <label class="emp-label">所属</label>
                        <select name="affiliation_id" id="csvFilterAffil" class="emp-select">
                            <option value="">すべての所属</option>
                        </select>
                    </div>
                    <div class="emp-field">
                        <label class="emp-label">部署</label>
                        <select name="department_id" id="csvFilterDept" class="emp-select">
                            <option value="">すべての部署</option>
                        </select>
                    </div>
                    <div class="emp-field">
                        <label class="emp-label">入社日（期間）</label>
                        <div class="emp-date-range">
                            <input type="date" id="csvDateFrom" class="emp-input">
                            <span>〜</span>
                            <input type="date" id="csvDateTo" class="emp-input">
                        </div>
                    </div>
                </div>
            </div>

            <!-- 出力設定 -->
            <div class="emp-card">
                <div class="emp-card-title">出力設定</div>
                <div class="emp-card-body">
                    <label class="emp-label">ファイル形式</label>
                    <div class="emp-format-row">
                        <button type="button" class="emp-format-btn active" data-format="csv">CSV</button>
                        <button type="button" class="emp-format-btn" data-format="tsv">TSV</button>
                    </div>
                </div>
                <div class="emp-field">
                    <label class="emp-label">文字コード</label>
                    <div class="emp-format-row">
                        <button type="button" class="emp-format-btn active" data-encoding="sjis">Shift-JIS</button>
                        <button type="button" class="emp-format-btn" data-encoding="utf8">UTF-8</button>
                    </div>
                </div>
                <div class="emp-field">
                    <label class="emp-label">ヘッダー行</label>
                    <div class="emp-format-row">
                        <button type="button" class="emp-format-btn active" data-header="1">あり</button>
                        <button type="button" class="emp-format-btn" data-header="0">なし</button>
                    </div>
                </div>
                <div class="emp-field">
                    <label class="emp-label">ファイル名</label>
                    <input type="text" id="csvFilename" class="emp-input" value="社員情報_出力">
                </div>
                </div><!-- /emp-card-body -->
            </div>

            <!-- サマリー＋出力ボタン -->
            <div class="emp-card">
                <div class="emp-card-title">出力サマリー</div>
                <div class="emp-card-body">
                    <table class="emp-summary-table">
                        <tr><td>出力列数</td><td><strong id="sumCols">0 列</strong></td></tr>
                        <tr><td>ファイル形式</td><td><strong id="sumFormat">CSV / Shift-JIS</strong></td></tr>
                        <tr><td>ヘッダー行</td><td><strong id="sumHeader">あり</strong></td></tr>
                    </table>
                    <button type="button" id="csvExportBtn" class="emp-btn emp-btn-success" style="width:100%;margin-top:1rem;">
                        <span class="dashicons dashicons-download"></span> CSV出力を実行
                    </button>
                </div>
            </div>

        </div><!-- /right -->
    </div><!-- /layout -->
</div>

<!-- テンプレート保存ダイアログ -->
<div id="csvTplDialog" class="emp-dialog-overlay" style="display:none;">
    <div class="emp-dialog-box">
        <div class="emp-dialog-title">テンプレートとして保存</div>
        <label class="emp-label">テンプレート名 <span style="color:red;">*</span></label>
        <input type="text" id="csvTplName" class="emp-input" placeholder="例：月次給与計算用" maxlength="30">
        <div class="emp-dialog-actions">
            <button type="button" class="emp-btn emp-btn-secondary" id="csvTplDialogCancel">キャンセル</button>
            <button type="button" class="emp-btn emp-btn-primary" id="csvTplDialogSave">保存する</button>
        </div>
    </div>
</div>

<!-- テンプレート名変更ダイアログ -->
<div id="csvRenameDialog" class="emp-dialog-overlay" style="display:none;">
    <div class="emp-dialog-box">
        <div class="emp-dialog-title">テンプレート名を変更</div>
        <input type="text" id="csvRenameInput" class="emp-input" maxlength="30">
        <input type="hidden" id="csvRenameId">
        <div class="emp-dialog-actions">
            <button type="button" class="emp-btn emp-btn-secondary" id="csvRenameCancel">キャンセル</button>
            <button type="button" class="emp-btn emp-btn-primary" id="csvRenameConfirm">変更する</button>
        </div>
    </div>
</div>
