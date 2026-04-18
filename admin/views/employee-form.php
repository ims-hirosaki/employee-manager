<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$emp_id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
$emp    = $emp_id > 0 ? EMP_Employee::get_by_id( $emp_id ) : null;
$is_new = ! $emp;
$page_title = $is_new ? '新規社員登録' : '社員情報編集：' . esc_html( $emp->name );
?>
<div class="wrap emp-wrap" id="emp-form-page"
     data-emp-id="<?php echo esc_attr( $emp_id ); ?>"
     data-emp-json="<?php echo $emp ? esc_attr( wp_json_encode( $emp ) ) : ''; ?>">

    <h1><?php echo esc_html( $page_title ); ?></h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=employee-manager' ) ); ?>"
       class="emp-back-link">← 社員一覧に戻る</a>

    <!-- ステップインジケーター -->
    <div class="emp-steps">
        <div class="emp-step active" data-step="1"><span>1</span> 基本情報</div>
        <div class="emp-step"        data-step="2"><span>2</span> 保険・退職</div>
        <div class="emp-step"        data-step="3"><span>3</span> 学歴・職歴・資格</div>
        <div class="emp-step"        data-step="4"><span>4</span> 扶養者</div>
        <div class="emp-step"        data-step="5"><span>5</span> 確認</div>
    </div>
    <div class="emp-step-progress"><div class="emp-step-fill" id="empStepFill" style="width:20%"></div></div>

    <form id="empForm" novalidate>
        <?php wp_nonce_field( 'emp_employee_nonce', 'emp_nonce' ); ?>
        <input type="hidden" id="empId" value="<?php echo esc_attr( $emp_id ); ?>">

        <!-- ===== STEP 1: 基本情報 ===== -->
        <div class="emp-step-panel active" data-panel="1">
            <div class="emp-card">
                <div class="emp-card-title">社員マスタ基本情報</div>
                <div class="emp-form-grid">
                    <div class="emp-field emp-field-half">
                        <label class="emp-label emp-required">社員コード</label>
                        <input type="text" name="employee_code" class="emp-input" placeholder="例：0001" required>
                    </div>
                    <div class="emp-field emp-field-half">
                        <label class="emp-label emp-required">氏名</label>
                        <input type="text" name="name" class="emp-input" placeholder="例：山田 太郎" required>
                    </div>
                    <div class="emp-field emp-field-half">
                        <label class="emp-label">フリガナ</label>
                        <input type="text" name="name_kana" class="emp-input" placeholder="例：ヤマダ タロウ">
                    </div>
                    <div class="emp-field emp-field-quarter">
                        <label class="emp-label">性別</label>
                        <select name="gender" class="emp-select">
                            <option value="">選択なし</option>
                            <option value="男性">男性</option>
                            <option value="女性">女性</option>
                            <option value="その他">その他</option>
                        </select>
                    </div>
                    <div class="emp-field emp-field-quarter">
                        <label class="emp-label">血液型</label>
                        <select name="blood_type" class="emp-select">
                            <option value="">選択なし</option>
                            <option value="A">A</option>
                            <option value="B">B</option>
                            <option value="O">O</option>
                            <option value="AB">AB</option>
                        </select>
                    </div>
                    <div class="emp-field emp-field-quarter">
                        <label class="emp-label">生年月日</label>
                        <input type="date" name="birthdate" class="emp-input">
                    </div>
                    <div class="emp-field emp-field-quarter">
                        <label class="emp-label">入社日</label>
                        <input type="date" name="hire_date" class="emp-input">
                    </div>
                    <div class="emp-field emp-field-half">
                        <label class="emp-label">個人番号（マイナンバー）</label>
                        <input type="password" name="my_number" class="emp-input" placeholder="半角数値12桁" maxlength="12" autocomplete="off">
                        <span class="emp-hint">保存時は暗号化されます</span>
                    </div>
                    <div class="emp-field emp-field-half">
                        <label class="emp-label">在籍状況</label>
                        <div class="emp-toggle-wrap">
                            <label class="emp-toggle">
                                <input type="checkbox" name="is_active" value="1" checked>
                                <span class="emp-toggle-slider"></span>
                            </label>
                            <span class="emp-toggle-label" id="isActiveLabel">在籍中</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="emp-card">
                <div class="emp-card-title">所属・役職</div>
                <div class="emp-form-grid">
                    <div class="emp-field emp-field-quarter">
                        <label class="emp-label">所属</label>
                        <select name="affiliation_id" class="emp-select" id="selAffiliation">
                            <option value="">選択なし</option>
                        </select>
                    </div>
                    <div class="emp-field emp-field-quarter">
                        <label class="emp-label">部署</label>
                        <select name="department_id" class="emp-select" id="selDepartment">
                            <option value="">選択なし</option>
                        </select>
                    </div>
                    <div class="emp-field emp-field-quarter">
                        <label class="emp-label">役職</label>
                        <select name="position_id" class="emp-select" id="selPosition">
                            <option value="">選択なし</option>
                        </select>
                    </div>
                    <div class="emp-field emp-field-quarter">
                        <label class="emp-label">職種</label>
                        <select name="job_type_id" class="emp-select" id="selJobType">
                            <option value="">選択なし</option>
                        </select>
                    </div>
                    <div class="emp-field emp-field-quarter">
                        <label class="emp-label">雇用区分</label>
                        <select name="employment_type" class="emp-select">
                            <option value="">選択なし</option>
                            <option value="正社員">正社員</option>
                            <option value="契約社員">契約社員</option>
                            <option value="パート・アルバイト">パート・アルバイト</option>
                        </select>
                    </div>
                    <div class="emp-field emp-field-quarter">
                        <label class="emp-label">週勤務日数</label>
                        <select name="weekly_work_days" class="emp-select">
                            <option value="">選択なし</option>
                            <option value="1">週1勤務</option>
                            <option value="2">週2勤務</option>
                            <option value="3">週3勤務</option>
                            <option value="4">週4勤務</option>
                            <option value="5">週5勤務</option>
                            <option value="6">週6勤務</option>
                        </select>
                    </div>
                    <div class="emp-field emp-field-half">
                        <label class="emp-label">乗組員コード</label>
                        <input type="text" name="crew_code" class="emp-input" placeholder="該当する場合のみ入力">
                        <span class="emp-hint">乗組員でない場合は空欄のままにしてください</span>
                    </div>
                </div>
            </div>

            <div class="emp-card">
                <div class="emp-card-title">住所・連絡先</div>
                <div class="emp-form-grid">
                    <div class="emp-field emp-field-quarter">
                        <label class="emp-label">郵便番号</label>
                        <input type="text" name="zip" class="emp-input" placeholder="例：030-0801" maxlength="8">
                    </div>
                    <div class="emp-field emp-field-three">
                        <label class="emp-label">住所</label>
                        <input type="text" name="address" class="emp-input" placeholder="都道府県から入力">
                    </div>
                    <div class="emp-field emp-field-third">
                        <label class="emp-label">自宅電話</label>
                        <input type="tel" name="tel_home" class="emp-input" placeholder="例：017-123-4567">
                    </div>
                    <div class="emp-field emp-field-third">
                        <label class="emp-label">携帯電話</label>
                        <input type="tel" name="tel_mobile" class="emp-input" placeholder="例：080-1234-5678">
                    </div>
                    <div class="emp-field emp-field-third">
                        <label class="emp-label">会社携帯</label>
                        <input type="tel" name="tel_company" class="emp-input" placeholder="例：080-9999-0000">
                    </div>
                </div>
            </div>

            <div class="emp-card">
                <div class="emp-card-title">緊急連絡先</div>
                <div class="emp-form-grid">
                    <div class="emp-field emp-field-third">
                        <label class="emp-label">氏名</label>
                        <input type="text" name="emergency_name" class="emp-input">
                    </div>
                    <div class="emp-field emp-field-third">
                        <label class="emp-label">電話番号</label>
                        <input type="tel" name="emergency_tel" class="emp-input">
                    </div>
                    <div class="emp-field emp-field-third">
                        <label class="emp-label">続柄</label>
                        <input type="text" name="emergency_relation" class="emp-input" placeholder="例：配偶者">
                    </div>
                </div>
            </div>

            <div class="emp-card">
                <div class="emp-card-title">備考</div>
                <textarea name="memo" class="emp-textarea" rows="4" placeholder="自由記入欄"></textarea>
            </div>
        </div>

        <!-- ===== STEP 2: 保険・退職 ===== -->
        <div class="emp-step-panel" data-panel="2">
            <div class="emp-card">
                <div class="emp-card-title">加入保険情報</div>
                <div class="emp-form-grid">
                    <div class="emp-field emp-field-half">
                        <label class="emp-label">健康保険番号</label>
                        <input type="text" name="health_no" class="emp-input">
                    </div>
                    <div class="emp-field emp-field-quarter">
                        <label class="emp-label">取得日</label>
                        <input type="date" name="health_date" class="emp-input">
                    </div>
                    <div class="emp-field emp-field-half">
                        <label class="emp-label">厚生年金番号</label>
                        <input type="text" name="pension_no" class="emp-input">
                    </div>
                    <div class="emp-field emp-field-quarter">
                        <label class="emp-label">取得日</label>
                        <input type="date" name="pension_date" class="emp-input">
                    </div>
                    <div class="emp-field emp-field-half">
                        <label class="emp-label">雇用保険番号</label>
                        <input type="text" name="employment_no" class="emp-input">
                    </div>
                    <div class="emp-field emp-field-quarter">
                        <label class="emp-label">取得日</label>
                        <input type="date" name="employment_date" class="emp-input">
                    </div>
                    <div class="emp-field emp-field-half">
                        <label class="emp-label">労災保険番号</label>
                        <input type="text" name="accident_no" class="emp-input">
                    </div>
                    <div class="emp-field emp-field-quarter">
                        <label class="emp-label">取得日</label>
                        <input type="date" name="accident_date" class="emp-input">
                    </div>
                </div>
            </div>
            <div class="emp-card">
                <div class="emp-card-title">退職情報</div>
                <div class="emp-form-grid">
                    <div class="emp-field emp-field-quarter">
                        <label class="emp-label">退職日</label>
                        <input type="date" name="retirement_date" class="emp-input">
                        <span class="emp-hint">在籍中の場合は空欄のままにしてください</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== STEP 3: 学歴・職歴・資格 ===== -->
        <div class="emp-step-panel" data-panel="3">
            <!-- 学歴 -->
            <div class="emp-card">
                <div class="emp-card-title-row">
                    <div class="emp-card-title">学歴</div>
                    <button type="button" class="emp-add-row-btn" data-target="educations">＋ 追加</button>
                </div>
                <div id="educationsRows" class="emp-rows-wrap">
                    <div class="emp-row-header emp-rows-header-3">
                        <span>卒業年月日</span><span>学校名</span><span>学科・専攻</span><span></span>
                    </div>
                </div>
            </div>
            <!-- 職歴 -->
            <div class="emp-card">
                <div class="emp-card-title-row">
                    <div class="emp-card-title">職歴</div>
                    <button type="button" class="emp-add-row-btn" data-target="careers">＋ 追加</button>
                </div>
                <div id="careersRows" class="emp-rows-wrap">
                    <div class="emp-row-header emp-rows-header-5">
                        <span>年</span><span>月</span><span>会社名</span><span>部署</span><span>役職</span><span></span>
                    </div>
                </div>
            </div>
            <!-- 資格 -->
            <div class="emp-card">
                <div class="emp-card-title-row">
                    <div class="emp-card-title">免許・資格</div>
                    <button type="button" class="emp-add-row-btn" data-target="qualifications">＋ 追加</button>
                </div>
                <div id="qualificationsRows" class="emp-rows-wrap">
                    <div class="emp-row-header emp-rows-header-2">
                        <span>資格・免許名</span><span>取得日</span><span></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== STEP 4: 扶養者 ===== -->
        <div class="emp-step-panel" data-panel="4">
            <div class="emp-card">
                <div class="emp-card-title-row">
                    <div class="emp-card-title">扶養者</div>
                    <button type="button" class="emp-add-row-btn" data-target="dependents">＋ 追加</button>
                </div>
                <div id="dependentsRows" class="emp-rows-wrap">
                    <div class="emp-row-header emp-rows-header-4">
                        <span>氏名</span><span>フリガナ</span><span>続柄</span><span>生年月日</span><span></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== STEP 5: 確認 ===== -->
        <div class="emp-step-panel" data-panel="5">
            <div class="emp-card">
                <div class="emp-card-title">入力内容の確認</div>
                <div id="empConfirmView" class="emp-confirm-wrap">
                    <!-- JS で生成 -->
                </div>
            </div>
        </div>

        <!-- ナビゲーションボタン：左＝登録/更新、右＝前へ/次へ -->
        <div class="emp-form-nav">
            <div class="emp-form-nav-left">
                <button type="submit" class="emp-btn emp-btn-success" id="empBtnSubmit">
                    <span class="dashicons dashicons-yes"></span>
                    <?php echo $is_new ? '登録する' : '更新する'; ?>
                </button>
            </div>
            <div class="emp-form-nav-right">
                <button type="button" class="emp-btn emp-btn-secondary" id="empBtnPrev" style="display:none;">
                    ← 前へ
                </button>
                <button type="button" class="emp-btn emp-btn-primary" id="empBtnNext">
                    次へ →
                </button>
            </div>
        </div>
    </form>
</div>