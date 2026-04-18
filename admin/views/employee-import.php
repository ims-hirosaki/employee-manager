<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$base_url = admin_url( 'admin-ajax.php?action=emp_csv_template_download&nonce=' . wp_create_nonce( 'emp_import_nonce' ) );
?>
<div class="wrap emp-wrap" id="emp-import-page">
    <h1>CSVインポート</h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=employee-manager' ) ); ?>" class="emp-back-link">← 社員一覧に戻る</a>

    <!-- 手順説明 -->
    <div class="emp-card">
        <div class="emp-card-title">インポート手順</div>
        <div class="emp-card-body">
            <div class="emp-import-steps">
                <div class="emp-import-step"><div class="emp-import-step-num">1</div><div><div class="emp-import-step-title">ひな形CSVをダウンロード</div><div class="emp-hint">3種類のひな形を用意しています。それぞれダウンロードして使用してください。</div></div></div>
                <div class="emp-import-step"><div class="emp-import-step-num">2</div><div><div class="emp-import-step-title">Excelでデータを入力 → CSV保存</div><div class="emp-hint">「名前を付けて保存」→「CSV UTF-8（コンマ区切り）」で保存してください。</div></div></div>
                <div class="emp-import-step"><div class="emp-import-step-num">3</div><div><div class="emp-import-step-title">①基本情報を先にインポート</div><div class="emp-hint">②③は社員コードで紐付けるため、必ず①を先に実行してください。</div></div></div>
                <div class="emp-import-step"><div class="emp-import-step-num">4</div><div><div class="emp-import-step-title">②③を任意でインポート</div><div class="emp-hint">経歴・資格・扶養者は後からでも追加できます。</div></div></div>
            </div>
        </div>
    </div>

    <!-- タブ -->
    <div class="emp-import-tabs">
        <button class="emp-import-tab active" data-tab="basic">① 基本情報</button>
        <button class="emp-import-tab" data-tab="career">② 経歴・資格</button>
        <button class="emp-import-tab" data-tab="dependent">③ 扶養者</button>
    </div>

    <!-- ===== ① 基本情報 ===== -->
    <div class="emp-import-panel active" data-panel="basic">
        <div class="emp-card">
            <div class="emp-card-title">① 基本情報インポート</div>
            <div class="emp-card-body">
                <div class="emp-hint" style="font-size:.85rem;">
                    <strong>含まれる情報：</strong>社員マスタ基本情報 / 住所・連絡先 / 緊急連絡先 / 保険情報 / 退職情報
                </div>
                <div>
                    <a href="<?php echo esc_url( $base_url . '&csv_type=basic' ); ?>" class="emp-btn emp-btn-secondary">
                        <span class="dashicons dashicons-download"></span> ひな形をダウンロード（①基本情報）
                    </a>
                </div>
                <?php echo self_import_upload_ui( 'basic' ); ?>
            </div>
        </div>
        <?php echo self_import_preview( 'basic' ); ?>
        <?php echo self_import_result( 'basic' ); ?>
    </div>

    <!-- ===== ② 経歴・資格 ===== -->
    <div class="emp-import-panel" data-panel="career">
        <div class="emp-card">
            <div class="emp-card-title">② 経歴・資格インポート</div>
            <div class="emp-card-body">
                <div class="emp-hint" style="font-size:.85rem;">
                    <strong>含まれる情報：</strong>学歴 / 職歴 / 資格　　「種別」列に <code>学歴</code> / <code>職歴</code> / <code>資格</code> のいずれかを入力してください。<br>
                    <strong>※ 社員コードで紐付けるため、先に①基本情報をインポートしてください。</strong>
                </div>
                <div>
                    <a href="<?php echo esc_url( $base_url . '&csv_type=career' ); ?>" class="emp-btn emp-btn-secondary">
                        <span class="dashicons dashicons-download"></span> ひな形をダウンロード（②経歴・資格）
                    </a>
                </div>
                <?php echo self_import_upload_ui( 'career' ); ?>
            </div>
        </div>
        <?php echo self_import_preview( 'career' ); ?>
        <?php echo self_import_result( 'career' ); ?>
    </div>

    <!-- ===== ③ 扶養者 ===== -->
    <div class="emp-import-panel" data-panel="dependent">
        <div class="emp-card">
            <div class="emp-card-title">③ 扶養者インポート</div>
            <div class="emp-card-body">
                <div class="emp-hint" style="font-size:.85rem;">
                    <strong>含まれる情報：</strong>扶養者氏名 / 続柄 / 生年月日<br>
                    <strong>※ 1社員につき複数行の登録が可能です。先に①基本情報をインポートしてください。</strong>
                </div>
                <div>
                    <a href="<?php echo esc_url( $base_url . '&csv_type=dependent' ); ?>" class="emp-btn emp-btn-secondary">
                        <span class="dashicons dashicons-download"></span> ひな形をダウンロード（③扶養者）
                    </a>
                </div>
                <?php echo self_import_upload_ui( 'dependent' ); ?>
            </div>
        </div>
        <?php echo self_import_preview( 'dependent' ); ?>
        <?php echo self_import_result( 'dependent' ); ?>
    </div>
