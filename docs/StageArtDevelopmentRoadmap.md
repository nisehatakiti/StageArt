# StageArt 全体開発ロードマップ・進捗管理台帳

- **管理対象リポジトリ**: `nisehatakiti/StageArt`
- **正本ファイル**: `docs/StageArtDevelopmentRoadmap.md`
- **基準日**: 2026-11-09
- **管理方針**: 本書をStageArt全体の進捗管理の入口とし、各Domain Blueprint・Module仕様・PR・テスト結果を根拠として更新する。
- **重要**: 本書は全体を見渡す管理台帳であり、各Domainの業務仕様を置き換えない。仕様の正本はリンク先のBlueprintとする。

---

## 1. このロードマップの目的

StageArtを一つの舞台芸術活動プラットフォームとして完成させるため、個別機能の開発に偏らず、全機能の状態・依存関係・優先順位を一元管理する。

StageArtの完成像は、団体の設立・メンバー管理から、公演企画、制作、稽古、チケット販売、予約・受付、会計・精算、活動履歴、広報までを一貫して支援すること。

今後は、個別タスクを開始する前に必ず本書を確認し、対象機能・全体フェーズ・依存関係を明記する。タスク完了時には本書を更新する。

## 2. ステータス定義と判定ルール

機能の状態と、実装レイヤーの状態を混同しない。例えばBackendが実装済みでも、Web UI・テスト・Dev反映が未完了なら、その機能を「完成」としない。

| ステータス | 意味 |
|---|---|
| **完成** | 要件を満たし、Backend/API/UI/DB/テスト/Dev/運用確認が必要範囲で完了し、受入条件を満たした |
| **実装済み・未検証** | コード・画面等は存在するが、必要なテスト・実環境確認・受入確認が不足 |
| **開発中** | 実装または検証が進行中 |
| **仕様確定・未着手** | Blueprint等で方針が確認できるが、実装に着手していない |
| **設計中** | 要件や方式を整理している段階。未確定事項が残る |
| **構想段階** | 将来構想として記載されているが、具体的な仕様・実装計画が未確定 |
| **要調査** | 現在のコード・DB・画面・環境を確認できておらず、状態を断定できない |
| **対象外** | 現フェーズでは実装しないと明示されている |

レイヤー別の記録は、原則として次の値を使う。

- 完了
- 一部
- 未着手
- 未確認
- 対象外

**注意**: 「Blueprintに記載がある」ことは「実装済み」を意味しない。「テストが通る」ことは「Devに反映済み」を意味しない。旧リポジトリに機能が存在することは「新StageArt本体に統合済み」を意味しない。

## 3. 全体サマリー（2026-11-09時点）

