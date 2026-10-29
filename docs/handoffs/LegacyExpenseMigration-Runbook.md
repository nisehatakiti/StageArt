# 旧形式Expense移行（Schema 1.44.0）運用手順案

作成日: 2026-10-29
対象ブランチ: `integrate/expense-approval-20261029`
対象コード: `plugin/src/Accounting/LegacyExpenseMigration.php`、`plugin/src/Accounting/LegacyExpenseMigrationClassifier.php`
仕様: `docs/04-DomainModel/ExpenseApprovalWorkflow.md`「Legacy CONFIRMED Expense」

本書は手順案であり、本番環境での実施を承認するものではない。本番DBへの接続、移行の実行、デプロイは、それぞれ別途承認を得てから行う。以下のSQLは確認用の読み取り専用クエリと、承認後に使う復旧用クエリの案である。テーブル接頭辞は`wp_`と仮定しているため、実環境の接頭辞に置き換える。

---

## 1. いつ実行されるか

- `SchemaUpgrader::maybeUpgrade()`は、保存済みのスキーマバージョン（`wp_options`の`stageart_db_schema_version`）が`1.44.0`と異なると、`Installer::install()`を実行する。移行はその中の`AccountingInstaller::install()`から呼ばれる。
- 1.44.0のコードを配置すると、**配置後の最初のリクエストで自動的に実行される**。配置後に移行だけを止めたり、事前に試したりする手段はない。そのため、事前確認とバックアップは**コード配置の前**に行う。
- 1.44.0より後のバージョンに上げるたびに再実行される。そのときCONFIRMEDのまま残っている行は再判定され、条件を満たすようになった行だけが移行される。

## 2. 判定条件

対象は`status = 'CONFIRMED'`の行だけで、上から順に判定する。

| 順 | 条件 | 結果 | 理由コード |
|---|---|---|---|
| 1 | `confirmed_by`または`confirmed_at`がNULL | CONFIRMEDのまま | `MISSING_CONFIRMATION` |
| 2 | 明細（`stageart_expense_lines`）が0件 | CONFIRMEDのまま | `NO_LINES` |
| 3 | `source_event_type = 'ExpenseConfirmed'`かつ`source_event_id = Expense ID`のJournal Entryが0件 | CONFIRMEDのまま | `NO_JOURNAL_ENTRY` |
| 4 | 該当Journal EntryのいずれかがREVERSED | CONFIRMEDのまま | `REVERSED_JOURNAL_ENTRY` |
| 5 | 該当Journal Entryが2件以上 | CONFIRMEDのまま | `MULTIPLE_JOURNAL_ENTRIES` |
| 6 | 該当Journal EntryがPOSTEDでない（DRAFT等） | CONFIRMEDのまま | `DRAFT_JOURNAL_ENTRY` |
| 7 | Journal EntryのProductionがExpenseと異なる（NULLを含む） | CONFIRMEDのまま | `PRODUCTION_MISMATCH` |
| 8 | 借方合計と貸方合計が異なる、または0（行がない場合を含む） | CONFIRMEDのまま | `UNBALANCED_JOURNAL_ENTRY` |
| 9 | 明細合計とJournal Entryの借方合計が異なる | CONFIRMEDのまま | `AMOUNT_MISMATCH` |
| — | 上記のいずれにも当たらない | **POSTEDへ移行** | — |

CONFIRMED以外の行は読まない。DRAFT、SUBMITTED、POSTED、REJECTEDのほか、未知の状態の行も変更しない（判定器には`UNKNOWN_STATUS`があるが、移行処理はCONFIRMEDしか読まないため使われない）。

旧確定処理は確定時に**下書き（DRAFT）のJournal Entry**を作っていた。別途転記（Post）していない旧データは、6の`DRAFT_JOURNAL_ENTRY`でCONFIRMEDのまま残る。旧データの多くはこの扱いになると見込まれる（推定。本番データは未確認）。

## 3. 移行で変わるもの・変わらないもの

移行した行（`status = 'CONFIRMED'`を条件にした1件ずつのUPDATE）：

| 列 | 移行後の値 |
|---|---|
| `status` | `POSTED` |
| `revision` | `1` |
| `submitted_by` / `submitted_at` | `created_by` / `created_at` |
| `requested_amount` / `approved_amount` | 明細合計 |
| `approved_by` / `approved_at` | `confirmed_by` / `confirmed_at` |
| `posted_journal_entry_id` | 該当Journal EntryのID |

あわせて`stageart_expense_reviews`に`action = 'MIGRATED'`の履歴を1件追加する（`INSERT IGNORE`。明細のスナップショットを含む）。

変わらないもの：

