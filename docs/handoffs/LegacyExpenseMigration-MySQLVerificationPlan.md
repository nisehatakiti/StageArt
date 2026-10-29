# 旧形式Expense移行・部分復旧 実MySQL検証計画（案）

作成日: 2026-10-29
対象ブランチ: `integrate/expense-approval-20261029`
関連: `docs/handoffs/LegacyExpenseMigration-Runbook.md`、`docs/04-DomainModel/ExpenseApprovalWorkflow.md`（Version 1.9）
改訂: 2026-10-29（実行権限と監査テーブルの検証を4.7に追加）

本書は計画であり、実施を承認するものではない。作成時点で、どのDBにも接続していない。各工程は、環境と工程ごとに承認を得てから行う。本番環境では実施しない。

これまでのテストはすべて偽のDB（`FakeExpenseMigrationDatabase`。SQLを解釈せず、決まった文だけに答える）で行った。偽のDBは、トランザクションを「開始時の表の写しに戻す」動きで模しているだけで、InnoDBのロック・待ち・デッドロック・分離レベル・タイムアウトは再現しない。下の表の「偽DB」の列は、そのテストで確認できた範囲を示す。

---

## 1. 環境・権限・バックアップ

| 項目 | 計画 |
|---|---|
| 環境 | 本番・開発と切り離した検証専用の環境（例：ローカルのDockerで、WordPress・WP-CLI・MySQLを用意する）。MySQLの系統とバージョン（MySQL 5.7 / 8.0、MariaDB）は本番と合わせる。本番の系統とバージョンは未確認のため、まず確認する |
| データ | 実データのコピーは使わない（個人情報・領収書を含むため）。3節の検証用データを作る。開発DBを使う場合は、別途承認を得る |
| 権限 | 検証環境のDBの全権限（トリガーの作成、`SET GLOBAL`、`performance_schema`の参照を含む）。WordPressは、System Administrator権限（`stageart_manage_accounts`）を持つ運用者のユーザーと、持たないユーザーを1人ずつ用意する |
| バックアップ | 各工程の前に`mysqldump --single-transaction`で全体を取得する。後始末はこのダンプからの復元で行う |
| 本番への影響を防ぐ手順 | (1) 検証環境の接続情報は本番・開発と別にし、共有しない。(2) 各コマンドの前に`wp option get siteurl`で対象のサイトを確認する（DBの接続情報は表示しない）。(3) 本番・開発のWP-CLIの設定（`wp-cli.yml`のエイリアス等）を検証用の端末に置かない。(4) メール送信は無効にする（検証環境ではSMTPを設定しない、または送信を捕捉する仕組みを使う） |

## 2. 事前確認（読み取りのみ）

| 確認 | 方法 | 偽DB | 実MySQL |
|---|---|---|---|
| WP-CLIが使えること | `wp --info`、`wp core version`、`wp eval 'echo get_option("stageart_db_schema_version");'`（1.45.0） | 対象外 | 未確認 |
| スクリプトが配置されていること | `wp eval-file <plugin>/scripts/legacy_expense_migration.php`（事前確認だけ） | スクリプトのテストで確認 | 未確認 |
| ストレージエンジン | `SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'wp_stageart_%';`と、事前確認の出力が一致すること | 値を与えた場合の判定（InnoDB以外・不明で拒否）だけを確認 | 未確認 |
| 分離レベル等 | `SELECT @@transaction_isolation, @@autocommit, @@innodb_lock_wait_timeout, @@innodb_rollback_on_timeout;`（MariaDB・5.7は`@@tx_isolation`） | 対象外 | 未確認 |
| PHPの時刻とログ | `wp eval 'echo date_default_timezone_get(), " ", ini_get("error_log");'`。ログの出力先と保持期間を記録する | 対象外 | 未確認 |
| posix拡張 | `wp eval 'var_dump(function_exists("posix_geteuid"));'` | 対象外 | 未確認 |

## 3. 検証用データの作成と削除