| 領域 | 現在の総合状態 | 現状の要点 | 次の大きな作業 |
|---|---|---|---|
| アカウント・認証・Identity | 要調査 | Person/UserAccount/Identityの設計と実装資産あり。AuthCoreとの接続・本番運用状態は別途確認が必要 | 認証フロー、アカウント紐付け、復旧・セキュリティの統合確認 |
| 団体・メンバー・権限 | 開発中／要調査 | Organization/Membership/Authorizationの基盤あり。招待・参加・委任領域の統合状態に差分がある | PR #6後のDev差分整理、権限・委任領域の統合 |
| Project・公演管理 | 実装済み・未検証／要調査 | Coreの主要Domain。公演CRUD・参加者管理等の実装記録あり | 最新コード・UI・テスト・Devの一体確認 |
| 稽古・予定管理 | 実装済み・未検証 | Rehearsal ModuleはBackend/Web、Contract・Package境界の整備記録あり | 通知配送・Google Calendar・実運用シナリオ確認 |
| チケット・予約・受付 | 仕様確定・未着手 | 新StageArtのPerformance & Ticket ModuleはBlueprint上、実装未開始 | PerformanceSessionからCheckInまでの実装計画・着手 |
| 会計・予算・実績・精算 | 開発中 | Account/Budget/Expense/Journal Entryの基本実装とModule境界あり。PR #6は経費申請・承認・旧形式移行のBackend変更 | PR #6のマージ前最終確認後、UI・予算実績・支払精算を順次実装 |
| 公開ページ・広報 | 実装済み・未検証／要調査 | Web βの基本表示・検索等の実装記録あり。Confirmed Blueprintとの差分が残る | 公開URL・公開制御・ページ構成・配信基盤の統合計画 |
| 内部ポータル・通知・文書 | 一部実装／要調査 | Home Dashboard・通知・お知らせ等の実装記録あり。Document/Drive等は確認が必要 | 通知契約・配送、文書共有、権限別導線の確認 |
| アンケート | 仕様確定・未着手 | 公演別ページ自動生成、管理者による設問設定、終演後メール送信の要求あり | Survey Blueprintの正本化・API/UI/メール実装 |
| 活動履歴・プロフィール | 要調査 | Domain方針はある。出演・スタッフ・観劇履歴の自動生成と画面の完成度は未確認 | Fact発生源と履歴生成ルールの棚卸し |
| 備品・規約・団体運営 | 仕様確定・未着手／要調査 | Equipment/Regulation等のDomain方針あり。実装状態は未確認 | 団体運営機能の優先順位と最小スコープ確定 |
| 外部連携（Drive/Calendar/SNS） | 設計中／要調査 | Blueprintに対象あり。接続・OAuth・同期・運用状態は個別確認が必要 | 連携ごとに認証・同期・失敗時運用を設計 |
| リリース・運用基盤 | 要調査 | Dev環境とDB差分の確認記録あり。本番環境・監視・バックアップ等の全体判定は未実施 | 環境一覧、デプロイ手順、バックアップ・復旧、受入基準を整備 |

### 現在の開発集中点

**現在の最優先作業はPR #6（会計Backend：Expense申請・承認ワークフローと旧形式移行）である。**

ただし、これはStageArt全体の一領域にすぎない。PR #6の作業を進めながら、チケット・予約、公開ページ、アンケート、外部連携等の後続フェーズを並行して可視化し、会計だけが全体計画を占有しないようにする。

## 4. 機能別進捗マトリクス

### 4.1 アカウント・認証・Identity

| 項目 | 状態 | 備考 |
|---|---|---|
| 仕様 | 一部 | Person/UserAccount/Identityの分離方針あり。詳細はIdentity/Account関連Blueprintを正本とする |
| Backend | 一部／要調査 | 実装履歴あり。最新mainと開発ブランチの差分を確認する |
| API | 要調査 | 認証・本人確認・アカウント紐付けの全経路を再確認 |
| Web UI | 要調査 | 登録・ログイン・本人確認・アカウント設定を一連で確認 |
| DB | 要調査 | 最新Schema・migration履歴を確認 |
| テスト | 要調査 | 認証・権限・境界値・復旧フローを統合確認 |
| Dev | 未確認 | 実環境での一連の受入確認が必要 |
| 本番準備 | 未確認 | セキュリティ・監視・障害時復旧を確認 |

**未完了・次アクション**: AuthCoreとの接続境界、StageArt内のPersonとUserAccountの紐付け、ログイン・登録・本人確認・パスワード復旧を一連の利用シナリオで確認する。AuthCoreリポジトリの実装をStageArt本体の完成判定に流用しない。

### 4.2 団体・メンバー・権限管理

| 項目 | 状態 | 備考 |
|---|---|---|
| 仕様 | 一部 | Organization/Membership/Role/AuthorizationのBlueprintあり |
| Backend | 一部 | Organization/Membership/Delegate関連の実装範囲を再棚卸し |
| API | 一部／要調査 | 招待・参加申請・承認・拒否・委任の経路を確認 |
| Web UI | 一部／要調査 | 団体設定・メンバー管理・権限管理画面の受入確認が必要 |
| DB | 要調査 | Devに存在する委任関連テーブルとPR #6のbase/head差分を別管理 |
| テスト | 一部／要調査 | Role/Capabilityごとの許可・拒否を確認 |
| Dev | 未確認 | PR #6対象外の委任関連DB差分を含めて確認 |
| 本番準備 | 未確認 | Owner移譲・退会・代理権失効等の運用確認 |

**未完了・次アクション**: 団体管理、メンバー管理、Role/Permission、Delegate Areasを機能単位で分け、API/UI/DBの実装差分を整理する。権限は画面表示だけでなくBackendで保証する。

