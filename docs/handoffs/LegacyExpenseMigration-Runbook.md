# 旧形式Expense移行（Schema 1.44.0）運用手順案

作成日: 2026-10-29（同日改訂：コード配置とデータ移行の分離、トランザクション化、仕訳の整合性判定の追加）
対象ブランチ: `integrate/expense-approval-20261029`
対象コード:
- `plugin/src/Accounting/LegacyExpenseMigration.php`（事前確認・実行）
- `plugin/src/Accounting/LegacyExpenseMigrationClassifier.php`（判定）
- `plugin/scripts/legacy_expense_migration.php`（WP-CLIから実行するスクリプト）

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
2. 対象Expenseを`SELECT ... FOR UPDATE`でロックし、明細・Journal Entry・Account・履歴を読み直して**再判定**する。事前確認と結果が違えば中止する。
3. 状態を更新する。条件は`status = 'CONFIRMED' AND revision = 0 AND posted_journal_entry_id IS NULL`で、更新件数が1件でなければ中止する。
4. `MIGRATED`の履歴を追加する（通常のINSERT。一意制約違反も失敗として扱う）。
5. `COMMIT`

いずれかの段階で失敗すると、そのExpenseは`ROLLBACK`してCONFIRMEDのまま残し、次の候補へ進む。

| 失敗の理由コード | 意味 |
|---|---|
| `TRANSACTION_START_FAILED` | トランザクションを開始できなかった |
| `CHANGED_SINCE_PREVIEW` | ロック後の再判定で移行できなくなっていた（行が消えた、Journal Entryが取り消された等） |
| `UPDATE_FAILED` | 状態の更新が失敗した、または更新件数が1件でなかった |
| `HISTORY_INSERT_FAILED` | 履歴の追加が失敗した（状態の更新も巻き戻す） |
| `COMMIT_FAILED` | COMMITが失敗した |
| `UNEXPECTED_ERROR` | 上記以外の例外 |

- 失敗した行は、PHPエラーログに`[StageArt legacy expense migration] failed: <Expense ID>=<理由コード>`と記録し、WP-CLIにも警告を出す。1件でも失敗があれば、スクリプトは失敗として終了する。
- 終了時には、件数の要約（`completed: migrated=N failed=N manual_review=N`）と、判定不能の一覧（`left CONFIRMED for manual review: <ID>=<理由>, ...`）をログに記録する。
- ログに出るのはID・件数・理由コードだけである（テストで確認済み）。
- 失敗した行は再判定の対象として残るので、原因を確認して承認を得たうえで、事前確認からやり直せる。

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
| 3 | 申請・承認履歴が既にある | `HISTORY_EXISTS` |
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

### 8.1 部分復旧：移行した行だけを戻す

**条件：** 移行の結果に問題があり、仕訳やほかのデータには問題がない場合（例：移行対象の判断を誤った）。仕訳は変更していないため、戻す必要はない。配置後に書き込まれたほかのデータは残る。

```sql
-- 対象の確認：MIGRATED履歴だけを持ち、その後に操作されていない行
SELECT e.id FROM wp_stageart_expenses e
  JOIN wp_stageart_expense_reviews r ON r.expense_id = e.id AND r.action = 'MIGRATED' AND r.revision = 1
 WHERE e.status = 'POSTED' AND e.revision = 1
   AND NOT EXISTS (SELECT 1 FROM wp_stageart_expense_reviews o WHERE o.expense_id = e.id AND o.action <> 'MIGRATED');

-- 戻す（承認後、復旧直前のバックアップ取得後に、1つのトランザクションで）
-- <ID一覧> は上のSELECTで確認し、承認を得たIDに限る
START TRANSACTION;
UPDATE wp_stageart_expenses
   SET status = 'CONFIRMED', revision = 0, submitted_by = NULL, submitted_at = NULL,
       requested_amount = NULL, approved_amount = NULL, approved_by = NULL, approved_at = NULL,
       posted_journal_entry_id = NULL
 WHERE id IN (<ID一覧>) AND status = 'POSTED' AND revision = 1;
DELETE FROM wp_stageart_expense_reviews
 WHERE expense_id IN (<ID一覧>) AND action = 'MIGRATED' AND revision = 1;
-- 2つの文の件数がどちらもIDの数と一致することを確認してからCOMMIT。一致しなければROLLBACK
COMMIT;
```

