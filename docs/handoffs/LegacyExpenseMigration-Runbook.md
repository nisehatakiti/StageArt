# 旧形式Expense移行（Schema 1.44.0）運用手順案

作成日: 2026-10-29（同日改訂：移行取消の履歴（仕様書Version 1.8）、再移行の禁止、実行者の記録。それ以前の改訂：部分復旧の保留と競合条件・監査性の整理、コード配置とデータ移行の分離、トランザクション化、仕訳の整合性判定の追加。再改訂：仕訳のロック、部分復旧の条件確認とスクリプト化）
対象ブランチ: `integrate/expense-approval-20261029`
対象コード:
- `plugin/src/Accounting/LegacyExpenseMigration.php`（事前確認・実行）
- `plugin/src/Accounting/LegacyExpenseMigrationClassifier.php`（判定）
- `plugin/src/Accounting/LegacyExpenseMigrationRestore.php`（部分復旧の確認・実行）
- `plugin/scripts/legacy_expense_migration.php`（移行をWP-CLIから実行するスクリプト）
- `plugin/scripts/legacy_expense_migration_restore.php`（部分復旧をWP-CLIから実行するスクリプト）

仕様: `docs/04-DomainModel/ExpenseApprovalWorkflow.md`「Legacy CONFIRMED Expense」

本書は手順案であり、本番環境・開発環境での実施を承認するものではない。DBへの接続、コードの配置、移行の実行は、環境ごと・工程ごとに別途承認を得てから行う。テーブル接頭辞は`wp_`と仮定しているため、実環境の接頭辞に置き換える。

---

## 1. 構成：コード配置とデータ移行の分離

| 工程 | 実行のきっかけ | Expenseのデータ |
|---|---|---|
| コード配置・スキーマ更新（1.44.0） | 配置後の最初のWebリクエスト（`SchemaUpgrader::maybeUpgrade()`） | **変更しない**（列とテーブルの追加のみ） |
| 事前確認（preview） | 運用者がWP-CLIで実行 | 読み取りのみ |
| 移行の実行（execute） | 運用者がWP-CLIで、事前確認の指紋（fingerprint）を指定して実行 | 候補だけをPOSTEDへ |

- スキーマ更新（`Installer::install()` → `AccountingInstaller::install()`）は移行を呼ばない。旧CONFIRMEDの行を読むことも、トランザクションを開くこともない（`SchemaUpgradePathTest`で確認）。
- 管理画面やREST APIからは実行できない。既存の運用方式（`docs/testing/SeedData.md`と同じ`wp eval-file`）に合わせ、WP-CLIのスクリプトだけを入口とした。WP-CLI以外から読み込まれた場合、スクリプトは何もしない。
- **互換性：** 以前の構成（スキーマ更新時に自動移行）を実行した環境があっても、移行済みの行はPOSTEDのため再判定されない。新しい構成で実行しても二重移行にはならない。ただし、既存環境で自動移行が実行されたかどうかは未確認である（開発DBは2026-10-26時点で1.43.0。本番は未確認）。

### 実行方法

```
# 事前確認（何も書き込まない）
wp eval-file plugin/scripts/legacy_expense_migration.php --path=<wordpress-root>

# 実行（事前確認の内容の承認後、バックアップ取得後に限る）
wp eval-file plugin/scripts/legacy_expense_migration.php execute <fingerprint> --path=<wordpress-root>
```

- スクリプトのパスは、サーバー上に配置されたプラグイン内のパスに置き換える。
- スキーマのバージョンが1.44.0未満の場合は、どちらのモードもExpenseを読まずに停止する。

## 2. 事前確認モード（preview）の動作

書き込み、行ロック、トランザクションの開始、ログ出力を一切行わない（テストで確認済み）。次を表示する。

- CONFIRMEDの件数
- 移行候補の件数と、各候補のExpense IDと対象Journal Entry ID
- 判定不能の件数と、各行のExpense IDと理由コード
- 書き込み対象テーブル（`stageart_expenses`、`stageart_expense_reviews`）のストレージエンジン
- 指紋（fingerprint）：移行候補と判定不能の一覧（ID・Journal Entry ID・金額・理由コード）から求めた16桁の値

表示するのはID・件数・理由コード・ストレージエンジンだけで、金額・氏名・明細の内容は表示しない。

## 3. 実行モード（execute）とトランザクション

実行は次の場合、**何も書き込まずに拒否**する。

| 拒否理由 | 条件 |
|---|---|
| `PLAN_CHANGED` | 指定した指紋が、実行時点で求め直した指紋と一致しない（事前確認の後に、候補や判定不能の内容が変わった） |
| `NOT_TRANSACTIONAL` | 書き込み対象テーブルのいずれかがInnoDBでない、またはエンジンを確認できない |

拒否しなかった場合は、候補の**1件ごとに独立したトランザクション**で処理する。

1. `START TRANSACTION`
2. 対象Expenseを`SELECT ... FOR UPDATE`でロックする。
3. 対象のJournal Entryを主キーで`SELECT ... FOR UPDATE`し、続けてその明細を`SELECT ... FOR UPDATE`でロックする（3.1参照）。
4. ロックした値で、明細・Journal Entry・Account・履歴を読み直して**再判定**する。事前確認と結果が違えば中止する。
5. 状態を更新する。条件は`status = 'CONFIRMED' AND revision = 0 AND posted_journal_entry_id IS NULL`で、更新件数が1件でなければ中止する。
6. `MIGRATED`の履歴を追加する（通常のINSERT。一意制約違反も失敗として扱う）。
7. `COMMIT`

いずれかの段階で失敗すると、そのExpenseは`ROLLBACK`してCONFIRMEDのまま残し、次の候補へ進む。