### 4.3 Project・公演管理

| 項目 | 状態 | 備考 |
|---|---|---|
| 仕様 | 一部 | Organization → Project → Productionの階層を正本とする |
| Backend | 実装記録あり／要調査 | 最新mainと開発ブランチの差分確認が必要 |
| API | 要調査 | CRUD・参加者・公開設定等を確認 |
| Web UI | 実装記録あり／要調査 | Setup Wizard・公演管理・参加者管理の受入確認が必要 |
| DB | 要調査 | migrationとDev schemaを照合 |
| テスト | 要調査 | Production lifecycle、参加申請、権限境界を確認 |
| Dev | 未確認 | 一連の公演登録シナリオを確認 |
| 本番準備 | 未確認 | データ移行・監査・運用手順を確認 |

**未完了・次アクション**: 公演登録から参加者登録、公開設定、稽古・会計・チケットへの参照まで、Productionを起点とした横断シナリオを確認する。

### 4.4 稽古・予定管理

| 項目 | 状態 | 備考 |
|---|---|---|
| 仕様 | 完了相当／継続 | Rehearsal、Attendance、Timetable等のBlueprintあり |
| Backend | 実装済み・未検証 | Module仕様ではBackend実装済み |
| API | 実装済み・未検証 | Module BootstrapにREST Controller群の記載あり |
| Web UI | 実装済み・未検証 | Rehearsal Module仕様ではBackend + Web実装済み |
| DB | 実装済み・未検証 | Rehearsal Module所有テーブルのInstallerあり |
| テスト | 実装済み・未検証 | Contract Isolation / Bootstrap Isolation等のテスト記録あり |
| Dev | 未確認 | 最新版の稽古・予定シナリオを確認 |
| 本番準備 | 未確認 | 通知・Calendar連携を含む運用確認が必要 |

**未完了・次アクション**:
- 出欠依頼・更新・確定通知の実配送を確認する。Contractを呼ぶことと通知が実際に配送されることを分けて判定する。
- Google Calendar連携は別の外部連携タスクとして管理する。
- 稽古候補・参加可能日・直接作成・タイムテーブル・予定コメントを横断テストする。

### 4.5 チケット・予約・受付

| 項目 | 状態 | 備考 |
|---|---|---|
| 仕様 | 仕様確定・未着手 | `docs/modules/Ticket.md` がModule Blueprint |
| Backend | 未着手 | Ticket Moduleは実装未開始と明記 |
| API | 未着手 | PerformanceSessionからCheckInまで未実装 |
| Web UI | 未着手 | 公演回設定・券種・予約・受付UI未実装 |
| DB | 未着手 | Module所有テーブルの詳細設計が必要 |
| テスト | 未着手 | Domain/Integration/Capacity/Concurrencyテストが必要 |
| Dev | 未着手 | 新StageArt本体での稼働実績なし |
| 本番準備 | 未着手 | 予約運用・受付端末・障害時対応が必要 |

**実装対象の正規フロー**:

`Production → PerformanceSession → Ticket / TicketType / TicketPrice → Reservation → IssuedTicket / QR → CheckIn`

**未完了・次アクション**:
1. 既存Ticket関連Blueprintを読み合わせ、PerformanceSessionを含む正規モデルに整合させる。
2. Rehearsal/Accountingと同様のModule Descriptor / Bootstrap / Installer / Contract境界を設計する。
3. 公演回・券種・価格・販売期間・定員・予約・発券・QR・受付の順に実装する。
4. 予約定員の競合、二重発券、二重受付、キャンセル・返金方針をテストする。

旧StageArtTicketの機能は参考資産であり、新StageArtへの統合済みとは扱わない。

### 4.6 会計・予算・実績・支払精算

