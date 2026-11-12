# PR #7：全体ロードマップへの追記事項（2026-11-11、R8時点）

全体ロードマップ `docs/StageArtDevelopmentRoadmap.md` は `main` にだけ存在し（`3e8728f`、`554a018`）、`feature/user-account-identity` には含まれていない。同じパスに作成するとマージ時に競合するため、反映すべき内容をここに記録する。PR #7のマージ後（または `main` の取り込み後）に、ロードマップ本体へ反映すること。

## §4.5 チケット・予約・受付 / §4.6 会計・予算・実績・支払精算 に追記

- PR #7（R3〜R7）で、決算完了・精算・予約・受付・設定変更をProduction単位のロック規約で整合させた（`docs/04-DomainModel/ProductionLifecycle.md`）。CANCELLED / COMPLETED / ARCHIVEDの公演活動の制限、HTTP 409 / 503 / 500 の応答を確定。
- 定員を予約済み席数より少なくする変更を禁止（`docs/23-CapacityPolicy.md` §6 9〜12）。決算完了後はPerformanceの編集・中止も不可。
- R7：精算済み額が確定額を上回る（精算後の返金による払い過ぎ）場合は決算完了を拒否（422 `stageart_production_settlement_overpaid`）。

## §4.x 稽古管理・タイムテーブル に追記

- R8：稽古詳細の参加メンバー追加（追加者のみ通知）、本人の回答表示（未回答時のみ回答ボタン）、管理者の出欠プルダウンと［更新］、稽古の自動開始・終了（手動ボタンなし）を、20-RehearsalManagementScreen.md §21 のとおり実装。メンバー実績サマリーを§19どおり（中止除外、実績未記録は欠席）に修正。
- R8：タイムテーブルは「小屋入り～本番 → タイムテーブル」の独立画面で作成・編集し、［保存］で公開（04-CommonNavigationDesign.md §20.3.1）。

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
10. ~~Frontendの仕様矛盾7件~~：R8で解消（`PR7-FrontendTestAudit.md` §7）。
11. **精算サイクル**（`PR7-SettlementCycleInvestigation.md`）：「チケット精算」の新設、個別精算の開始条件、精算開始後のCheck-in取消・返金の禁止、例外的な訂正方法。現行仕様の変更になるため判断待ち。
12. **稽古管理の残課題**（20-RehearsalManagementScreen.md §21）：回答済みの回答変更（§8.1との関係）、未回答・欠席回答者の実績記録（Domain規則と§13の不一致）、稽古作成・確定時の通知（§9.1・§11が未実装）、手動の開始・終了APIの扱い。