| 失敗の理由コード | 意味 |
|---|---|
| `TRANSACTION_START_FAILED` | トランザクションを開始できなかった |
| `LOCK_FAILED` | Expense・Journal Entry・その明細のロックが失敗した（デッドロック、ロック待ちのタイムアウト等。`$wpdb->last_error`で判定） |
| `CHANGED_SINCE_PREVIEW` | ロック後の再判定で移行できなくなっていた（行が消えた、Journal Entryが取り消された等） |
| `UPDATE_FAILED` | 状態の更新が失敗した、または更新件数が1件でなかった |
| `HISTORY_INSERT_FAILED` | 履歴の追加が失敗した（状態の更新も巻き戻す） |
| `COMMIT_FAILED` | COMMITが失敗した |
| `UNEXPECTED_ERROR` | 上記以外の例外 |

- 失敗した行は、PHPエラーログに`[StageArt legacy expense migration] failed: <Expense ID>=<理由コード>`と記録し、WP-CLIにも警告を出す。1件でも失敗があれば、スクリプトは失敗として終了する。
- 終了時には、件数の要約（`completed: migrated=N failed=N manual_review=N`）と、判定不能の一覧（`left CONFIRMED for manual review: <ID>=<理由>, ...`）をログに記録する。
- ログに出るのはID・件数・理由コードだけである（テストで確認済み）。
- 失敗した行は再判定の対象として残るので、原因を確認して承認を得たうえで、事前確認からやり直せる。

### 3.1 仕訳の同時変更への対策

**調査結果（コードで確認）：**

- 仕訳を書き込む処理は`WordPressJournalEntryRepository::save()`だけで、仕訳の行を`UPDATE`（または`INSERT`）した後に、明細を`DELETE`・`INSERT`する。仕訳を読むときに`FOR UPDATE`は使っていない。
- 既存の仕訳を変更する処理は、転記（`PostJournalEntryUseCase`：DRAFT→POSTED）と取消（`CheckInProcessor::reverse()`：チケット入金の仕訳だけ）の2つ。どちらもアプリケーション層の1つのトランザクション内で行い、Expenseの行はロックしない。`ExpenseConfirmed`の仕訳を取り消す処理は、現在のコードにはない。
- 承認（`ApproveExpenseUseCase`）は、Expenseを`FOR UPDATE`でロックしてから新しい仕訳を作る。ロックの順序はExpense→仕訳である。
- `stageart_journal_entries`には`source_event_type`・`source_event_id`のインデックスがない。この条件で`FOR UPDATE`すると、InnoDBでは全行を走査してロックするため、ほかの仕訳の書き込みをすべて止めてしまう。

**対策（実装済み）：**

- 仕訳は、ロックせずに`source_event`で**IDだけを探し**、そのIDを**主キーで**`FOR UPDATE`する。ロックするのは対象の仕訳の行だけになる。続けて、その仕訳の明細を`journal_entry_id`で`FOR UPDATE`する。
- ロックの順序は**Expense → 仕訳 → 仕訳の明細**で、承認の順序（Expense→仕訳）や`save()`の順序（仕訳→明細）と同じ向きになる。仕訳を書き込む処理はExpenseをロックしないため、アプリケーションのロックとの間で循環は生じない（コードからの推論で、実MySQLでは未確認）。
- 再判定はロックした後の値で行う。ロックはCOMMITまたはROLLBACKまで続くため、再判定からCOMMITまでの間に、アプリケーションが対象の仕訳や明細を変更することはできない（`save()`は最初の`UPDATE`で待つ）。
- ロック後に仕訳がなくなっていた、または別のExpenseに付け替えられていた場合は、その仕訳を除いて判定し、移行しない（`CHANGED_SINCE_PREVIEW`）。
- ロックが失敗した場合は`LOCK_FAILED`としてロールバックする。InnoDBはデッドロックを検出すると、片方のトランザクションを取り消す。移行が取り消された場合は、そのExpenseがCONFIRMEDのまま残る。

**限界：**

- アカウント（`stageart_accounts`）、Production・Project、Expenseの明細はロックしない。Expenseの明細は、Expenseの行のロックで守られる（`WordPressExpenseRepository::save()`は明細より先にExpenseの行を更新する。コードで確認）。Accountを変更する処理は有効・無効の切り替えだけで、種別とOrganizationは変更できない（`Account`のコードで確認）。有効・無効は判定に使っていない。
- 主キーでのロックの前に行う、IDの検索はロックしない。REPEATABLE READでは、検索の後に別のセッションが同じExpenseの`ExpenseConfirmed`の仕訳を**新しく追加**した場合、それを見落とす可能性がある。アプリケーションにはその処理がないため、起こりうるのはDBを直接操作した場合に限られる。
- 明細の`FOR UPDATE`は、`journal_entry_id`の非一意インデックスに対してギャップロックを伴う。COMMITまでの短い間、隣り合う値への明細の追加が待たされることがある。実行は利用の少ない時間帯に行う。
- ロック・デッドロック・タイムアウトの実際の動作は、偽のDBでは確認できない。テストで確認したのは、ロックの順序・対象と、ロック失敗時のロールバックだけである。

## 4. 判定条件（仕訳の整合性を含む）

旧確定処理（修正8以前の`ConfirmExpenseUseCase`）は、Expense 1件につき次の仕訳を作っていた。

- `source_event_type = 'ExpenseConfirmed'`、`source_event_id` = Expense ID
- OrganizationはProductionの所属Organization、ProductionはExpenseのProduction
- 借方：Expenseの明細ごとに1行（明細と同じAccount・同じ金額）
- 貸方：合計額を1行。同じOrganizationのLIABILITY（未払金）Account
- 作成時はDRAFTで、別途転記（POSTED）していた

この形と完全に一致し、POSTED済みの仕訳を持つ行だけを移行する。対象はCONFIRMEDの行だけで、上から順に判定して最初に当てはまった理由を記録する。

