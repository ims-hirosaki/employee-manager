# 社員情報管理システム — WordPress プラグイン

WordPress で社員マスタ・保険・学歴・職歴・資格・扶養者情報を一元管理するプラグインです。管理者専用で動作し、他のプラグインからデータを参照できる公開APIも備えています。

---

## 機能一覧

| 機能 | 説明 |
|---|---|
| 社員一覧 | 検索・フィルター・ソート・ページング・一括在籍変更 |
| 社員登録・編集 | 5ステップフォーム（基本情報・保険・学歴職歴・扶養者・確認） |
| マスタ管理 | 所属・部署・役職・職種のCRUD・インライン編集 |
| CSV出力 | 列選択・列順調整・テンプレート保存・文字コード/形式指定 |
| 公開API | 在籍社員一覧・社員情報取得などを他プラグインに提供 |

---

## ファイル構成

```
employee-manager/
│
├── employee-manager.php              # メインファイル・公開API関数定義
├── uninstall.php                     # プラグイン削除時にテーブルをDROP
│
├── includes/
│   ├── class-db-install.php         # 全12テーブルの作成・削除
│   ├── class-employee.php           # 社員CRUD・AJAX ハンドラー
│   ├── class-master.php             # マスタCRUD・AJAX ハンドラー
│   └── class-csv-export.php         # CSV出力ロジック・テンプレート管理
│
└── admin/
    ├── class-admin-menu.php         # メニュー登録・アセット読込・AJAXフック
    │
    ├── views/
    │   ├── employee-list.php        # 社員一覧画面
    │   ├── employee-form.php        # 社員登録・編集フォーム
    │   ├── master.php               # マスタ管理画面
    │   └── csv.php                  # CSV出力画面
    │
    └── assets/
        ├── admin.css                # 全画面共通スタイル
        └── admin.js                 # 全画面のAJAX・インタラクション
```

---

## インストール

1. `employee-manager.zip` をダウンロード
2. WordPress 管理画面 → **プラグイン → 新規追加 → ZIPをアップロード**
3. アップロード後、**有効化**をクリック
4. 有効化と同時に全テーブルが自動作成されます

### 動作要件

| 項目 | 要件 |
|---|---|
| WordPress | 5.8 以上 |
| PHP | 8.0 以上 |
| MySQL | 5.7 以上 / MariaDB 10.3 以上 |
| 権限 | 管理者（`manage_options`）のみ使用可能 |

---

## 使い方

### 初期設定の手順

プラグインを有効化したら、最初にマスタデータを登録してください。

```
管理画面 → 社員情報管理 → マスタ管理
```

1. **所属**タブ → 会社や事業所名を登録
2. **部署**タブ → 部署名を登録
3. **役職**タブ → 役職名を登録
4. **職種**タブ → 職種名を登録

マスタを登録後、社員の登録ができるようになります。

---

### 社員登録

```
管理画面 → 社員情報管理 → 新規社員登録
```

5ステップのフォームで入力します。

| ステップ | 入力内容 |
|---|---|
| STEP 1 | 基本情報・所属・住所・緊急連絡先 |
| STEP 2 | 加入保険（健康・年金・雇用・労災）・退職情報 |
| STEP 3 | 学歴・職歴・免許資格（行の追加・削除が可能） |
| STEP 4 | 扶養者情報（行の追加・削除が可能） |
| STEP 5 | 入力内容の確認・登録実行 |

社員コードと氏名が必須項目です。それ以外はすべて省略可能です。

---

### CSV出力

```
管理画面 → 社員情報管理 → CSV出力
```

#### 基本的な操作流れ

1. **STEP 0** — 保存済みテンプレートがあればクリックして選択（→ STEP 4へ）
2. **STEP 1** — プリセット（基本情報のみ / 人事用 / 保険情報 など）から一括選択
3. **STEP 2** — 個別に出力項目を選択・解除
4. **STEP 3** — ドラッグ&ドロップで列の順序を調整
5. 右パネルで絞り込み条件・ファイル設定を指定
6. **CSV出力を実行**ボタンをクリック

#### テンプレートの保存

よく使う項目の組み合わせをテンプレートとして保存できます。

1. STEP 2〜3 で出力したい項目・列順を設定
2. 「現在の選択を保存」ボタンをクリック
3. テンプレート名を入力して保存

次回以降はSTEP 0でテンプレートカードをクリックするだけで同じ設定を再現できます。テンプレートはWordPressユーザーごとにデータベースへ保存されます。

#### 出力設定オプション

| 設定 | 選択肢 |
|---|---|
| ファイル形式 | CSV / TSV |
| 文字コード | Shift-JIS（Excel推奨）/ UTF-8（BOM付き） |
| ヘッダー行 | あり / なし |
| ファイル名 | 任意の文字列 |

