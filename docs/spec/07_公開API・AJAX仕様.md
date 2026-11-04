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

---

# Part C. REST API（Excel Power Query 向け）

定義元: `includes/class-rest-api.php`

## C-1. 従業員マスタAPI

```
GET /wp-json/employee-manager/v1/employees
```

| パラメータ | 値 | 既定 | 説明 |
|---|---|---|---|
| `status` | `active` / `retired` / `all` | `active` | 在籍区分で絞り込み |

レスポンス（JSON配列。社員コードの数値順）:

```json
[
  {
    "employee_code": "101",
    "name": "山田 太郎",
    "category": "chokyo",
    "job_type_name": "長距離",
    "affiliation_name": "本社"
  }
]
```

| 項目 | 出典 | 備考 |
|---|---|---|
| `employee_code` | `emp_master.employee_code` | 常に文字列（先頭0を保持） |
| `name` | `emp_master.name` | |
| `category` | 職種名から算出 | 職種名に「長距離」を含む → `chokyo`、それ以外（未設定含む） → `jiba` |
| `job_type_name` | `mst_job_type.name` | 未設定は空文字 |
| `affiliation_name` | `mst_affiliation.name` | 未設定は空文字 |

上記以外（個人番号・住所・電話・保険等）は返さない。

`category` の対応はフィルターで変更できる:

```php
add_filter( 'emp_rest_category_map', function ( $map ) {
    $map['中距離'] = 'chukyo';   // 職種名に含まれる文字列 => category
    return $map;
} );
```

## C-2. 認証・権限

社内ツール共通の API 発行ルール（勤怠管理リポジトリ `docs/api-guidelines.md`）に準拠する。

| 項目 | 内容 |
|---|---|
| 認証方式 | WordPress の**アプリケーションパスワード**（Basic 認証・HTTPS 必須） |
| 必要な権限 | `emp_export_data` **または** `access_custom_plugins`（どちらか一方でよい） |
| ロール | 「**API取得**」（スラッグ `api_reader`）。勤怠管理など他ツールと共有 |
| 未ログイン | 401（コード `emp_forbidden`） |
| 権限不足 | 403（コード `emp_forbidden`） |

- ロール「API取得」は `init` フック（`EMP_REST_API::ensure_role()`）で確認する。**無ければ作成**し、**既にあれば作り直さず**、ケイパビリティ `emp_export_data` だけを追加する（FTP デプロイでは有効化フックが走らないため）。
- 同じ「API取得」ロールでも、プラグインごとにケイパビリティが分かれる（勤怠管理は `am_export_data`）。社員管理のデータを見せたくないユーザーには、ロールではなくケイパビリティ単位で調整する。
- 「API取得」ロールは社員の登録・編集・管理画面の権限（`edit_custom_plugins` など）を持たない。
- `access_custom_plugins` を持つ既存の管理画面利用者は、そのまま API を使える。
- プラグイン削除時（`uninstall.php`）は、共有ロールは残し、`emp_export_data` だけを外す。

### 利用手順

1. Excel 担当者ごとに WordPress ユーザーを作り、ロール「API取得」を付ける（共有アカウントにしない）。
2. そのユーザーのプロフィール画面で「アプリケーションパスワード」を発行して控える（発行直後の 1 回しか表示されない）。
3. Excel → データ → Webから → URL に `https://<サイト>/wp-json/employee-manager/v1/employees` を入力。
4. 認証方法で「基本」を選び、ユーザー名と発行したアプリケーションパスワードを入力。
5. Power Query エディターで「テーブルへの変換」→ 列を展開。
6. 退職・異動時は、そのユーザーのアプリケーションパスワードを失効する。

Power Query (M) 例:

```m
let
    Source = Json.Document(Web.Contents("https://example.com/wp-json/employee-manager/v1/employees", [Query = [status = "active"]])),
    Table  = Table.FromRecords(Source, {"employee_code", "name", "category", "job_type_name", "affiliation_name"}, MissingField.UseNull),
    Typed  = Table.TransformColumnTypes(Table, {{"employee_code", type text}, {"name", type text}, {"category", type text}, {"job_type_name", type text}, {"affiliation_name", type text}})
in
    Typed
```

動作確認:

```bash
curl -u 'USER:APP_PASSWORD' 'https://ドメイン/wp-json/employee-manager/v1/employees'
```

注意: XSERVER などで `Authorization` ヘッダーが PHP に渡らず 401 になる場合は、`.htaccess` に `RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]` を追記する。