| 順 | 条件（当てはまれば移行しない） | 理由コード |
|---|---|---|
| 1 | 確認者・確認日時のいずれかがない | `MISSING_CONFIRMATION` |
| 2 | 申請回が0でない、またはワークフロー用の列（申請者・金額・承認・差し戻し・仕訳ID）のいずれかに値がある | `WORKFLOW_COLUMNS_SET` |
| 3 | 申請・承認履歴が既にある。移行取消済み（`MIGRATED`と`MIGRATION_REVERTED`が申請回1に1件ずつ）なら`MIGRATION_REVERTED`、移行に関する履歴が矛盾していれば`HISTORY_INCONSISTENT`、それ以外の履歴なら`HISTORY_EXISTS` | `MIGRATION_REVERTED`・`HISTORY_INCONSISTENT`・`HISTORY_EXISTS` |
| 4 | 明細がない | `NO_LINES` |
| 5 | 明細のAccountがない、または金額が0以下 | `INVALID_EXPENSE_LINE` |
| 6 | 対象の仕訳がない | `NO_JOURNAL_ENTRY` |
| 7 | 対象の仕訳のいずれかがREVERSED | `REVERSED_JOURNAL_ENTRY` |
| 8 | 対象の仕訳が2件以上 | `MULTIPLE_JOURNAL_ENTRIES` |
| 9 | 仕訳がPOSTEDでない（DRAFT等） | `DRAFT_JOURNAL_ENTRY` |
| 10 | 仕訳のProductionがExpenseと異なる（NULLを含む） | `PRODUCTION_MISMATCH` |
| 11 | ExpenseのProductionまたはそのProjectがなく、Organizationを特定できない | `ORGANIZATION_UNKNOWN` |
| 12 | 仕訳のOrganizationがProductionの所属Organizationと異なる | `ORGANIZATION_MISMATCH` |
| 13 | 仕訳の行に、借方・貸方以外の区分、Accountのない行、金額0以下の行がある | `INVALID_JOURNAL_LINE` |
| 14 | 借方合計と貸方合計が異なる、または0（行がない場合を含む） | `UNBALANCED_JOURNAL_ENTRY` |
| 15 | 明細合計と借方合計が異なる | `AMOUNT_MISMATCH` |
| 16 | Accountごとの借方の金額が、Accountごとの明細の金額と一致しない | `DEBIT_LINES_MISMATCH` |
| 17 | 貸方が1行でない | `CREDIT_LINES_MISMATCH` |
| 18 | 貸方のAccountがない、別Organizationのもの、またはLIABILITYでない | `PAYABLE_ACCOUNT_INVALID` |
| 19 | 借方のAccountがない、または別Organizationのもの | `DEBIT_ACCOUNT_INVALID` |

- CONFIRMED以外の行（DRAFT、SUBMITTED、POSTED、REJECTED、未知の状態）は読まず、変更しない。判定器には`UNKNOWN_STATUS`もあるが、移行処理はCONFIRMEDしか読まないため使われない。
- **自動判定しないもの：** 仕訳日付、摘要、借方Accountの種別（EXPENSE等）、Accountの有効・無効、実際に支払が行われたかどうか。旧確定処理の時点でも検証していなかった項目や、DBからは判断できない事実であり、移行の可否には使わない。
- 旧データの多くは9の`DRAFT_JOURNAL_ENTRY`になると見込まれる（推測で、本番データは未確認）。旧確定処理は下書きの仕訳を作っており、転記していなければこの扱いになる。

## 5. 移行で変わるもの・変わらないもの

移行した行：

| 列 | 移行前（判定2で保証） | 移行後 |
|---|---|---|
| `status` | `CONFIRMED` | `POSTED` |
| `revision` | `0` | `1` |
| `submitted_by` / `submitted_at` | NULL | `created_by` / `created_at` |
| `requested_amount` / `approved_amount` | NULL | 明細合計 |
| `approved_by` / `approved_at` | NULL | `confirmed_by` / `confirmed_at` |
| `posted_journal_entry_id` | NULL | 対象仕訳のID |

あわせて`stageart_expense_reviews`に`action = 'MIGRATED'`の履歴を1件追加する。この履歴には、明細のスナップショットと金額を含める。

- **移行前の値の保持：** 判定2により、ワークフロー用の列が初期値（申請回0、その他NULL）の行しか移行しない。そのため、移行前の値は上表のとおり一意に決まり、`MIGRATED`の履歴で移行した行を特定できる。復旧に必要な情報は、この2つと実行前のバックアップで足りる。
- **変わらないもの：**
  - 仕訳と仕訳の明細（件数・明細数・借方合計・貸方合計・状態とも同じ。テストで確認済み）。既存の仕訳を自動で修正・再作成することはない。
  - Actual。POSTEDの仕訳から集計するため、移行では変わらない。
  - Expenseの明細、作成者、立替払いを受ける者、確認者。
  - 移行しなかった行。
- 移行したExpenseは承認済み（未払い）として扱い、支払済みとは推定しない（仕様書「Payment」）。

## 6. 判定不能データの扱い

- **自動では修正しない。** 判定不能の行はCONFIRMEDのまま残し、仕訳の転記・取消・修正も行わない。
- 判定不能の一覧は、事前確認の表示と、実行時のPHPエラーログで確認できる。PHPエラーログの出力先はサーバー設定によるため、実施前に確認する（未確認）。DBには理由を保存しないため、DBの状態（CONFIRMEDの残り）を正とし、事前確認を実行し直せば同じ一覧が得られる。
- 個別調査の進め方（案）：
  - 理由コードごとに、対象のExpenseと仕訳を読み取り専用で確認し、旧確定処理でどう作られたかを記録する。
  - 修正が必要な場合は、その方法（例：DRAFTの仕訳を転記した後で再判定する、取消の原則に従って訂正する）を個別に承認を得て行う。
  - CONFIRMEDの行は閲覧できるが、編集や状態の変更はできない（Domainで拒否）。画面の「旧形式（確認待ち）」表示は元の作業ツリーにある未統合の実装で、このブランチには含まれない。

