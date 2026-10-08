# 07 公開API・AJAX仕様

- 読者: エンジニア（他プラグインの開発者含む）
- 定義元: 公開API `employee-manager.php`／AJAX登録 `admin/class-admin-menu.php`（`register_ajax_hooks`）

---

# Part A. 公開API（PHP関数）

他プラグインから `employee-manager` を有効化した状態で呼び出せるグローバル関数。権限チェックは**しない**（呼び出し側の責任）。管理画面（`is_admin()`）以外のコンテキストでもクラスは読み込まれているため利用可能。 [コード確認]

| 関数 | 戻り値 | 内容 |
|---|---|---|
| `emp_get_active_employees( $args = [] )` | 社員オブジェクトの配列 | 在籍中（`is_active=1`）の社員一覧 |
| `emp_get_employee_by_id( $employee_id )` | オブジェクト／null | IDで1件（関連データ込み） |
| `emp_get_employee_by_code( $employee_code )` | オブジェクト／null | 社員コードで1件（関連データ込み） |
| `emp_get_crew_code_history( $employee_id )` | 配列 | 乗組員コード履歴 |
| `emp_get_crew_codes_for_period( $employee_id, $start_date, $end_date )` | 配列（連想配列の配列） | 期間と重なる乗組員コード |
| `emp_get_affiliations()` | 配列 | 有効な所属マスタ |
| `emp_get_departments()` | 配列 | 有効な部署マスタ |
| `emp_get_positions()` | 配列 | 有効な役職マスタ |
| `emp_get_job_types()` | 配列 | 有効な職種マスタ |

## A-1. `emp_get_active_employees( $args )`
引数（すべて任意）:
| キー | 型 | 説明 |
|---|---|---|
| `affiliation_id` | int | 所属IDで絞り込み |
| `department_id` | int | 部署IDで絞り込み |
| `orderby` | string | `employee_code`（既定）／`name`／`hire_date`。不正値は `employee_code` |

戻り値の各要素（stdClass）: `id, employee_code, name, name_kana, hire_date, is_active, crew_code, employment_type, weekly_work_days, affiliation_name, department_name, position_name, job_type_name`。
並び: `ORDER BY CAST(m.{orderby} AS UNSIGNED), m.{orderby}`（数値として読める社員コードは数値順）。**保険・学歴等の関連データは含まない**（それらが必要なら `emp_get_employee_by_id`）。

```php
$employees = emp_get_active_employees( array( 'affiliation_id' => 2, 'orderby' => 'name' ) );
foreach ( $employees as $emp ) {
    echo $emp->name . '（' . $emp->employee_code . '）';
}
```

## A-2. `emp_get_employee_by_id( $id )` / `emp_get_employee_by_code( $code )`
`emp_master` の全カラム＋名称（`affiliation_name` `department_name` `position_name` `job_type_name`）＋ `insurance`, `retirement`, `educations[]`, `careers[]`, `qualifications[]`, `dependents[]`, `crew_code_history[]` を含むオブジェクト。無ければ `null`。
- コード版は `sanitize_text_field` された社員コードで完全一致検索。
- **注意**: `my_number`（個人番号）もそのまま含まれる（平文）。取り扱いに注意。

## A-3. `emp_get_crew_code_history( $employee_id )`
行（stdClass）: `id, employee_id, crew_code, valid_from, valid_to, is_current, created_at, updated_at`。並び: 現行が先、次に開始日が新しい順（開始日NULLは最も古い扱い）。履歴テーブルが無い場合は空配列。

## A-4. `emp_get_crew_codes_for_period( $employee_id, $start_date, $end_date )`
- 日付は `YYYY-MM-DD`。不正／開始>終了／社員ID未指定は空配列。
- 条件: `(valid_from IS NULL OR valid_from <= $end) AND (valid_to IS NULL OR valid_to >= $start)`。
- 戻り値: `[ [ 'crew_code' => '..', 'valid_from' => '..'|null, 'valid_to' => '..'|null, 'is_current' => '0'|'1' ], ... ]`（開始日昇順）。
- 履歴行が1件もヒットしなければ、`emp_master.crew_code`（空でなければ）を `valid_from=null, valid_to=null, is_current=1` の1要素として返す。

勤怠管理など外部システムは、これを使って「対象月に有効な全コードをまとめて」1人の社員として集計できる。 [リポジトリ外：引き継ぎ書]

## A-5. マスタ一覧
`emp_get_*()` は `SELECT * ... WHERE is_active = 1 ORDER BY sort_order ASC, id ASC`。各行: `id, name, sort_order, is_active, created_at, updated_at`。

---

# Part B. AJAXアクション（管理画面内部API）

- エンドポイント: `wp-admin/admin-ajax.php`（POST。ひな形DLのみGET）
- 認証: ログイン必須（`wp_ajax_*` のみ登録＝未ログイン用 `wp_ajax_nopriv_*` は**ない**）
- 共通: `nonce` パラメータで `check_ajax_referer` を検証。権限不足は `wp_die(-1)`（応答は `-1`）。インポート実行のみJSONエラー「権限がありません」。
- 応答: 多くは `wp_send_json_success( data )`／`wp_send_json_error( ['message' => ...] )` の形式（`{"success":bool,"data":...}`）。CSV出力のみファイルまたはエラーページ。
- JSへの受け渡し（`wp_localize_script` の `empData`）: `ajaxUrl, masterNonce, employeeNonce, csvNonce, importNonce, currentPage, pluginUrl`。