- **作成：** Organization・Project・Production・Account（EXPENSEとLIABILITY）・Person・旧形式のExpense（CONFIRMED）とJournal Entryを、SQLファイルで作る。IDは検証用と分かる固定の値にする（例：`eeeeeeee-0000-4000-8000-00000000000N`）。偽DBの`LegacyExpenseMigrationPreflightTest`と同じ種類の行（移行できる行、DRAFTの仕訳しかない行、仕訳がない・複数ある・取り消された・金額が違う行、移行履歴が矛盾する行など）を用意する。このSQLファイルは未作成で、作成も承認の対象とする。
- **削除：** 行の`DELETE`では消さない。1節のダンプから復元するか、検証環境ごと破棄する。復元後に、検証用のIDが残っていないこと（`SELECT COUNT(*) ... WHERE id LIKE 'eeeeeeee-%'`）を確認する。

## 4. 検証項目

各項目で、実行前後に`CHECKSUM TABLE`（Expense・明細・履歴・仕訳・仕訳明細）と、Runbook工程2・工程6のSQLの結果を記録する。

### 4.1 事前確認と実行結果の照合

| 確認 | 方法 | 偽DB | 実MySQL |
|---|---|---|---|
| 事前確認が何も書き込まない | 実行前後の`CHECKSUM TABLE`が同じ。可能ならgeneral logで`SELECT`以外がないこと | 確認済み（書き込み・ロック・トランザクションがない） | 未確認 |
| 候補・判定不能の一覧が、3節で作った行の想定どおり | 出力と想定の一覧を照合する | 確認済み | 未確認 |
| 実行の結果が事前確認と一致する | 移行件数・ID、`MIGRATED`の件数、仕訳の件数・合計が変わらないこと | 確認済み | 未確認 |
| 指紋が違えば何も書き込まない | 1文字変えた指紋で実行し、`CHECKSUM TABLE`が同じ | 確認済み | 未確認 |
| 移行取消した行が候補にならない | 4.5の後に事前確認を実行し、`<ID>=MIGRATION_REVERTED`が出ること | 確認済み | 未確認 |

### 4.2 トランザクションとロールバック

| 確認 | 方法 | 偽DB | 実MySQL |
|---|---|---|---|
| 履歴の追加の失敗で、状態の更新も戻る（移行） | 検証環境だけで、特定のExpense IDの`INSERT`を拒否する`BEFORE INSERT`トリガーを`stageart_expense_reviews`に作り、実行する。そのExpenseがCONFIRMEDのまま、`HISTORY_INSERT_FAILED`が出て、ほかの候補は移行されること。終わったらトリガーを削除する | 確認済み | 未確認 |
| 同じ失敗で、移行取消の全件が戻る | 同じトリガーで、2件の移行取消を実行する。どちらもPOSTEDのまま、`MIGRATION_REVERTED`が追加されないこと | 確認済み | 未確認 |
| 状態の更新の失敗 | 同様の`BEFORE UPDATE`トリガーで`UPDATE_FAILED`を確認する | 確認済み | 未確認 |
| COMMITの失敗 | 実MySQLで意図的に起こす安全な方法がないため、対象外とする（偽DBの確認だけ） | 確認済み | 検証しない |

### 4.3 ロックの対象と順序

DBに2つのセッション（mysqlクライアント）を開き、片方でロックを保持したまま、WP-CLIで移行・移行取消を実行する。ロックの様子は、MySQL 8では`performance_schema.data_locks`・`data_lock_waits`、5.7・MariaDBでは`information_schema.INNODB_LOCKS`・`INNODB_LOCK_WAITS`等で見る。