## 7. 本番での手順案

各工程の前に承認を得る。工程1と工程5は別の作業として行い、続けて実施しない。

### 工程1：コード配置（スキーマ更新のみ）

1. 配置前のプラグインコードと、DBのバックアップを取得する（工程3と同じ方法）。
2. コードを配置する。最初のリクエストでスキーマが1.44.0になる。Expenseのデータは変わらない。
3. 確認する（読み取りのみ）。

```sql
SELECT option_value FROM wp_options WHERE option_name = 'stageart_db_schema_version';  -- 1.44.0
SELECT status, COUNT(*) FROM wp_stageart_expenses GROUP BY status;                    -- 配置前と同じ
SELECT COUNT(*) FROM wp_stageart_expense_reviews WHERE action = 'MIGRATED';           -- 0
```

### 工程2：事前確認

1. WP-CLIで事前確認を実行し、出力（件数・ID・理由コード・ストレージエンジン・指紋）を保存する。
2. ストレージエンジンがInnoDBでない場合は、工程5に進まず報告する。
3. 判定材料の照合として、次の読み取り専用SQLの結果も記録する。

```sql
SELECT status, COUNT(*) FROM wp_stageart_expenses GROUP BY status;
SELECT COUNT(*) FROM wp_stageart_journal_entries;
SELECT debit_credit, COUNT(*), SUM(amount) FROM wp_stageart_journal_entry_lines GROUP BY debit_credit;
SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME LIKE 'wp_stageart_%';
```

### 工程3：バックアップ（実行の直前）

1. DB全体のダンプを取得する（少なくとも`wp_stageart_*`の全テーブルと`wp_options`）。InnoDBでは`--single-transaction`を使う。
2. ダンプは`public_html`の外、またはホスティングのバックアップ領域に保存し、外部に共有しない。
3. ダンプのサイズと工程2の件数を記録する。可能であれば、別環境に復元できることを確認する。
4. 本番の日次バックアップの有無と保持期間を確認する（`SystemAdministrationPolicy.md`「日次バックアップ」）。

### 工程4：実行の承認

次を記録し、承認を得る。

- 工程2の指紋、移行候補の件数とID、判定不能の件数と理由コード
- バックアップの取得日時と保存場所
- 実行者と実行予定日時

承認後は、工程2を実行し直さない。データが変わると指紋が変わり、工程5は拒否される。その場合は工程2からやり直す。

### 工程5：実行

1. `execute <指紋>`で実行し、出力を保存する。
2. `Refused`が出た場合は何も書き込まれていない。理由を確認して報告する。
3. `Rolled back`の警告が出た行はCONFIRMEDのままである。理由コードを記録して報告する。

### 工程6：移行後の確認

```sql
SELECT status, COUNT(*) FROM wp_stageart_expenses GROUP BY status;
SELECT COUNT(*) FROM wp_stageart_expense_reviews WHERE action = 'MIGRATED';            -- 移行件数と一致
SELECT COUNT(*) FROM wp_stageart_journal_entries;                                       -- 工程2と同じ
SELECT debit_credit, COUNT(*), SUM(amount) FROM wp_stageart_journal_entry_lines GROUP BY debit_credit;  -- 工程2と同じ
-- 移行した行は、MIGRATED履歴がちょうど1件あり、対象仕訳がExpenseConfirmedである
SELECT e.id FROM wp_stageart_expenses e
  JOIN wp_stageart_expense_reviews r ON r.expense_id = e.id AND r.action = 'MIGRATED'
  LEFT JOIN wp_stageart_journal_entries j ON j.id = e.posted_journal_entry_id
 WHERE e.status <> 'POSTED' OR j.source_event_type IS NULL OR j.source_event_type <> 'ExpenseConfirmed';  -- 0件
```

最後に事前確認を再度実行し、候補が0件で、判定不能の一覧が工程2と同じであることを確認する。

## 8. 復旧手順案

### 8.1 部分復旧：移行した行だけを戻す（移行取消）

**条件：** 移行の結果に問題があり、仕訳やほかのデータには問題がない場合（例：移行対象の判断を誤った）。仕訳は変更していないため、戻す必要はない。配置後に書き込まれたほかのデータは残る。

**方針（仕様書Version 1.8）：**

- 移行**後に別の処理で変更された行は戻さない。** 移行が書いた値と`MIGRATED`の履歴がそのまま残っている行だけを戻す。
- 指定したIDのうち1件でも条件に合わなければ、**どの行も更新せずに停止する。**
- **履歴は削除しない。** `MIGRATED`の行は残し、同じ申請回1の行として`MIGRATION_REVERTED`（移行取消）を追記する。状態を戻す更新と履歴の追記は1つのトランザクションで行い、どちらかが失敗すれば全体をロールバックする。
- 移行取消したExpenseは、**再び移行しない**（事前確認で`MIGRATION_REVERTED`として判定不能の一覧に出る）。再移行の操作はない。
- 仕訳は変更しない（読むだけで、ロックもしない）。
- 実行者は、StageArtのPersonに結び付いたWordPressユーザーとして特定できる場合に限り記録する。特定できなければ何も書き込まない（「実行者の識別」参照）。

**復旧できる条件（すべて満たす行だけ）：**

