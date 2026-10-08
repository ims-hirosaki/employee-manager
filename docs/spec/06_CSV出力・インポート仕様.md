# 06 CSV出力・インポート仕様

- 読者: 利用者・エンジニア
- 実装: 出力 `includes/class-csv-export.php`、インポート `includes/class-csv-import.php`、画面 `admin/views/csv.php` `admin/views/employee-import.php`、動作 `admin/assets/admin.js`

---

# Part A. CSV出力

## A-1. 出力できる項目（全31項目、`EMP_CSV_Export::column_definitions()`）

出力項目キー（`key`）がテンプレート・AJAX・列順で使われる。ヘッダ行にはラベルが出力される。

| グループ | キー | ヘッダ（ラベル） | 取得元 |
|---|---|---|---|
| 基本情報 | `id` | 内部ID | emp_master.id |
| | `employee_code` | 社員コード | emp_master.employee_code |
| | `name` | 氏名 | emp_master.name |
| | `name_kana` | フリガナ | name_kana |
| | `gender` | 性別 | gender |
| | `birthdate` | 生年月日 | birthdate（YYYY-MM-DD） |
| | `blood_type` | 血液型 | blood_type |
| | `hire_date` | 入社日 | hire_date |
| | `is_active` | 在籍区分 | 1→「在籍中」、0→「退職」に変換 |
| 所属・役職 | `affiliation_name` | 所属 | mst_affiliation.name |
| | `department_name` | 部署 | mst_department.name |
| | `position_name` | 役職 | mst_position.name |
| | `job_type_name` | 職種 | mst_job_type.name |
| | `crew_code` | 乗組員コード | emp_master.crew_code（**現行コードのみ**。履歴は出ない） |
| 連絡先 | `zip` | 郵便番号 | zip |
| | `address` | 住所 | address |
| | `tel_home` | 自宅電話 | tel_home |
| | `tel_mobile` | 携帯電話 | tel_mobile |
| | `tel_company` | 会社携帯 | tel_company |
| | `emergency_name` | 緊急連絡先氏名 | emergency_name |
| | `emergency_tel` | 緊急連絡先電話 | emergency_tel |
| | `emergency_relation` | 緊急連絡先続柄 | emergency_relation |
| | `memo` | 備考 | memo |
| 加入保険 | `health_no` / `health_date` | 健康保険番号／健康保険取得日 | emp_insurance |
| | `pension_no` / `pension_date` | 厚生年金番号／厚生年金取得日 | |
| | `employment_no` / `employment_date` | 雇用保険番号／雇用保険取得日 | |
| | `accident_no` / `accident_date` | 労災保険番号／労災保険取得日 | |

**出力できないデータ**: マイナンバー、雇用区分、週勤務日数、退職日、学歴・職歴・資格・扶養者、乗組員コード履歴。 [コード確認]

保険項目を1つでも選んだ場合のみ `emp_insurance` を LEFT JOIN する。

## A-2. 絞り込み条件
| 条件 | 値 | 動作 |
|---|---|---|
| 在籍状況 | 空／`1`／`0` | 空=すべて、1=在籍中、0=退職 |
| 所属 | 所属ID（0/空=すべて） | `affiliation_id` 完全一致 |
| 部署 | 部署ID | `department_id` 完全一致 |
| 入社日（開始） | YYYY-MM-DD | `hire_date >= 値`（入社日が空の社員は除外される） |
| 入社日（終了） | YYYY-MM-DD | `hire_date <= 値` |
条件は AND。並び順は `ORDER BY m.employee_code ASC`（**文字列順**。数値順ではない）。

## A-3. 出力設定とファイル仕様
| 設定 | 値 | 動作 |
|---|---|---|
| 形式 | `csv`／`tsv` | 区切り文字 `,`／タブ。Content-Type `text/csv` / `text/tab-separated-values` |
| 文字コード | `sjis`（既定）／`utf8` | `sjis`: UTF-8→`SJIS-win` に変換（変換できない文字は化ける可能性あり）。`utf8`: 先頭にBOM（EF BB BF）を付与 |
| ヘッダー行 | あり（既定）／なし | `with_header` |
| ファイル名 | 任意 | `sanitize_file_name`、空なら `employee-export`。拡張子 `.csv`／`.tsv` を付与。`filename*=UTF-8''` で日本語名に対応 |

- 改行: **CRLF**。クォート: 必要な場合に `"` で囲む（`fputcsv`）。
- **数式インジェクション対策**: セル値が（先頭の空白・制御文字を除いて）`=` `+` `-` `@` で始まる場合、先頭に `'` を付与。例: 電話番号が `+81...` で始まると `'+81...`。
- レスポンスヘッダ: `X-Content-Type-Options: nosniff`、`Pragma: no-cache`、`Expires: 0`。
- 結果が0件なら、ファイルではなくエラーページ（HTTP 400）。
- 出力はAJAXではなく**フォームPOST**（`action=emp_csv_export`）。パラメータ: `nonce`, `column_keys[]`（順序＝列順）, `format`, `encoding`, `with_header`, `filename`, `is_active`, `affiliation_id`, `department_id`, `hire_date_from`, `hire_date_to`。
- 権限: **`access_custom_plugins`**（閲覧権限）で出力可能（編集権限は不要）。個人情報を含むため運用で制限すること（`08`）。
- デバッグ: `WP_DEBUG` 有効時のみ、列キーや絞り込み条件・件数をログ出力（個人データそのものは出さない）。