| 確認 | 方法 | 偽DB | 実MySQL |
|---|---|---|---|
| 仕訳は対象の行だけがロックされる | 別セッションで対象外の仕訳を`SELECT ... FOR UPDATE`し、待たないこと。対象の仕訳では待つこと | ロックの順序・対象の文だけを確認 | 未確認 |
| ロックの順序（移行：Expense→仕訳→仕訳明細、移行取消：ID順にExpense→履歴→明細） | 別セッションで途中の行をロックし、待つ位置を確認する | 文の順序だけを確認 | 未確認 |
| ロック待ちのタイムアウトで`LOCK_FAILED` | 検証環境で`innodb_lock_wait_timeout`を短くし、別セッションで対象のExpenseをロックしたまま実行する | 失敗の扱いだけを確認（エラーを模した） | 未確認 |
| デッドロックの検出で`LOCK_FAILED` | 2つのセッションで逆順にロックを取る状況を作る（例：別セッションで仕訳をロックした後、移行が待っているExpenseを要求する） | 同上 | 未確認 |
| `$wpdb->last_error`にロック失敗が入る | 上の2つで、ログに`LOCK_FAILED`が出ること | 模しただけ | 未確認 |
| ギャップロックによる待ち | 移行取消のトランザクションを止めた状態（別セッションで後続のExpenseをロックして待たせる）で、ほかのExpenseの承認を実行し、待ち時間とデッドロックの有無を見る | 対象外 | 未確認 |

### 4.4 承認・転記・入金との競合

検証環境のREST APIで操作する。4.3と同じく、移行・移行取消を別セッションのロックで途中に止めた状態で、次を実行する。

| 確認 | 期待 | 偽DB | 実MySQL |
|---|---|---|---|
| 別のExpenseの承認・差し戻し | デッドロックせず、両方が完了する（移行・移行取消のCOMMITまで待つことはある） | 対象外 | 未確認 |
| 移行中のExpenseの仕訳の転記・取消 | 移行のCOMMITまで待つ。移行の再判定に影響しない | 対象外（コードの調査だけ） | 未確認 |
| チケット入金（Check-in）・入金の取消 | 移行・移行取消と互いに待たない（仕訳の別の行） | 対象外 | 未確認 |
| 移行取消の対象を承認しようとする | 状態がPOSTEDのため拒否される | Domainのテストで確認 | 未確認 |
| 承認・差し戻し・通知の既存の動作 | 移行・移行取消の前後で変わらない。通知は送られない（移行・移行取消は通知を作らない） | 既存のテストで確認 | 未確認 |

### 4.5 部分復旧（移行取消）

| 確認 | 方法 | 偽DB | 実MySQL |
|---|---|---|---|
| `check`が何も書き込まない | `CHECKSUM TABLE`が同じ | 確認済み | 未確認 |
| `--user`がない、Personに結び付いていない、理由がない場合に停止する | それぞれ実行し、`CHECKSUM TABLE`が同じ | 確認済み（`get_current_user_id()`を模した） | 未確認（WP-CLIの`--user`の実際の動作） |
| 1件でも不適合なら全件を変えない | 適合する行と、移行後に明細を変えた行を一緒に指定する | 確認済み | 未確認 |
| 履歴が残り、移行取消が追記される | Runbook 8.1の復旧後の確認SQL | 確認済み | 未確認 |
| 実行者の記録 | `actor_person_id`が`--user`のPersonであること。出力とログの`wp_user`・`os_user` | 確認済み（OSアカウントはテスト環境の値） | 未確認 |
| 日時の順序 | `MIGRATION_REVERTED`の`acted_at`が`MIGRATED`より後で、`UTC_TIMESTAMP()`と比べてタイムゾーンが想定どおり | 形式と順序だけを確認 | 未確認 |
| 仕訳が変わらない | 仕訳・仕訳明細の`CHECKSUM TABLE`が同じ | 確認済み | 未確認 |
| 二重の取消 | 同じIDで2回目を実行し、`STATE_CHANGED`で拒否される | 確認済み | 未確認 |

### 4.6 失敗時の検出と復旧