- Journal Entryとその明細は一切変更しない（件数・金額・状態とも同じ）。
- Actualは確定済み（POSTED）のJournal Entryから集計するため、移行によって変わらない。
- Expenseの明細、作成者、立替払いを受ける者（`payer_person_id`）、確認者（`confirmed_by` / `confirmed_at`）は変更しない。
- 移行しなかった行は一切変更しない。
- 移行したExpenseは承認済み（未払い）として扱い、支払済みとは推定しない（仕様書「Payment」）。

## 4. 判定不能データの記録と確認方法

- CONFIRMEDのまま残した行は、実行のたびに1行のPHPエラーログとして出力する。
  `[StageArt Schema 1.44.0] Expenses left CONFIRMED for manual review: <Expense ID>=<理由コード>, ...`
- ログに出るのはExpense IDと理由コードだけで、金額、氏名などの内容は出さない（テストで確認済み）。
- ログはPHPの`error_log`の出力先に書かれる。出力先はサーバー設定による（WordPressの`debug.log`とは限らない）。実際の出力先は未確認である。
- ログが見られない場合でも、移行後に5.3のSQLでCONFIRMEDの残りを一覧できる。DBの状態が正となる。
- CONFIRMEDの行は閲覧できるが、編集や状態遷移はできない（Domainで拒否）。画面の「旧形式（確認待ち）」表示は元の作業ツリーにある未統合の実装で、このブランチには含まれない。個別確認の進め方（転記するか、取り消すか）は別途定める。

## 5. 本番移行の手順案

### 5.1 事前確認（読み取りのみ、コード配置前）

```sql
-- スキーマバージョン
SELECT option_value FROM wp_options WHERE option_name = 'stageart_db_schema_version';

-- 状態別の件数
SELECT status, COUNT(*) FROM wp_stageart_expenses GROUP BY status;

-- CONFIRMEDの行ごとの判定材料（移行前のスキーマにある列だけを使う）
SELECT e.id,
       e.confirmed_by IS NULL OR e.confirmed_at IS NULL AS missing_confirmation,
       (SELECT COUNT(*) FROM wp_stageart_expense_lines l WHERE l.expense_id = e.id) AS line_count,
       (SELECT COALESCE(SUM(l.amount), 0) FROM wp_stageart_expense_lines l WHERE l.expense_id = e.id) AS line_total,
       (SELECT COUNT(*) FROM wp_stageart_journal_entries j
         WHERE j.source_event_type = 'ExpenseConfirmed' AND j.source_event_id = e.id) AS entry_count,
       (SELECT GROUP_CONCAT(j.status) FROM wp_stageart_journal_entries j
         WHERE j.source_event_type = 'ExpenseConfirmed' AND j.source_event_id = e.id) AS entry_statuses
FROM wp_stageart_expenses e
WHERE e.status = 'CONFIRMED';

-- Journal Entryの件数と金額（移行後の照合用）
SELECT COUNT(*) FROM wp_stageart_journal_entries;
SELECT debit_credit, COUNT(*), SUM(amount) FROM wp_stageart_journal_entry_lines GROUP BY debit_credit;
```

`missing_confirmation = 0`、`line_count > 0`、`entry_count = 1`、`entry_statuses = 'POSTED'`の行が移行候補になる。金額と借方・貸方の一致は、移行時に判定器が確認する。候補の件数と各行の理由を記録し、移行の承認を得る。

### 5.2 バックアップ（コード配置の直前）

1. 配置前に、DB全体のダンプを取得する（少なくとも`wp_stageart_*`の全テーブルと`wp_options`）。整合性を保つため、InnoDBでは`--single-transaction`を使う。
2. ダンプは`public_html`の外、またはホスティングのバックアップ領域に保存し、外部に共有しない。
3. ダンプのサイズと、5.1で取得した件数を記録する。可能であれば開発環境などに復元できることを確認する。
4. 配置前のプラグインコードを保管する（コードを戻すため）。
5. 本番の日次バックアップの有無と保持期間を確認する（`SystemAdministrationPolicy.md`「日次バックアップ」）。

### 5.3 移行後の確認

```sql
SELECT option_value FROM wp_options WHERE option_name = 'stageart_db_schema_version';  -- 1.44.0
SELECT status, COUNT(*) FROM wp_stageart_expenses GROUP BY status;
SELECT COUNT(*) FROM wp_stageart_expense_reviews WHERE action = 'MIGRATED';            -- 移行件数と一致
SELECT id FROM wp_stageart_expenses WHERE status = 'CONFIRMED';                         -- 判定不能の残り
SELECT COUNT(*) FROM wp_stageart_journal_entries;                                       -- 5.1と同じ
SELECT debit_credit, COUNT(*), SUM(amount) FROM wp_stageart_journal_entry_lines GROUP BY debit_credit;  -- 5.1と同じ
-- 移行した行は、MIGRATED履歴がちょうど1件ある
SELECT e.id FROM wp_stageart_expenses e
 WHERE e.status = 'POSTED' AND e.revision = 1 AND e.submitted_at = e.created_at
   AND NOT EXISTS (SELECT 1 FROM wp_stageart_expense_reviews r WHERE r.expense_id = e.id AND r.action = 'MIGRATED')
   AND EXISTS (SELECT 1 FROM wp_stageart_journal_entries j WHERE j.id = e.posted_journal_entry_id AND j.source_event_type = 'ExpenseConfirmed');
```