| 項目 | 状態 | 備考 |
|---|---|---|
| 仕様 | 一部確定／設計中 | Account/JournalEntry/Budget/Expense等のBlueprintあり。締め・期またぎ等に未確定事項 |
| Backend | 開発中 | Account/Budget/Expense/Journal Entryの基盤とExpense承認ワークフロー |
| API | 一部／開発中 | PR #6のExpense API変更を含む。全会計APIの完成を意味しない |
| Web UI | 未完了／要調査 | PR #6はBackend only。会計Web UIは別タスク |
| DB | 開発中 | PR #6 head schema 1.45.0、Dev schema 1.44.0というPR説明時点の差分 |
| テスト | 開発中 | PR #6説明ではPHPUnit 1,970 tests / 6,071 assertions / 2 skips PASS |
| Dev | 未反映 | PR #6のマージ・DB更新・デプロイは未実施 |
| 本番準備 | 未完了 | MySQL 2013、MySQL 8/MariaDB、Production実データ等に未検証事項 |

**会計実用化の業務フロー**:

`予算策定 → 経費入力 → 申請・承認 → 自動仕訳 → 予算・実績対比 → 支払・精算 → 公演決算`

**現在の実装・未完了範囲**:
- Account / Budget / Expense / Journal Entryの基本機能とModule境界が存在する。
- PR #6は経費申請・承認・差し戻し・再申請、承認と仕訳の同一トランザクション、旧形式Expenseの移行・復旧・監査を扱うBackend PR。
- PR #6に会計Web UI、予算・実績画面、支払・精算の完成は含まれない。
- 月次・年度締め、会計期間・期またぎ、仕掛品、公演決算の実運用仕様・実装は未完了または要調査。
- Organization Timezoneを会計に適用する場合の扱いは未確定。PR #6のN1は会計日付をJST基準とする方針。

**次アクション**:
1. PR #6のレビュー指摘・検証結果を最新headで確認する。
2. マージ可否を判断する前に、未検証事項とDev DB差分の扱いを明文化する。
3. マージ・Dev更新は別途明示承認を得て実施する。
4. 会計UIを予算・経費申請・承認・仕訳・実績対比の順に実装する。
5. 支払・立替精算・Production Settlement・公演決算を後続タスクとして設計・実装する。

### 4.7 公開ページ・広報

| 項目 | 状態 | 備考 |
|---|---|---|
| 仕様 | 一部確定／差分あり | Public Page/URL/Publication Blueprintあり。URL仕様の複数版・優先順位を再確認 |
| Backend | 実装記録あり／未検証 | Web βではslug参照等の実装記録あり |
| API | 一部／要調査 | 公開Organization/Production取得・検索等の現状を確認 |
| Web UI | 実装済み・未検証 | Web βの基本ページ実装記録あり。ABOUT/MEMBERS等の差分あり |
| DB | 要調査 | public_page_*関連テーブルがDevに存在するとの監査記録あり。PR #6とは別管理 |
| テスト | 要調査 | 公開・非公開・slug・権限・SEO/キャッシュを確認 |
| Dev | 未確認 | 実際のURL・ドメイン・公開導線を確認 |
| 本番準備 | 未着手／要調査 | stageart.topのDNS/Hosting/SSL等は未確認 |

**重要な設計差分**:
- `docs/04-DomainModel/PublicPageUrlPolicy.md` はhatakiti.com/StageArt形式を記載。
- `docs/03-PublicPageURLAndPublicationSchedule.md` 等に別のConfirmed URL Blueprintがあるとの監査記録がある。
- どのURL仕様を現行正本とするかを、ドキュメント履歴・確認済み決定に基づいて明確化する。ロードマップだけでURL仕様を独断変更しない。
- Web βの動的ページ表示と、Blueprintが想定する公開ページ構成・生成方式・公開予約制御は別々に判定する。

**次アクション**: Organizationトップ、ABOUT、MEMBERS、PRODUCTIONS、公演詳細、SNS、Contact、公開日時制御、非公開制御、静的生成/更新方式を項目別にギャップ分析する。

### 4.8 内部ポータル・通知・文書共有

| 項目 | 状態 | 備考 |
|---|---|---|
| 仕様 | 一部 | Internal Portal/Announcement/Document/NotificationのBlueprintあり |
| Backend | 一部／要調査 | Home・Notification等の実装記録あり |
| API | 一部／要調査 | 通知履歴・既読化・対象遷移等の確認が必要 |
| Web UI | 一部／要調査 | Home Dashboard等の実装記録あり |
| DB | 要調査 | 通知・文書・お知らせテーブルの最新状態を確認 |
| テスト | 要調査 | 未読・既読・権限・通知失敗時の挙動を確認 |
| Dev | 未確認 | 実環境で受入確認が必要 |
| 本番準備 | 未確認 | ファイル共有・通知配送・保存容量・監査を確認 |

