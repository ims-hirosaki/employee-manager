/* global empData, jQuery */
(function ($) {
    'use strict';

    // =====================================================
    //  共通ユーティリティ
    // =====================================================

    function empAjax(action, data, callback) {
        $.post(empData.ajaxUrl, $.extend({ action: action }, data), function (res) {
            if (typeof callback === 'function') callback(res);
        });
    }

    let toastTimer;
    function showToast(msg, type) {
        let $t = $('#empToast');
        if (!$t.length) {
            $t = $('<div id="empToast"></div>').appendTo('body');
        }
        $t.text(msg).attr('class', type || '').addClass('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => $t.removeClass('show'), 3000);
    }

    // =====================================================
    //  社員一覧ページ
    // =====================================================

    if ($('#emp-list-page').length) {

        let currentPage = 1, perPage = 20, sortCol = 'employee_code', sortDir = 'ASC';
        let selectedIds = new Set();

        // マスタ選択肢を読み込む
        function loadFilterOptions() {
            ['affiliation', 'department'].forEach(function (type) {
                empAjax('emp_master_get_list', { nonce: empData.masterNonce, master_type: type }, function (res) {
                    if (!res.success) return;
                    const $sel = $('#empFilter' + (type === 'affiliation' ? 'Affil' : 'Dept'));
                    res.data.forEach(function (item) {
                        $sel.append($('<option>', { value: item.id, text: item.name }));
                    });
                });
            });
        }
        // 統計カード：フィルター非依存の全体集計をロードして描画
        function loadStats() {
            empAjax('emp_get_stats', { nonce: empData.employeeNonce }, function (res) {
                if (!res.success) return;
                const d = res.data;
                $('#statActiveTotal').html(d.active_total + '<span class="emp-stat-unit">名</span>')

                const $row = $('#empAffilStatsRow').empty();
                if (!d.affiliations.length) {
                    $row.append('<div class="emp-affil-stats-placeholder">所属データがありません</div>');
                    return;
                }
                d.affiliations.forEach(function (a) {
                    $row.append(
                        '<div class="emp-stat-card emp-stat-card--affil">' +
                        '<div class="emp-stat-label">' + escHtml(a.name) + '</div>' +
                        '<div class="emp-stat-value emp-stat-affil-count">' + a.active_count + '<span class="emp-stat-unit">名</span></div>' +
                        '</div>'
                    );
                });
            });
        }

        function loadList() {
            const params = {
                nonce: empData.employeeNonce,
                search: $('#empSearch').val(),
                affiliation_id: $('#empFilterAffil').val(),
                department_id: $('#empFilterDept').val(),
                is_active: $('#empFilterStatus').val(),
                per_page: perPage,
                page: currentPage,
                orderby: sortCol,
                order: sortDir,
            };
            empAjax('emp_employee_get_list', params, function (res) {
                if (!res.success) return;
                renderTable(res.data);
            });
        }

        function renderTable(data) {
            const { items, total } = data;
            const $tbody = $('#empTbody');
            $tbody.empty();

            if (!items.length) {
                $tbody.append('<tr><td colspan="10" class="emp-empty">該当する社員が見つかりません</td></tr>');
                $('#empTableInfo').text('0 件');
                $('#empTotal').text(0);
                $('#empRange').text('—');
                renderPagination(0);
                return;
            }

            const start = (currentPage - 1) * perPage + 1;
            const end = Math.min(start + items.length - 1, total);
            $('#empTableInfo').text(total + ' 件中 ' + start + '–' + end + ' 件表示');
            $('#empTotal').text(total);
            $('#empRange').text(start + '–' + end);

            items.forEach(function (emp) {
                const isActive = parseInt(emp.is_active, 10) === 1;
                const checked = selectedIds.has(emp.id) ? 'checked' : '';
                const row = `
                <tr data-id="${emp.id}">
                    <td onclick="event.stopPropagation()"><input type="checkbox" class="emp-cb" ${checked} data-id="${emp.id}"></td>
                    <td><span class="emp-cell-id">#${String(emp.id).padStart(3, '0')}</span></td>
                    <td><span class="emp-cell-code">${escHtml(emp.employee_code)}</span></td>
                    <td>
                        <div class="emp-cell-name">${escHtml(emp.name)}</div>
                        <div class="emp-cell-kana">${escHtml(emp.name_kana || '')}</div>
                    </td>
                    <td><span class="emp-dept-badge">${escHtml(emp.affiliation_name || '—')}</span></td>
                    <td>${escHtml(emp.department_name || '—')}</td>
                    <td>${escHtml(emp.position_name || '—')}</td>
                    <td>${escHtml(emp.hire_date || '—')}</td>
                    <td onclick="event.stopPropagation()">
                        <span class="emp-active-toggle ${isActive ? 'on' : 'off'}" data-id="${emp.id}">
                            <span class="emp-active-dot"></span>
                            ${isActive ? '在籍中' : '退職'}
                        </span>
                    </td>
                    <td>
                        <button class="emp-row-edit-btn" data-id="${emp.id}">編集</button>
                    </td>
                </tr>`;
                $tbody.append(row);
            });

            renderPagination(total);
        }

        function renderPagination(total) {
            const pages = Math.ceil(total / perPage);
            const $pg = $('#empPagination').empty();
            if (pages <= 1) return;

            const prevDisabled = currentPage === 1 ? 'disabled' : '';
            const nextDisabled = currentPage === pages ? 'disabled' : '';
            $pg.append(`<button class="emp-page-btn" ${prevDisabled} data-page="${currentPage - 1}">‹</button>`);
            for (let i = 1; i <= pages; i++) {
                if (i === 1 || i === pages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                    $pg.append(`<button class="emp-page-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`);
                } else if (i === currentPage - 2 || i === currentPage + 2) {
                    $pg.append('<span style="padding:0 .2rem;color:#9ba3b4;">…</span>');
                }
            }
            $pg.append(`<button class="emp-page-btn" ${nextDisabled} data-page="${currentPage + 1}">›</button>`);
        }

        // 在籍トグル
        $('#empTbody').on('click', '.emp-active-toggle', function () {
            const $el = $(this);
            const id = $el.data('id');
            const isOn = $el.hasClass('on');
            const newVal = isOn ? 0 : 1;
            empAjax('emp_employee_toggle', { nonce: empData.employeeNonce, id: id, is_active: newVal }, function (res) {
                if (res.success) {
                    $el.toggleClass('on', !isOn).toggleClass('off', isOn)
                        .find('.emp-active-dot').end()
                        .html('<span class="emp-active-dot"></span>' + (newVal ? '在籍中' : '退職'));
                    showToast(res.data.message, 'success');
                    loadStats();
                    loadList();
                } else {
                    showToast(res.data.message, 'error');
                }
            });
        });

        // 編集ボタン（ボタンクリック時のみ遷移）
        $('#empTbody').on('click', '.emp-row-edit-btn', function (e) {
            e.stopPropagation();
            const id = $(this).data('id');
            window.location.href = empData.ajaxUrl.replace('admin-ajax.php', 'admin.php') +
                '?page=employee-manager-new&id=' + id;
        });

        // チェックボックス
        $('#empTbody').on('change', '.emp-cb', function () {
            const id = $(this).data('id');
            if (this.checked) selectedIds.add(id);
            else selectedIds.delete(id);
            updateBulkBar();
        });
        $('#empCheckAll').on('change', function () {
            $('.emp-cb').prop('checked', this.checked).each(function () {
                const id = $(this).data('id');
                if (this.checked) selectedIds.add(id);
                else selectedIds.delete(id);
            });
            updateBulkBar();
        });

        function updateBulkBar() {
            const n = selectedIds.size;
            if (n > 0) {
                $('#empBulkCount').text(n);
                $('#empBulkBar').show();
            } else {
                $('#empBulkBar').hide();
            }
        }

        function bulkToggle(active) {
            const ids = Array.from(selectedIds);
            let done = 0;
            ids.forEach(function (id) {
                empAjax('emp_employee_toggle', { nonce: empData.employeeNonce, id: id, is_active: active }, function () {
                    done++;
                    if (done === ids.length) {
                        showToast(ids.length + '件を変更しました', 'success');
                        selectedIds.clear();
                        $('#empBulkBar').hide();
                        loadList();
                    }
                });
            });
        }

        $('#empBulkActive').on('click', () => bulkToggle(1));
        $('#empBulkRetire').on('click', () => bulkToggle(0));
        $('#empBulkClose').on('click', function () {
            selectedIds.clear();
            $('.emp-cb').prop('checked', false);
            $('#empCheckAll').prop('checked', false);
            $('#empBulkBar').hide();
        });

        // ソート
        $('#empTable').on('click', 'th.sortable', function () {
            const col = $(this).data('col');
            if (sortCol === col) sortDir = sortDir === 'ASC' ? 'DESC' : 'ASC';
            else { sortCol = col; sortDir = 'ASC'; }
            currentPage = 1;
            loadList();
        });

        // ページネーション
        $('#empPagination').on('click', '.emp-page-btn', function () {
            const p = parseInt($(this).data('page'), 10);
            if (p > 0) { currentPage = p; loadList(); }
        });

        // 件数変更
        $('#empPerPage').on('change', function () {
            perPage = parseInt(this.value, 10); currentPage = 1; loadList();
        });

        // 検索・フィルタ（デバウンス）
        let searchTimer;
        $('#empSearch, #empFilterAffil, #empFilterDept, #empFilterStatus').on('input change', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () { currentPage = 1; loadList(); }, 350);
        });

        // 初期化
        loadFilterOptions();
        loadStats();  // ← 追加
        loadList();
    }

    // =====================================================
    //  社員登録・編集フォームページ
    // =====================================================

    if ($('#emp-form-page').length) {

        let currentStep = 1;
        const TOTAL_STEPS = 5;

        // 既存データを復元（編集時）
        const existingData = $('#emp-form-page').data('emp-json');
        if (existingData) {
            fillForm(existingData);
        }

        // マスタ選択肢を読み込む
        ['affiliation', 'department', 'position', 'job_type'].forEach(function (type) {
            const selId = {
                affiliation: '#selAffiliation', department: '#selDepartment',
                position: '#selPosition', job_type: '#selJobType',
            }[type];
            empAjax('emp_master_get_list', { nonce: empData.masterNonce, master_type: type }, function (res) {
                if (!res.success) return;
                const $sel = $(selId);
                res.data.forEach(function (item) {
                    $sel.append($('<option>', { value: item.id, text: item.name }));
                });
                // 編集時は選択状態を復元
                if (existingData) {
                    const key = type + '_id';
                    $sel.val(existingData[key] || '');
                }
            });
        });

        // ステップ移動
        function goStep(step) {
            $('.emp-step-panel').removeClass('active');
            $('.emp-step-panel[data-panel="' + step + '"]').addClass('active');
            $('.emp-step').removeClass('active done');
            $('.emp-step').each(function () {
                const s = parseInt($(this).data('step'), 10);
                if (s < step) $(this).addClass('done');
                else if (s === step) $(this).addClass('active');
            });
            $('#empStepFill').css('width', (step / TOTAL_STEPS * 100) + '%');

            // ボタン表示切り替え：前へ／次へは右、登録・更新は左（常時表示）
            $('#empBtnPrev').toggle(step > 1);
            $('#empBtnNext').toggle(step < TOTAL_STEPS);
            $('#empBtnSubmit').show();

            if (step === TOTAL_STEPS) buildConfirmView();
            currentStep = step;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        // ステップタブクリックで直接ジャンプ
        $('.emp-steps').on('click', '.emp-step', function () {
            const step = parseInt($(this).data('step'), 10);
            // 前のステップはバリデーションなしで移動可、先のステップは現在ステップを検証
            if (step > currentStep && !validateStep(currentStep)) return;
            goStep(step);
        });

        $('#empBtnNext').on('click', function () {
            if (validateStep(currentStep)) goStep(currentStep + 1);
        });
        $('#empBtnPrev').on('click', function () {
            goStep(currentStep - 1);
        });

        // 在籍フラグのトグルラベル
        $('[name="is_active"]').on('change', function () {
            $('#isActiveLabel').text(this.checked ? '在籍中' : '退職');
        });

        // 動的行追加
        $('.emp-add-row-btn').on('click', function () {
            addDynamicRow($(this).data('target'));
        });

        function addDynamicRow(target) {
            const templates = {
                educations: `
                    <div class="emp-dynamic-row edu">
                        <input type="date" name="educations[graduation_date][]" class="emp-input">
                        <input type="text" name="educations[school_name][]"     class="emp-input" placeholder="学校名">
                        <input type="text" name="educations[department][]"      class="emp-input" placeholder="学科・専攻">
                        <button type="button" class="emp-row-del-btn">✕</button>
                    </div>`,
                careers: `
                    <div class="emp-dynamic-row car">
                        <input type="text"   name="careers[career_year][]"  class="emp-input" placeholder="年" maxlength="4">
                        <input type="text"   name="careers[career_month][]" class="emp-input" placeholder="月" maxlength="2">
                        <input type="text"   name="careers[company_name][]" class="emp-input" placeholder="会社名">
                        <input type="text"   name="careers[department][]"   class="emp-input" placeholder="部署">
                        <input type="text"   name="careers[position][]"     class="emp-input" placeholder="役職">
                        <button type="button" class="emp-row-del-btn">✕</button>
                    </div>`,
                qualifications: `
                    <div class="emp-dynamic-row qual">
                        <input type="text" name="qualifications[name][]"          class="emp-input" placeholder="資格・免許名">
                        <input type="date" name="qualifications[acquired_date][]"  class="emp-input">
                        <button type="button" class="emp-row-del-btn">✕</button>
                    </div>`,
                dependents: `
                    <div class="emp-dynamic-row dep">
                        <input type="text" name="dependents[name][]"      class="emp-input" placeholder="氏名">
                        <input type="text" name="dependents[name_kana][]" class="emp-input" placeholder="フリガナ">
                        <input type="text" name="dependents[relation][]"  class="emp-input" placeholder="続柄">
                        <input type="date" name="dependents[birthdate][]" class="emp-input">
                        <button type="button" class="emp-row-del-btn">✕</button>
                    </div>`,
            };
            $('#' + target + 'Rows').append(templates[target]);
        }

        // 動的行削除
        $('#empForm').on('click', '.emp-row-del-btn', function () {
            $(this).closest('.emp-dynamic-row').remove();
        });

        // バリデーション
        function validateStep(step) {
            let ok = true;
            if (step === 1) {
                ['[name="employee_code"]', '[name="name"]'].forEach(function (sel) {
                    const $f = $(sel);
                    if (!$f.val().trim()) {
                        $f.addClass('error');
                        ok = false;
                    } else {
                        $f.removeClass('error');
                    }
                });
                if (!ok) showToast('必須項目を入力してください', 'error');
            }
            return ok;
        }

        // 確認画面の構築
        function buildConfirmView() {
            const d = collectFormData();
            let html = '';

            const basic = [
                ['社員コード', d.employee_code], ['氏名', d.name], ['フリガナ', d.name_kana],
                ['性別', d.gender], ['生年月日', d.birthdate], ['血液型', d.blood_type],
                ['入社日', d.hire_date], ['在籍状況', parseInt(d.is_active) ? '在籍中' : '退職'],
            ];
            html += buildConfirmSection('基本情報', basic);

            const org = [
                ['所属', $('#selAffiliation option:selected').text()],
                ['部署', $('#selDepartment option:selected').text()],
                ['役職', $('#selPosition option:selected').text()],
                ['職種', $('#selJobType option:selected').text()],
                ['雇用区分', $('[name="employment_type"] option:selected').text()],
                ['週勤務日数', d.weekly_work_days ? '週' + d.weekly_work_days + '勤務' : ''],
                ['乗組員コード', d.crew_code],
            ];
            html += buildConfirmSection('所属・役職', org);

            $('#empConfirmView').html(html);
        }

        function buildConfirmSection(title, rows) {
            let html = `<div class="emp-confirm-section"><div class="emp-confirm-section-title">${title}</div><div class="emp-confirm-grid">`;
            rows.forEach(function (r) {
                const val = r[1] || '';
                html += `<span class="emp-confirm-key">${r[0]}</span>
                         <span class="emp-confirm-val ${val ? '' : 'empty'}">${val || '未入力'}</span>`;
            });
            html += '</div></div>';
            return html;
        }

        // フォームデータ収集
        function collectFormData() {
            const data = {};

            // 通常フィールド（配列形式を除く・selectも含む）
            $('#empForm').find('[name]:not([name*="["])').each(function () {
                const name = $(this).attr('name');
                if ($(this).is('[type="checkbox"]')) {
                    data[name] = this.checked ? this.value : '0';
                } else {
                    data[name] = $(this).val();
                }
            });
            // select（所属・部署・役職・職種）を明示的に収集
            data.affiliation_id = $('#selAffiliation').val() || '';
            data.department_id = $('#selDepartment').val() || '';
            data.position_id = $('#selPosition').val() || '';
            data.job_type_id = $('#selJobType').val() || '';
            data.employment_type = $('[name="employment_type"]').val() || '';
            data.weekly_work_days = $('[name="weekly_work_days"]').val() || '';
            data.is_active = $('[name="is_active"]').is(':checked') ? 1 : 0;

            // 動的行：学歴
            data.educations = [];
            $('#educationsRows .emp-dynamic-row').each(function () {
                data.educations.push({
                    graduation_date: $(this).find('[name="educations[graduation_date][]"]').val(),
                    school_name: $(this).find('[name="educations[school_name][]"]').val(),
                    department: $(this).find('[name="educations[department][]"]').val(),
                });
            });

            // 動的行：職歴
            data.careers = [];
            $('#careersRows .emp-dynamic-row').each(function () {
                data.careers.push({
                    career_year: $(this).find('[name="careers[career_year][]"]').val(),
                    career_month: $(this).find('[name="careers[career_month][]"]').val(),
                    company_name: $(this).find('[name="careers[company_name][]"]').val(),
                    department: $(this).find('[name="careers[department][]"]').val(),
                    position: $(this).find('[name="careers[position][]"]').val(),
                });
            });

            // 動的行：資格
            data.qualifications = [];
            $('#qualificationsRows .emp-dynamic-row').each(function () {
                data.qualifications.push({
                    name: $(this).find('[name="qualifications[name][]"]').val(),
                    acquired_date: $(this).find('[name="qualifications[acquired_date][]"]').val(),
                });
            });

            // 動的行：扶養者
            data.dependents = [];
            $('#dependentsRows .emp-dynamic-row').each(function () {
                data.dependents.push({
                    name: $(this).find('[name="dependents[name][]"]').val(),
                    name_kana: $(this).find('[name="dependents[name_kana][]"]').val(),
                    relation: $(this).find('[name="dependents[relation][]"]').val(),
                    birthdate: $(this).find('[name="dependents[birthdate][]"]').val(),
                });
            });

            return data;
        }

        // フォーム送信
        $('#empForm').on('submit', function (e) {
            e.preventDefault();
            if (!validateStep(1)) { goStep(1); return; }

            const data = collectFormData();
            const id = $('#empId').val();

            $('#empBtnSubmit').prop('disabled', true).text('保存中...');
            empAjax('emp_employee_save', { nonce: empData.employeeNonce, id: id, data: data }, function (res) {
                $('#empBtnSubmit').prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> ' + (id > 0 ? '更新する' : '登録する'));
                if (res.success) {
                    showToast(res.data.message, 'success');
                    setTimeout(function () {
                        window.location.href = empData.ajaxUrl.replace('admin-ajax.php', 'admin.php') + '?page=employee-manager&emp_saved=1';
                    }, 1200);
                } else {
                    showToast(res.data.message, 'error');
                }
            });
        });

        function fillForm(emp) {
            // 保険・退職情報をフラット展開してマージ
            const flat = Object.assign({}, emp);
            if (emp.insurance) Object.assign(flat, emp.insurance);
            if (emp.retirement) Object.assign(flat, emp.retirement);

            // 通常フィールドの復元（selectも含む）
            Object.keys(flat).forEach(function (key) {
                const $f = $('[name="' + key + '"]');
                if (!$f.length) return;
                if ($f.is('[type="checkbox"]')) {
                    $f.prop('checked', parseInt(flat[key], 10) === 1);
                    $('#isActiveLabel').text(parseInt(flat[key], 10) ? '在籍中' : '退職');
                } else {
                    $f.val(flat[key] || '');
                }
            });

            // 動的行の復元：学歴
            if (emp.educations && emp.educations.length) {
                $('#educationsRows').empty();
                emp.educations.forEach(function (row) {
                    $('#educationsRows').append(`
                        <div class="emp-dynamic-row edu">
                            <input type="date" name="educations[graduation_date][]" class="emp-input" value="${escAttr(row.graduation_date || '')}">
                            <input type="text" name="educations[school_name][]"     class="emp-input" placeholder="学校名" value="${escAttr(row.school_name || '')}">
                            <input type="text" name="educations[department][]"      class="emp-input" placeholder="学科・専攻" value="${escAttr(row.department || '')}">
                            <button type="button" class="emp-row-del-btn">✕</button>
                        </div>` );
                });
            }

            // 動的行の復元：職歴
            if (emp.careers && emp.careers.length) {
                $('#careersRows').empty();
                emp.careers.forEach(function (row) {
                    $('#careersRows').append(`
                        <div class="emp-dynamic-row car">
                            <input type="text"   name="careers[career_year][]"  class="emp-input" placeholder="年" maxlength="4" value="${escAttr(row.career_year || '')}">
                            <input type="text"   name="careers[career_month][]" class="emp-input" placeholder="月" maxlength="2" value="${escAttr(row.career_month || '')}">
                            <input type="text"   name="careers[company_name][]" class="emp-input" placeholder="会社名" value="${escAttr(row.company_name || '')}">
                            <input type="text"   name="careers[department][]"   class="emp-input" placeholder="部署" value="${escAttr(row.department || '')}">
                            <input type="text"   name="careers[position][]"     class="emp-input" placeholder="役職" value="${escAttr(row.position || '')}">
                            <button type="button" class="emp-row-del-btn">✕</button>
                        </div>` );
                });
            }

            // 動的行の復元：資格
            if (emp.qualifications && emp.qualifications.length) {
                $('#qualificationsRows').empty();
                emp.qualifications.forEach(function (row) {
                    $('#qualificationsRows').append(`
                        <div class="emp-dynamic-row qual">
                            <input type="text" name="qualifications[name][]"         class="emp-input" placeholder="資格・免許名" value="${escAttr(row.name || '')}">
                            <input type="date" name="qualifications[acquired_date][]" class="emp-input" value="${escAttr(row.acquired_date || '')}">
                            <button type="button" class="emp-row-del-btn">✕</button>
                        </div>` );
                });
            }

            // 動的行の復元：扶養者
            if (emp.dependents && emp.dependents.length) {
                $('#dependentsRows').empty();
                emp.dependents.forEach(function (row) {
                    $('#dependentsRows').append(`
                        <div class="emp-dynamic-row dep">
                            <input type="text" name="dependents[name][]"      class="emp-input" placeholder="氏名" value="${escAttr(row.name || '')}">
                            <input type="text" name="dependents[name_kana][]" class="emp-input" placeholder="フリガナ" value="${escAttr(row.name_kana || '')}">
                            <input type="text" name="dependents[relation][]"  class="emp-input" placeholder="続柄" value="${escAttr(row.relation || '')}">
                            <input type="date" name="dependents[birthdate][]" class="emp-input" value="${escAttr(row.birthdate || '')}">
                            <button type="button" class="emp-row-del-btn">✕</button>
                        </div>` );
                });
            }
        }
    }

    // =====================================================
    //  マスタ管理ページ
    // =====================================================

    if ($('#emp-master-page').length) {

        let currentType = 'affiliation';

        function loadMasterList(type) {
            empAjax('emp_master_get_list', { nonce: empData.masterNonce, master_type: type }, function (res) {
                if (!res.success) return;
                renderMasterTable(type, res.data);
            });
        }

        function renderMasterTable(type, items) {
            const $tbody = $('#masterTbody-' + type).empty();
            const $stats = $('#stats-' + type);

            const total = items.length;
            const active = items.filter(i => parseInt(i.is_active, 10) === 1).length;
            const inactive = total - active;
            $stats.find('.js-total').text(total);
            $stats.find('.js-active').text(active);
            $stats.find('.js-inactive').text(inactive);

            const searchVal = $('.master-search[data-type="' + type + '"]').val().toLowerCase();

            if (!items.length) {
                $tbody.append('<tr><td colspan="5" class="emp-empty">データがありません</td></tr>');
                return;
            }

            items.forEach(function (item) {
                if (searchVal && item.name.toLowerCase().indexOf(searchVal) === -1) return;
                const isActive = parseInt(item.is_active, 10) === 1;
                const row = `
                <tr data-id="${item.id}" data-type="${type}">
                    <td><span class="emp-cell-id">#${item.id}</span></td>
                    <td class="master-name-cell">${escHtml(item.name)}</td>
                    <td>${item.sort_order}</td>
                    <td>
                        <span class="emp-active-toggle ${isActive ? 'on' : 'off'}" data-id="${item.id}" data-type="${type}">
                            <span class="emp-active-dot"></span>
                            ${isActive ? '有効' : '無効'}
                        </span>
                    </td>
                    <td>
                        <button class="emp-row-edit-btn master-edit-btn" data-id="${item.id}" data-type="${type}" data-name="${escHtml(item.name)}" data-order="${item.sort_order}">編集</button>
                        <button class="emp-row-edit-btn master-del-btn" data-id="${item.id}" data-type="${type}" data-name="${escHtml(item.name)}" style="color:#e53e3e;border-color:#e53e3e;margin-left:.25rem;">削除</button>
                    </td>
                </tr>`;
                $tbody.append(row);
            });
        }

        // タブ切替
        $('.emp-tab').on('click', function () {
            const type = $(this).data('type');
            currentType = type;
            $('.emp-tab').removeClass('active');
            $(this).addClass('active');
            $('.emp-tab-panel').removeClass('active');
            $('.emp-tab-panel[data-panel="' + type + '"]').addClass('active');
            loadMasterList(type);
        });

        // 新規追加ボタン
        $('.master-add-btn').on('click', function () {
            const type = $(this).data('type');
            $('#addForm-' + type).slideToggle();
        });

        $('.master-cancel-new').on('click', function () {
            const type = $(this).data('type');
            $('#addForm-' + type).slideUp();
        });

        // 新規登録
        $('.master-save-new').on('click', function () {
            const type = $(this).data('type');
            const name = $('#addForm-' + type + ' .master-new-name').val().trim();
            const order = $('#addForm-' + type + ' .master-new-order').val() || 0;
            if (!name) { showToast('名称を入力してください', 'error'); return; }
            empAjax('emp_master_insert', { nonce: empData.masterNonce, master_type: type, name: name, sort_order: order }, function (res) {
                if (res.success) {
                    showToast(res.data.message, 'success');
                    $('#addForm-' + type).slideUp();
                    $('#addForm-' + type + ' .master-new-name').val('');
                    loadMasterList(type);
                } else {
                    showToast(res.data.message, 'error');
                }
            });
        });

        // インライン編集
        $(document).on('click', '.master-edit-btn', function () {
            const id = $(this).data('id');
            const type = $(this).data('type');
            const name = $(this).data('name');
            const order = $(this).data('order');
            const $row = $(this).closest('tr');
            $row.find('.master-name-cell').html(
                `<input class="master-inline-edit" type="text" value="${escAttr(name)}" data-id="${id}" data-type="${type}" data-order="${order}">`
            );
            $row.find('.master-inline-edit').focus().select();
        });

        $(document).on('blur keydown', '.master-inline-edit', function (e) {
            if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== 'Escape') return;
            if (e.key === 'Escape') { loadMasterList($(this).data('type')); return; }
            const id = $(this).data('id');
            const type = $(this).data('type');
            const name = $(this).val().trim();
            if (!name) return;
            empAjax('emp_master_update', { nonce: empData.masterNonce, master_type: type, id: id, name: name }, function (res) {
                if (res.success) { showToast(res.data.message, 'success'); loadMasterList(type); }
                else showToast(res.data.message, 'error');
            });
        });

        // 有効フラグトグル
        $(document).on('click', '.emp-active-toggle[data-type]', function () {
            const $el = $(this);
            const id = $el.data('id');
            const type = $el.data('type');
            const isOn = $el.hasClass('on');
            empAjax('emp_master_update', { nonce: empData.masterNonce, master_type: type, id: id, is_active: isOn ? 0 : 1 }, function (res) {
                if (res.success) { showToast(res.data.message, 'success'); loadMasterList(type); }
                else showToast(res.data.message, 'error');
            });
        });

        // 削除
        $(document).on('click', '.master-del-btn', function () {
            const id = $(this).data('id');
            const type = $(this).data('type');
            const name = $(this).data('name');
            if (!confirm('「' + name + '」を削除してもよいですか？')) return;
            empAjax('emp_master_delete', { nonce: empData.masterNonce, master_type: type, id: id }, function (res) {
                if (res.success) { showToast(res.data.message, 'success'); loadMasterList(type); }
                else showToast(res.data.message, 'error');
            });
        });

        // 検索
        let searchTimer;
        $('.master-search').on('input', function () {
            const type = $(this).data('type');
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => loadMasterList(type), 250);
        });

        // 初期読み込み
        loadMasterList('affiliation');
    }

    // =====================================================
    //  CSV出力ページ
    // =====================================================

    if ($('#emp-csv-page').length) {

        let checkedKeys = new Set(['employee_code', 'name', 'name_kana', 'hire_date', 'is_active']);
        let columnOrder = [];
        let outputFormat = 'csv';
        let encoding = 'sjis';
        let withHeader = true;
        let dragSrc = null;

        // 列定義（PHP側と同じ定義をJSでも保持）
        const COL_LABELS = {};
        $('.emp-col-item').each(function () {
            const key = $(this).data('key');
            const label = $(this).find('.emp-col-item-label').text();
            COL_LABELS[key] = label;
        });

        // フィルター選択肢読み込み
        ['affiliation', 'department'].forEach(function (type) {
            empAjax('emp_master_get_list', { nonce: empData.masterNonce, master_type: type }, function (res) {
                if (!res.success) return;
                const $sel = type === 'affiliation' ? $('#csvFilterAffil') : $('#csvFilterDept');
                res.data.forEach(function (item) {
                    $sel.append($('<option>', { value: item.id, text: item.name }));
                });
            });
        });

        // 初期チェック状態を適用
        checkedKeys.forEach(function (key) {
            $('.emp-col-item[data-key="' + key + '"]').addClass('checked')
                .find('.emp-col-check').prop('checked', true);
        });
        syncOrderList();
        updateCount();

        // 項目チェック
        $('.emp-col-item').on('click', function (e) {
            e.preventDefault(); // ← 追加: inputへの二重発火を阻止
            const key = $(this).data('key');
            const checked = !$(this).hasClass('checked');
            $(this).toggleClass('checked', checked);
            if (checked) checkedKeys.add(key);
            else checkedKeys.delete(key);
            syncOrderList();
            updateCount();
        });

        // グループ一括切替
        $('.emp-group-toggle').on('click', function () {
            const group = $(this).data('group');
            const $items = $('.emp-col-items[data-group-el="' + group + '"] .emp-col-item');
            const allOn = $items.toArray().every(el => $(el).hasClass('checked'));
            $items.each(function () {
                const key = $(this).data('key');
                $(this).toggleClass('checked', !allOn);
                if (!allOn) checkedKeys.add(key);
                else checkedKeys.delete(key);
            });
            syncOrderList();
            updateCount();
        });

        // プリセット
        const PRESETS = {
            basic: ['employee_code', 'name', 'name_kana', 'hire_date', 'is_active'],
            hr: ['employee_code', 'name', 'name_kana', 'gender', 'birthdate', 'hire_date', 'affiliation_name', 'department_name', 'position_name', 'is_active'],
            insurance: ['employee_code', 'name', 'health_no', 'health_date', 'pension_no', 'pension_date', 'employment_no', 'employment_date', 'accident_no', 'accident_date'],
            contact: ['employee_code', 'name', 'tel_mobile', 'tel_home', 'tel_company', 'emergency_name', 'emergency_tel', 'emergency_relation'],
            all: Object.keys(COL_LABELS),
            none: [],
        };

        $('.emp-preset-btn').on('click', function () {
            const preset = $(this).data('preset');
            const keys = PRESETS[preset] || [];
            checkedKeys = new Set(keys);
            $('.emp-col-item').each(function () {
                const key = $(this).data('key');
                $(this).toggleClass('checked', checkedKeys.has(key));
            });
            columnOrder = keys.map(k => ({ key: k, label: COL_LABELS[k] || k }));
            renderOrderList();
            updateCount();
        });

        // 列順
        function syncOrderList() {
            const allChecked = [];
            $('.emp-col-item.checked').each(function () {
                const key = $(this).data('key');
                allChecked.push({ key: key, label: COL_LABELS[key] || key });
            });
            // 既存の順序を維持しつつ追加/削除に対応
            columnOrder = columnOrder.filter(c => checkedKeys.has(c.key));
            const existing = columnOrder.map(c => c.key);
            allChecked.forEach(function (c) {
                if (!existing.includes(c.key)) columnOrder.push(c);
            });
            renderOrderList();
        }

        function renderOrderList() {
            const $list = $('#csvOrderList').empty();
            if (!columnOrder.length) {
                $list.append('<div class="emp-order-empty">項目が選択されていません</div>');
                return;
            }
            columnOrder.forEach(function (col, i) {
                const item = `
                <div class="emp-order-item" draggable="true" data-key="${col.key}">
                    <span class="emp-order-drag">⠿</span>
                    <span class="emp-order-num">${i + 1}</span>
                    <span class="emp-order-label">${escHtml(col.label)}</span>
                    <button type="button" class="emp-order-remove" data-key="${col.key}">✕</button>
                </div>`;
                $list.append(item);
            });
        }

        $('#csvOrderList').on('click', '.emp-order-remove', function () {
            const key = $(this).data('key');
            checkedKeys.delete(key);
            $('.emp-col-item[data-key="' + key + '"]').removeClass('checked');
            columnOrder = columnOrder.filter(c => c.key !== key);
            renderOrderList();
            updateCount();
        });

        // Drag & Drop（順序変更）
        $('#csvOrderList').on('dragstart', '.emp-order-item', function (e) {
            dragSrc = this;
            $(this).addClass('dragging');
            e.originalEvent.dataTransfer.setData('text/plain', $(this).data('key'));
        });
        $('#csvOrderList').on('dragend', '.emp-order-item', function () {
            $(this).removeClass('dragging');
        });
        $('#csvOrderList').on('dragover', '.emp-order-item', function (e) {
            e.preventDefault();
        });
        $('#csvOrderList').on('drop', '.emp-order-item', function (e) {
            e.preventDefault();
            const fromKey = e.originalEvent.dataTransfer.getData('text/plain');
            const toKey = $(this).data('key');
            if (fromKey === toKey) return;
            const fi = columnOrder.findIndex(c => c.key === fromKey);
            const ti = columnOrder.findIndex(c => c.key === toKey);
            const [item] = columnOrder.splice(fi, 1);
            columnOrder.splice(ti, 0, item);
            renderOrderList();
        });

        function updateCount() {
            $('#csvSelectedCount').text(checkedKeys.size + ' 項目');
            $('#sumCols').text(checkedKeys.size + ' 列');
        }

        // 出力設定
        $('.emp-format-btn[data-format]').on('click', function () {
            outputFormat = $(this).data('format');
            $('.emp-format-btn[data-format]').removeClass('active');
            $(this).addClass('active');
            updateSummary();
        });
        $('.emp-format-btn[data-encoding]').on('click', function () {
            encoding = $(this).data('encoding');
            $('.emp-format-btn[data-encoding]').removeClass('active');
            $(this).addClass('active');
            updateSummary();
        });
        $('.emp-format-btn[data-header]').on('click', function () {
            withHeader = $(this).data('header') === 1;
            $('.emp-format-btn[data-header]').removeClass('active');
            $(this).addClass('active');
            $('#sumHeader').text(withHeader ? 'あり' : 'なし');
        });
        function updateSummary() {
            $('#sumFormat').text(outputFormat.toUpperCase() + ' / ' + (encoding === 'sjis' ? 'Shift-JIS' : 'UTF-8'));
        }

        // CSV出力実行
        $('#csvExportBtn').on('click', function () {
            if (!checkedKeys.size) { showToast('出力する項目を1つ以上選択してください', 'error'); return; }
            const keys = columnOrder.length ? columnOrder.map(c => c.key) : Array.from(checkedKeys);
            const $btn = $(this).prop('disabled', true).text('生成中...');
            const $form = $('<form method="POST" action="' + empData.ajaxUrl + '" style="display:none;">');
            const fields = {
                action: 'emp_csv_export',
                nonce: empData.csvNonce,
                format: outputFormat,
                encoding: encoding,
                with_header: withHeader ? 1 : 0,
                filename: $('#csvFilename').val() || '社員情報_出力',
                is_active: $('#csvFilterStatus').val(),
                affiliation_id: $('#csvFilterAffil').val(),
                department_id: $('#csvFilterDept').val(),
                hire_date_from: $('#csvDateFrom').val(),
                hire_date_to: $('#csvDateTo').val(),
            };
            Object.keys(fields).forEach(function (k) {
                $form.append($('<input type="hidden">').attr('name', k).val(fields[k]));
            });
            keys.forEach(function (k) {
                $form.append($('<input type="hidden" name="column_keys[]">').val(k));
            });
            $('body').append($form);
            $form.submit().remove();
            setTimeout(() => $btn.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> CSV出力を実行'), 2000);
        });

        // ===== テンプレート =====

        // テンプレートカードのクリック（読み込み）
        $('#csvTplGrid').on('click', '.emp-tpl-card', function (e) {
            if ($(e.target).is('.emp-tpl-icon-btn')) return;
            const keys = JSON.parse($(this).data('tpl-keys') || '[]');
            checkedKeys = new Set(keys);
            $('.emp-col-item').each(function () {
                const key = $(this).data('key');
                $(this).toggleClass('checked', checkedKeys.has(key));
            });
            columnOrder = keys.map(k => ({ key: k, label: COL_LABELS[k] || k }));
            renderOrderList();
            updateCount();
            $('.emp-tpl-card').removeClass('active');
            $(this).addClass('active');
            showToast('「' + $(this).find('.emp-tpl-name').text() + '」を読み込みました', 'success');
        });

        // テンプレート保存ダイアログ
        $('#csvSaveTpl').on('click', function () {
            if (!checkedKeys.size) { showToast('保存する項目を選択してください', 'error'); return; }
            $('#csvTplName').val('');
            $('#csvTplDialog').fadeIn(150);
            setTimeout(() => $('#csvTplName').focus(), 100);
        });
        $('#csvTplDialogCancel').on('click', () => $('#csvTplDialog').fadeOut(150));
        $('#csvTplDialog').on('click', function (e) { if ($(e.target).is(this)) $(this).fadeOut(150); });

        $('#csvTplDialogSave').on('click', function () {
            const name = $('#csvTplName').val().trim();
            if (!name) { $('#csvTplName').focus(); return; }
            const keys = columnOrder.length ? columnOrder.map(c => c.key) : Array.from(checkedKeys);
            empAjax('emp_csv_save_template', { nonce: empData.csvNonce, name: name, column_keys: keys }, function (res) {
                if (res.success) {
                    $('#csvTplDialog').fadeOut(150);
                    showToast('「' + name + '」を保存しました', 'success');
                    reloadTemplates();
                } else {
                    showToast(res.data.message, 'error');
                }
            });
        });

        // リネームダイアログ
        $('#csvTplGrid').on('click', '.emp-tpl-rename', function (e) {
            e.stopPropagation();
            const id = $(this).data('id');
            const name = $(this).closest('.emp-tpl-card').find('.emp-tpl-name').text();
            $('#csvRenameId').val(id);
            $('#csvRenameInput').val(name);
            $('#csvRenameDialog').fadeIn(150);
            setTimeout(() => $('#csvRenameInput').focus().select(), 100);
        });
        $('#csvRenameCancel').on('click', () => $('#csvRenameDialog').fadeOut(150));
        $('#csvRenameDialog').on('click', function (e) { if ($(e.target).is(this)) $(this).fadeOut(150); });

        $('#csvRenameConfirm').on('click', function () {
            const id = $('#csvRenameId').val();
            const name = $('#csvRenameInput').val().trim();
            if (!name) return;
            empAjax('emp_csv_update_template', { nonce: empData.csvNonce, id: id, name: name }, function (res) {
                if (res.success) {
                    $('#csvRenameDialog').fadeOut(150);
                    showToast('テンプレート名を変更しました', 'success');
                    reloadTemplates();
                } else {
                    showToast(res.data.message, 'error');
                }
            });
        });

        // テンプレート削除
        $('#csvTplGrid').on('click', '.emp-tpl-delete', function (e) {
            e.stopPropagation();
            const id = $(this).data('id');
            const name = $(this).closest('.emp-tpl-card').find('.emp-tpl-name').text();
            if (!confirm('「' + name + '」を削除しますか？')) return;
            empAjax('emp_csv_delete_template', { nonce: empData.csvNonce, id: id }, function (res) {
                if (res.success) { showToast('テンプレートを削除しました', 'success'); reloadTemplates(); }
                else showToast(res.data.message, 'error');
            });
        });

        // テンプレート一覧の再描画
        function reloadTemplates() {
            empAjax('emp_csv_get_templates', { nonce: empData.csvNonce }, function (res) {
                if (!res.success) return;
                const $grid = $('#csvTplGrid').empty();
                $('#csvTplCount').text(res.data.length + ' 件');
                if (!res.data.length) {
                    $grid.append('<div class="emp-tpl-empty">テンプレートがありません。</div>');
                    return;
                }
                res.data.forEach(function (tpl) {
                    const keys = JSON.parse(tpl.column_keys || '[]');
                    const count = keys.length;
                    const previews = keys.slice(0, 4);
                    const more = count > 4 ? `<span class="emp-tpl-tag">+${count - 4}</span>` : '';
                    const tagsHtml = previews.map(k => `<span class="emp-tpl-tag">${escHtml(COL_LABELS[k] || k)}</span>`).join('');
                    const card = `
                    <div class="emp-tpl-card" data-tpl-id="${tpl.id}" data-tpl-keys="${escAttr(tpl.column_keys)}">
                        <div class="emp-tpl-top">
                            <div class="emp-tpl-name">${escHtml(tpl.name)}</div>
                            <div class="emp-tpl-actions">
                                <button class="emp-tpl-icon-btn emp-tpl-rename" data-id="${tpl.id}" title="名前変更">✏</button>
                                <button class="emp-tpl-icon-btn emp-tpl-delete"  data-id="${tpl.id}" title="削除">✕</button>
                            </div>
                        </div>
                        <div class="emp-tpl-meta"><span class="emp-tpl-count">${count} 列</span></div>
                        <div class="emp-tpl-tags">${tagsHtml}${more}</div>
                    </div>`;
                    $grid.append(card);
                });
            });
        }

        // Enter キーでダイアログ確定
        $('#csvTplName').on('keydown', e => { if (e.key === 'Enter') $('#csvTplDialogSave').click(); });
        $('#csvRenameInput').on('keydown', e => { if (e.key === 'Enter') $('#csvRenameConfirm').click(); });
        $(document).on('keydown', e => { if (e.key === 'Escape') { $('#csvTplDialog, #csvRenameDialog').fadeOut(150); } });
    }

    // =====================================================
    //  ユーティリティ
    // =====================================================
    function escHtml(str) {
        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function escAttr(str) {
        return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    // =====================================================
    //  CSVインポートページ（3種対応）
    // =====================================================

    if ($('#emp-import-page').length) {

        const parsedRowsMap = { basic: [], career: [], dependent: [] };

        const previewHeaders = {
            basic: ['行', '社員コード', '氏名', 'フリガナ', '性別', '生年月日', '入社日', '所属', '部署', '雇用区分', '週勤務日数', '健康保険No.', '状態'],
            career: ['行', '社員コード', '種別', '日付/年/月', '学校名・会社名・資格名', '学科/部署', '役職', '状態'],
            dependent: ['行', '社員コード', '氏名', 'フリガナ', '続柄', '生年月日', '状態'],
        };

        function previewCols(type, row) {
            if (type === 'basic')
                // col13=雇用区分 col14=週勤務日数 col24=健康保険No（2列追加で+2ずれ）
                return [row[0] || '', row[1] || '', row[2] || '', row[3] || '', row[4] || '', row[6] || '', row[8] || '', row[13] || '', row[14] || '', row[24] || ''];
            if (type === 'career') {
                const dateStr = row[2] ? row[2] : (row[3] ? row[3] + '年' + row[4] + '月' : '');
                return [row[0] || '', row[1] || '', dateStr, row[5] || '', row[6] || '', row[7] || ''];
            }
            if (type === 'dependent')
                return [row[0] || '', row[1] || '', row[2] || '', row[3] || '', row[4] || ''];
            return [];
        }

        // タブ切り替え
        $('.emp-import-tab').on('click', function () {
            const tab = $(this).data('tab');
            $('.emp-import-tab').removeClass('active');
            $(this).addClass('active');
            $('.emp-import-panel').removeClass('active');
            $('.emp-import-panel[data-panel="' + tab + '"]').addClass('active');
        });

        // ファイル選択
        $(document).on('change', '.emp-import-file', function () {
            if (this.files && this.files[0]) handleFile(this.files[0], $(this).data('type'));
        });

        // ドラッグ＆ドロップ
        $(document).on('dragover', '.emp-drop-zone', function (e) {
            e.preventDefault();
            $(this).addClass('emp-drop-active');
        }).on('dragleave', '.emp-drop-zone', function () {
            $(this).removeClass('emp-drop-active');
        }).on('drop', '.emp-drop-zone', function (e) {
            e.preventDefault();
            $(this).removeClass('emp-drop-active');
            const type = $(this).attr('id').replace('empDropZone-', '');
            const file = e.originalEvent.dataTransfer.files[0];
            if (file) handleFile(file, type);
        });

        function handleFile(file, type) {
            if (!file.name.match(/\.csv$/i)) { showToast('CSVファイルを選択してください', 'error'); return; }
            const enc = $('.emp-import-encoding[data-type="' + type + '"]').val() || 'UTF-8';
            const reader = new FileReader();
            reader.onload = function (e) {
                let text = e.target.result;
                if (text.charCodeAt(0) === 0xFEFF) text = text.slice(1);
                const rows = parseCSV(text);
                if (rows.length < 2) { showToast('データ行が見つかりませんでした', 'error'); return; }
                parsedRowsMap[type] = rows;
                $('#empDropZone-' + type + ' .emp-file-name').text('選択済み：' + file.name);
                $('.emp-import-preview-btn[data-type="' + type + '"]').prop('disabled', false);
                showToast((rows.length - 1) + '行を読み込みました', 'success');
            };
            reader.readAsText(file, enc);
        }

        function parseCSV(text) {
            const rows = [];
            text.split(/\r?\n/).forEach(function (line) {
                if (!line.trim()) return;
                const cols = []; let cur = '', inQ = false;
                for (let i = 0; i < line.length; i++) {
                    const c = line[i];
                    if (c === '"') { if (inQ && line[i + 1] === '"') { cur += '"'; i++; } else inQ = !inQ; }
                    else if (c === ',' && !inQ) { cols.push(cur); cur = ''; }
                    else cur += c;
                }
                cols.push(cur);
                rows.push(cols);
            });
            return rows;
        }

        // プレビュー
        $(document).on('click', '.emp-import-preview-btn', function () {
            const type = $(this).data('type');
            const rows = parsedRowsMap[type];
            if (!rows.length) return;

            const dataRows = rows.slice(1).filter(r => {
                const first = (r[0] || '').trim();
                return first.indexOf('#') !== 0 && r.some(c => c.trim() !== '');
            });

            const headers = previewHeaders[type];
            const $headRow = $('#empPreviewTable-' + type + ' .emp-preview-head-row').empty();
            headers.forEach(h => $headRow.append('<th>' + escHtml(h) + '</th>'));

            const $tbody = $('#empPreviewTable-' + type + ' .emp-preview-tbody').empty();
            let errorCount = 0;
            const errors = [];

            dataRows.forEach(function (row, i) {
                const code = (row[0] || '').trim();
                const name = (row[1] || '').trim();
                let statusClass = 'ok', statusMsg = '✓ OK';

                if (!code) { errors.push((i + 2) + '行目：社員コードが空です'); statusClass = 'error'; statusMsg = '⚠ エラー'; errorCount++; }
                else if (type !== 'career' && !name) { errors.push((i + 2) + '行目：氏名が空です'); statusClass = 'error'; statusMsg = '⚠ エラー'; errorCount++; }

                const cols = previewCols(type, row);
                let td = '<td>' + (i + 2) + '</td>';
                cols.forEach(c => { td += '<td>' + escHtml(c) + '</td>'; });
                td += '<td class="emp-import-status-' + statusClass + '">' + statusMsg + '</td>';
                $tbody.append('<tr>' + td + '</tr>');
            });

            const valid = dataRows.length - errorCount;
            $('#empSummary-' + type).text(`全 ${dataRows.length} 行 / エラー ${errorCount} 行 / 登録予定 ${valid} 行`);
            $('#empPreviewSection-' + type + ' .emp-import-notice').text(`「インポートを実行する」で ${valid} 件を登録します。`);

            const $errDiv = $('#empErrors-' + type);
            if (errors.length) {
                $errDiv.find('.emp-import-error-list').html(errors.map(e => '<li>' + escHtml(e) + '</li>').join(''));
                $errDiv.show();
            } else { $errDiv.hide(); }

            $('#empPreviewSection-' + type).show();
            $('#empResult-' + type).hide();
            $('html,body').animate({ scrollTop: $('#empPreviewSection-' + type).offset().top - 60 }, 300);
        });

        // インポート実行
        $(document).on('click', '.emp-import-run-btn', function () {
            const type = $(this).data('type');
            const rows = parsedRowsMap[type].slice(1);
            const dupMode = $('[name="dup_mode_' + type + '"]:checked').val();
            const $btn = $(this);

            $btn.prop('disabled', true).text('処理中...');

            $.post(empData.ajaxUrl, {
                action: 'emp_csv_import',
                nonce: empData.importNonce,
                csv_type: type,
                rows: rows,
                dup_mode: dupMode,
            }, function (res) {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> インポートを実行する');
                if (!res.success) { showToast(res.data.message || 'エラーが発生しました', 'error'); return; }

                const d = res.data;
                let html = '<div class="emp-notice emp-notice-success" style="font-size:.95rem;font-weight:700;">✓ インポートが完了しました</div>';
                html += '<table class="emp-summary-table">';
                if (d.success !== undefined) html += '<tr><td>新規登録</td><td><strong>' + d.success + ' 件</strong></td></tr>';
                if (d.updated !== undefined) html += '<tr><td>上書き更新</td><td><strong>' + d.updated + ' 件</strong></td></tr>';
                if (d.skipped !== undefined) html += '<tr><td>スキップ</td><td><strong>' + d.skipped + ' 件</strong></td></tr>';
                if (d.errors && d.errors.length) html += '<tr><td style="color:var(--emp-danger)">エラー</td><td style="color:var(--emp-danger)"><strong>' + d.errors.length + ' 件</strong></td></tr>';
                html += '</table>';
                if (d.errors && d.errors.length) {
                    html += '<div style="margin-top:.75rem;font-size:.82rem;color:var(--emp-danger);"><strong>エラー詳細：</strong><ul style="padding-left:1.25rem;margin-top:.35rem;">' +
                        d.errors.map(e => '<li>' + escHtml(e) + '</li>').join('') + '</ul></div>';
                }
                html += '<a href="' + empData.ajaxUrl.replace('admin-ajax.php', 'admin.php') + '?page=employee-manager" class="emp-btn emp-btn-primary" style="margin-top:.75rem;">社員一覧を確認する</a>';

                $('#empResult-' + type + ' .emp-import-result-body').html(html);
                $('#empResult-' + type).show();
                $('#empPreviewSection-' + type).hide();
                showToast('✓ インポート完了', 'success');
            });
        });

        // キャンセル
        $(document).on('click', '.emp-import-cancel-btn', function () {
            $('#empPreviewSection-' + $(this).data('type')).hide();
        });
    }

})(jQuery);