| 確認内容 | 満たさない場合の理由コード |
|---|---|
| 行が存在する | `NOT_FOUND` |
| `status = 'POSTED'`、`revision = 1`、仕訳IDがある、差し戻しの列（`rejected_by`・`rejected_at`・`rejection_reason`）がNULL | `STATE_CHANGED` |
| 申請額＝承認額、`submitted_by/at`＝`created_by/at`、`approved_by/at`＝`confirmed_by/at`（移行が書いた値のまま） | `MODIFIED_SINCE_MIGRATION` |
| 履歴がちょうど1件で、それが`MIGRATED`・申請回1・金額が一致・実行者が確認者 | `HISTORY_MISMATCH` |
| 明細が`MIGRATED`履歴の明細スナップショットと同じで、合計が金額と一致する | `LINES_CHANGED` |
| 仕訳IDが、そのExpenseの`ExpenseConfirmed`の仕訳を指している | `JOURNAL_ENTRY_MISMATCH` |

移行取消した行はCONFIRMEDに戻っているため、もう一度指定すると`STATE_CHANGED`で拒否される（二重の取消は起きない。`(expense_id, revision, action)`の一意制約でも防がれる）。

**`MIGRATION_REVERTED`の行に記録する値：**

| 列 | 値 |
|---|---|
| `expense_id` | 対象のExpense ID |
| `revision` | 1（`MIGRATED`と同じ申請回） |
| `action` | `MIGRATION_REVERTED` |
| `requested_amount`・`approved_amount` | 取り消した時点の金額（移行が書いた金額） |
| `lines_snapshot` | 取り消した時点の明細 |
| `reason` | 運用者が指定した取消理由（500文字以内。必須） |
| `actor_person_id` | 実行した運用者のStageArt Person ID |
| `acted_at` | 実行日時（サーバーのPHPの時刻。ほかの履歴と同じ形式） |

取消前の状態は、`MIGRATED`の行（移行時の値）とExpenseの元の値（第5節）で分かり、取消後の状態は第5節の「移行前」と同じである。仕訳IDは`source_event`（`ExpenseConfirmed`・Expense ID）から分かる。

#### 実行者の識別

| 識別情報 | 取得方法 | 記録先 | 取得できない場合 |
|---|---|---|---|
| StageArt Person ID（**必須**） | WP-CLIの`--user=<ログイン名>`で指定したWordPressユーザーに結び付いたPerson（`stageart_people.wp_user_id`） | 履歴の`actor_person_id`、ログ、出力 | 復旧しない（`--user`がない、Personに結び付いていない）。ほかのIDで代用しない |
| WordPressユーザーID | WP-CLIの現在のユーザー | ログ、出力 | `--user`がなければ上と同じく復旧しない |
| OSのアカウント | PHPのposix拡張があれば実効ユーザー名、なければ環境変数`USER`/`USERNAME`（`env:`を付けて区別） | ログ、出力のみ | `unknown`と記録して続行する（必須ではない） |
| 実行日時・対象ID・操作 | 処理内で決まる | 履歴（`acted_at`・`expense_id`・`action`）、ログ | — |

**限界：**

- `--user`はWP-CLIを実行できる者なら任意に指定できる。認証ではなく、**申告された運用者**の記録である。WP-CLIを実行できるサーバー権限の管理と、作業記録（誰が承認し、誰が実行したか）との照合が必要である。
- 環境変数のOSユーザーは実行者が書き換えられる。posix拡張がない環境では、OSアカウントを確実には特定できない。
- OSアカウントとWordPressユーザーIDはDBの履歴には残らず、PHPエラーログとWP-CLIの出力にだけ残る。DBに残すには列の追加（スキーマ変更）が必要で、今回は行っていない。ログの出力先と保持期間は未確認である。
- 実行者に特定の権限（例：組織のOwner）があるかは確認しない。誰が実行してよいかは運用で決め、作業記録に残す。
- 取消理由は自由記述で、ログと出力には出さない（DBの履歴にだけ残る）。個人情報を書かない。

#### 手順（スクリプトを使う）

1. **確認（読み取りのみ）：** 戻す候補のIDを指定して確認する。各IDに`RESTORABLE`または理由コードが表示される。`--user`は不要。

   ```
   wp eval-file plugin/scripts/legacy_expense_migration_restore.php check <ID>,<ID>,... --path=<wordpress-root>
   ```

2. 理由コードが出たIDは、復旧の対象から外すか、個別に調査する（自動では直さない）。対象から外した後、もう一度確認する。
3. 確認結果、対象ID、取消理由、実行者（WordPressのログイン名）を記録し、承認を得る。
4. 復旧の直前にバックアップを取得する（工程3と同じ方法）。
5. **復旧前の値を記録する（読み取りのみ）：**

   ```sql
   SELECT id, status, revision, requested_amount, approved_amount, posted_journal_entry_id, updated_at
     FROM wp_stageart_expenses WHERE id IN (<ID一覧>);
   SELECT expense_id, revision, action, acted_at FROM wp_stageart_expense_reviews
    WHERE expense_id IN (<ID一覧>) ORDER BY expense_id, acted_at;          -- 各IDにMIGRATEDが1件だけ
   SELECT status, COUNT(*) FROM wp_stageart_expenses GROUP BY status;
   SELECT action, COUNT(*) FROM wp_stageart_expense_reviews GROUP BY action;
   SELECT COUNT(*) FROM wp_stageart_journal_entries;
   SELECT debit_credit, COUNT(*), SUM(amount) FROM wp_stageart_journal_entry_lines GROUP BY debit_credit;
   ```