**次アクション**: お知らせ、通知一覧、通知既読、出欠依頼から対象予定への遷移、Document、Google Drive参照、権限別アクセスを個別に棚卸しする。

### 4.9 アンケート

| 項目 | 状態 | 備考 |
|---|---|---|
| 仕様 | 設計中／仕様確定候補 | 過去会話で公演別自動ページ・自由設問・終演後メール送信の方針を承認済み。正式Blueprintとの照合が必要 |
| Backend | 未着手／要調査 | 新StageArt実装を確認できていない |
| API | 未着手／要調査 | Survey/Response APIの確認が必要 |
| Web UI | 未着手／要調査 | 管理者の設問編集・観客回答画面が必要 |
| DB | 未着手／要調査 | Survey/Question/Response等のモデル確認が必要 |
| テスト | 未着手 | 公開回答・重複回答・個人情報・送信制御等 |
| Dev | 未確認 | 稼働実績なし |
| 本番準備 | 未着手 | メール配信・同意・保存期間等の運用設計が必要 |

**要求として管理する内容**:
- 公演に紐づく専用アンケートページを自動作成する。
- 公演管理者がアンケート項目を自由に設定できる。
- 予約者には終演後にアンケート依頼メールとURLを自動送信する。
- Survey Responseを公演・予約との関係で管理する。

**次アクション**: 過去に承認された要求を正式Blueprintへ反映し、Ticket/Reservation/Mailとの依存関係を確定する。

### 4.10 活動履歴・プロフィール

| 項目 | 状態 | 備考 |
|---|---|---|
| 仕様 | 一部 | Person/Profile/HistoryのDomain方針あり |
| Backend | 要調査 | ParticipantやCheckIn等から履歴を生成する処理を確認 |
| API | 要調査 | プロフィール・履歴取得/編集APIを確認 |
| Web UI | 要調査 | 個人公開ページ・履歴表示を確認 |
| DB | 要調査 | 自動生成Factと手入力履歴の保存モデルを確認 |
| テスト | 要調査 | 重複生成・取消・公開範囲を確認 |
| Dev | 未確認 | 画面・データの受入確認が必要 |
| 本番準備 | 未確認 | 個人情報・公開範囲・本人修正フローを確認 |

**次アクション**: 出演・スタッフ履歴、観劇履歴、本人入力の過去履歴を区別し、Participant・CheckIn等の発生Factから履歴を生成するルールを定義する。

### 4.11 備品・規約・団体運営

| 項目 | 状態 | 備考 |
|---|---|---|
| 仕様 | 一部確定 | Equipmentは所在・管理者・状態の管理。Regulation等のBlueprintを正本とする |
| Backend | 要調査 | 新StageArtの実装状態未確認 |
| API | 要調査 | CRUD/履歴APIを確認 |
| Web UI | 要調査 | 団体管理者・メンバー向け画面を確認 |
| DB | 要調査 | 所有テーブルとMigrationを確認 |
| テスト | 要調査 | 貸出・返却・所在・権限を確認 |
| Dev | 未確認 | 実環境の稼働状態未確認 |
| 本番準備 | 未確認 | 運用ルール・データ移行を確認 |

**次アクション**: Equipment、Regulation、Regulation Version、Accounting Period、年度計画等を個別機能として分類し、団体運営上の優先順位を決める。

### 4.12 外部連携

| 対象 | 仕様 | Backend/API/UI/DB/テスト/Dev |
|---|---|---|
| Google Drive | 設計対象 | 要調査 |
| Google Calendar | 設計対象 | 要調査 |
| SNSアカウント・投稿 | 設計対象 | 要調査 |
| メール配信 | 機能要件あり | 要調査 |

**次アクション**: 外部連携ごとに、OAuth/認証情報保管、権限範囲、同期方向、再試行、トークン失効、ログ、ユーザー解除を仕様化する。SNS Credential等の秘密情報を平文保存しない。

