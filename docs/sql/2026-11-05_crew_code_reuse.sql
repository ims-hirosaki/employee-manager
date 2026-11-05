-- =====================================================================
-- 乗務員コード履歴：同じ社員が過去のコードに戻れるようにする（v1.2.2）
--
-- 通常はこのSQLを流す必要はありません。
-- v1.2.2 のプラグインが読み込まれると EMP_DB_Install::migrate_crew_code_history_index()
-- が同じ変更を自動で行います。自動移行が失敗した場合や、先にDBだけ変えたい場合に使います。
--
-- テーブル接頭辞が wp_ 以外の場合は書き換えてください。
-- 実行前にバックアップを取ってください。
-- =====================================================================

-- 1. 現在のインデックスを確認（uq_employee_code が Non_unique = 0 で出ていれば未移行）
SHOW INDEX FROM `wp_emp_crew_code_history`;

-- 2. UNIQUE(employee_id, crew_code) を通常インデックスに置き換える
--    ※ 2回目以降の実行でエラーになる場合は、すでに移行済みです。
ALTER TABLE `wp_emp_crew_code_history`
  ADD INDEX `idx_employee_code` (`employee_id`, `crew_code`),
  DROP INDEX `uq_employee_code`;

-- 3. 結果確認（uq_employee_code が無く、idx_employee_code が Non_unique = 1 であること）
SHOW INDEX FROM `wp_emp_crew_code_history`;

-- ---------------------------------------------------------------------
-- 元に戻す場合（ロールバック）
-- 同じ社員×同じコードの行が複数できた後は UNIQUE を戻せません。
-- 先に次のSQLで重複が無いことを確認してから実行してください。
-- ---------------------------------------------------------------------
-- SELECT employee_id, crew_code, COUNT(*) FROM `wp_emp_crew_code_history`
--  GROUP BY employee_id, crew_code HAVING COUNT(*) > 1;
--
-- ALTER TABLE `wp_emp_crew_code_history`
--   ADD UNIQUE KEY `uq_employee_code` (`employee_id`, `crew_code`),
--   DROP INDEX `idx_employee_code`;