6. **復旧（運用者自身のWordPressユーザーで実行する）：**

   ```
   wp --user=<運用者のログイン名> eval-file plugin/scripts/legacy_expense_migration_restore.php restore <ID>,<ID>,... "<取消理由>" --path=<wordpress-root>
   ```

   出力の`Operator: person=... wp_user=... os_user=...`を作業記録に残す。スクリプトは次の順で処理する。
   1. 取消理由がなければ停止する。`--user`がない、またはPersonに結び付いていなければ停止する（どれも何も書き込まない）。
   2. 書き込み対象テーブルがInnoDBでなければ拒否する（`NOT_TRANSACTIONAL`）。
   3. 全IDを確認し、1件でも条件に合わなければ何も書き込まずに拒否する（`Refused (TARGET_MISMATCH)`。行ごとに`Not restorable: <ID>=<理由>`を表示し、ログにも記録する）。
   4. `START TRANSACTION`。ID順に、Expenseの行を`FOR UPDATE`でロックし、履歴と明細を`FOR UPDATE`で読み、**ロックした値でもう一度確認**する。確認の後に別の処理が変更していれば、全体をロールバックする（下記「競合条件」）。
   5. 状態を戻す。`UPDATE`の`WHERE`にも上表の条件を入れ、更新件数が1件でなければ全体をロールバックする。
   6. `MIGRATION_REVERTED`の履歴を追加する（通常の`INSERT`）。追加件数が1件でなければ全体をロールバックする。
   7. 全IDが終わったら`COMMIT`する。COMMITが失敗した場合もロールバックする。

   | 失敗・拒否の理由コード | 意味 |
   |---|---|
   | `ACTOR_MISSING` | 実行者のPerson IDがない、またはPerson IDの形式でない（スクリプトはこの前に停止する） |
   | `REASON_MISSING`・`REASON_TOO_LONG` | 取消理由がない、または500文字を超える |
   | `NOT_TRANSACTIONAL`・`TARGET_MISMATCH` | 上記2・3 |
   | `TRANSACTION_START_FAILED` | トランザクションを開始できなかった |
   | `LOCK_FAILED` | ロックが失敗した（デッドロック、ロック待ちのタイムアウト等） |
   | 上表の理由コード | ロックした後の確認で、条件に合わなくなっていた |
   | `UPDATE_FAILED` | 状態の更新が失敗した、または更新件数が1件でなかった |
   | `HISTORY_INSERT_FAILED` | 移行取消の履歴の追加が失敗した（状態の更新も巻き戻す） |
   | `COMMIT_FAILED` | COMMITが失敗した |
   | `UNEXPECTED_ERROR` | 上記以外の例外 |

   失敗した場合は`Rolled back (<理由> at <ID>) - nothing was restored.`と表示し、どの行も戻らず、履歴も追加されない。

7. **復旧後の確認（読み取りのみ）：**

   ```sql
   -- 対象の行がCONFIRMEDで、ワークフロー用の列が初期値に戻っている
   SELECT id FROM wp_stageart_expenses
    WHERE id IN (<ID一覧>)
      AND NOT (status = 'CONFIRMED' AND revision = 0 AND submitted_by IS NULL AND submitted_at IS NULL
               AND requested_amount IS NULL AND approved_amount IS NULL AND approved_by IS NULL
               AND approved_at IS NULL AND posted_journal_entry_id IS NULL);              -- 0件

   -- 監査記録：各IDにMIGRATEDとMIGRATION_REVERTEDが1件ずつ（申請回1）、移行取消が後
   SELECT expense_id, revision, action, actor_person_id, acted_at FROM wp_stageart_expense_reviews
    WHERE expense_id IN (<ID一覧>) ORDER BY expense_id, acted_at;
   SELECT expense_id FROM wp_stageart_expense_reviews
    WHERE expense_id IN (<ID一覧>)
    GROUP BY expense_id
   HAVING COUNT(*) <> 2 OR SUM(action = 'MIGRATED') <> 1 OR SUM(action = 'MIGRATION_REVERTED') <> 1;  -- 0件

   -- 移行取消の行の実行者が、出力のperson=と同じ
   SELECT DISTINCT actor_person_id FROM wp_stageart_expense_reviews
    WHERE expense_id IN (<ID一覧>) AND action = 'MIGRATION_REVERTED';

   SELECT status, COUNT(*) FROM wp_stageart_expenses GROUP BY status;        -- POSTEDが対象数だけ減り、CONFIRMEDが同数増える
   SELECT action, COUNT(*) FROM wp_stageart_expense_reviews GROUP BY action;  -- MIGRATION_REVERTEDだけが対象数だけ増える
   SELECT COUNT(*) FROM wp_stageart_journal_entries;                         -- 手順5と同じ
   SELECT debit_credit, COUNT(*), SUM(amount) FROM wp_stageart_journal_entry_lines GROUP BY debit_credit;  -- 手順5と同じ
   ```

   最後に移行の事前確認を実行し、対象IDが候補に含まれず、判定不能の一覧に`<ID>=MIGRATION_REVERTED`として出ることを確認する。

- 戻した行の値は、移行前（第5節の表）と同じになる。`updated_at`は移行でも復旧でも変更しない。
- 取消理由はDBの履歴にだけ残る。ログでは`restored to CONFIRMED: <ID一覧>; recorded MIGRATION_REVERTED at <日時> by person=<ID> wp_user=<ID> os_user=<名前>`の形で記録される。

#### 復旧できない場合

- **確認で理由コードが出た行**（移行後に申請・承認・差し戻し、明細の変更、履歴の追加などがあった行）は、部分復旧の対象にしない。その後の操作を無視して移行前の状態に戻すと、操作の結果が失われるためである。行ごとに、その後の操作の内容を確認し、対処（戻さない、業務上の訂正を行う、全体復旧を検討する）を個別に決めて承認を得る。
- **復旧が失敗してロールバックされた場合**は、どの行も変わっておらず、履歴も追加されていない。理由コードを記録し、`check`からやり直す。ロックの失敗は、利用の少ない時間帯に再実行すれば解消することがある。
- **実行者を特定できない場合**（`--user`の運用者がStageArtのPersonに結び付いていない等）は、復旧しない。結び付いた運用者が実行する。
- **WP-CLIが使えない場合**は、スクリプトを使わずにSQLで戻さない。明細スナップショットとの比較と、同じトランザクションでの履歴の追記を手作業で正確に行うことは難しく、「移行後に変更された行を戻さない」「履歴を必ず残す」ことを保証できない。WP-CLIを用意するか、8.2の全体復旧を検討する。