</div>

<div id="empToast"></div>

<?php
function self_import_upload_ui( $type ) {
    ob_start(); ?>
    <div class="emp-drop-zone" id="empDropZone-<?php echo $type; ?>">
        <span class="dashicons dashicons-upload" style="font-size:2.5rem;color:var(--emp-muted);"></span>
        <div style="font-weight:700;margin:.5rem 0 .25rem;">ここにCSVをドロップ</div>
        <label class="emp-btn emp-btn-secondary" style="cursor:pointer;margin-top:.4rem;">
            ファイルを選択
            <input type="file" class="emp-import-file" data-type="<?php echo $type; ?>" accept=".csv" style="display:none;">
        </label>
        <div class="emp-hint emp-file-name" style="margin-top:.4rem;">対応形式：CSV（UTF-8 / Shift-JIS）</div>
    </div>
    <div style="display:flex;gap:1.5rem;flex-wrap:wrap;margin-top:.5rem;">
        <div class="emp-field">
            <label class="emp-label">重複社員コードの処理</label>
            <div style="display:flex;flex-direction:column;gap:.3rem;margin-top:.3rem;">
                <label style="display:flex;align-items:center;gap:.5rem;font-size:.88rem;cursor:pointer;">
                    <input type="radio" name="dup_mode_<?php echo $type; ?>" value="skip" checked> スキップ（既存を保持）
                </label>
                <label style="display:flex;align-items:center;gap:.5rem;font-size:.88rem;cursor:pointer;">
                    <input type="radio" name="dup_mode_<?php echo $type; ?>" value="update"> 上書き更新
                </label>
            </div>
        </div>
        <div class="emp-field">
            <label class="emp-label">文字コード</label>
            <select class="emp-select emp-import-encoding" data-type="<?php echo $type; ?>" style="max-width:180px;">
                <option value="UTF-8">自動 / UTF-8</option>
                <option value="Shift-JIS">Shift-JIS</option>
            </select>
        </div>
    </div>
    <button type="button" class="emp-btn emp-btn-primary emp-import-preview-btn" data-type="<?php echo $type; ?>" disabled>
        <span class="dashicons dashicons-visibility"></span> プレビューを確認する
    </button>
    <?php return ob_get_clean();
}

function self_import_preview( $type ) {
    ob_start(); ?>
    <div class="emp-import-preview-section" id="empPreviewSection-<?php echo $type; ?>" style="display:none;">
        <div class="emp-card">
            <div class="emp-card-title-row">
                <div class="emp-card-title">プレビュー</div>
                <div class="emp-import-summary" id="empSummary-<?php echo $type; ?>" style="font-size:.82rem;color:var(--emp-secondary);"></div>
            </div>
            <div class="emp-import-errors" id="empErrors-<?php echo $type; ?>" style="display:none;padding:1rem 1.25rem;">
                <div style="font-weight:700;color:var(--emp-danger);margin-bottom:.4rem;">⚠ エラー行（スキップされます）</div>
                <ul class="emp-import-error-list" style="font-size:.82rem;color:var(--emp-danger);padding-left:1.25rem;"></ul>
            </div>
            <div style="overflow-x:auto;">
                <table class="emp-table emp-preview-table" id="empPreviewTable-<?php echo $type; ?>">
                    <thead><tr class="emp-preview-head-row"></tr></thead>
                    <tbody class="emp-preview-tbody"></tbody>
                </table>
            </div>
            <div class="emp-card-body" style="padding-top:0;">
                <div class="emp-import-notice emp-hint"></div>
                <div style="display:flex;gap:.75rem;">
                    <button type="button" class="emp-btn emp-btn-success emp-import-run-btn" data-type="<?php echo $type; ?>">
                        <span class="dashicons dashicons-yes"></span> インポートを実行する
                    </button>
                    <button type="button" class="emp-btn emp-btn-secondary emp-import-cancel-btn" data-type="<?php echo $type; ?>">
                        キャンセル
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php return ob_get_clean();
}

function self_import_result( $type ) {
    ob_start(); ?>
    <div class="emp-import-result" id="empResult-<?php echo $type; ?>" style="display:none;">
        <div class="emp-card">
            <div class="emp-card-title">インポート結果</div>
            <div class="emp-card-body emp-import-result-body"></div>
        </div>
    </div>
    <?php return ob_get_clean();
}
?>