## A-4. テンプレート（`emp_csv_template`）
- 内容: 名前＋列キーの順序付き配列（JSON）。保存時の列順がそのまま再現される。
- 範囲: **保存したWPユーザー本人のみ**参照・更新・削除可（SQLに `wp_user_id` 条件）。
- 画面ロード時にサーバーが一覧を描画し（`get_templates()`）、操作後は `emp_csv_get_templates` で再描画。
- テンプレートに現在存在しない列キーが含まれていても、読み込み時はそのまま扱い、出力時に無効キーは除外される。
- 保存権限: `edit_custom_plugins`。一覧参照: `access_custom_plugins`。

---

# Part B. CSVインポート

## B-1. 共通仕様
- 3種類（`csv_type`）: `basic`（①基本情報）／`career`（②経歴・資格）／`dependent`（③扶養者）。
- 実行順序: **①→②→③**（②③は社員コードで①の社員に紐づけるため）。
- **ヘッダー行は常に読み飛ばす。列は「位置」で解釈する**（見出し文字は見ない）。列の並び替え・欠落・追加は不可。
- 読み飛ばし行: 先頭セルが `#` で始まる行（サンプル説明行）、すべて空欄の行。
- ファイルの読込・解析はブラウザ（JS）側: `.csv` のみ、BOM除去、改行で行分割しカンマ区切り（ダブルクォート対応）。**セル内改行は非対応**。文字コードは画面の選択（UTF-8／Shift-JIS）で読込。実際に「自動判定」はしていない（選んだ文字コードで読むだけ）。
- 解析済み行を `rows`（配列の配列）としてAJAX（`emp_csv_import`）で送信。サーバー側で行ごとに処理し、エラーは行単位で収集して継続する（**全体のトランザクションはなく、成功した行は確定される**）。
- エラー行番号: ファイルの行番号（1行目=ヘッダなので、最初のデータ行は「2行目」）。
- 権限: 実行 `edit_custom_plugins`、ひな形DL `access_custom_plugins`。
- 注意（技術）: 行・列ごとにPOST変数が作られるため、行数×列数がPHPの `max_input_vars`（既定1000）を超えると、一部の行が欠落する可能性がある。サーバー設定の確認・分割インポート推奨。 [推測：コードの送信方式から]
- 日付の解釈（`parse_date`）: `/` を `-` に置換し、`YYYY-M-D` 形式をゼロ埋めして `YYYY-MM-DD` に。形式に合わなければ **NULL（エラーにならない）**。実在しない日付（2020-13-45等）は形式が合えばそのまま保存され得る（実在チェックはしない）。
- 値の検証: 性別・雇用区分・週勤務日数・在籍区分などの**値域チェックはしない**。

## B-2. ひな形ダウンロード
- URL: `admin-ajax.php?action=emp_csv_template_download&nonce=…&csv_type=basic|career|dependent`
- ファイル名: `①基本情報インポートひな形.csv`／`②経歴・資格インポートひな形.csv`／`③扶養者インポートひな形.csv`
- 形式: UTF-8（BOM付き）。1行目=見出し、サンプル行、最後に `#` で始まる説明行。

## B-3. ① 基本情報（`import_basic`、33列）

列順（位置は0始まりの番号）:

| # | 列キー | 見出し | 処理・保存先 |
|---|---|---|---|
| 0 | employee_code | 社員コード | **必須**。照合キー |
| 1 | name | 氏名 | **必須** |
| 2 | name_kana | フリガナ | |
| 3 | gender | 性別 | 値は検証なし（男性/女性/その他の想定） |
| 4 | birthdate | 生年月日 | 日付 |
| 5 | blood_type | 血液型 | |
| 6 | hire_date | 入社日 | 日付 |
| 7 | is_active | 在籍区分 | 空=1（在籍）。それ以外は整数化（`1`=在籍、`0`=退職） |
| 8 | affiliation_name | 所属名 | マスタ名称で照合（大文字小文字無視、有効なもののみ） |
| 9 | department_name | 部署名 | 同上 |
| 10 | position_name | 役職名 | 同上 |
| 11 | job_type_name | 職種名 | 同上 |
| 12 | crew_code | 乗組員コード | 下記「乗組員コードの扱い」 |
| 13 | employment_type | 雇用区分 | 「正社員」「契約社員」「パート・アルバイト」の想定。空→NULL |
| 14 | weekly_work_days | 週勤務日数 | 1〜6の想定。空→NULL |
| 15 | zip | 郵便番号 | |
| 16 | address | 住所 | |
| 17 | tel_home | 自宅電話 | |
| 18 | tel_mobile | 携帯電話 | |
| 19 | tel_company | 会社携帯 | |
| 20 | emergency_name | 緊急連絡先氏名 | |
| 21 | emergency_tel | 緊急連絡先電話 | |
| 22 | emergency_relation | 緊急連絡先続柄 | |
| 23 | memo | 備考 | |
| 24 | health_no | 健康保険番号 | emp_insurance |
| 25 | health_date | 健康保険取得日 | |
| 26 | pension_no | 厚生年金番号 | |
| 27 | pension_date | 厚生年金取得日 | |
| 28 | employment_no | 雇用保険番号 | |
| 29 | employment_date | 雇用保険取得日 | |
| 30 | accident_no | 労災保険番号 | |
| 31 | accident_date | 労災保険取得日 | |
| 32 | retirement_date | 退職日 | emp_retirement（値がある場合のみ upsert） |

