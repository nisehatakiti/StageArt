# PR #7：全体ロードマップへの追記事項（2026-11-10、R7時点）

全体ロードマップ `docs/StageArtDevelopmentRoadmap.md` は `main` にだけ存在し（`3e8728f`、`554a018`）、`feature/user-account-identity` には含まれていない。同じパスに作成するとマージ時に競合するため、反映すべき内容をここに記録する。PR #7のマージ後（または `main` の取り込み後）に、ロードマップ本体へ反映すること。

## §4.5 チケット・予約・受付 / §4.6 会計・予算・実績・支払精算 に追記

- PR #7（R3〜R7）で、決算完了・精算・予約・受付・設定変更をProduction単位のロック規約で整合させた（`docs/04-DomainModel/ProductionLifecycle.md`）。CANCELLED / COMPLETED / ARCHIVEDの公演活動の制限、HTTP 409 / 503 / 500 の応答を確定。
- 定員を予約済み席数より少なくする変更を禁止（`docs/23-CapacityPolicy.md` §6 9〜12）。決算完了後はPerformanceの編集・中止も不可。
- R7：精算済み額が確定額を上回る（精算後の返金による払い過ぎ）場合は決算完了を拒否（422 `stageart_production_settlement_overpaid`）。

## 将来課題（「未完了・次アクション」へ）

1. **決算完了後の訂正**（要仕様判断）：追加精算・返金・調整仕訳と監査履歴（理由・実行者・日時・金額）。公演の再開機能は作らない。
2. **精算明細の新設要否**：現在の精算記録は累計と最後の1回だけ。複数回精算した後の払い過ぎは、現在の操作では解消できず決算完了できない（`ProductionLifecycle.md`「決算完了の精算条件」）。
3. **メンバー精算を会計仕訳として扱うか**（現在は仕訳なし）。
4. **Repository監査のB・C分類の残件**（`PR7-RepositoryReadErrorAudit.md`）。
5. **MySQL 8.0 / MariaDB検証**（`LOCK IN SHARE MODE`、生成列を含む）、エラー2013、本番規模の待ち時間計測。
6. **デッドロック時の503の実DB検証**（現在は単体テストのみ）。
7. **アプリ全画面での409・503表示の確認**（`getErrorMessage` を使わない画面がありうる）。
8. **DB一意制約・冪等性設計**（`PR7-JournalEntryIdempotency.md`：まず非一意インデックス、次に生成列の一意制約）。
9. **セルフ予約の試行回数制限**（`PR7-SelfServiceReservationRateLimit.md`：本番公開前に応答統一と回数制限）。
10. **Frontendの仕様矛盾7件**（`PR7-FrontendTestAudit.md` §4：稽古詳細のメンバー追加・開始/終了・本人回答表示・出席実績、タイムテーブル作成）。画面を戻すか仕様を改訂するかの判断待ち。