## B-1. 一覧

| アクション | 権限 | nonce名 | 主なパラメータ | 応答 `data` |
|---|---|---|---|---|
| `emp_master_get_list` | access | emp_master_nonce | `master_type` | マスタ全件（無効含む）配列 |
| `emp_master_insert` | edit | emp_master_nonce | `master_type`, `name`, `sort_order` | `{id, message}`（`is_active`=1で登録） |
| `emp_master_update` | edit | emp_master_nonce | `master_type`, `id`, `name`?, `sort_order`?, `is_active`? | `{message}` |
| `emp_master_delete` | edit | emp_master_nonce | `master_type`, `id` | `{message}`／使用中エラー |
| `emp_employee_get_list` | access | emp_employee_nonce | `search`, `affiliation_id`, `department_id`, `is_active`, `per_page`, `page`, `orderby`, `order` | `{items:[], total}` |
| `emp_employee_get_one` | access | emp_employee_nonce | `id` | 社員1件（関連込み） |
| `emp_employee_save` | edit | emp_employee_nonce | `id`(0=新規), `data`(配列) | `{id, message}` |
| `emp_employee_toggle` | edit | emp_employee_nonce | `id`, `is_active` | `{message}` |
| `emp_crew_history_add` | edit | emp_employee_nonce | `employee_id`, `crew_code` | `{message}` |
| `emp_crew_history_update` | **manage** | emp_employee_nonce | `history_id`, `employee_id`, `crew_code`, `valid_from`, `valid_to` | `{message}` |
| `emp_get_stats` | access | emp_employee_nonce | — | `{total, active_total, affiliations:[{id,name,active_count}]}` |
| `emp_csv_export` | access | emp_csv_nonce | `column_keys[]`, `format`, `encoding`, `with_header`, `filename`, `is_active`, `affiliation_id`, `department_id`, `hire_date_from`, `hire_date_to` | ファイル（エラー時HTML） |
| `emp_csv_get_templates` | access | emp_csv_nonce | — | テンプレート配列 |
| `emp_csv_save_template` | edit | emp_csv_nonce | `name`, `column_keys[]` | `{id, message}` |
| `emp_csv_update_template` | edit | emp_csv_nonce | `id`, `name` | `{message}` |
| `emp_csv_delete_template` | edit | emp_csv_nonce | `id` | `{message}` |
| `emp_csv_template_download` | access | emp_import_nonce | GET `csv_type` | CSVファイル（ひな形） |
| `emp_csv_import` | edit | emp_import_nonce | `csv_type`, `rows[][]`, `dup_mode` | `{success, updated, skipped, errors[]}`（種別で一部のみ） |

権限略称: **access**=`access_custom_plugins`、**edit**=`edit_custom_plugins`、**manage**=`manage_custom_plugin_settings`。

備考:
- 取り込み結果のキー: ①= `success`(新規)/`updated`/`skipped`/`errors`、②③= `success`/`errors`。
- `emp_employee_get_list` の `is_active` は空文字なら全件。`per_page`/`page` は整数化、`orderby` は `sanitize_key` 後に許可値のみ（`employee_code, name, hire_date, id`）、`order` は ASC／DESC。
- `emp_employee_save` の `data` に含める主なキー: 基本項目（`employee_code`, `name`, ... ）、保険項目、`retirement_date`、`educations[]`, `careers[]`, `qualifications[]`, `dependents[]`（各行は連想配列）。**行の連想配列のキーはそのまま列名として INSERT に使われる**ので、定義外のキーを送るとINSERTが失敗する（サーバー側でホワイトリスト化されていない）。 [コード確認]
- `emp_employee_get_list` の応答 `items[]`: `id, employee_code, name, name_kana, gender, birthdate, hire_date, is_active, crew_code, employment_type, weekly_work_days, affiliation_name, department_name, position_name, job_type_name`。

## B-2. JS側の呼び出し例
```js
$.post(empData.ajaxUrl, {
  action: 'emp_employee_toggle',
  nonce: empData.employeeNonce,
  id: 5,
  is_active: 0
}, function (res) { /* res.success, res.data.message */ });
```

## B-3. 新しいAJAXを追加する手順（開発者向け）
1. 処理を `includes/class-*.php` に `public static function ajax_xxx()` として実装（先頭で `check_ajax_referer`、`current_user_can`）。
2. `admin/class-admin-menu.php` の `register_ajax_hooks()` に `add_action( 'wp_ajax_emp_xxx', ... )` を追加。
3. 必要なら nonce を `enqueue_assets()` の `wp_localize_script` に追加。
4. `admin.js` の該当画面ブロック（`if ($('#emp-xxx-page').length) { ... }`）から `empAjax('emp_xxx', {...}, cb)` を呼ぶ。
