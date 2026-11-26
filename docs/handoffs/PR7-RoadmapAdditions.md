# PR #7：全体ロードマップへの追記事項（2026-11-11、R8時点。2026-11-19 R14-Bを追記）

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

## R14-B：本人の同意に基づく公演参加者登録（2026-11-19、第10段階の時点）

- 設計の正本：`docs/handoffs/PR7-R14B-ParticipantInvitationDesign.md`（設計書ブランチ `docs/r14b-participant-invitation-design`、第9版 `9b42eb5`。実装ブランチには取り込んでいない）。
- 実装ブランチ：`feature/r14b-participant-consent`（`e645b46`）。PR #7 への取り込みは未定。

### 実装済み（第1〜9段階、第10段階の D-1）

- 招待による参加：
  - 公演参加者の招待の作成・一覧・再送・取消。
  - 本人の招待の一覧・承諾・辞退（ログイン必須、確認済みのアドレスだけ、トークンなし）。
  - 人物の直接追加・登録時の自動解決の廃止。NAME_ONLYの記録だけを残した。
- 参加者の状態と同意：
  - 参加者の状態（PENDING・REJECTED・WITHDRAWN を含む7つ）と、INACTIVE → ACTIVE の直接の復帰の禁止。
  - 同意の記録（`enrollment_source`・`consent_source`・`consented_at`）。
  - 本人の参加の取りやめ（withdraw。PrimaryManagerは不可）。
- 担当者：
  - 担当者の有効性（同意を記録済みのACTIVEな参加者だけ）と、参加者の状態の変更による担当者の無効化。
  - `GET /productions/{id}/delegates/me`（第9段階で追加）。
- 参加者情報の開示の制御（設計書 §7.3）と、`/people` の削除。
- 監査ログ（`stageart_participant_events`）と送信の記録。
- レート制限、email_key の鍵、保存期間の削除（WP-Cron）、個人宛てのAPIの `Cache-Control: no-store`。
- スキーマ 1.49.0（追加だけ）。
- Frontend：
  - メンバー管理（招待・招待の一覧・同意の確認の依頼）、担当者の画面。
  - 招待の一覧・確認、ホームの件数、参加の取りやめ、担当者本人への無効の理由の表示。
  - 古い `/register?token=` のリンクの処理、ログイン・名前の設定の後の `return_to`。
- D-1：新しい団体招待メールのリンクを、トークンなしの `/register` に変更した（`e645b46`）。団体招待のBackendの処理は変更していない。

### テスト済み

- PHPUnit：3,043件成功（`e645b46`）。
- Jest：302スイート・672件成功（`5e3d660`。D-1 では Frontend を変更していない）。
- tsc・eslint：エラー0。Web の export：成功。
- 隔離環境（MySQL 5.7）で、実REST・実DBの確認：
  - 同時実行・ロック順序・Migration、レート制限、保存期間。
  - APIの契約の確認：27件。

### ドキュメント更新済み（第10段階、未コミット）

- `docs/08-API/ParticipantAPI.md`（3.1）・`README.md`・`ProductionAPI.md`・`PersonAPI.md`
- `docs/21-MemberManagementScreen.md`（1.3）
- `docs/04-DomainModel/Participant.md`（2.2）・`ProductionDelegate.md`（2.2）
- `docs/09-Authorization/Authorization.md`・`ProductionDelegatePolicy.md`
- `docs/07-DomainEvents/README.md`・`ParticipantAdded.md`・`ParticipantRemoved.md`・`ParticipantUpdated.md`
- `docs/handoffs/PR7-R14B-OperationsRunbook.md`
- 設計書（正本）への D-1 の反映：文案を作成済み。ユーザーの承認待ち。

### Dev 環境での確認待ち（いずれも未実施。実施の都度、ユーザーの承認が必要）

- コードの配置とスキーマ 1.49.0 の確認、WP-Cron の登録の確認（`DISABLE_WP_CRON` なら外部の cron）。
- 鍵を設定した後の、招待の作成（201）の確認。
- 個人宛てのAPIの `no-store` と nginx のキャッシュの確認（設計書 §14.3、運用手順書 §11）。
- PR #7 の公開ページのキャッシュの確認（設計書 N3。本番への適用の前提）。
- ブラウザ・実機での一連の操作（招待 → 登録・ログイン → 承諾、古いリンク、団体招待の新しいリンク）、メールの到達。

### 本番リリース前の運用作業（いずれも未実施）

- Dev・本番の `wp-config.php` に `STAGEART_EMAIL_KEYS`・`STAGEART_EMAIL_KEY_CURRENT` を設定する（環境ごとに別の値。既存の値は変えない）。
- 既存の担当者の移行（運用手順書 §6）：件数の把握、PrimaryManagerへの周知、適用の時期の判断。
- Backend と Frontend の同時配布（運用手順書 §7.1）。
- 本番への適用のユーザーの承認。

### R14-B の残課題（R14-C ではない）

- 設計書 §9.2 の【推奨】：招待の一覧・確認の画面（Web）の `Referrer-Policy: no-referrer` は未実装。
- Frontend の `AuthContext.registerWithEmail` に、使われていない `invitationToken` の引数が残っている（動作への影響はない）。
- `docs/12-FunctionalStructure.md` §21（Production Member Management Screen）は、R14-Bより前の「メンバーを追加（直接追加）」のままで、第10段階の更新の対象外とした。
- MySQL 8.0・MariaDB での検証（既存の課題 5 と同じ）。
- Android App Links・iOS Universal Links（招待メールのリンクがアプリで開かない）。設計書で別の課題として記録している。

### R14-C で対応する事項（R14-B の完了条件には含めない）

- 団体招待を、本人の同意で所属する方式に変える（設計書 D1）。次を廃止し、承諾・辞退で所属するようにする。
  - 既存の利用者をその場で所属させる処理
  - 登録・メールの確認・Googleサインインの時点での自動解決
- 団体招待の専用の一覧・確認の画面と、ログイン後の移動先。
- 団体招待メールのリンク・文面と、団体招待の有効期限（24時間。公演招待の7日とは別）の見直し。宛先のアドレスで登録する必要があることの案内。
- 使われていない団体招待の処理の整理：
  - 登録時のトークンによるメールの確認の省略（`invitation_token`）
  - `GET /organization-invitations/by-token/{token}`
- 団体に所属したときの本人への通知、アドレスが異なる場合の案内。
- 団体のメンバーの一覧でメールアドレスが見える問題（設計書 §16.3）。
- 団体の代理人の任命の条件（ACTIVEな所属）との整合。
