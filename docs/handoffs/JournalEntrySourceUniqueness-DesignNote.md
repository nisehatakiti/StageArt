# 仕訳の発生元による二重作成防止：調査結果（設計記録）

作成日: 2026-10-30
対象ブランチ: `integrate/expense-approval-20261029`
状態: **調査のみ。実装しない。** スキーマ・仕訳作成処理は変更していない。

本書は、実MySQL検証（2026-10-30）の後に行った「Journal Entryに発生元を識別する一意制約を設けられるか」の調査結果を記録する。決定事項は次のとおりである。

- `source_event_type`・`source_event_id`の単純な一意制約は**設けない（禁止）**。理由は2を参照。
- 生成列による一意制約（3）も**今回は実装しない**。
- 非一意インデックス（4）は、今後の改善候補として記録する。

---

## 1. 現在の仕訳と発生元の関連付け

`wp_stageart_journal_entries`は、発生元を`source_event_type`（VARCHAR(50)、NULL可）と`source_event_id`（CHAR(36)、NULL可）で持つ。この2列には**一意制約もインデックスもない**（主キーと`organization_id`・`production_id`・`status`のインデックスだけ）。取消仕訳は`reversal_of_journal_entry_id`で元の仕訳を指す。

| `source_event_type` | 作成する処理 | `source_event_id` | 二重作成の防ぎ方（現在） |
|---|---|---|---|
| `ExpenseApproved` | `ApproveExpenseUseCase`（経費の承認） | Expense ID | Expenseの行ロック（`findByIdForUpdate`）の中で`findBySourceEvent`を確認。Expenseの`posted_journal_entry_id`には一意制約がある |
| `TicketPaymentReceived` | `CheckInProcessor`（受付での代金受領） | Reservation ID | `findBySourceEvent`（取り消された仕訳を除く）で確認 |
| `OrganizationAccountingEnabled` | `CreateOrganizationUseCase`（会計を有効にしてOrganizationを作成したときの開始残高） | Organization ID | Organizationの作成のトランザクションの中で1回だけ作る（新しいOrganization IDのため、既存の仕訳と重ならない） |
| `ExpenseConfirmed` | なし（旧形式。現在は作成しない） | Expense ID | 旧データ。旧形式Expense移行は読むだけで、仕訳を作らない |
| `CheckInCompleted`・`ReservationNoShow` | なし（旧形式。現在は作成しない） | CheckIn ID・Reservation ID | 旧データ。受付の二重計上の確認でだけ読む |
| NULL | — | — | 発生元のない仕訳 |

`findBySourceEvent()`は、`status != 'REVERSED'`の仕訳のうち最新の1件を返す。つまり現在の設計は「発生元ごとに、取り消されていない仕訳は1件まで」を前提にしている。

実MySQL 5.7.44で、同じExpenseを2つのプロセスから同時に承認した場合、片方が200、もう片方が409になり、承認仕訳は1件だけ作られることを確認した（2026-10-30）。

## 2. 単純な一意制約を設けられない理由

`UNIQUE (source_event_type, source_event_id)`は、現在の正しいデータと処理を拒否する。

1. **取消仕訳が元の仕訳と同じ発生元を持つ。** `JournalEntry::createReversalOf()`は、元の仕訳の`source_event_type`・`source_event_id`をそのまま引き継ぐ。取り消しを行うと、同じ発生元の仕訳が必ず2件になる。
2. **取り消した後に同じ発生元で記録し直す。** 受付で、返金して取り消した後に再び受付をすると、同じReservationについて新しい`TicketPaymentReceived`の仕訳が作られる（元の仕訳・取消仕訳・新しい仕訳の3件）。
3. **旧データに重複がありうる。** 旧形式の`ExpenseConfirmed`の仕訳は、1件のExpenseに複数ある場合がある（旧形式Expense移行では`MULTIPLE_JOURNAL_ENTRIES`として手動確認の対象にしている）。本番・開発のデータは確認していない。制約を追加すると、スキーマ更新が失敗するおそれがある。

## 3. 一意制約を追加する場合の候補キー（参考。実装しない）