### 4.13 リリース・運用基盤

| 項目 | 状態 | 備考 |
|---|---|---|
| 仕様 | 設計中 | 環境・リリース・バックアップ・監視の統一台帳が必要 |
| Backend/API/UI | 対象横断 | 各機能の受入状態に依存 |
| DB | 開発中 | Schemaバージョン・Migration・Dev差分を管理 |
| テスト | 一部 | PR単位のテスト記録あり。全体CI/回帰基準を整備 |
| Dev | 一部／要調査 | PR #6に関する読み取り比較記録あり。実更新は未実施 |
| 本番準備 | 要調査 | 本番デプロイ・監視・復旧訓練の証跡が必要 |

**次アクション**: 環境一覧、デプロイ経路、DBバックアップ、Migration/Restore、監視、障害対応、ロールバック、リリース判定チェックリストを作る。

## 5. 全体ロードマップ（フェーズ）

| Phase | 優先度 | 対象 | 完了条件 |
|---|---|---|---|
| 0. 全体棚卸し・進捗管理 | 最優先・継続 | 全機能の状態・Blueprint・PR・DB・Devの可視化 | 本書を各タスク開始/完了時に更新し、未確認項目を放置しない |
| 1. 基盤安定・PR #6 | 最優先 | 経費承認・旧Expense移行、JST日付、トランザクション安全性、Dev差分整理 | PRレビュー完了、残存リスク承認、マージ判断、承認後のDev更新・受入 |
| 2. 会計の実運用化 | 高 | 会計UI、Budget、Expense、Journal、Actual、支払・精算、Production Settlement | 予算策定から公演決算まで一連の業務を実データで完了 |
| 3. チケット・予約・受付 | 高 | PerformanceSession、Ticket、Reservation、QR、CheckIn | 公演回登録から予約・発券・当日受付まで一連の業務を完了 |
| 4. 公開ページ・広報 | 高 | Organization/Production Public Page、公開制御、公開URL、SNS | 公開・非公開・更新・公開ページ閲覧をDevで受入 |
| 5. 公演運営・内部ポータル | 中〜高 | 通知、Document、Drive、Calendar、Survey | 公演関係者が準備・稽古・本番・終演後対応を一元管理 |
| 6. 団体運営・個人活動 | 中 | Profile、History、Equipment、Regulation、年度計画 | 団体・個人の継続運営に必要な基本機能を受入 |
| 7. リリース準備・本番運用 | 最終 | セキュリティ、性能、監視、バックアップ、運用手順 | リリースチェックリストと本番受入条件をすべて満たす |

フェーズは完全な直列工程ではない。依存関係を守りながら、設計・調査・UI設計等は並行して進めてよい。ただし、実装優先順位と現在の作業対象は本書に記録する。

## 6. 現在の作業ボード

### 6.1 PR #6 — 経費申請・承認ワークフローと旧形式移行