| 確認 | 方法 | 偽DB | 実MySQL |
|---|---|---|---|
| 失敗がログと出力に出る | 4.2・4.3の各失敗で、ログに`<ID>=<理由コード>`が出て、金額・氏名・取消理由が出ないこと | 確認済み | 未確認（ログの出力先） |
| 失敗後に事前確認からやり直せる | 失敗した行が再び候補に出て、原因を取り除いた後に移行できる | 確認済み | 未確認 |
| 全体復旧 | 1節のダンプから復元し、工程2の件数に戻ること | 対象外 | 未確認 |
| 移行取消の後に旧いコードへ戻さないこと | 検証しない（Runbook 8.3の注意事項） | 対象外 | 検証しない |

### 4.7 実行権限と監査テーブル（Schema 1.45.0、仕様書Version 1.9）

| 確認 | 方法 | 偽DB | 実MySQL |
|---|---|---|---|
| スキーマ更新で監査テーブルが作られる | 1.44.0（または1.43.0）の状態から配置し、`SHOW CREATE TABLE wp_stageart_legacy_expense_migration_audit`で列・主キー・一意制約`operation_success`・インデックスを確認する。エンジンがInnoDBであること | dbDeltaに渡す文を確認 | 未確認 |
| スキーマ更新を繰り返しても変わらない | 保存されたバージョンを1.44.0に戻して再度リクエストし、`SHOW CREATE TABLE`と行数が同じこと | 同じ文が出ることを確認 | 未確認（dbDeltaの実際の差分） |
| 一意制約が、NULLの`success_key`を重複させてよい | 失敗・拒否の行が同じ操作で複数入ること、成功の行は同じExpenseで2件目が拒否されること（4.2のトリガーとは別に、検証環境でだけ手作業のINSERTで確認） | 偽DBで同じ規則を模した | 未確認（MySQL・MariaDBの一意制約の実際の動作） |
| 権限のないユーザーでは何も書かない | `stageart_manage_accounts`を持たないユーザー、Personに結び付いていないユーザー、存在しないログイン名、`--user`なしで`execute`・`restore`を実行し、全テーブルの`CHECKSUM TABLE`（監査テーブルを含む）が同じこと | 確認済み（`get_userdata()`・`user_can()`を模した） | 未確認（WP-CLIの`--user`、存在しないユーザーでのWP-CLI自体の動作） |
| 権限のあるユーザーで記録される | `stageart_admin`ロールのユーザーと`administrator`ロールのユーザーで実行し、`wp_user_id`・`person_id`・`os_user`・`acted_at_utc`を確認する。`acted_at_utc`が`UTC_TIMESTAMP()`と数秒以内であること | 確認済み | 未確認（`os_user`の実際の値、時刻） |
| 監査の追加の失敗で、状態の更新と履歴も戻る | 検証環境だけで、監査テーブルに特定のExpense IDの`SUCCEEDED`を拒否する`BEFORE INSERT`トリガーを作る。移行ではそのExpenseがCONFIRMEDのまま`AUDIT_INSERT_FAILED`、移行取消では一覧の全件が戻ること。その後に`FAILED`の行が残ること | 確認済み | 未確認 |
| 失敗の記録が書けない場合の報告 | 同様のトリガーで`FAILED`・`REFUSED`の行を拒否し、出力に`The audit row could not be written`、ログに`audit record failed`が出て、スクリプトが失敗で終わること | 確認済み | 未確認 |
| 監査記録と業務の履歴の件数が一致する | Runbook 3.2の確認SQL | 確認済み | 未確認 |
| 業務の履歴と監査記録が混ざらない | `stageart_expense_reviews`に運用の行がなく、`MIGRATED`の`actor_person_id`が旧確定の確認者のままであること | 確認済み | 未確認 |

## 5. 結果の記録

項目ごとに、日時、環境（MySQLの系統・バージョン、分離レベル）、実行者、実行したコマンドとWP-CLIの出力、`CHECKSUM TABLE`の前後、ログの該当行、判定（期待どおり／違う）を残す。期待と違う結果は、その場で直さずに報告する。実MySQLで確認したと報告するのは、承認された環境で実施した項目だけとする。