※個人番号（マイナンバー）はインポート対象外。

**処理手順（行ごと）**
1. 社員コード空→エラー・スキップ。氏名空→エラー・スキップ。
2. 所属/部署/役職/職種を名称で解決（見つからなければエラーを記録し、その項目はNULLで続行）。
3. `employee_code` で既存社員を検索。
   - 既存＋「スキップ」→ `skipped++`、何もしない。
   - 既存＋「上書き更新」→ UPDATE。
   - 新規 → INSERT（`success++`）。
4. 保険は常に upsert（空欄は空で上書き）。退職日は値があるときのみ upsert。
5. 乗組員コード履歴を補完（`ensure_current_crew_code_history`）。

**乗組員コードの扱い**
| ケース | 動作 |
|---|---|
| 既存社員（上書き更新）で CSV値 ≠ 現行（空欄含む） | 現行コードを維持。「適用開始日が必要」エラーを記録。他項目は更新 |
| 既存社員でCSV値＝現行 | 変更なし（履歴の整合のみ確認） |
| 新規で CSV にコードあり、他社員の現行コードまたは履歴に同コードあり | コードを空にして登録。エラーを記録 |
| 新規で CSV にコードあり、衝突なし | 登録。履歴を `valid_from=NULL, is_current=1` で補完 |
| 新規でコードなし | コードなしで登録 |

**「上書き更新」時の保存内容（注意）**: CSVの全列で `emp_master` を更新するため、**CSVの空欄は空で上書き**される（既存値が消える）。更新対象外なのは乗組員コード（上記）と `my_number`（CSVに無いので触れない）、退職日（空欄の場合）。CSV経由の保存では、任意の文字列項目は NULL ではなく **空文字 ''** になる。

結果表示: 新規登録（`success`）／上書き更新（`updated`）／スキップ（`skipped`）／エラー（`errors` の件数と詳細）。

## B-4. ② 経歴・資格（`import_career`、9列）

| # | 列キー | 見出し | 使い方 |
|---|---|---|---|
| 0 | employee_code | 社員コード | **必須**。社員が存在しなければエラー |
| 1 | record_type | 種別 | `学歴`／`職歴`／`資格` のいずれか |
| 2 | date | 日付 | 学歴=卒業年月日、資格=取得日（職歴では使わない） |
| 3 | career_year | 年（職歴用） | 職歴のみ |
| 4 | career_month | 月（職歴用） | 職歴のみ |
| 5 | title | 学校名・会社名・資格名 | 種別により意味が変わる |
| 6 | sub1 | 学科・専攻／部署名 | 学歴=学科、職歴=部署 |
| 7 | sub2 | 役職名（職歴のみ） | |
| 8 | memo | 備考（職歴のみ） | |

保存先: 学歴→`emp_education`（graduation_date, school_name, department）／職歴→`emp_career`（career_year, career_month, company_name, department, position, memo）／資格→`emp_qualification`（name, acquired_date）。
**常に追記**（`sort_order`=0）。既存行との重複判定・上書きはしない。**同じファイルを2回取り込むと重複する。**「重複社員コードの処理」の選択は無視される。

## B-5. ③ 扶養者（`import_dependent`、6列）

| # | 列キー | 見出し |
|---|---|---|
| 0 | employee_code | 社員コード（必須。存在しなければエラー） |
| 1 | name | 氏名（必須） |
| 2 | name_kana | フリガナ |
| 3 | relation | 続柄 |
| 4 | birthdate | 生年月日 |
| 5 | memo | 備考 |

保存先 `emp_dependent`。**常に追記**。1社員に複数行可。

## B-6. 画面のプレビュー表示（参考）
- ①のプレビュー: 見出し13列に対して値が12セルで、1列ずれて表示される（取り込み結果には影響なし）。
- ②③のプレビュー表示は正しい。
- クライアント側のエラー判定は最低限（社員コード空・氏名空）。

## B-7. 運用上のヒント
- Excelで「CSV UTF-8（コンマ区切り）」保存。社員コードの先頭ゼロを守るため、列を「文字列」にしてから入力。
- 文字化けしたら文字コードをShift-JISに切り替えて読み直す。
- 大量データは分割して取り込み、各回の結果件数を確認する。
- ②③を誤って重複取り込みした場合は、画面の社員編集で学歴・職歴・資格・扶養者の不要行を削除して保存（保存時にその社員の行が全入れ替えされる）。