「取り消されていない元の仕訳」だけを一意にする。MySQLには部分インデックスがないため、生成列を使う。

```sql
-- 参考（実装しない）：取り消されていない元の仕訳だけに値を持つ生成列と、その一意制約
live_source_key VARCHAR(90) AS (
  IF(status <> 'REVERSED' AND reversal_of_journal_entry_id IS NULL
     AND source_event_type IS NOT NULL AND source_event_id IS NOT NULL,
     CONCAT(source_event_type, ':', source_event_id), NULL)) STORED,
UNIQUE KEY live_source (live_source_key)
```

MySQL 5.7.44の一時テーブル（検証用DBのみ。永続的な変更なし）で、次を確認した。

| 操作 | 結果 |
|---|---|
| 取り消されていない同じ発生元の仕訳を2件目として追加 | エラー1062で拒否 |
| 発生元のない仕訳を複数追加 | 追加できる（NULLは重複しない） |
| 元の仕訳を取消済みにし、取消仕訳を追加し、同じ発生元で新しい仕訳を追加 | すべて追加できる |
| 新しい仕訳がある状態で、元の仕訳を取消前の状態に戻す | エラー1062で拒否 |

実装する場合に必要になること（参考）：

- スキーマの番号を上げる（1.46.0以上）。dbDeltaは生成列を正しく比較できないおそれがあるため、インストーラーで`information_schema`を確認したうえでの`ALTER TABLE`にする必要がある。
- 追加の前に、既存データの重複を読み取りだけで確認する（下記の確認SQL）。重複がある場合は追加しない。旧形式の発生元（`ExpenseConfirmed`等）を対象から外す（式に今使っている3種類の発生元だけを含める）ことも検討する。
- 一意制約に当たった場合、現在は`WordPressJournalEntryRepository::save()`が例外を投げ、周りのトランザクションごとロールバックされる（データは安全）。ただしAPIの応答は409ではなくエラーになるため、409等への対応付けが必要になる。
- MySQL 8.0・MariaDBでの生成列と一意制約の動作は確認していない。

```sql
-- 取り消されていない元の仕訳で、発生元が重複しているもの（読み取りのみ。通常は0件）
SELECT source_event_type, source_event_id, COUNT(*) AS live_entries, GROUP_CONCAT(id ORDER BY created_at) AS ids
  FROM wp_stageart_journal_entries
 WHERE status <> 'REVERSED' AND reversal_of_journal_entry_id IS NULL
   AND source_event_type IS NOT NULL AND source_event_id IS NOT NULL
 GROUP BY source_event_type, source_event_id
HAVING COUNT(*) > 1;
```

## 4. 今後の改善候補：非一意インデックス

```sql
-- 改善候補（未実装）：発生元での検索のためのインデックス（一意ではない）
KEY source_event (source_event_type, source_event_id)
```

- `findBySourceEvent()`（承認・受付のたびに実行）と、旧形式Expense移行の仕訳の検索は、現在インデックスがないためテーブル全体を読む。仕訳の件数が増えると遅くなる。
- 一意ではないため、2の取消仕訳・記録し直し・旧データの重複のどれも拒否しない。既存の処理の動作は変わらない。
- 旧形式Expense移行は、発生元での検索を「インデックスがないため、ロックせずに読む」前提で書いている（`LegacyExpenseMigration`のコメント）。インデックスを追加した場合も、ロックせずに読む動作は変わらない。
- 追加する場合はスキーマの番号を上げる（dbDeltaのインデックスの追加として扱える）。大きなテーブルでのインデックスの作成時間とロックは、本番の件数で確認する必要がある。

## 5. 結論

- 現在は、承認はExpenseの行ロックと`posted_journal_entry_id`の一意制約、受付は`findBySourceEvent`の確認で二重作成を防いでおり、実MySQLの同時承認の試験でも二重作成は起きていない。急ぎの対応は不要と判断する。
- 単純な一意制約は設けない。生成列による一意制約は、既存データの重複確認・409への対応付け・MySQL 8.0／MariaDBでの確認の後に、別の計画として判断する。
- 非一意インデックスは、性能の改善として先に検討できる候補である。
