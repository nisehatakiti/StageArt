# PR #7 R7-3：仕訳の二重計上防止策の調査と設計案（2026-11-10）

状態：**調査と設計案のみ。実装していない。** スキーマ・仕訳作成処理は変更していない。

`docs/handoffs/JournalEntrySourceUniqueness-DesignNote.md`（2026-10-30）の調査を前提に、その後のPR #7（R4〜R7）の変更を踏まえて再確認した結果を記録する。

## 1. 発生元を表すデータ構造（変更なし）

- `wp_stageart_journal_entries.source_event_type`（VARCHAR(50)、NULL可）と `source_event_id`（CHAR(36)、NULL可）。一意制約もインデックスもない。
- 取消仕訳は `reversal_of_journal_entry_id` で元の仕訳を指し、元の仕訳と同じ発生元を引き継ぐ（`JournalEntry::createReversalOf()`）。元の仕訳は `status = REVERSED` になる。
- `findBySourceEvent()` は「`status != REVERSED` のうち最新の1件」を返す。設計上の前提は「発生元ごとに、取り消されていない元の仕訳は1件まで」。

## 2. 同一の発生元から複数の仕訳が正当に生じる場合

| 場合 | 仕訳 |
| --- | --- |
| 取消（返金付きCheck-in取消、経費の取消） | 元の仕訳（REVERSED）＋取消仕訳（同じ発生元） |
| 返金して取り消した後の再Check-in | 元（REVERSED）＋取消＋新しい `TicketPaymentReceived`（3件） |
| 旧形式データ | `ExpenseConfirmed` が1件のExpenseに複数ある場合がある（旧形式移行で手動確認の対象） |

このため `UNIQUE (source_event_type, source_event_id)` は設けられない（前回の結論どおり）。

## 3. 現在のアプリケーション側の重複防止

| 発生元 | 仕組み | R4〜R7での変化 |
| --- | --- | --- |
| `TicketPaymentReceived`（受付の入金） | `CheckInProcessor` が `findBySourceEvent` で既存の入金仕訳を確認してから作成 | R4：Check-in・当日券・Check-in取消はProduction共有ロック＋Reservation行ロック（`FOR UPDATE`）の下で実行。同じ予約への同時Check-inは行ロックで直列化され、後の処理は確定済みのCheck-inを返し、仕訳を作らない。R6：確認の読み取り失敗を「計上なし」と扱わず例外にした（実MySQLで二重計上を再現・修正確認済み） |
| `ExpenseApproved` | Expense行ロック＋`posted_journal_entry_id` の一意制約 | 変更なし（PR #6で実MySQL同時承認を確認済み） |
| `OrganizationAccountingEnabled` | Organization作成のトランザクション内で1回 | 変更なし |

## 4. 二重計上が起きうる経路の再評価

| 経路 | 評価 |
| --- | --- |
| 同じ予約への同時Check-in（2台の受付端末） | 行ロックで直列化され、後の要求は先に確定したCheck-inをそのまま返す（両方200、同じ `check_in_id`）。仕訳は1件。**防止済み**（R7で実MySQL・REST・会計利用ありで4回確認：入金仕訳1件、Check-in 1件） |
| ロック待ちタイムアウト・デッドロック後の再送 | 1件目は全体ROLLBACK（503）のため、再送しても1件。**防止済み**（R5） |
| COMMIT後に通信が切れ、クライアントが再送 | 2回目は確定済みのCheck-inを読み、新しい仕訳を作らない（同時Check-inと同じ経路）。仕訳は1件。**防止済み**。当日券は冪等キーで元の結果を返す |
| 確認の読み取りだけが失敗し、作成は成功する | R6で修正（例外→ROLLBACK）。**防止済み** |
| アプリケーションのバグ・将来の別経路からの作成、DBを直接操作した場合 | **DB側の保証がないため防げない**（残存リスク） |
| 既存データ | 本番・開発のデータに重複があるかは**未確認**（Dev・Productionには触れていない） |

## 5. 設計案

### 案A（推奨・第1段階）：非一意インデックス

```sql
KEY source_event (source_event_type, source_event_id)
```

- 既存の処理・データを一切拒否しない。`findBySourceEvent()`（受付・承認のたびに実行）の全件走査を解消する。
- スキーマ番号を上げ、dbDeltaのインデックス追加として扱える。本番件数でのインデックス作成時間・ロックの確認が必要。

### 案B（第2段階）：「取り消されていない元の仕訳」だけの一意制約（生成列）

```sql
live_source_key VARCHAR(90) AS (
  IF(status <> 'REVERSED' AND reversal_of_journal_entry_id IS NULL
     AND source_event_type IN ('TicketPaymentReceived', 'ExpenseApproved', 'OrganizationAccountingEnabled')
     AND source_event_id IS NOT NULL,
     CONCAT(source_event_type, ':', source_event_id), NULL)) STORED,
UNIQUE KEY live_source (live_source_key)
```

- 2の正当な複数仕訳（取消・再計上）はすべて通り、取り消されていない2件目だけを1062で拒否する（MySQL 5.7.44の一時テーブルで動作確認済み、前回の調査）。
- 旧形式の発生元（`ExpenseConfirmed`・`CheckInCompleted`・`ReservationNoShow`）は対象外にする。
- 前提条件：(1) 既存データの重複確認（下記SQL、読み取りのみ）で0件であること、(2) 1062を409等に対応付けること（現在は保存失敗→ROLLBACK→500）、(3) dbDeltaでは生成列を比較できないため `information_schema` を確認した `ALTER TABLE` で追加すること、(4) MySQL 8.0・MariaDBでの確認。

### 案C（代替）：冪等キーの台帳

発生元ごとの冪等キーを別テーブル（`UNIQUE`）に記録してから仕訳を作る。取消・再計上の扱いをキーの設計に持ち込む必要があり、案Bより複雑になるため推奨しない。

## 6. 既存データの重複確認SQL（読み取りのみ）

```sql
SELECT source_event_type, source_event_id, COUNT(*) AS live_entries,
       GROUP_CONCAT(id ORDER BY created_at) AS ids
  FROM wp_stageart_journal_entries
 WHERE status <> 'REVERSED' AND reversal_of_journal_entry_id IS NULL
   AND source_event_type IS NOT NULL AND source_event_id IS NOT NULL
 GROUP BY source_event_type, source_event_id
HAVING COUNT(*) > 1;
```

本番・開発での実行はユーザーの判断で行う（本作業では実行していない）。

## 7. 結論

- 現在の経路では、同時実行・再送・通信断・読み取り失敗のいずれでも二重計上は起きない（R4〜R7で確認）。DB側の保証はない。
- まず案A（非一意インデックス）を別PRで入れ、本番データの重複確認の結果を見て案Bを判断することを推奨する。
- 今回は会計仕様・既存データへの影響を確認できないため、実装しない。
