# PR #7：全体ロードマップへの追記事項（2026-11-10、R6時点）

全体ロードマップ `docs/StageArtDevelopmentRoadmap.md` は `main` にだけ存在し（`3e8728f`、`554a018`）、`feature/user-account-identity` には含まれていない。このブランチで同じパスに作成するとマージ時に競合するため、ロードマップへ反映すべき内容をここに記録する。`main` をこのブランチへ取り込んだ時点、またはPR #7のマージ後に、ロードマップ本体へ反映すること（取り込みの判断はユーザーが行う）。

## §4.5 チケット・予約・受付 / §4.6 会計・予算・実績・支払精算 に追記

- PR #7（R3〜R6）で、決算完了・精算・予約・受付・設定変更をProduction単位のロック規約で整合させた（`docs/04-DomainModel/ProductionLifecycle.md`）。CANCELLED / COMPLETED / ARCHIVEDの公演活動の制限、HTTP 409 / 503 / 500 の応答を確定。
- 定員を予約済み席数より少なくする変更を禁止（`docs/23-CapacityPolicy.md` §6 9〜12）。
- 決算完了後はPerformanceの編集・中止も不可。

## 将来課題（Roadmapの「未完了・次アクション」へ）

1. **決算完了後の訂正**（会計、未着手・要仕様判断）：精算記録を変更・削除せず、追加精算・返金・調整として記録し、理由・実行者・日時・金額を監査履歴に残す方向（`ProductionLifecycle.md`「決算完了後の訂正（設計課題）」）。仕様判断が必要な点：
   - 現在の精算記録は累計と最後の1回だけを保持し、1回ごとの履歴がない（精算明細の新設が必要）。
   - メンバー精算はJournal Entryを生成しない。調整を仕訳で記録するなら、精算自体の会計処理を先に決める。
   - 権限、ARCHIVED後の扱い。公演の再開機能は作らない（R5・R6の決定）。
2. **Repository読み取りエラーの一括対応**（B・C分類、`docs/handoffs/PR7-RepositoryReadErrorAudit.md`）。
3. **`journal_entries` のソースイベント一意性**の根本対策（`JournalEntrySourceUniqueness-DesignNote.md`）。
4. **Frontendのテスト・型チェックの不合格**（R6時点、PR #7のコミット済みコード）：Jest 248スイート中38〜40スイート・46〜48件が失敗（ホーム、ログイン、稽古・出欠画面。存在しないtestIDを参照するなど、画面の作り替えにテストが追随していない）。`tsc --noEmit` 3件（稽古の `activityType` がAPI型に無い2件、`@/global.css` の型宣言1件）、`expo lint` エラー12件。Webビルド（`expo export --platform web`）は成功。
5. **MySQL 8.0 / MariaDB** での検証（`LOCK IN SHARE MODE` を含む）、エラー2013、本番規模の待ち時間計測。
6. 自己申告の予約変更・取消（予約番号＋メールで認証）に対する試行回数制限は未実装（R6のレビューで確認）。