- PR: [#6](https://github.com/nisehatakiti/StageArt/pull/6)
- Head branch: `integrate/expense-approval-20261029`
- Base branch: `feature/user-account-identity`
- PR説明に記載されたHead SHA: `271df86751a26a042bac2a6da8c4e5393d3a5fd3`
- PR説明に記載されたBase SHA: `48b0b5aa0a869dd7112a9c51f6434446210b5514`
- PR状態: Open（2026-11-09の検索結果）
- Schema: PR 1.45.0 / Dev 1.44.0（PR説明の確認時点）
- 変更範囲: Backend only。Web UI変更なし。

| 項目 | 状態 | 記録 |
|---|---|---|
| F1 TransactionManager接続断対策 | 検証済み | 実MySQLで接続断・COMMIT/ROLLBACK失敗等を確認 |
| N3 Legacy Migration/Restore接続断対策 | 検証済み | Migration/Restoreの接続断・復旧シナリオを確認 |
| N1 JST日付基準 | 対応済み・検証済み | 会計日付はJST。保存方式は既存UTC DATETIMEを維持 |
| N2/F5 通知失敗時の扱い | PR説明上対応済み | 確定処理を通知失敗で500にしない。通知先ごとの失敗分離 |
| N4 権限テスト | PR説明上対応済み | 他団体・他公演・無効勘定科目等の拒否をテスト |
| N5 不正UUID | PR説明上対応済み | 422へ統一 |
| N6/F6 入力型・金額検証 | PR説明上対応済み | 明細型、整数金額、小数、理由等の422検証 |
| PHPUnit | PR説明上PASS | 1,970 tests / 6,071 assertions / 2 skips |
| 実MySQL検証 | PR説明上PASS | MySQL 5.7.44 / WordPress 7.1.4 / PHP 8.2.33 |
| MySQL 2013 | 未検証 | Lost connection during queryは実環境で未再現 |
| MySQL 8 / MariaDB | 未検証 | 対象環境での検証なし |
| Production実データUpgrade | 未検証 | 実データを使ったUpgradeは未検証 |
| Dev DB更新 | 未実施 | マージ後に別途承認を得る |
| デプロイ | 未実施 | マージ後に別途承認を得る |
| PRマージ | 未実施 | 明示承認が必要 |

**注意**: 上記のテスト数・SHA・状態はPR説明に記載された値である。マージ判断前にPRの最新head、最新レビュー、CI/ローカル検証結果と一致しているか再取得する。

### 6.2 PR #6の次の作業

1. PR最新状態とhead SHAを再取得する。
2. N1〜N6/F1/N3のコード・テスト・PR説明の整合性を確認する。
3. 未検証事項（MySQL 2013、MySQL 8/MariaDB、Production実データUpgrade）をリスクとして残すか、追加検証するか判断する。
4. Dev DBのschema差分と、PR対象外のDev固有テーブル（delegate areas、public_page_*等）の維持・統合方針を整理する。
5. マージ可否をユーザーへ報告し、明示承認を得る。
6. 承認があるまでマージ・DB更新・デプロイをしない。

## 7. 主要な依存関係

| 依存元 | 依存先 | 理由 |
|---|---|---|
| Project / Production | Organization / Identity / Membership | 公演の所有・参加・権限判定 |
| Rehearsal | Production / Identity / Membership / Authorization / Notification | 稽古対象・参加者・権限・通知 |
| Performance & Ticket | Production / Identity / Membership / Authorization / Notification | 公演回・販売・予約・受付 |
| Accounting | Organization / Production / Identity / Authorization | 団体勘定・公演予算・経費・仕訳 |
| Survey | Production / Reservation / Mail | 公演単位の設問・予約者への終演後依頼 |
| Public Page | Organization / Production / Profile / SNS | 公開対象となるBusiness Fact |
| History | Participant / CheckIn / Profile | 活動Factから履歴を生成 |
| Drive | Production / Membership / Authorization | 公演ファイルと共有範囲 |
| Google Calendar | Rehearsal / Timetable / Membership | 稽古・予定の外部同期 |
| Release | 全機能 / DB / CI / Infrastructure | 全体の本番運用 |

## 8. 旧リポジトリ・別資産の取り扱い

次のリポジトリは参考資産・別プロダクトとして扱い、新StageArt本体への統合を確認できるまでは「新StageArt実装済み」と数えない。

- **StageArtCore**: 旧WordPress基盤プラグイン。Member/Production/Survey等の資産がある。
- **StageArtTicket**: 旧StageArtCore拡張。予約・発券・受付等の参考実装がある。新StageArtのTicket ModuleはBlueprint上未着手。
- **AuthCore**: 共通認証基盤。StageArtとの接続状態は別途確認する。
- **mobile/**: CurrentStateAuditではFlutterの単一コミットのLegacy/Archive Candidateと記録。
- **mobile-rn/**: CurrentStateAuditではWeb βのActive Implementationと記録。最新main・PRブランチで再確認する。

## 9. 完成判定基準

機能を「完成」とするには、原則として次をすべて満たす。

1. Blueprintが承認済みで、実装との仕様差分が解消されている。
2. Backend/Domain/Applicationの実装がある。
3. API Contractとエラー応答・認可が定義されている。
4. Web UIまたは対象Client UIがあり、実際の業務フローを操作できる。
5. DB Schema/Migration/Upgrade/Restoreが確認されている。
6. Unit/Integration/API/UIテストが必要範囲で通過している。
7. Dev環境に反映され、実データに近いシナリオで受入確認されている。
8. 権限・個人情報・監査ログ・失敗時の復旧が確認されている。
9. 運用手順・既知の制約・本番移行手順が記録されている。

一部レイヤーしか完成していない場合は、機能全体を「完成」とせず「開発中」「実装済み・未検証」等とする。

## 10. 開発・進捗管理ルール（必須）

### タスク開始時

- 本ロードマップを確認する。
- 対象機能・Phase・優先度・依存機能を明記する。
- 対象となるBlueprintと、実装対象レイヤー（Backend/API/UI/DB/Test/Dev）を特定する。
- ロードマップに存在しない作業を追加する場合は、先に本書へ追加する。
- 既存仕様と異なる提案が出た場合は、実装を始める前に仕様変更として確認する。

### 作業中

- 変更対象PR/branch/commitを記録する。
- 予定外の機能追加や仕様変更を勝手に混在させない。
- 既知の未検証事項・ブロッカーを作業ボードへ記録する。
- 他機能への影響・依存関係を確認する。

### タスク完了時

- Backend/API/UI/DB/Test/Dev/本番準備の各状態を更新する。
- PR URL、branch、commit SHA、テスト件数・結果、schema versionを記録する。
- Dev反映と本番反映は別の状態として記録する。
- 完了した機能だけでなく、未完了項目・残存リスク・次アクションも更新する。
- PRが完了したら、全体ロードマップ上の他領域との優先順位を再確認する。

### 定期レビュー

- 各PR完了時、または少なくとも週次で全体進捗を見直す。
- 1つの機能領域に作業が集中している場合、他領域の設計・調査・UI仕様作成が止まっていないか確認する。
- フェーズ・優先度・依存関係の変更は、理由と決定日を変更履歴に記録する。

### 変更権限・安全策

- PRマージ、Dev DB更新、デプロイ、本番DB変更、破壊的Migrationは、それぞれ明示承認を得てから実施する。
- ロードマップの更新だけを理由に、アプリコード・DB・環境を変更しない。
- 未確認の情報を推測で「完成」としない。

## 11. 次に実施するタスク

| 順位 | タスク | 対象Phase | 完了条件 |
|---|---|---|---|
| 1 | PR #6最新状態・head SHA・レビュー・検証結果の再照合 | 1 | PR説明と実際のhead/testが一致し、残存リスクが明確 |
| 2 | Dev DBとPR #6のSchema差分・PR対象外テーブルの整理 | 1 | 更新対象・保持対象・未統合機能が文書化される |
| 3 | 会計Web UIの機能マップ・画面遷移・権限設計 | 2 | 予算→経費→承認→仕訳→実績の画面仕様が確定 |
| 4 | Ticket Moduleの詳細設計・実装分割 | 3 | PerformanceSessionからCheckInまでの実装計画が確定 |
| 5 | Public PageのBlueprintとWeb β実装のGap分析 | 4 | URL・公開状態・ページ構成・生成方式の正本が確定 |
| 6 | Survey Blueprintの正本化 | 5 | 公演ページ・設問・予約者メール・回答保存仕様が確定 |
| 7 | 全機能の最新コード・API・UI・Dev状態の再監査 | 0 | 本書の「要調査」が根拠付きの状態へ更新される |

## 12. 変更履歴

| 日付 | 変更 |
|---|---|
| 2026-11-09 | 全体ロードマップ初版を作成。機能領域別のレイヤー進捗、PR #6の状態、全体フェーズ、完成判定基準、継続的な更新ルールを定義 |

---

## 参照資料

- [Current State Audit](CurrentStateAudit.md)
- [Modular Architecture](03-ModularArchitecture.md)
- [Rehearsal Module](modules/Rehearsal.md)
- [Accounting Module](modules/Accounting.md)
- [Performance & Ticket Module](modules/Ticket.md)
- [Expense Approval Workflow PR #6](https://github.com/nisehatakiti/StageArt/pull/6)

各Domainの詳細仕様は `docs/04-DomainModel/` 以下の該当Blueprintを参照する。