#### 競合条件（確認からCOMMITまで）

コードで確認した事実と、そこからの評価を分けて記す。実MySQLでは確認していない。

| 対象 | 書き込む処理（コードで確認） | 復旧での扱い | 評価 |
|---|---|---|---|
| Expenseの行 | `WordPressExpenseRepository::save()`（状態・申請回を条件にした`UPDATE`） | `FOR UPDATE`でロックし、条件付きの`UPDATE`で戻す | ロック中は他の処理が書き込めない |
| 履歴（`stageart_expense_reviews`） | 同じ`save()`が、Expenseの行の更新の**後**に追加する | `FOR UPDATE`で読む（現在の行を読む） | Expenseのロックで守られる。ロック付きで読むため、2件目以降でも古い読み取り（REPEATABLE READのスナップショット）にならない |
| Expenseの明細 | 同じ`save()`が、Expenseの行の更新の**後**に置き換える | `FOR UPDATE`で読む（今回追加） | 同上。以前はロックなしで読んでおり、複数件の復旧で2件目以降の明細をスナップショットから読むおそれがあった |
| 仕訳 | `WordPressJournalEntryRepository::save()`（状態・明細の更新。`source_event`の列は作成時から変わらない）。仕訳の行を削除する処理はない | ロックせずに読み、`source_event`だけを確認する | 確認に使う値をアプリケーションが変えないため、ロックしなくても結果は変わらない。復旧は仕訳の状態に依存しない（復旧後に仕訳が取り消されても、その行は移行前と同じく判定不能の扱いになる） |

既存処理との関係：

- **承認・差し戻し**はSUBMITTEDのExpenseだけを扱う。復旧の対象はPOSTEDなので、同じExpenseを対象にした場合はロックを待ち、その後に状態の確認で失敗する。
- **転記・チケット入金・取消**は仕訳だけを書き込み、Expenseをロックしない。復旧は仕訳をロックしないため、互いに待たない。
- **ロックの順序：** 復旧は、ID順にExpense→履歴→明細の順でロックする（ID順は今回追加）。指定の順序が違う2つの復旧が同時に動いても、逆順にロックすることはない。
- **ギャップロック：** 履歴と明細のロックは、`expense_id`の非一意インデックスに対してギャップロックを伴う。COMMITまでの間、ほかのExpenseの履歴の追加（承認・差し戻し）が待たされることがある。待っている側は自分のExpenseのロックを持つが、そのExpenseはSUBMITTEDで、復旧の対象にはならない。そのため循環は生じないと評価している（推論）。
- **移行取消の履歴の追加：** 追加する行の`expense_id`は、復旧側が`FOR UPDATE`でロックした範囲の中にある。そのため、自分のロックでは待たないと評価している（推論）。同じExpenseの履歴をほかの処理が同時に追加することは、Expenseの行のロックで防がれる。
- デッドロックやロック待ちのタイムアウトが起きた場合は`LOCK_FAILED`として全体をロールバックする。InnoDBが復旧側のトランザクションを取り消した場合も、その後の書き込みは行わない（各ロックの直後にエラーを確認する）。

保証の範囲：アプリケーションの処理との競合については、上記の確認とロックで整合性を保てる。DBを直接操作した場合（仕訳の`source_event`の書き換えや削除など）は対象外である。

### 8.2 全体復旧：バックアップから復元する

**条件：** 部分復旧では対処できない場合。例：移行以外のデータにも影響がある、原因が特定できない、8.1のスクリプトが`RESTORABLE`以外を返す行があり、個別の対処が決まらない。

1. サービスへの書き込みを止める（メンテナンス）。
2. 復元前の状態のダンプを追加で取得する（調査用）。
3. 工程3のダンプから復元する。
4. 工程2のSQLで、件数が実行前と一致することを確認する。

工程3の後に書き込まれたデータは失われる。復元の前に影響範囲を確認し、承認を得る。

### 8.3 コードを1.44.0より前に戻す場合

- 1.44.0より前のコードは、DRAFTとCONFIRMED以外の状態を読めない。配置後に申請・承認・差し戻しされたExpense（SUBMITTED / POSTED / REJECTED）があると、旧コードでは読めなくなる。
- そのため、コードを戻す場合は8.2の全体復旧が必要になる。工程1のバックアップから復元し、配置後に書き込まれたデータの扱いを事前に確認する。
- 旧コードのスキーマバージョンは1.44.0と異なるため、旧コードの配置後の最初のリクエストで旧Installerが実行され、バージョンが旧値に戻る。追加された列とテーブルは削除されない（dbDeltaは削除しない）。
- **移行取消の後に、このブランチのVersion 1.8より前のコードへ戻す場合：** それより前のコードは`MIGRATION_REVERTED`の種別を知らず、その履歴を持つExpenseを読み込むと例外になり、APIで表示できなくなる。移行取消を実行した後は、Version 1.8より前のコードへ戻さない。戻す必要がある場合は、8.2の全体復旧で移行取消の前の状態に戻すことを含めて検討する。

### 8.4 復旧の記録と監査性（決定済み：案A）

2026-10-29に案A（移行取消を履歴の新しい種別として追記する）が承認され、仕様書Version 1.8として実装した。それまでの「`MIGRATED`の行を削除する」方式はコードから取り除いた（復旧処理に`DELETE`はない。テストで確認）。

**変更点（コードで確認）：**