- 戻した行の値は、移行前（第5節の表）と同じになる。
- 戻した後、事前確認を実行すると、同じ行が再び候補として表示される。運用者が実行しない限り、再移行はされない。
- MIGRATED以外の履歴を持つ行（将来、支払処理などで操作された行）は、このSQLでは戻らない。個別に判断する。

### 8.2 全体復旧：バックアップから復元する

**条件：** 部分復旧では対処できない場合。例：移行以外のデータにも影響がある、原因が特定できない、8.1のSQLの件数が一致しない。

1. サービスへの書き込みを止める（メンテナンス）。
2. 復元前の状態のダンプを追加で取得する（調査用）。
3. 工程3のダンプから復元する。
4. 工程2のSQLで、件数が実行前と一致することを確認する。

工程3の後に書き込まれたデータは失われる。復元の前に影響範囲を確認し、承認を得る。

### 8.3 コードを1.44.0より前に戻す場合

- 1.44.0より前のコードは、DRAFTとCONFIRMED以外の状態を読めない。配置後に申請・承認・差し戻しされたExpense（SUBMITTED / POSTED / REJECTED）があると、旧コードでは読めなくなる。
- そのため、コードを戻す場合は8.2の全体復旧が必要になる。工程1のバックアップから復元し、配置後に書き込まれたデータの扱いを事前に確認する。
- 旧コードのスキーマバージョンは1.44.0と異なるため、旧コードの配置後の最初のリクエストで旧Installerが実行され、バージョンが旧値に戻る。追加された列とテーブルは削除されない（dbDeltaは削除しない）。

## 9. 残る制約と、実施前に確認すること

- テストは偽のDB（`FakeExpenseMigrationDatabase`）で行い、実際のMySQLでは実行していない。InnoDBでのロック・ロールバックの実動作、`information_schema`の値、WP-CLIからの実行は、承認を得た環境で確認する必要がある。
- 本番・開発のサーバーで、WP-CLIが使えるかを確認していない。開発環境ではSeedDataの手順でWP-CLIを使っている。
- PHPエラーログの出力先を確認していない。
- 仕訳と仕訳明細の読み取りはロックしない（Expenseの行だけをロックする）。ロック後の再判定とCOMMITの間に、別の操作で仕訳が取り消される可能性は残る。実行は利用の少ない時間帯に行う。
- 指紋はSHA-256の先頭16桁で、事前確認の内容が変わっていないことを確かめるためのものである。認証の仕組みではない。

## 10. テストで確認した範囲

| テスト | 確認内容 |
|---|---|
| `LegacyExpenseMigrationClassifierTest` | 判定条件ごとの結果（仕訳のOrganization・Accountごとの借方・貸方・Account・行の形式を含む） |
| `LegacyExpenseMigrationTest` | 事前確認が書き込まないこと、指紋の一致・不一致、InnoDB以外での拒否、1件ごとのトランザクション、状態の更新・履歴の追加・COMMIT・開始の失敗時のロールバックとログ、ロック後の再判定、再実行で二重移行・二重履歴がないこと、仕訳への書き込みがないこと、ログの内容 |
| `LegacyExpenseMigrationPreflightTest` | 本番で想定される行の種類を1つのDBに入れた場合の、事前確認の結果、実行の前後での状態・金額・仕訳の件数と合計、再実行、ログの内容 |
| `LegacyExpenseMigrationScriptTest` | WP-CLIスクリプトの事前確認・実行・拒否、スキーマが古い場合の停止、WP-CLI以外での無動作、出力の内容 |
| `SchemaUpgradePathTest` | スキーマ更新（通常のリクエスト）では移行が実行されず、Expenseが変わらないこと |