最後のクエリは、履歴を書けなかった移行済みの行を探す（6.1参照）。結果は0件であるべきである。

## 6. 既知の制約

1. **トランザクションがない。** 状態のUPDATEと履歴のINSERTは別の文で、まとめて巻き戻されない。UPDATEの後でINSERTが失敗すると、POSTEDだがMIGRATED履歴がない行が残る。再実行しても、その行はもうCONFIRMEDではないため直らない。5.3の最後のクエリで検出する。
2. **UPDATEの失敗は記録されない。** UPDATEが失敗した行（例：`posted_journal_entry_id`の一意制約違反）は、ログにもサマリーにも出ずにCONFIRMEDのまま残る。5.3でCONFIRMEDの残りを一覧すれば見つかるが、理由はログに出ない。
3. **勘定科目は照合しない。** 判定は合計額だけで、Journal Entryの借方科目と明細の科目の一致、貸方が未払金であることは確認しない。
4. **判定不能の記録はPHPエラーログだけ。** DBには理由を保存しない。

これらは現状の実装の事実であり、今回は変更していない。本番移行の前に対応するかどうかは別途判断する。

## 7. 復旧手順案

### 7.1 移行した行だけを戻す（推奨）

配置後にほかのデータが書き込まれていても、移行した行だけを戻せる。Journal Entryは変更していないため、戻す必要はない。

```sql
-- 対象の確認（MIGRATED履歴がある行だけ）
SELECT e.id FROM wp_stageart_expenses e
  JOIN wp_stageart_expense_reviews r ON r.expense_id = e.id AND r.action = 'MIGRATED' AND r.revision = 1
 WHERE e.status = 'POSTED';

-- 戻す（承認後、バックアップ取得後に実行）
UPDATE wp_stageart_expenses e
  JOIN wp_stageart_expense_reviews r ON r.expense_id = e.id AND r.action = 'MIGRATED' AND r.revision = 1
   SET e.status = 'CONFIRMED', e.revision = 0, e.submitted_by = NULL, e.submitted_at = NULL,
       e.requested_amount = NULL, e.approved_amount = NULL, e.approved_by = NULL, e.approved_at = NULL,
       e.posted_journal_entry_id = NULL
 WHERE e.status = 'POSTED';
DELETE FROM wp_stageart_expense_reviews WHERE action = 'MIGRATED';
```

- 戻した後も、1.44.0のコードのままなら再移行されない（スキーマバージョンは既に1.44.0のため）。次にバージョンを上げたときに再判定される。
- 6.1の履歴のない行は、このSQLでは戻らない。5.3のクエリで特定し、個別に戻す。

### 7.2 コードを1.44.0より前に戻す場合

- 1.44.0より前のコードは、DRAFTとCONFIRMED以外の状態を読めない。配置後に申請・承認・差し戻しされたExpense（SUBMITTED / POSTED / REJECTED）があると、旧コードでは読めなくなる。
- そのため、コードを戻すにはデータも戻す必要がある。5.2のバックアップから復元し、配置後に書き込まれたデータの扱い（失われる範囲）を事前に確認する。
- 旧コードのスキーマバージョンは1.44.0と異なるため、旧コードの配置後の最初のリクエストで旧Installerが実行され、バージョンが旧値に戻る。追加された列とテーブルは削除されない（dbDeltaは削除しない）。

### 7.3 全体の復元

7.1で対処できない問題があれば、5.2のダンプから復元する。配置後に書き込まれたデータは失われるため、復元前に影響範囲を確認し、承認を得る。

## 8. テストで確認した範囲

テストは偽のDB（`FakeExpenseMigrationDatabase`）で行った。実際のMySQLでは実行していない。

- `LegacyExpenseMigrationClassifierTest`：判定条件ごとの結果。
- `LegacyExpenseMigrationTest`：移行してよい行だけが変わること、2回目の実行で何も変わらないこと、Journal Entryのテーブルへ書き込まないこと。
- `LegacyExpenseMigrationPreflightTest`：本番で想定される行の種類を1つのDBにまとめて確認する。確認したことは次のとおり。
  - 移行する行の状態と金額
  - それ以外の行が一切変わらないこと
  - Journal Entryの件数と借方・貸方の合計が変わらないこと
  - 移行済み・新しいワークフローの行に触れないこと
  - 2回目の実行で何も変わらず、同じ行が判定不能として報告されること
  - ログにIDと理由コードしか出ないこと