- `ExpenseReview`の種別に`MIGRATION_REVERTED`を追加した。DBから読み込むときの検証も通るため、移行取消した行も通常どおり読み込め、APIの`reviews`には`MIGRATED`・`MIGRATION_REVERTED`が日時順（`acted_at`、`revision`の順）で並ぶ。
- 業務上の操作（申請・取り下げ・承認・差し戻し）は`MIGRATION_REVERTED`を記録しない。記録するのは部分復旧だけである。CONFIRMEDのExpenseは、もともと業務上の操作の対象ではない（Domainで拒否）。
- 移行の判定は、件数ではなく履歴の種別と申請回を見る。`MIGRATED`と`MIGRATION_REVERTED`が申請回1に1件ずつだけある行は`MIGRATION_REVERTED`、そのほか移行に関する履歴が含まれる行は`HISTORY_INCONSISTENT`、移行に関係しない履歴だけの行は従来どおり`HISTORY_EXISTS`として、どれも移行しない（第4節の判定3）。
- **スキーマの変更はない。** `action`列はVARCHAR(20)で、`MIGRATION_REVERTED`（18文字）が収まる。列の種類の制約（ENUM・CHECK）はない。一意制約`(expense_id, revision, action)`はそのまま使える。スキーマのバージョンは1.44.0のままで、元の作業ツリーの未統合の変更とのスキーマ番号の調整は、今回の変更では生じない（取り込み時に1.45.0以上にする必要は、従来どおり残る）。

**画面：** このブランチには、Expenseの履歴を表示する画面がない（モバイル・Webのコードで履歴を参照する箇所はない。コードで確認）。APIの`reviews`は種別の文字列をそのまま返すため、将来の画面では`MIGRATED`・`MIGRATION_REVERTED`の表示名を用意する必要がある。元の作業ツリーにある未統合の画面（「旧形式（確認待ち）」の表示）が履歴を表示するかは未確認である。

## 9. 残る制約と、実施前に確認すること

- テストは偽のDB（`FakeExpenseMigrationDatabase`）で行い、実際のMySQLでは実行していない。InnoDBでのロック・ロールバックの実動作、`information_schema`の値、WP-CLIからの実行は、承認を得た環境で確認する必要がある。
- 本番・開発のサーバーで、WP-CLIが使えるかを確認していない。開発環境ではSeedDataの手順でWP-CLIを使っている。
- PHPエラーログの出力先を確認していない。
- 仕訳と仕訳明細は、再判定の前に主キーでロックする（3.1）。ロックの実動作、デッドロックの有無、ギャップロックによる待ちの程度は、実MySQLで確認する必要がある。実行は利用の少ない時間帯に行う。
- 部分復旧（8.1）は、履歴を削除せず`MIGRATION_REVERTED`を追記する（仕様書Version 1.8、8.4）。実行者は`--user`で申告されたWordPressユーザーのPersonとして記録され、認証ではない（8.1「実行者の識別」）。
- 部分復旧の条件（8.1）は、移行が書いた値と履歴が残っていることを確かめるものである。別の処理が移行と同じ値を書き直した場合は区別できない（その場合、戻しても失われる情報はない）。
- 指紋はSHA-256の先頭16桁で、事前確認の内容が変わっていないことを確かめるためのものである。認証の仕組みではない。

## 10. テストで確認した範囲

| テスト | 確認内容 |
|---|---|
| `LegacyExpenseMigrationClassifierTest` | 判定条件ごとの結果（仕訳のOrganization・Accountごとの借方・貸方・Account・行の形式を含む） |
| `LegacyExpenseMigrationTest` | 事前確認が書き込まないこと、指紋の一致・不一致、InnoDB以外での拒否、1件ごとのトランザクション、状態の更新・履歴の追加・COMMIT・開始の失敗時のロールバックとログ、ロック後の再判定、再実行で二重移行・二重履歴がないこと、仕訳への書き込みがないこと、ログの内容。ロックの順序と対象（Expense→仕訳（主キー）→仕訳の明細、事前確認ではロックしない）、事前確認の後に仕訳が取り消された場合の拒否、ロック直前に仕訳の明細が変わった場合にCONFIRMEDのまま残ること、各ロックの失敗時のロールバック |
| `LegacyExpenseMigrationRestoreTest` | 部分復旧の確認が書き込まないこと、ID順のロック、確認の後に履歴・明細が変わった場合の全体のロールバック、明細のロック失敗時のロールバック、`MIGRATED`の行が残り`MIGRATION_REVERTED`が同じトランザクションで追記されること（記録する値を含む）、履歴の追記の失敗で状態の更新もロールバックされること、移行取消したExpenseが再び候補にならないこと、移行の履歴が矛盾する場合に判定不能になること、移行取消した行の二重の取消の拒否、実行者・取消理由がない場合の拒否、`DELETE`を発行しないこと、正常な復旧で対象だけが移行前と同じ値に戻り、仕訳が変わらないこと、移行後に変更された行（状態・申請回・差し戻し・金額・承認者・申請者・仕訳・履歴・明細）の拒否、1件でも不一致があれば全体を拒否すること、ロック直前の変更や各段階の失敗で全体がロールバックされること、InnoDB以外とID未指定での拒否、仕訳をロック・変更しないこと、ログの内容 |
| `LegacyExpenseMigrationRestoreScriptTest` | 部分復旧スクリプトの確認・復旧・拒否、`--user`がない・Personに結び付いていない・取消理由がない場合の停止、実行者の記録と出力、引数の誤り、WP-CLI以外での無動作、出力に取消理由が出ないこと |
| `WordPressExpenseRepositoryTest` | `MIGRATED`と`MIGRATION_REVERTED`を持つExpenseが読み込め、APIの結果に日時順で両方が出ること |
| `LegacyExpenseMigrationPreflightTest` | 本番で想定される行の種類を1つのDBに入れた場合の、事前確認の結果、実行の前後での状態・金額・仕訳の件数と合計、再実行、ログの内容 |
| `LegacyExpenseMigrationScriptTest` | WP-CLIスクリプトの事前確認・実行・拒否、スキーマが古い場合の停止、WP-CLI以外での無動作、出力の内容 |
| `SchemaUpgradePathTest` | スキーマ更新（通常のリクエスト）では移行が実行されず、Expenseが変わらないこと |