---

## テーブル構成

### 作成されるテーブル一覧

| テーブル名 | 用途 |
|---|---|
| `{prefix}mst_affiliation` | 所属マスタ |
| `{prefix}mst_department` | 部署マスタ |
| `{prefix}mst_position` | 役職マスタ |
| `{prefix}mst_job_type` | 職種マスタ |
| `{prefix}emp_master` | 社員マスタ（基本情報） |
| `{prefix}emp_insurance` | 加入保険情報（1:1） |
| `{prefix}emp_retirement` | 退職情報（1:1） |
| `{prefix}emp_education` | 学歴（1:多） |
| `{prefix}emp_career` | 職歴（1:多） |
| `{prefix}emp_qualification` | 免許・資格（1:多） |
| `{prefix}emp_dependent` | 扶養者（1:多） |
| `{prefix}emp_csv_template` | CSV出力テンプレート |

詳細なカラム定義・ER図・連携プラグイン開発サンプルは **`DATABASE.md`** を参照してください。

### アンインストール

WordPress 管理画面でプラグインを**削除**すると、上記の全テーブルが自動的に DROP されます。  
**無効化だけでは削除されません**（データは保持されます）。

---

## 他プラグインとの連携

このプラグインは連携用の公開API関数を提供しています。有給管理・勤怠管理などの連携プラグインから社員情報を参照できます。

### 主な公開API関数

```php
// 在籍中の社員一覧を取得
$employees = emp_get_active_employees();

// IDで社員を取得（保険・学歴・扶養者情報も含む）
$emp = emp_get_employee_by_id( 5 );

// 社員コードで取得
$emp = emp_get_employee_by_code( 'E0001' );

// マスタ一覧を取得（有効なもののみ）
$affiliations = emp_get_affiliations();
$departments  = emp_get_departments();
$positions    = emp_get_positions();
$job_types    = emp_get_job_types();
```

連携プラグイン開発の詳細は `DATABASE.md` の「公開API関数リファレンス」を参照してください。

---

## AJAXアクション一覧

管理画面のJavaScriptから呼び出せるAJAXアクションです。すべて Nonce 検証と `manage_options` 権限チェックを行っています。

| アクション名 | 説明 | Nonce |
|---|---|---|
| `emp_master_get_list` | マスタ一覧取得（無効含む） | `emp_master_nonce` |
| `emp_master_insert` | マスタ新規登録 | `emp_master_nonce` |
| `emp_master_update` | マスタ更新 | `emp_master_nonce` |
| `emp_master_delete` | マスタ削除（使用中チェックあり） | `emp_master_nonce` |
| `emp_employee_get_list` | 社員一覧取得（検索・ページング対応） | `emp_employee_nonce` |
| `emp_employee_get_one` | 社員1件取得 | `emp_employee_nonce` |
| `emp_employee_save` | 社員の新規登録・更新 | `emp_employee_nonce` |
| `emp_employee_toggle` | 在籍フラグの切り替え | `emp_employee_nonce` |
| `emp_csv_export` | CSV生成・ダウンロード | `emp_csv_nonce` |
| `emp_csv_get_templates` | CSVテンプレート一覧取得 | `emp_csv_nonce` |
| `emp_csv_save_template` | CSVテンプレート保存 | `emp_csv_nonce` |
| `emp_csv_update_template` | CSVテンプレート名変更 | `emp_csv_nonce` |
| `emp_csv_delete_template` | CSVテンプレート削除 | `emp_csv_nonce` |

---

## 今後の拡張ポイント

現在の実装で対応を検討できる追加機能の候補です。

- **マイナンバーの暗号化** — `my_number` カラムの保存・復号処理の実装
- **有給管理プラグイン** — `emp_get_active_employees()` を活用した連携
- **勤怠管理プラグイン** — 社員コードをキーにした打刻データの紐付け
- **組織図ビュー** — 所属・部署・役職をツリー表示するビジュアライズ
- **CSVインポート** — 既存システムからの一括移行機能
- **変更履歴ログ** — 社員情報の更新履歴を記録するテーブルの追加
- **権限の細分化** — 閲覧専用・編集可能などロールごとのアクセス制御

---

## ライセンス

GPL-2.0+

---

## 開発メモ

- PHP 8.0 の `match` 式・名前付き引数・Union 型は意図的に不使用（WordPress 推奨の後方互換を重視）
- `dbDelta()` を使用しているため、カラム追加時は `class-db-install.php` の CREATE TABLE 文を修正して再実行するだけでマイグレーションが可能
- JavaScriptは jQuery（WordPress 同梱）のみを使用し、外部ライブラリへの依存なし
