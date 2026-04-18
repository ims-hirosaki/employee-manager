<?php if ( ! defined('ABSPATH') ) exit; ?>

<div class="wrap pl-wrap" id="pl-requests-page">
<h1 class="pl-page-title">
    <span class="dashicons dashicons-bell"></span> 有給申請管理
</h1>

<!-- フィルター -->
<div class="pl-card">
    <div class="pl-card-title">絞り込み</div>
    <div class="pl-search-row" style="flex-wrap:wrap; gap:12px;">
        <div style="display:flex; align-items:center; gap:8px;">
            <label class="pl-label" style="margin:0; white-space:nowrap;">状態</label>
            <select id="plReqStatus" class="pl-input" style="width:130px;">
                <option value="">すべて</option>
                <option value="pending"  selected>申請中のみ</option>
                <option value="approved">承認済み</option>
                <option value="rejected">却下済み</option>
            </select>
        </div>
        <div style="display:flex; align-items:center; gap:8px;">
            <label class="pl-label" style="margin:0; white-space:nowrap;">希望日</label>
            <input type="date" id="plReqFrom" class="pl-input" style="width:150px;">
            <span>〜</span>
            <input type="date" id="plReqTo" class="pl-input" style="width:150px;">
        </div>
        <button id="plReqSearch" class="pl-btn pl-btn-primary">
            <span class="dashicons dashicons-search" style="vertical-align:middle;"></span> 検索
        </button>
        <span id="plReqCount" class="pl-badge" style="display:none;"></span>
    </div>
</div>

<!-- 申請一覧テーブル -->
<div class="pl-card">
    <div class="pl-card-title">申請一覧</div>
    <div class="pl-table-wrap">
        <table class="pl-data-table" id="plReqTable">
            <thead>
                <tr>
                    <th style="width:60px;">ID</th>
                    <th>社員コード</th>
                    <th>氏名</th>
                    <th>希望日</th>
                    <th>申請日時</th>
                    <th>状態</th>
                    <th>申請備考</th>
                    <th>管理者コメント</th>
                    <th style="width:170px;">操作</th>
                </tr>
            </thead>
            <tbody id="plReqTbody">
                <tr><td colspan="9" class="pl-empty pl-loading">読み込み中...</td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- 承認・却下モーダル -->
<div id="plReqModal" class="pl-modal-overlay" style="display:none;">
    <div class="pl-modal-box">
        <h3 id="plReqModalTitle" class="pl-modal-title"></h3>
        <div class="pl-modal-body">
            <div class="pl-info-grid" id="plReqModalInfo"></div>
            <div class="pl-field" style="margin-top:16px;">
                <label class="pl-label">管理者コメント（任意）</label>
                <textarea id="plReqAdminNote" class="pl-input" rows="3"
                    placeholder="承認/却下の理由など"></textarea>
            </div>
            <div id="plReqAutoConsumeRow" class="pl-field" style="margin-top:12px;">
                <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                    <input type="checkbox" id="plReqAutoConsume" checked>
                    <span>承認と同時に消化登録する（1日）</span>
                </label>
                <p class="pl-hint" style="margin-left:24px; margin-top:4px;">
                    残日数が不足の場合は消化登録がスキップされます。付与・消化登録ページから手動登録してください。
                </p>
            </div>
        </div>
        <div class="pl-modal-footer">
            <button id="plReqModalConfirm" class="pl-btn pl-btn-primary">確定</button>
            <button id="plReqModalCancel" class="pl-btn pl-btn-secondary">キャンセル</button>
        </div>
    </div>
</div>
