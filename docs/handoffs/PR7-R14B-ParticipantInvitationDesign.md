# PR #7 R14-B：本人の同意に基づく公演参加者登録（N4・F5）設計

- 作成：2026-11-15（第1版、`f13196c`）
- 改訂：2026-11-16（第2版、`979de28`）。ChatGPTの設計レビューと、ユーザーの判断（D1〜D11）を反映した。
- 改訂：2026-11-16（第3版、`8200b89`）。第2版のレビュー結果と、ユーザーの追加回答（U6ほか）を反映した。
- 改訂：2026-11-16（第4版、`2238177`）。第3版の再レビュー結果と、ユーザーの判断（U9・U12・U15）を反映した。
- 改訂：2026-11-16（第5版）。第4版の再レビュー結果と、ユーザーの判断（U10）を反映した。
- 対象：Head `c6480c2`（R14-A後）、スキーマ1.48.0
- 状態：**設計のみ（未実装）**。第5版の最終レビューの後に、実装を始めるかを判断する。
- 確定方針（R14-B指示書 §3）：
  - 公演参加者への登録は、招待と本人の承諾を経由する方式に統一する。
  - 管理者が人物IDを指定して直接登録する方式は採用しない。
  - 同じ団体のメンバーであることだけを理由に、承諾なしで登録する方式も採用しない。
  - 団体メンバー管理と公演参加者管理は別の概念として扱う。
- 確定方針（ユーザー回答、第3版）：**StageArt API は StageArt アプリ専用とする。**StageArt アプリ以外の独自プログラムや外部サービスから呼び出して利用することは想定しない（U6）。

**表記**

- 【確定】：ユーザーが判断した事項（D1〜D11、U6・U9・U12・U15などの回答）と、レビューの指示で方針が決まった事項。
- 【推奨】：設計者の提案。再レビューで否定されなければ実装する。
- 【未解決】：実装前に確認または判断が必要な事項。§16.2 に一覧と決定者がある。

本文中の「管理者」は、PrimaryManager、または `Participant.Update` 権限を持つ**有効な**担当者（PARTICIPANT_MANAGER など）を指す（`ProductionAuthorizationService::canManageParticipants()`）。「有効な担当者」の定義は §4.6 で変更する。

### 第5版の主な変更点

| 項目 | 内容 | 主な節 |
|---|---|---|
| email_key の記述の統一 | §6.4 に残っていた「SHA-256」の記述を HMAC-SHA-256 に直した。鍵なしの SHA-256 単独での計算は認めない | §6.4、§6.7 |
| 鍵のローテーション | 「現在の鍵と前の鍵」の2つから、複数の版を持てる鍵の束に変えた。30日以内に何回ローテーションしても、期間内に使われたすべての版で計算し、版と `email_key` の組で集計する。旧い版を外せる条件、緊急のローテーション、インデックスと集計のSQLを記載した | §6.2、§6.7 |
| `close_reason` の意味 | `status` は現在の状態、`close_reason`・`closed_at` は過去の無効化の記録、`closed_by_person_id` は管理者が無効化した場合の実行者、と定義を統一した。無効化の後に辞退した招待は REJECTED で、元の `close_reason`・`closed_at` を保持する | §5.1、§6.1、§7 |
| U10 | 【確定】招待者の権限喪失を最初に検出した書き込み処理（承諾・辞退）で、PrimaryManager に一度だけ通知する。読み取り処理では通知しない。`revocation_notified_at` で二重通知を防ぐ | §11.5 |

### 第4版の主な変更点

| 項目 | 内容 | 主な節 |
|---|---|---|
| U15 同意後の担当者権限 | 【確定】同意未確認の ACTIVE 参加者に付いている ACTIVE な担当者の行は、同意の記録で再任命なしに有効になる。INACTIVE の行と、再参加の前の担当者の行は自動では戻らない | §4.6 |
| U9 照合順序の確認 | 【確定】Dev・Production での読み取り専用のSELECTの実行が許可された。実装開始後の事前確認として行う（本書の改訂ではDBに接続しない） | §6.4、§12、§15 |
| U12 保存期間 | 【確定】監査ログは2年、送信記録は原則90日（最新の試行は保持）。同意の情報は参加者・公演の履歴と同じ期間。期間を限定した削除の処理を設計した | §6.2、§6.3、§6.6 |
| 辞退の状態遷移（U7） | `CANCELLED(PRODUCTION_CLOSED)`・`CANCELLED(INVITER_REVOKED)` から本人の辞退（REJECTED）への遷移を認める。`CANCELLED(MANAGER)` からは認めない。accept・reject・一覧・lookup の判定を1つの表にまとめた | §4.3、§5.1、§7.2 |
| email_key | SHA-256 を、サーバー側の秘密鍵による HMAC-SHA-256 に変更した。鍵の管理とローテーションを設計した | §6.2、§6.7 |
| 再送の競合 | 送信の直前に最新の試行かどうかを確認し、古い試行のメールを送らない（SUPERSEDED）。防げない競合と利用者への案内を明記した | §6.2、§11.3 |
| 既存担当者の移行 | リリース前・リリース後の運用手順を追加した | §16.3.1 |

### 第3版の主な変更点

| 項目 | 内容 | 主な節 |
|---|---|---|
| 外部APIの利用（U6） | 【確定】APIはアプリ専用。`/people` の2つのAPIは、アプリ内に利用箇所がないことを最終確認したうえで削除する | 冒頭、§1.5、§13 |
| 登録経路と同意の分離 | `enrollment_source`（最初の登録経路、変更しない）と、`consent_source`・`consented_at`（本人の同意）を分ける。既存の参加者が後から承諾しても、登録経路は上書きしない | §4.5、§6.1 |
| 担当者と本人の同意 | 有効な担当者の条件に「本人の同意が確認できる（`consented_at IS NOT NULL`）」を加える。同意未確認の参加者の担当者権限は、同意が記録されるまで有効にしない | §4.6、§8 |
| 送信回数の上限 | 同じ公演・同じメールアドレスへの送信を、招待をまたいで30日間に6回までにする。取消して作り直しても戻らない | §5.3、§6.2 |
| アカウントの有無の推測 | 招待作成時のアプリ内通知をやめる。招待メールは誰にでも同じ方式で送る | §4.1、§9.2、§11 |
| 送信回数と送信の失敗 | 送信は受け付けた時点で数え、失敗しても戻さない。送信の試行ごとに識別子を持ち、送信の状態（送信中・送信済み・失敗・結果不明）を回数と別に管理する | §6.2、§11.3 |
| 公演終了後などの辞退 | 公演の終了・招待者の権限喪失の後でも、本人は辞退できる（承諾はできない）。照会画面も、辞退の操作だけを出す | §4.3、§7.2 |
| ロック順序 | 参加者を更新するすべての処理で、ロックの順序を統一する | §5.4 |
| 未解決事項 | U1〜U14 の状態と決定者を整理し、U15を追加した | §16.2 |

### 第2版の主な変更点（参考）

| 項目 | 内容 |
|---|---|
| D1〜D11 | 未決事項を【確定】に変更し、本文・API・データモデル・画面・テストに反映 |
| 既存の担当者 | 参加者でない人物の担当者の行は残すが、権限は有効にしない |
| 旧方式の参加者 | 同意を確認できない既存の参加者を区別できるようにした |
| 参加申請との競合 | 招待の承諾と参加申請の承認・却下の排他と、経路の記録 |
| 未ログインの導線 | トークンだけでは招待の詳細を返さない |
| 通知の失敗 | DBのコミットを先にし、送信の失敗を記録する |
| 不正利用対策 | レート制限、トークンの露出防止、照合失敗時の応答の統一 |

---

## 1. 現状の仕組み

### 1.1 公演参加者（Participant）

- **Entity**：`Domain/Participant/Participant`
  - 主な属性：`subjectType`（PERSON／ORGANIZATION／NAME_ONLY）、`subjectId`、`participantType`（CAST／STAFF）、`status`、`displayName`（公演側の氏名）、`remarks`
  - 状態（`ParticipantStatus`）：DRAFT／ACTIVE／INACTIVE／CANCELLED／PENDING／REJECTED
- **テーブル** `stageart_participants`：`UNIQUE (production_id, subject_type, subject_id, participant_type)`。同じ人物・同じ種別の行は、状態にかかわらず1行しか持てない。`created_at`・`updated_at` はあるが、どの経路で作られたかの記録はない。
- **API**（`ParticipantRestController`、いずれもログイン必須）
  - `GET /productions/{id}/participants`：`canReadParticipants`（PrimaryManager、PARTICIPANT_MANAGER、REHEARSAL_MANAGER）。全状態の行と、人物の姓・名・メールを返す。
  - `POST /productions/{id}/participants`：`canManageParticipants`。PERSON／ORGANIZATIONは**任意のID**、NAME_ONLYは氏名だけで、即座に ACTIVE の行を作る。
  - `GET|PUT|DELETE /participants/{id}`：`canManageParticipants`。PUTは `status` を任意の値に変更できる（CANCELLED・REJECTED → ACTIVE も可）。DELETEは CANCELLED にする（物理削除ではない）。
- **参加申請**（`RequestProductionParticipationUseCase`、`ApproveParticipantRequestUseCase`、`RejectParticipantRequestUseCase`）
  - 本人がJoin Keyで申請すると、PENDING の Participant 行を作る。**申請専用のテーブルはなく、PENDING の行そのものが申請**である。
  - 管理者が承認すると `Participant::approve()` で ACTIVE、却下すると `reject()` で REJECTED になる。どちらも PENDING 以外の行では例外になる。
  - 申請・承認・却下とも、公演や参加者の行をロックしていない。
  - 申請時に同じ人物・種別の ACTIVE または PENDING の行があれば拒否する。CANCELLED・REJECTED の行がある場合は新しい行を作ろうとするため、UNIQUEキーに当たる可能性がある（動作は未確認。§16.2 U13）。
  - **本人の意思が起点の既存経路**である。
- **本人による辞退**：APIも画面もない。
- **自分の参加公演**：`GET /me/participating-productions`。
- 出欠（稽古の出欠）・精算・会計の記録は、参加者の行のIDや人物IDを参照している。参加者の行は物理削除されない（CANCELLED などの状態の変更だけ）。

### 1.2 公演参加者の招待（ParticipantInvitation）

- **Entity**：`Domain/ParticipantInvitation/ParticipantInvitation`
  - 主な属性：`email`、`invitedName`（管理者が入力した氏名）、`invitedByPersonId`、`participantType`、`remarks`、`tokenHash`（SHA-256。平文は保存しない）、`expiresAt`、`consumedAt`
  - 状態：PENDING／CONSUMED／CANCELLED。EXPIREDは保存せず、`expiresAt` から都度判定する。
- **テーブル** `stageart_participant_invitations`
  - 列：`id`、`production_id`、`email`、`invited_name`、`invited_by_person_id`、`participant_type`、`remarks`、`token_hash`（UNIQUE）、`status`、`created_at`、`expires_at`、`consumed_at`
  - キー：`production_id`、`email`
- **有効期間**：`TOKEN_LIFETIME_SPEC = 'PT24H'`。トークンは `bin2hex(random_bytes(32))`。
- **メールアドレスの扱い**：作成時は `trim()` だけで、小文字化はしない。照合は `WHERE email = %s` で、DBの照合順序（`_ci` なら大文字小文字を区別しない）に依存している。
- **API**（`ParticipantInvitationRestController`）
  - `POST /productions/{id}/participant-invitations`：`CreateParticipantInvitationUseCase` が、メールで3通りに分岐する。
    1. **メールが既存利用者の確認済みアドレスなら、`CreateParticipantUseCase` で即座に ACTIVE の参加者にする**（N4）。応答 `outcome=PARTICIPANT_ADDED` には、参加者の姓・名・メールが入る。
    2. 同じ公演・メール・種別に有効な PENDING があれば、再送する（`INVITATION_RESENT`）。
    3. それ以外は新しい招待を作成し、メールを送る（`INVITATION_SENT`）。
  - `GET /productions/{id}/participant-invitations`：一覧（`canManageParticipants`）。
  - `POST /participant-invitations/{id}/resend|cancel`：PENDINGのみ。再送はトークンと期限を同じ行で差し替える。再送の回数に上限はない。
  - `GET /participant-invitations/resolve?token=`：**認証なし**。公演名・メール・招待時の氏名・種別・状態を返す（登録画面の初期値に使う）。
- **自動解決**：`ResolveParticipantInvitationUseCase` が、メールに一致する有効な招待をすべて、招待者の権限で `CreateParticipantUseCase` に通して ACTIVE の参加者にする。呼び出し元は次の3つで、**本人が承諾する操作はない**。
  - `RegisterWithEmailUseCase`：招待リンクから登録した直後
  - `VerifyEmailUseCase`：メール確認の完了時（R14-A F6）
  - `AuthenticateWithGoogleUseCase`：Googleでサインインするたび（Googleがメールを確認済みとした場合）
- **招待メール**：`WordPressParticipantInvitationMailer`。リンクは `{registrationBaseUrl}/register?token=...`（トークンがURLのクエリに入る）。文面は「登録すると、この公演のメンバーに追加されます」「有効期限は24時間です」。送信は `StageArtMail::send()`（`wp_mail()` の戻り値 bool を返す）だが、招待側は戻り値を使っていない。

### 1.3 団体メンバー（Membership）と団体招待（OrganizationInvitation）

- **Membership**：役割は `RoleKey`（OWNER／ORGANIZATION_MANAGER／MEMBER）。参加申請（`/membership-requests`、Join Key）と承認・却下がある。
- **OrganizationInvitation**：公演側と同じ形をしている（PENDING／CONSUMED／CANCELLED、24時間、SHA-256、`by-token`、resend／cancel）。`CreateOrganizationInvitationUseCase` も、**既存利用者ならそのまま Membership（MEMBER）を作る**。`ResolveOrganizationInvitationUseCase` も、登録・確認・Googleサインインの各時点で自動的に解決する。
- **団体代理人の任命**（`AssignOrganizationDelegateUseCase`）は、ACTIVE な Membership を持つ人物に限られている。これは公演側にない制約。
- 団体メンバー一覧（`organizations/[id]/members.tsx`）にもメールが表示される。

### 1.4 公演担当者（ProductionDelegate）

- `POST /productions/{id}/delegates`（`CreateProductionDelegateUseCase`）は、**存在する任意の人物ID**を担当者にできる。参加者である必要はない。応答には対象者の姓・名が入る。役割・状態の変更は `PUT /production-delegates/{id}`。
- 画面 `productions/[id]/delegates.tsx` には、参加者の候補のほかに、人物IDの自由入力欄がある。
- テーブル `stageart_production_delegates`：`person_id`・`role`・`status`（ACTIVE／INACTIVE）・`created_by`／`updated_by`・`created_at`／`updated_at`。公演・人物・役割で一意。
- **権限の判定**
  - `ProductionAuthorizationService` の `activeDelegateFor()`・`activeDelegatesFor()` が、`status=ACTIVE` の行を「有効な担当者」として返す。`hasProductionPermission()`・`hasProductionCapability()`・`canReadProduction()`・`isProductionMember()`・`canManageParticipants()` などは、すべてこの2つを経由する。
  - `CoreProductionCapabilityDirectoryAdapter`（会計モジュールの通知先など）は、行を直接読むが、最後に `hasProductionCapability()` で確認する。
  - **`GetMyDashboardUseCase`（関係する公演の一覧）と `ListProductionsUseCase`（自分の公演一覧）は、行の `isActive()` だけを見ている**。`ProductionAuthorizationService` を経由しない。
  - いずれも、担当者が参加者かどうかは見ていない。
- PrimaryManager は、公演の `primary_manager_person_id` で判定され、担当者の行とは独立している。

### 1.5 人物情報の取得（F5）と利用箇所の調査

- `GET /people?email=&production_id=`（`SearchPersonByEmailUseCase`）：管理者は、確認済みメールに一致する人物のID・姓・名を取得できる。一致しなければ404、複数の人物に一致すれば409。**アカウントの有無と氏名が分かる**。
- `GET /people/{id}`（`GetPersonByIdUseCase`）：**ログインしていれば誰でも**、任意の人物IDの姓・名を取得できる。
- `FindPersonByEmailUseCase` は、ログイン用の確認済み EmailCredential と、確認済みの NotificationEmail だけを対象にする（R14-A F6以降）。Googleアカウントのメールは ExternalIdentity に保存されないため、NotificationEmail 経由で一致する。

**利用箇所の調査（第2版で調査し、第3版で `git grep` によりリポジトリ全体を再確認。`c6480c2`、本書を除く）**

| 範囲 | 結果 |
|---|---|
| Backend のルート | `PersonRestController` だけが `/people` と `/people/{id}` を登録している（`Plugin.php` の `use` 文 127・128・222行と、生成・登録の 632・827・1031行） |
| Backend の UseCase・Repository | `SearchPersonByEmailUseCase`・`GetPersonByIdUseCase` を使うのは `PersonRestController` だけ。他の UseCase・Repository からは呼ばれていない。`PersonSummaryResult` は `FindPersonByEmailUseCase`（内部用、残す）も使っている |
| Frontend | `mobile-rn/src/features/person/api.ts` に `fetchPersonById`・`searchPersonByEmail` が定義されているが、**このファイルを import している箇所はない**（`features/person/` の他のファイル `useCurrentPerson`・`ProfileContent` は別のAPIを使う）。`types/api.ts` の `PersonSummary` 型は、この `api.ts` だけが使う。`components/schedule-comment-list.tsx` には、コメントで言及があるだけ |
| テスト | PHPUnit：`tests/Application/Person/GetPersonByIdUseCaseTest.php`（UseCaseの単体テスト）。`SearchPersonByEmailUseCase`・`PersonRestController` を使うテストはない。Jest：該当なし |
| 文書 | `docs/` 以下に `/people` のAPI仕様の記述はない（`docs/08-API/` を含む） |
| 検証用スクリプト（リポジトリ外） | `C:\StageArt-TestEnvironment\scripts`（R9〜R14-Aの実DB検証）にも、`/people` の呼び出しはない |
| 外部連携・他のAPI利用者 | 【確定】想定しない（ユーザー回答、U6）。StageArt API はアプリ専用である |

**結論**：アプリ内に利用箇所はない。2つのエンドポイントを削除する（§13）。外部の利用者との互換性を理由に残すことはしない。

### 1.6 通知基盤

- `NotificationDispatcherInterface::dispatch(PersonId, type, payload)` → `WordPressNotificationDispatcher` が、アプリ内通知（`InAppNotificationAdapter`、`stageart_notifications`）とメール（`WordPressEmailNotificationAdapter`）に配信する。payload には `message`・`production_id`・`target_type`・`target_id` を入れられる。配信の失敗はログに記録するだけで、処理は止めない。失敗の記録をDBに残す仕組みはない。
- 招待メールは、通知基盤ではなく `ParticipantInvitationMailer` で直接送る。宛先がStageArtの利用者でなくても送れる。
- **非同期の仕組み**：キューはない。WP-Cron の `wp_schedule_single_event()` を、稽古のリマインダー（`WordPressRehearsalReminderScheduler`）だけが使っている。WP-Cron はサイトへのアクセスを契機に動くため、実行時刻は保証されない。
- 画面：`my-notifications.tsx`（`GET /me/notifications`）。

### 1.7 不正利用対策の現状

- **レート制限**：プラグイン全体に、レート制限の仕組みはない（`rate limit`・`throttle`・429 を検索して該当なし）。
- **トークンの受け渡し**：招待トークンは、メールのリンクのクエリ（`?token=`）と、`resolve?token=` のクエリで送られる。Webのアクセスログ・ブラウザの履歴・Referer に残る可能性がある。
- **ログ**：`ResolveParticipantInvitationUseCase` が `error_log` にメールアドレスを出力している。`CreateParticipantInvitationUseCase` の入力エラーの例外メッセージにもメールアドレスが入る。
- **Referrer-Policy**：Frontend（Expo Web）に設定は見つからない。
- **Frontendのエラー報告・解析**：`mobile-rn` に、外部のエラー報告・解析サービス（Sentry など）の導入は見つからない（第3版で検索）。

## 2. 問題となるAPI・画面・権限

| # | 経路 | 問題 |
|---|---|---|
| P1 | `POST /productions/{id}/participants`（subject_type=PERSON） | 任意の人物IDを、同意なく ACTIVE の参加者にできる。応答に姓・名・メールが入る。公演は誰でも作れるため、実質ログイン済みの誰でもできる（N4の本体） |
| P2 | 同上（subject_type=ORGANIZATION） | 任意の団体を、同意なく参加団体にできる |
| P3 | `POST .../participant-invitations` の既存利用者分岐 | 確認済みメールを入力するだけで即座に参加者になる。応答 `PARTICIPANT_ADDED` と姓・名・メールで、アカウントの有無と氏名が分かる |
| P4 | 自動解決（登録・メール確認・Googleサインイン） | 本人の承諾操作なしに参加者になる。Googleでは、サインインするたびに未処理の招待がすべて解決される |
| P5 | `PUT /participants/{id}` の `status` | 本人が抜けた行（CANCELLED）や、却下・申請中の行を、管理者が ACTIVE に戻せる |
| P6 | `POST /productions/{id}/delegates` | 参加者でない任意の人物を担当者にできる（参加者一覧・稽古などの閲覧権限が付く）。応答に姓・名が入る |
| P6b | 既存の担当者の行 | P6で過去に作られた担当者の行、または同意なく追加された参加者に付けられた担当者の行が ACTIVE のまま残っていれば、権限も有効なまま残る |
| P7 | `GET /people?email=`（F5） | メールからアカウントの有無と氏名が分かる |
| P8 | `GET /people/{id}` | ログイン済みの誰でも、任意の人物の氏名を取得できる |
| P9 | `GET /participant-invitations/resolve?token=` | 認証なしで、招待先のメール・氏名・公演名を返す。トークンがクエリに入る |
| P10 | `GET /productions/{id}/participants` | REHEARSAL_MANAGER（閲覧のみ）にもメールを返す。CANCELLED・REJECTED の行にも、人物の姓・名・メールを返す |
| P11 | 団体招待の既存利用者分岐と自動解決 | P3・P4と同じ問題が団体メンバーにもある。【確定】R14-Cで対応する（D1） |
| P12 | 招待の作成・再送・トークン照合 | レート制限も送信回数の上限もなく、同じ相手に招待メールを送り続けられる。トークンの総当たりも制限されない |

## 3. 既存招待基盤の再利用可能な部分

新しい招待のテーブル・Entityは作らない。`ParticipantInvitation` を「承諾待ちの招待」として拡張する。送信の試行の記録（§6.2）だけは、新しい表にする。

| 再利用するもの | 用途 |
|---|---|
| `stageart_participant_invitations` と `ParticipantInvitation` | 招待の本体。列を追加し、状態を拡張する（§6） |
| トークンの生成・SHA-256保存・`findByTokenHash` | 招待メールのリンク。生成と保存の方式は変更しない |
| `rotateToken()` と `ResendParticipantInvitationUseCase` | 再送。期限を7日にし、回数の上限を加える |
| `CancelParticipantInvitationUseCase` | 管理者による取消。理由と実行者を記録し、冪等にする |
| `ListParticipantInvitationsUseCase` と画面の「招待中のメンバー」表 | 招待一覧。状態の表示を拡張 |
| `CreateParticipantUseCase` の重複確認と `Participant::create()` | 承諾時の参加者作成。管理者の権限確認の代わりに、本人の承諾を条件にする内部サービスへ分ける（§7.4） |
| `Participant::approve()` | 参加申請中（PENDING）の行に、本人が承諾した場合の扱い |
| `FindPersonByEmailUseCase`（確認済みメールのみ） | 「この招待のメールが本人の確認済みアドレスか」の判定と、本人宛ての招待の検索。招待の作成では使わない（§9.2） |
| `RegisterWithEmailUseCase` の招待トークン経路 | 招待リンクから登録した人のメールを確認済みにする。参加者は作らない |
| `NotificationDispatcherInterface` | 承諾・辞退・参加辞退の通知（アプリ内とメール） |
| `ProductionRepositoryInterface::findByIdForUpdate` | 参加者を更新するすべての処理の直列化（§5.4） |
| `TransactionManagerInterface` | 承諾などの一連の処理を1トランザクションにまとめる |
| `ProductionAuthorizationService::isActivePersonParticipant()` | 有効な担当者の判定（§4.6） |
| `ProductionDelegate` の INACTIVE と `updated_by`／`updated_at` | 担当者の役割の解除（行は削除しない） |

## 4. 新しい業務フロー

### 4.1 招待（管理者）

1. 管理者が、公演の「メンバー管理」画面で、氏名・メール・役割（CAST／STAFF）・備考を入力し、「招待する」を押す。
2. `POST /productions/{id}/participant-invitations` は、**アカウントの有無にかかわらず、常に招待を作る**（同じ宛先の招待が承諾待ちなら再送する）。応答は「招待した」「再送した」の2種類だけで、**アカウントの有無も、人物の氏名も返さない**。
3. 次の場合は招待を作らない（§5.3）。
   - 公演が COMPLETED／ARCHIVED／CANCELLED（【確定】D5）
   - 同じ公演・同じメールアドレスの招待が、本人によって辞退されてから30日以内（【確定】D6）
   - 同じ公演・同じメールアドレスへの送信が、直近30日間で6回に達している（【確定】第3版、§5.3）
   - 招待1件あたりの再送が5回に達している（【確定】D6）
   - レート制限を超えた（§9.2）
4. 招待メールを送る。**登録済みかどうかにかかわらず、同じ方式・同じ文面のメールだけを送る。招待作成時のアプリ内通知は送らない**（【確定】第3版。アカウントの有無の推測を防ぐため、§9.2）。
   - 文面には「承諾するまで参加者にはなりません」「有効期限は7日間です」と書く。
   - 登録済みの人は、ログイン後のホームの件数と、自分宛ての招待一覧（`GET /me/participant-invitations`）からも確認できる。
   - 登録前の人は、メールのリンクから登録・ログインを経て招待を確認する（§4.2）。
5. 招待の有効期間は**7日間**（【確定】D4）。

### 4.2 承諾・辞退（本人）

【確定】D7に従い、ログイン済みの場合と未ログインの場合の導線を次のとおり統一する。**招待トークンだけでは、招待の詳細（公演名・団体名・役割・招待者・期限）を返さない。**

**ログイン済みで招待リンクを開いた場合**

1. 招待確認画面が、トークンを `POST /participant-invitations/lookup`（ログイン必須）に送る。
2. 招待のメールが本人の確認済みアドレスなら、招待の内容を表示する。
3. 一致しなければ、内容を表示せず、「このアカウント宛ての招待ではありません。招待を受け取ったメールアドレスでログインし直してください」と表示する。

**未ログインで招待リンクを開いた場合**

1. 招待リンクを開く。画面には「StageArtの公演への招待です。内容を確認するには、ログインまたは登録してください」とだけ表示する。公演名などは表示しない。
2. 登録またはログインへ誘導する。
   - 登録を選んだ場合は、`POST /participant-invitations/registration-hint`（未ログインで可）で、招待先のメールアドレスだけを取得し、登録画面の初期値にする（D7で許容された範囲、U14）。無効なトークンでは何も返さない（§9.2）。
   - 招待リンクから、招待先と同じアドレスで登録した場合は、メールを確認済みにする（R14-A F6の既存動作。参加者は作らない）。
3. 登録またはログインの後、招待確認画面に戻る（§10）。ここで初めて `lookup` を呼び、確認済みメールを照合する。
4. 一致した場合だけ、招待確認画面を表示する。
5. 一致しない場合は、招待を受け取ったアドレスでログインし直すよう案内する。

**ホームから開いた場合**：ログイン済みなので、`GET /me/participant-invitations` の内容から招待確認画面を開く（トークンは使わない）。

**招待確認画面の表示**（【確定】D7）

- 公演名
- 団体名
- 役割（CAST／STAFF）
- 招待者の公演側の表示名
- 招待の期限
- 「承諾すると、公演の管理者にあなたの氏名・メールアドレスが共有されます」という説明
- 既にこの公演の参加者（旧方式の登録を含む）である場合は、「あなたは既にこの公演のメンバーです。承諾すると、参加への同意が記録されます」と表示する（§4.5）
- 公演が終了・中止している場合、または招待者が権限を失っている場合（承諾を試みて招待が `CANCELLED(PRODUCTION_CLOSED／INVITER_REVOKED)` になった後を含む）は、「この招待は現在承諾できません」と表示し、**「参加しない（辞退）」だけを出す**（【確定】U7、§4.3）

承諾前には、管理者が入力した氏名・備考・内部メモと、招待先のメールアドレスを含む不要な個人情報を表示しない。

**承諾と辞退**

1. 本人が「参加する（承諾）」または「参加しない（辞退）」を選ぶ。
2. 承諾した場合だけ、参加者（ACTIVE）にする。**辞退は参加者を作らず、参加の権限も発生させない。**
3. 結果を招待者に通知する（§11）。

### 4.3 承諾・辞退の条件

**承諾の条件（すべて満たす場合だけ）**

- 認証済みの StageArt アカウントであり、UserAccount が ACTIVE であること（SUSPENDED・DISABLED は不可）。発行済みのアクセストークンが有効でも、承諾の時点で都度確認する。
- 招待のメールが、**本人の確認済みアドレス**であること（ログイン用の確認済み EmailCredential、または確認済み NotificationEmail。`FindPersonByEmailUseCase` が本人の PersonId を返すこと）。
  - 未確認のアドレスしか持たないアカウントは承諾できない（F6と一致）。
  - 複数の人物に一致する場合（Ambiguous）は承諾できない。
- 招待が PENDING で、期限内であること。
- 公演が COMPLETED／ARCHIVED／CANCELLED でないこと。該当すれば、招待を CANCELLED（`close_reason=PRODUCTION_CLOSED`）にして拒否する（【確定】D5）。
- 招待者が、承諾の時点でも `canManageParticipants` を満たすこと。満たさなければ、招待を CANCELLED（`close_reason=INVITER_REVOKED`）にして拒否し、参加者は作らない（【確定】D3）。
  - 現在の PrimaryManager が代わりに承諾を認めるような、暗黙の処理はしない。
  - 再び招待する場合は、現在の権限を持つ管理者が新しい招待を作る。

**承諾は、本人がAPIを明示的に呼んだときだけ成立する**。メールの一致やログイン、登録、メール確認、Googleサインインだけでは成立しない（自動解決は廃止する）。トークンは招待を見つけるためのもので、承諾の権限の根拠にはしない。リンクを転送されても、別のアカウントでは承諾できない。

**辞退の条件**（【確定】U7、第4版で状態遷移を整理）

- アカウントと宛先の条件は、承諾と同じ。
- **公演の終了・中止の後、または招待者の権限喪失の後でも、正当な招待対象者は辞退できる。**辞退は参加者を作らず、参加の権限も発生させないため。招待は REJECTED になる。
- 辞退できる招待の状態は、次のとおり（§5.1 の判定表）。
  - PENDING（期限内）。公演の終了・招待者の権限喪失の状態（導出の PRODUCTION_CLOSED・INVITER_REVOKED）を含む。
  - **`CANCELLED(PRODUCTION_CLOSED)`・`CANCELLED(INVITER_REVOKED)`（期限内）**。本人が承諾を試みた結果、システムが無効にした招待で、本人の意思はまだ示されていないため、辞退を記録できるようにする。
- 辞退できない招待：
  - `CANCELLED(MANAGER)`：管理者が取り消した招待。本人に応答を求めていないため（409 `not_pending`）。
  - 理由が NULL の CANCELLED（R14-B以前の取消）・ACCEPTED・CONSUMED（409 `not_pending`）。
  - 期限切れ（410）。既に効力がないため。`CANCELLED(PRODUCTION_CLOSED／INVITER_REVOKED)` も、`expires_at` を過ぎれば辞退できない。

### 4.4 公演参加者の管理（承諾後）

- 管理者による削除（既存の `DELETE /participants/{id}`）は CANCELLED にする。本人による辞退（新設）は WITHDRAWN にして、区別する（§5.2）。
- いずれも行は削除しない。**稽古の出欠・精算・会計などの過去の記録は、削除も変更もしない。**
- 役割（CAST／STAFF）・備考・公演側の氏名は、管理者が変更できる（既存の `PUT`）。ただし、`status` を ACTIVE 以外から ACTIVE に戻す操作はできなくする（P5）。参加者に戻すには、もう一度招待して承諾してもらう。
- 参加者が ACTIVE でなくなった時点（本人の辞退・管理者の削除・INACTIVE への変更）で、その人物のこの公演での担当者の役割も解除する（§4.6）。
- ORGANIZATION の参加者は、新しく追加できない（【確定】D10）。既存の行は変更・削除しない。団体のメンバーであることを理由に、公演の参加者として扱わない。将来必要になった場合は、団体の OWNER による承諾の方式を別に設計する。

### 4.5 登録経路と本人の同意（【確定】D2、第3版で変更）

第2版では、既存の参加者が後から招待を承諾すると `enrollment_source=INVITATION` に更新する案だった。これは最初の登録経路を上書きしてしまうため、第3版で**登録経路と同意を別の列に分ける**。

| 列 | 意味 | 変更 |
|---|---|---|
| `enrollment_source` | 最初に参加者の行が作られた経路 | 行を作ったときに記録し、**以後は変更しない** |
| `consent_source` | 本人が同意した経路（直近の同意） | 本人が同意したときに記録する |
| `consented_at` | 本人が同意した日時（直近の同意） | 同上 |

**値**

| 経路 | 行を作るとき（`enrollment_source`） | 同意の記録（`consent_source`／`consented_at`） |
|---|---|---|
| 招待の承諾で新しく行を作る | INVITATION | INVITATION／承諾の日時 |
| 参加申請（R14-B以降の申請） | JOIN_REQUEST（PENDING の行を作るとき） | 管理者が承認した時点で JOIN_REQUEST／申請の日時（行の `created_at`。本人の意思表示の時点） |
| NAME_ONLY の追加 | MANAGER_RECORD | 記録しない（同意の対象外） |
| R14-B以前に作られた行（既存データ） | NULL のまま | NULL のまま |
| 既存の行（どの経路でも）の本人が、後から招待を承諾する | **変更しない** | INVITATION／承諾の日時（上書き） |

- **既存データは同意済みとみなさない。**Migrationでは、`enrollment_source`・`consent_source`・`consented_at` に値を入れない（NULL のまま）。
- R14-B以前に申請され、R14-B以降に承認された行は、`enrollment_source` が NULL（既存データ）なので、承認しても同意を記録しない（既存データを同意済みとみなさないため）。本人の同意を記録するには、招待を送って承諾してもらう。
- 本人が辞退（WITHDRAWN）した後に、再び招待を承諾した場合は、`consented_at` を新しい承諾の日時に更新する。過去の同意の履歴は監査ログに残る（§6.3）。
- 参加者一覧の `consent_status`：`consented_at` があれば CONFIRMED、PERSON で `consented_at` が NULL なら UNCONFIRMED（「同意未確認」。既存データの場合は「旧方式」と併記）、NAME_ONLY・ORGANIZATION は NOT_APPLICABLE。

**既存の ACTIVE な参加者の扱い（D2）**

- **一括で変更・削除しない。**稽古・出欠・タイムテーブル・精算・実績集計への影響はない（参加者としての機能はこれまでどおり）。
- **管理者が認識できるようにする。**参加者一覧で、同意未確認の PERSON の参加者に「同意未確認（旧方式）」と表示する。
- **本人は辞退できる。**参加公演の画面から、参加をやめられる（§7.2 withdraw）。
- **再確認ができる。**管理者は、参加者一覧の「同意の確認を依頼」から、その参加者のメールアドレス宛てに通常の招待を送る。本人が承諾すると、行の状態は ACTIVE のまま、`consent_source=INVITATION`・`consented_at` を記録する。本人が辞退しても、参加者の行は変更しない（参加をやめる場合は withdraw を使う）。
- 既存データへの一斉の再招待と、一斉の無効化は、今回の対象外とする。

### 4.6 担当者（ProductionDelegate）の有効性（第3版で変更、【確定】D9）

**有効な担当者の条件**（【確定】第3版）

担当者の行が、次の**すべて**を満たす場合だけ、担当者としての権限を与える。

1. 担当者の行の `status` が ACTIVE であること（既存）。
2. その人物が、当該公演の **ACTIVE な PERSON 参加者**であること（第2版で追加）。
3. その参加者の行で、**本人の同意が確認できること（`consented_at IS NOT NULL`）**（第3版で追加）。

- **DB上の行が ACTIVE で残っていることを理由に、既存の権限を維持してはならない。**
- 同じ人物が CAST と STAFF の2行を持つ場合は、どちらか一方が条件2・3を満たせばよい。
- 判定は `ProductionAuthorizationService::activeDelegateFor()`・`activeDelegatesFor()` で行う。これを経由するすべての権限判定（`canReadProduction`・`canManageParticipants`・`hasProductionCapability` など）に、自動的に適用される。
- `ProductionAuthorizationService` を経由せずに行を読んでいる `GetMyDashboardUseCase`・`ListProductionsUseCase` も、同じ判定に切り替える（§1.4）。切り替えないと、権限のない公演が一覧に出る。
- `CoreProductionCapabilityDirectoryAdapter` は、最後に `hasProductionCapability()` を通るため、自動的に適用される（実装時にテストで確認する）。
- **PrimaryManager の権限は従来どおり別扱いで、影響を受けない。**PrimaryManager が参加者でなくても、同意を記録していなくても、従来どおりすべての操作ができる。
- 参加者としての機能（稽古の閲覧・出欠の回答など、`isActivePersonParticipant` による判定）は、同意の有無にかかわらずこれまでどおり（D2）。同意を求めるのは、担当者の権限だけである。

**既存の担当者の権限への影響**

| 既存の担当者の状況 | R14-B以降 | 権限を戻す方法 |
|---|---|---|
| ACTIVE な PERSON 参加者で、同意未確認（既存データの参加者はすべてこれに当たる） | 権限は無効（`ineffective_reason=CONSENT_UNCONFIRMED`）。行は ACTIVE のまま残す | 本人が「同意の確認を依頼」の招待を承諾する。**再任命は不要**で、同意が記録された時点で、ACTIVE な担当者の行が有効になる（【確定】U15）。INACTIVE の行は戻らない |
| 参加者でない（P6で任命された） | 権限は無効（`NOT_ACTIVE_PARTICIPANT`）。行は ACTIVE のまま残す | 本人が招待を承諾して参加者になった後、管理者が**改めて任命する**（承諾の時点で古い行は INACTIVE になる） |
| 参加者だが ACTIVE でない（CANCELLED・WITHDRAWN など） | 権限は無効（`NOT_ACTIVE_PARTICIPANT`） | 同上（再招待と承諾、その後に再任命）。以前の担当者の権限は自動では戻らない |
| PrimaryManager | 影響なし | — |

- **既存データの参加者は、すべて同意未確認になる（§4.5）。**そのため、R14-Bのリリースの時点で、PrimaryManager 以外の既存の担当者は、全員の担当者権限が無効になる。リリースの前に、管理者への周知と、同意の確認の依頼の手順が必要になる（§16.3）。
- 公演の進行が止まらないよう、PrimaryManager は担当者の権限なしで、すべての操作（招待・同意の確認の依頼・任命）ができる。

**管理者への表示**

- 担当者の一覧（`GET /productions/{id}/delegates` と画面）では、有効な担当者と、無効な担当者を分けて表示する。無効な担当者には理由を表示する。
  - 「同意未確認のため無効です。本人に同意の確認を依頼してください」（`CONSENT_UNCONFIRMED`、操作：同意の確認を依頼）
  - 「この公演のメンバーではないため無効です。招待して、承諾後に改めて任命してください」（`NOT_ACTIVE_PARTICIPANT`）
  - 「解除済み」（`INACTIVE`）
- 担当者本人には、自分の担当者権限が無効になっていることと、その理由を、公演の画面に表示する【推奨】（本人が同意の確認の依頼に気づけるように）。

**任命**

- 新しく任命・再有効化できるのは、その公演の ACTIVE な PERSON 参加者で、**同意が確認できる人だけ**（422 `stageart_production_delegate_target_not_participant`／`stageart_production_delegate_target_not_consented`）。第2版の U2（同意未確認の参加者を任命できるか）は、この条件で解決する。
- 本人が招待を承諾して ACTIVE な参加者になった後に、管理者が改めて任命できる。同じ公演・人物・役割の行が INACTIVE で残っている場合は、新しい行を作らず、その行を ACTIVE に戻す（一意キーのため）。

**役割の解除（D9）**

- 参加者が ACTIVE でなくなった時点（本人の辞退 WITHDRAWN・管理者の削除 CANCELLED・INACTIVE への変更）で、その人物のこの公演での担当者の行をすべて INACTIVE にする。行は削除しない。`updated_by` に操作した人、監査ログに `DELEGATE_DEACTIVATED` を記録する。
- 参加者が ACTIVE でない状態（行なしを含む）から ACTIVE になった時点（招待の承諾・参加申請の承認）で、その人物の担当者の行が ACTIVE のまま残っていれば（参加者でない間は無効だった行）、それも INACTIVE にする（`DORMANT_ON_ENROLLMENT`）。**古い任命が、承諾によって自動的に有効にならないようにするため**である。担当者にするには、管理者が改めて任命する。
**同意・再参加と担当者の行**（【確定】U15）

| 場面 | ACTIVE な担当者の行 | INACTIVE な担当者の行 |
|---|---|---|
| 同意未確認の ACTIVE 参加者が、同意を記録する（参加者の状態は ACTIVE のまま） | INACTIVE にしない。同意の記録で条件3を満たし、**再任命なしに有効になる** | 自動では ACTIVE に戻さない。必要なら管理者が任命する |
| 参加者でなかった人物が、招待を承諾して参加者になる | INACTIVE にする（`DORMANT_ON_ENROLLMENT`）。改めて任命する | そのまま。改めて任命する |
| WITHDRAWN・CANCELLED・INACTIVE・REJECTED から、招待の承諾で再び参加者になる | （辞退・削除の時点で INACTIVE にしているため、通常は残っていない。残っていれば INACTIVE にする） | そのまま。**以前の担当者の権限は自動では戻らない**。改めて任命する |
| PrimaryManager | 担当者の行とは独立して、従来どおりすべての操作ができる | 同左 |

- 「自動では戻さない」とは、システムが INACTIVE の行を ACTIVE に変えないことを指す。管理者の任命（§7.1）で ACTIVE に戻すことはできる。
- PrimaryManager 本人は、通常の辞退操作ができない。PrimaryManager が参加をやめる場合は、先に管理者の権限を引き継ぐ（既存の `ChangePrimaryManagerUseCase`）。

### 4.7 参加申請と招待の承諾が重なる場合

参加申請（Join Key）によって PENDING の参加者の行があるときに、同じ人物が招待を承諾する場合。

- **招待の承諾で、その行を ACTIVE にする。**`enrollment_source` は申請のときの JOIN_REQUEST のまま（変更しない）。`consent_source=INVITATION`、`consented_at` は承諾の日時とする。
- **申請の履歴は削除しない。**申請専用のテーブルはないため（§1.1）、監査ログで経路を区別する。
  - 申請時：`JOIN_REQUESTED`
  - 承諾時：`ACCEPTED`（`detail` に `previous_status=PENDING`、`superseded_join_request=true`）
  - 管理者の承認・却下：`JOIN_APPROVED`・`JOIN_REJECTED`
- **排他制御**：§5.4 のロック順序に従い、最初に公演の行を FOR UPDATE でロックしてから、参加者の行を読み直す。ロックを取得した順に処理し、後から来た処理は、その時点の行の状態で判定する。
  - 承諾が先：行は ACTIVE になる。その後の管理者の承認・却下は、PENDING でないため409 `stageart_participant_request_not_pending`（「この申請は既に処理されています」）。
  - 承認が先：行は ACTIVE になる。その後の承諾は「既に ACTIVE」として、招待を ACCEPTED にし、同意を記録する（§5.3、冪等）。
  - 却下が先：行は REJECTED になる。その後の承諾は、管理者が出した招待への本人の承諾として有効で、REJECTED → ACTIVE にする。却下と招待は、どちらも管理者の操作であり、招待が取り消されていない限り、本人の承諾を優先する。
- 参加申請の承認・却下に、現在はロックがない（§1.1）。R14-Bで、§5.4 の順序でロックを追加する。

## 5. 招待状態遷移

### 5.1 招待（ParticipantInvitation）

既存の命名（`ParticipantStatus` の REJECTED、招待の CANCELLED）に合わせる。

| 状態 | 保存 | 意味 |
|---|---|---|
| PENDING | ○ | 承諾待ち |
| ACCEPTED | ○（新設） | 本人が承諾した（参加者になった、または既存の参加者として同意を記録した） |
| REJECTED | ○（新設） | 本人が辞退した。無効化（`PRODUCTION_CLOSED`・`INVITER_REVOKED`）の後の辞退では、`close_reason`・`closed_at` も値を持つ（第5版） |
| CANCELLED | ○（既存） | 招待が無効になった。理由は `close_reason`（下記）。`PRODUCTION_CLOSED`・`INVITER_REVOKED` の場合だけ、期限内なら本人の辞退（REJECTED）へ遷移できる（第4版） |
| EXPIRED | ×（導出） | PENDING かつ `expires_at <= 現在`。保存せず、APIでは `effective_status` として返す |
| PRODUCTION_CLOSED | ×（導出） | PENDING・期限内かつ公演が COMPLETED／ARCHIVED／CANCELLED。保存せず、一覧では「無効（公演終了）」と表示する（D5）。本人は辞退だけできる（U7） |
| INVITER_REVOKED | ×（導出） | PENDING・期限内かつ招待者が `canManageParticipants` を満たさない。一覧では「無効（招待者の権限なし）」と表示する。本人は辞退だけできる（U7） |
| CONSUMED | ○（既存のみ） | R14-B以前に自動解決された招待。新しくは作らない。表示上は「参加済み（旧方式）」とする |

**状態と無効化の記録の列の意味**（第5版で統一。§6.1・§7・監査ログ・テストはこの定義に従う）

| 列 | 意味 |
|---|---|
| `status` | 招待の**現在の**状態（PENDING／ACCEPTED／REJECTED／CANCELLED／CONSUMED） |
| `close_reason` | 招待が**過去に無効化された理由**。一度記録したら変更しない。無効化されていなければ NULL |
| `closed_at` | その無効化が発生した（記録された）日時。`close_reason` と同時に記録し、変更しない |
| `closed_by_person_id` | 管理者が無効化した場合（`close_reason=MANAGER`）の実行者。システムが無効化した場合は NULL |

- 無効化は、招待1件につき最大1回だけ記録する。`close_reason` が既にある招待に、別の理由を上書きしない。
- `status` と `close_reason` の組み合わせは次のどれかになる。

| `status` | `close_reason` | 意味 |
|---|---|---|
| PENDING・ACCEPTED・CONSUMED | NULL | 無効化されていない |
| CANCELLED | MANAGER | 管理者が取り消した（終端。本人は辞退できない） |
| CANCELLED | PRODUCTION_CLOSED／INVITER_REVOKED | 本人が承諾しようとした時点で、システムが無効化した（本人は期限内なら辞退できる） |
| CANCELLED | NULL | R14-B以前に取り消された（理由不明。終端） |
| REJECTED | NULL | 有効な招待を、本人が辞退した |
| REJECTED | PRODUCTION_CLOSED／INVITER_REVOKED | 無効化された招待（または、辞退の時点で無効化を初めて検出した招待）を、本人が辞退した。最終の状態は REJECTED で、元の `close_reason`・`closed_at` を保持する |

`close_reason` の値

| 値 | 意味 | 記録する時点 |
|---|---|---|
| MANAGER | 管理者が取り消した | 取消の操作 |
| PRODUCTION_CLOSED | 公演が終了・中止になっていた | 本人の承諾・辞退の書き込み処理で、初めて検出した時点（D5） |
| INVITER_REVOKED | 招待者が参加者を管理する権限を失っていた | 本人の承諾・辞退の書き込み処理で、初めて検出した時点（D3）。PrimaryManager への通知の契機（§11.5、U10） |
| NULL | 無効化されていない、または R14-B以前に取り消された（理由不明） | — |

- 一覧・lookup などの読み取り処理は、公演の状態・招待者の権限から `effective_status` を導くだけで、`close_reason` を書き込まない。
- 再送・取消（管理者の操作）は、招待者の権限喪失を検出しても `close_reason` を書き込まない（再送は409を返すだけ。取消は `MANAGER` として記録する）。

導出の状態は、次の優先順で1つだけ返す：EXPIRED → PRODUCTION_CLOSED → INVITER_REVOKED → PENDING。

```
            resend（期限切れでも可。トークンを差し替え、期限を再送時点から7日に更新。
              ┌──────┐   上限：招待1件あたり5回、同じ公演・メールで30日間に6回の送信）
              ▼      │
 create ──► PENDING ─┴─► accept（本人）────────────────────────► ACCEPTED
              │      ├─► reject（本人。公演終了・権限喪失の後も可）─► REJECTED
              │      ├─► cancel（管理者）──────────────────────► CANCELLED(MANAGER)
              │      ├─► accept時に公演がCOMPLETED/ARCHIVED/CANCELLED ─► CANCELLED(PRODUCTION_CLOSED) ─┐
              │      └─► accept時に招待者が権限を喪失 ──────────► CANCELLED(INVITER_REVOKED) ──┤
              │                                                                              │
              │                         reject（本人。期限内のみ）◄────────────────────────────┘
              │                              └────────────► REJECTED（close_reason・closed_at は保持）
              ├─(expires_at経過)─► EXPIRED（導出。resendでPENDINGに戻る。accept・reject不可）
              ├─(公演が終了・中止)─► PRODUCTION_CLOSED（導出。resend・accept不可、reject可）
              └─(招待者が権限を喪失)─► INVITER_REVOKED（導出。accept・resend不可、reject可）
```

- ACCEPTED・REJECTED・`CANCELLED(MANAGER)`・理由が NULL の CANCELLED・CONSUMED は終端の状態。`CANCELLED(PRODUCTION_CLOSED)`・`CANCELLED(INVITER_REVOKED)` は、本人の辞退（REJECTED）への遷移だけを残し、それ以外の操作（承諾・再送・取消）はできない（第4版）。
- 同じ宛先にもう一度招待する場合は、新しい行を作る（辞退後30日の制限と、30日間の送信上限がある。§5.3）。

**操作の可否の判定**（第4版。accept・reject・`GET /me/participant-invitations`・`lookup`・管理者の一覧は、すべてこの表で判定する）

判定は「保存された状態と `close_reason`」「期限」「公演の状態」「招待者の権限」の順に見る。

| 保存された状態 | 期限 | 公演・招待者 | `effective_status`（一覧・lookup） | 承諾 | 辞退 | 本人の一覧（`/me`）に出すか | 再送・取消（管理者） |
|---|---|---|---|---|---|---|---|
| PENDING | 期限内 | 受付中・権限あり | PENDING | ○ | ○ | 出す（`can_accept=true`） | ○ |
| PENDING | 期限内 | 公演が終了・中止 | PRODUCTION_CLOSED | ×（409。招待を `CANCELLED(PRODUCTION_CLOSED)` にする） | ○（REJECTED、`close_reason=PRODUCTION_CLOSED` を記録） | 出す（`can_accept=false`） | 再送×・取消○ |
| PENDING | 期限内 | 招待者が権限なし | INVITER_REVOKED | ×（409。招待を `CANCELLED(INVITER_REVOKED)` にし、PrimaryManager に通知） | ○（REJECTED、`close_reason=INVITER_REVOKED` を記録し、PrimaryManager に通知） | 出す（`can_accept=false`） | 再送×・取消○ |
| PENDING | 期限切れ | — | EXPIRED | ×（410） | ×（410） | 出さない | 再送○（公演が受付中で、招待者に権限がある場合）・取消○ |
| CANCELLED(PRODUCTION_CLOSED／INVITER_REVOKED) | 期限内 | — | CANCELLED（`close_reason` 付き） | ×（409 `closed`） | ○ | 出す（`can_accept=false`） | × |
| CANCELLED(PRODUCTION_CLOSED／INVITER_REVOKED) | 期限切れ | — | CANCELLED | ×（409 `closed`） | ×（410） | 出さない | × |
| CANCELLED(MANAGER)・理由 NULL | — | — | CANCELLED | ×（409 `not_pending`） | ×（409 `not_pending`） | 出さない | 取消は冪等（MANAGER のみ） |
| ACCEPTED | — | — | ACCEPTED | 本人なら200（冪等） | ×（409） | 出さない | × |
| REJECTED（`close_reason` の有無を問わない） | — | — | REJECTED（`close_reason`・`closed_at` があれば併せて返す） | ×（409） | 本人なら200（冪等。通知・監査ログは追加しない） | 出さない | × |
| CONSUMED | — | — | CONSUMED | ×（409） | ×（409） | 出さない | × |

- `lookup` は、本人宛ての招待について、この表の承諾・辞退の可否を `can_accept`・`can_reject` として返す。期限切れは410、承諾も辞退もできない処理済みの招待は409を返す。
- 本人宛てでない招待の扱い（403・404）は、§7.2 のとおりで、この表より先に判定する。
- 公演の状態が変わったときに、未処理の招待をまとめて更新する処理は追加しない（【確定】D5）。
- **冪等性**：承諾・辞退・取消は、同じ操作を繰り返しても、最初の結果を返し、状態・通知・監査ログを重ねない（§5.3）。

### 5.2 参加者（Participant）

`ParticipantStatus` に **WITHDRAWN（本人による辞退）** を追加する。列は VARCHAR(20) なので、DDLの変更は不要。

| 遷移 | 実行者 | 経路 | 記録（§4.5） |
|---|---|---|---|
| （行なし）→ ACTIVE | 本人の承諾 | `POST /participant-invitations/{id}/accept` | `enrollment_source=INVITATION`、`consent_source=INVITATION`、`consented_at` |
| CANCELLED／WITHDRAWN／REJECTED／INACTIVE → ACTIVE | 本人の承諾 | 同上（UNIQUEキーにより同じ行を再び ACTIVE にする） | `enrollment_source` は変更しない。`consent_source=INVITATION`、`consented_at` |
| PENDING（参加申請中）→ ACTIVE | 本人の承諾 | 同上（§4.7） | 同上 |
| PENDING（参加申請中）→ ACTIVE | 管理者の承認 | 既存の承認 API（ロックを追加） | `enrollment_source=JOIN_REQUEST` の行なら、`consent_source=JOIN_REQUEST`、`consented_at`＝申請の日時。NULL の行（既存データ）は記録しない |
| ACTIVE → ACTIVE（同意の記録だけ） | 本人の承諾 | 承諾 API（既に ACTIVE の場合） | `enrollment_source` は変更しない。`consent_source=INVITATION`、`consented_at` |
| ACTIVE → CANCELLED | 管理者 | `DELETE /participants/{id}`（既存） | 担当者の役割を解除（§4.6） |
| ACTIVE → WITHDRAWN | 本人 | `POST /participants/{id}/withdraw`（新設） | 担当者の役割を解除（D9） |
| ACTIVE → INACTIVE | 管理者 | `PUT`（既存。ACTIVE への復帰は不可） | 担当者の役割を解除 |
| （行なし）→ PENDING | 本人の申請 | 既存の申請 API（ロックを追加） | `enrollment_source=JOIN_REQUEST` |
| PENDING → REJECTED | 管理者 | 既存の却下 API（ロックを追加） | — |

- 管理者の操作で ACTIVE にする経路は、参加申請の承認だけになる（本人の申請が起点のため）。
- どの遷移でも、参加者の行は削除しない。出欠・精算・会計の記録も変更しない。

### 5.3 招待の制約

| 場面 | 扱い |
|---|---|
| 同じ公演・同じメール・同じ種別に PENDING（期限内）がある | 新しく作らず、再送する（既存どおり。再送の上限に数える）。作成は公演の行をロックして直列化し、二重に作らない |
| 同じ公演・同じメールで、種別が異なる | 別の招待として作れる（参加者のUNIQUEキーが種別ごとのため、既存どおり）。送信の上限は、種別を問わず合算する |
| 有効期間 | 新しい招待は**作成から7日間**（D4） |
| 再送 | 同じ行のトークンを差し替え、**期限を再送の時点から7日間に更新する**。古いトークン（古いリンク）は無効になる |
| 招待1件あたりの再送の上限（D6） | 初回の送信は再送の回数に含めない。**再送は最大5回**（初回を含めて最大6回の送信）。6回目の再送は429 `stageart_participant_invitation_resend_limit` |
| 同じ公演・同じメールアドレスへの送信の上限（第3版） | **キャンセル済み・期限切れ・辞退済みを含む過去の招待をまたいで、初回の送信を含めて直近30日間に6回まで。**取消して新しく招待を作っても、数え直さない。メールアドレスは §6.4 の正規化で集計する。超えた場合は、作成・再送とも429 `stageart_participant_invitation_send_limit`（`available_at`：30日の枠から最も古い送信が外れる日時）。R14-B以前の送信は記録がないため数えない |
| 送信の数え方（第3版） | **作成・再送のAPIが受け付けた時点で、送信1回として数える。**`wp_mail()` の成功・失敗によって回数を戻さない。送信の状態は回数と別に管理する（§11.3） |
| 送信に失敗した招待 | 管理者は、上限の範囲内で再送できる（再送も1回として数える）。第2版の「失敗した場合は数えない」案は採用しない（U11） |
| 辞退の後の再招待（D6） | 本人が辞退（REJECTED）した日時から**30日間**は、同じ公演・同じメールアドレス（種別を問わない）への新しい招待を409 `stageart_participant_invitation_recently_rejected` で拒否する。応答に再招待できる日時（`available_at`）を入れる。30日を過ぎれば作れる（送信の上限の範囲内で）。辞退の履歴は削除しない |
| メールアドレスの比較 | 同じ宛先かどうかの判定（重複・送信の上限・30日の制限・本人の判定）は、すべて同じ正規化で比較する（§6.4）。大文字小文字の違いで制限を回避できないようにする |
| 期限切れ | 承諾・辞退はできない（410）。管理者は上限の範囲内で再送できる |
| 既に ACTIVE な参加者への招待 | 作成時点では誰の招待か判定しない（アカウントの有無を漏らさないため）。承諾時に既に ACTIVE なら、新しい行は作らず、招待を ACCEPTED にし、同意を記録する（冪等、§4.5） |
| 公演が COMPLETED／ARCHIVED／CANCELLED（D5） | 招待の作成・再送は409 `stageart_participant_invitation_production_closed`。承諾時に該当すれば、招待を CANCELLED(PRODUCTION_CLOSED) にして409。辞退はできる（U7）。一覧では「無効（公演終了）」と表示する |
| 招待者が権限を失った（D3） | 承諾時に、招待者が `canManageParticipants` でなければ CANCELLED(INVITER_REVOKED) にして409。参加者は作らない。辞退はできる（U7）。再送しても招待者は変わらず、承諾できないため、INVITER_REVOKED の状態の招待は再送できない（409 `stageart_participant_invitation_inviter_revoked`）。権限のある管理者が、取り消して新しい招待を作る（送信の上限の範囲内で） |
| 対象者のアカウントが SUSPENDED／DISABLED | 承諾・辞退できない（403）。招待は PENDING のまま残す（管理者に対象者の状態を知らせないため）。期限が来れば EXPIRED になる |
| 同時に2回承諾した | §5.4 の順にロックし、2回目は「既に処理済み」として最初の結果を返す（冪等）。参加者のUNIQUEキーで二重登録も防ぐ。通知は最初の1回だけ送る |
| 辞退・取消を2回行った | 2回目は最初の結果を返す（冪等）。辞退は本人の REJECTED、取消は CANCELLED(MANAGER) の場合だけ同じ結果を返し、それ以外の終端の状態なら409 `not_pending` |
| 承諾と取消が同時 | 同じロック順で直列化する。取消が先なら承諾は409、承諾が先なら取消は409 `stageart_participant_invitation_not_pending` |
| 承諾と参加申請の承認・却下が同時 | §4.7 |

**招待の作成を拒否する場合の応答とアカウントの有無**：上の拒否理由（公演の状態・辞退後30日・送信の上限・レート制限）は、いずれも管理者が既に知っている情報（公演の状態、自分の公演の招待一覧に表示される辞退・送信の履歴）から判断できるもので、宛先のアカウントの有無には依存しない。

### 5.4 ロックの順序（第3版で追加）

参加者・招待・担当者を更新するすべての処理で、次の順序でロックを取る。デッドロックを防ぐため、逆の順序で取る処理を作らない。

**公演（`findByIdForUpdate`） → 招待 → 参加者 → 担当者 → 送信の記録**

| 処理 | ロックする行（この順） |
|---|---|
| 招待の作成・再送 | 公演 → 同じ宛先の招待 → 送信の記録の追加 |
| 招待の取消 | 公演 → 招待 |
| 承諾 | 公演 → 招待 → 参加者 → 担当者 |
| 辞退 | 公演 → 招待 |
| 参加申請の作成・承認・却下 | 公演 → 参加者 |
| 参加者の更新（PUT）・削除（DELETE） | 公演 → 参加者 → 担当者 |
| 本人による参加辞退（withdraw） | 公演 → 参加者 → 担当者 |
| NAME_ONLY の追加 | 公演 → 参加者 |
| 担当者の任命・状態の変更 | 公演 → 参加者（対象者の確認） → 担当者 |
| 送信の直前の確認・送信の結果の記録（コミットの後） | 送信の記録だけ（§11.3。招待の `current_send_id` はロックせずに読むだけで、招待の行は更新しない。表示の状態は `current_send_id` から導くため、招待の行の更新は不要） |
| 保存期間による削除（定期処理） | 監査ログ・送信の記録だけ（§6.6。他の表はロックしない） |

- 監査ログ（追記だけ）は、ロックを取らずに同じトランザクションで INSERT する。
- 通知とメールの送信は、すべてコミットの後に行う（§11.2）。

## 6. データモデル・Migration案

### 6.1 列の追加（スキーマ1.49.0、追加のみ）

**`stageart_participant_invitations`**

| 列 | 型 | 用途 |
|---|---|---|
| `invitee_person_id` | CHAR(36) NULL | 承諾・辞退した本人。応答した時点で記録する |
| `responded_at` | DATETIME NULL | 承諾・辞退の日時（30日の制限の起点） |
| `participant_id` | CHAR(36) NULL | 承諾で作成・再有効化・同意記録した参加者 |
| `closed_at` | DATETIME NULL | 無効化が発生した日時（§5.1。`close_reason` と同時に記録し、変更しない） |
| `closed_by_person_id` | CHAR(36) NULL | 管理者が無効化した場合（MANAGER）の実行者。システムによる無効化では NULL |
| `close_reason` | VARCHAR(30) NULL | 過去に無効化された理由：MANAGER／PRODUCTION_CLOSED／INVITER_REVOKED（§5.1）。現在の状態は `status` で表す。無効化の後に辞退した招待（`status=REJECTED`）でも保持する |
| `revocation_notified_at` | DATETIME NULL | 招待者の権限喪失（INVITER_REVOKED）を PrimaryManager に通知すると決めた日時（第5版、§11.5）。二重通知の防止に使う |
| `resend_count` | INT UNSIGNED NOT NULL DEFAULT 0 | この招待の再送の回数（初回は含めない。上限5、D6）。受け付けた時点で数える |
| `current_send_id` | CHAR(36) NULL | 最新の送信の試行（§6.2 の行）。古い試行の結果で、表示の状態を上書きしないために使う |
| `last_sent_at` | DATETIME NULL | 最新の送信を受け付けた日時 |
| `updated_at` | DATETIME NULL | 更新日時 |

- インデックス：`KEY production_email_type (production_id, email, participant_type)`、`KEY production_email_status (production_id, email, status, responded_at)`（30日の制限の判定）、`KEY invitee_person_id (invitee_person_id)`。
- 一意制約は追加しない。同じ宛先の履歴の行が並ぶため。PENDING の一意性は、公演の行ロックで保証する。
- `status` の値に ACCEPTED／REJECTED を追加する。VARCHAR(20) なので、DDLの変更は不要。
- `TOKEN_LIFETIME_SPEC` を `'PT24H'` から `'P7D'` に変更する（D4）。既存の招待の `expires_at` は変更しない。
- `resend_count` の既存行は、列の追加によって既定値の0になる（UPDATE文は実行しない）。R14-B以前の再送の回数は分からないため、R14-B以降の再送から数える。
- 送信の状態（送信中・送信済み・失敗・結果不明）は、招待の行ではなく、§6.2 の送信の記録で管理する（第2版の `last_send_status` 列は設けない）。

**`stageart_participants`**（D2、第3版で変更）

| 列 | 型 | 用途 |
|---|---|---|
| `enrollment_source` | VARCHAR(20) NULL | 最初に行が作られた経路：INVITATION／JOIN_REQUEST／MANAGER_RECORD。以後は変更しない。NULL は R14-B以前の行 |
| `consent_source` | VARCHAR(20) NULL | 本人が同意した経路（直近）：INVITATION／JOIN_REQUEST。NULL は同意の記録なし |
| `consented_at` | DATETIME NULL | 本人が同意した日時（直近）。NULL は同意の記録なし |

- 既存の行は、3列とも NULL のまま（値を入れない）。
- 担当者の有効性の判定（§4.6）のため、`KEY production_subject_status (production_id, subject_type, subject_id, status)` を追加する【推奨】（既存のUNIQUEキーで足りるかは実装時に確認する）。

**`stageart_production_delegates`**：列の追加はしない。役割の解除には既存の `status`（INACTIVE）・`updated_by`・`updated_at` を使う。

### 6.2 招待の送信の記録（新テーブル、追加のみ。第3版で追加）

送信の回数（招待をまたいだ30日間の上限）と、送信の試行ごとの状態を管理する。

```sql
CREATE TABLE {prefix}stageart_participant_invitation_sends (
  id CHAR(36) NOT NULL,                -- 送信の試行の識別子
  invitation_id CHAR(36) NOT NULL,
  production_id CHAR(36) NOT NULL,
  email_key CHAR(64) NOT NULL,         -- 正規化したメールアドレス（§6.4）の HMAC-SHA-256（16進）。鍵は §6.7。アドレスそのものは保存しない
  email_key_version VARCHAR(20) NOT NULL, -- email_key を計算した鍵の版（§6.7）
  kind VARCHAR(10) NOT NULL,           -- INITIAL / RESEND
  status VARCHAR(12) NOT NULL,         -- PENDING / SENT / FAILED / SUPERSEDED
  requested_by_person_id CHAR(36) NOT NULL,
  requested_at DATETIME NOT NULL,      -- APIが受け付けた日時（回数の集計に使う）
  completed_at DATETIME NULL,          -- 送信の結果を記録した日時
  PRIMARY KEY (id),
  KEY production_version_email_requested (production_id, email_key_version, email_key, requested_at),
  KEY production_requested (production_id, requested_at),
  KEY version_requested (email_key_version, requested_at),
  KEY invitation_id (invitation_id, requested_at),
  KEY requested_at (requested_at)
);
```

- **数え方**：作成・再送のAPIが受け付けたときに、トランザクションの中で1行を `status=PENDING` で追加する（§5.4）。この行の数が送信の回数になる。送信の結果で行を削除したり、回数を戻したりしない。
- **集計**：`production_id` が同じで、`requested_at` が直近30日の行のうち、**`email_key_version` と `email_key` の組**が宛先に一致する行を数える。期間内に使われたすべての鍵の版で HMAC を計算して照合する（§6.7。30日以内に複数回ローテーションしても合算される）。キャンセル済み・期限切れ・辞退済みの招待の送信も含まれる。SUPERSEDED の行も数える（受け付けた時点で数えるため）。
- **状態**：

| 保存する値 | 意味 |
|---|---|
| PENDING | 受け付けたが、送信の結果をまだ記録していない（送信中） |
| SENT | `wp_mail()` が true を返した（メールサーバーが受け付けた。**配達の完了は意味しない**） |
| FAILED | `wp_mail()` が false を返した、または例外になった |
| SUPERSEDED | 送信の直前に、同じ招待でより新しい試行が受け付けられていたため、メールを送らなかった（第4版、§11.3）。回数には数える |

  - **UNKNOWN（導出）**：PENDING のまま、受け付けから10分【推奨】を過ぎた行。送信の処理が途中で止まった（PHPのタイムアウト・プロセスの停止など）ため、結果が確定できない状態。保存はせず、表示とAPIで導出する。
- **`email_key` と招待の `email` の用途の区別**（第4版）
  - 招待の行の `email`：招待メールの宛先と、本人の判定（確認済みアドレスとの照合）に使う。入力された値（`trim` 後）を、招待の行と同じ期間保持する（既存どおり）。
  - 送信の記録の `email_key`：30日間の送信回数の集計だけに使う。宛先として使わず、アドレスに戻すこともしない。HMAC にしたのは、送信の記録だけが漏れた場合に、よく使われるアドレスの一覧から総当たりでアドレスを推測されないようにするため（単純な SHA-256 では、鍵がないため推測できる）。
  - 新しく個人情報を増やすものではない（招待の行に同じアドレスがある）。
- この表は送信の制御のためのもので、監査ログ（§6.3）とは別にする。保持期間は §6.6（原則90日、最新の試行は保持）。

### 6.3 参加者の監査ログ（新テーブル、追加のみ。【確定】D8）

既存に汎用の監査ログはない（会計の移行監査だけ）。参加者・招待・担当者の状態変更を記録する、**追記専用**の表を設ける。

```sql
CREATE TABLE {prefix}stageart_participant_events (
  id CHAR(36) NOT NULL,
  production_id CHAR(36) NOT NULL,
  participant_id CHAR(36) NULL,
  invitation_id CHAR(36) NULL,
  delegate_id CHAR(36) NULL,
  event_type VARCHAR(40) NOT NULL,
  enrollment_path VARCHAR(20) NULL,  -- INVITATION / JOIN_REQUEST / MANAGER_RECORD（ACTIVEになった・同意した経路）
  actor_person_id CHAR(36) NULL,     -- 操作した人（自動の場合はNULL）
  subject_person_id CHAR(36) NULL,   -- 対象の人物（承諾前は不明なのでNULL）
  detail TEXT NULL,                  -- JSON（変更前後の状態・close_reason・送信の試行IDなど。メール・氏名・トークンは入れない）
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY production_id (production_id, created_at),
  KEY participant_id (participant_id),
  KEY invitation_id (invitation_id),
  KEY created_at (created_at)
);
```

**event_type**

| 区分 | 値 |
|---|---|
| 招待 | INVITED、RESENT、SEND_FAILED、INVITATION_CANCELLED、INVITATION_CLOSED（PRODUCTION_CLOSED・INVITER_REVOKED）、ACCEPTED、REJECTED |
| 参加申請 | JOIN_REQUESTED、JOIN_APPROVED、JOIN_REJECTED |
| 参加者 | NAME_ONLY_ADDED、REMOVED_BY_MANAGER、WITHDRAWN、DEACTIVATED、TYPE_CHANGED、CONSENT_RECORDED（既に ACTIVE な人の承諾） |
| 担当者 | DELEGATE_APPOINTED、DELEGATE_REACTIVATED、DELEGATE_DEACTIVATED（`detail.reason`：PARTICIPANT_WITHDRAWN／PARTICIPANT_REMOVED／PARTICIPANT_DEACTIVATED／DORMANT_ON_ENROLLMENT／MANAGER） |

**運用の方針**

- 追記専用。アプリケーションからは INSERT だけを行い、UPDATE・DELETE はしない。
- メールアドレス・氏名・**トークン（平文・ハッシュとも）**を保存しない。招待・参加者・担当者・人物の行IDで追跡する。
- 記録する項目：操作した人（`actor_person_id`）、対象（参加者・招待・担当者・人物のID）、イベントの種別、日時。
- 閲覧権限を限定する。R14-Bでは、監査ログを閲覧するAPIも画面も作らない。DBを直接参照できる運用者だけが見られる。将来、APIを作る場合は、PrimaryManager に限るなど、別途設計する。
- **保持期間は、記録日時（`created_at`）から2年間**（【確定】U12）。期間を過ぎた行は、§6.6 の削除の処理で削除する。`created_at` の範囲で削除できるよう、`created_at` にインデックスを付け、他のテーブルから外部キーで参照しない。
- 監査ログの書き込みは、状態の変更と同じトランザクションで行う（記録だけが失われないように）。`SEND_FAILED` だけは、コミット後の送信の結果を記録するときに、送信の記録の更新と同じトランザクションで書く（§11.3）。

### 6.4 メールアドレスの正規化（D6）

- **方針**【確定】（第3版）：比較と集計には「前後の空白を除き、全体を小文字にした値」を使う。保存する値は、入力された値（`trim` 後）のままにする。
- 比較・集計の場所：招待の重複確認（作成・再送）、30日間の送信の上限（`email_key` は、この正規化した値を入力とする HMAC-SHA-256。鍵は §6.7。鍵なしの SHA-256 単独では計算しない）、辞退後30日の制限、本人の判定（`FindPersonByEmailUseCase` との照合）、`GET /me/participant-invitations`。
- DBの照合順序が `_ci`（大文字小文字を区別しない）であれば、SQLの `=` でも同じ結果になる。PHP側で比較する場合は `mb_strtolower` を使い、SQLとPHPの結果が食い違わないようにする（R13 Medium「メールアドレスの大文字小文字の照合」）。
- 既存の行を正規化して書き換えることはしない（Migrationでデータを変更しない）。
- **DBの照合順序とアプリ側の正規化の整合性を確認する**（§16.2 U9）。ユーザーから、読み取り専用のSELECTの実行が許可された（Dev：§12 のSELECT、Production：§12 の `information_schema.COLUMNS` の照合順序のSELECTだけ）。確認は、実装開始後の事前確認（§15の0）として行い、結果を記録する。本書の改訂ではDBに接続しない。`_ci` 以外（`_bin` など）だった場合は、比較の方法を見直し、本書を改訂する。

### 6.5 Migration（`Installer`、スキーマ1.48.0 → 1.49.0）

1. `dbDelta` で、6.1の列とインデックス、6.2の送信の記録、6.3の監査ログ、§9.2 のレート制限の表を追加する。
2. **データの更新はしない**（UPDATE・DELETE文を実行しない）。
   - 既存の PENDING の招待は、そのまま承諾待ちとして扱う。期限（24時間で作られたもの）は既存の値のまま（D4）。
   - 既存の CONSUMED は終端として残す。
   - 既存の ACTIVE な参加者には触れない。`enrollment_source`・`consent_source`・`consented_at` は NULL のまま（D2）。
   - 既存の担当者の行には触れない。権限の判定だけが変わる（§4.6）。
   - `NOT NULL DEFAULT 0` の列（`resend_count`）は、列の追加によって既存行が既定値になる。これはDDLの結果で、データの書き換えではない。
3. 冪等にする（列・テーブルが既にあれば何もしない）。失敗時はバージョンを上げずに、次回もう一度実行する（R14-A N2と同じ形）。
4. 削除を伴う処理はない（保存期間による削除は、§6.6 の定期処理で行い、Migrationでは行わない）。

### 6.6 保存期間と削除（【確定】U12、第4版で追加）

| データ | 保存期間 | 削除の方法 |
|---|---|---|
| 参加者の監査ログ（`stageart_participant_events`） | 記録日時（`created_at`）から**2年間** | 期間を過ぎた行を削除する |
| 招待メールの送信記録（`stageart_participant_invitation_sends`） | 原則として記録日時（`requested_at`）から**90日間** | 期間を過ぎた行を削除する。ただし、各招待の**最新の試行**（招待の `current_send_id` が指す行）は、招待一覧で最新の送信状態を表示するため、90日を過ぎても削除しない |
| 参加者の同意の情報（`consent_source`・`consented_at`） | 参加者の行と同じ期間（参加者・公演の履歴を保持する間） | 参加者の行の一部として保持する。この処理では削除しない |
| 参加者の行・出欠・精算・会計の記録 | 既存どおり（この設計では期間を設けない） | **削除しない** |
| 招待の行 | 既存どおり（この設計では期間を設けない） | 削除しない |

- 30日間の送信回数の集計には直近30日の行だけが必要で、90日の保存期間で正しく集計できる。最新の試行の行を残しても、集計の結果は変わらない（30日より古い行は数えないため）。
- 監査ログの `detail` には、メールアドレス・氏名・トークンを保存しない（§6.3）。

**削除の処理**【推奨】

- 1日1回の定期処理にする。WP-Cron（`wp_schedule_event`）を使う。WP-Cron の実行時刻は保証されないが、削除が遅れるだけで、正しさには影響しない。
- 対象を期間で限定する：監査ログは `created_at < 現在 - 2年`、送信記録は `requested_at < 現在 - 90日` かつ、どの招待の `current_send_id` からも指されていない行。
- 一度に大量の行を削除しないよう、件数を区切って削除する（例：1回に1,000行まで、残りは次回）。
- この2つの表以外は削除しない。参加者・招待・出欠・会計の表には触れない。
- 削除した件数だけをログに出す（行の内容は出さない）。
- 削除の処理は、R14-Bの実装に含める（本番での利用の開始前に動いている必要がある）。

### 6.7 email_key の鍵の管理（第4版で追加）

- `email_key = HMAC-SHA-256(鍵, 正規化したメールアドレス)` を16進で保存する（§6.2）。正規化は §6.4（前後の空白を除き、全体を小文字）。
- **SHA-256 単独（鍵なし）での計算は認めない。**鍵が使えない場合も、単純なハッシュに切り替えない。
- **鍵はDBに保存しない。**`wp-config.php` の定数で与える。既存の `STAGEART_JWT_SECRET` とは別の鍵にする（用途を分け、片方の漏えいやローテーションが他方に影響しないように）。

**鍵の束（キーリング）**（第5版で変更。30日以内の複数回のローテーションに対応する）

- `STAGEART_EMAIL_KEYS`：鍵の版と鍵の値の組を、**複数**持てる設定にする（例：`['v1' => '…', 'v2' => '…', 'v3' => '…']`。各値は32バイト以上のランダムな値）。
- `STAGEART_EMAIL_KEY_CURRENT`：新しい送信の記録に使う版（例：`v3`）。`STAGEART_EMAIL_KEYS` に含まれている必要がある。
- 新しい送信の記録は、現在の版の鍵で `email_key` を計算し、その版を `email_key_version` に保存する。
- 第4版の「現在の鍵と前の鍵の2つ」の設計は、30日以内に2回以上ローテーションすると集計から漏れるため、この鍵の束に置き換える。

**30日間の集計**

1. 集計対象の期間（直近30日）に、その公演の送信の記録で使われている鍵の版を求める。

   ```sql
   SELECT DISTINCT email_key_version FROM {prefix}stageart_participant_invitation_sends
    WHERE production_id = %s AND requested_at >= %s;   -- %s: 現在 - 30日
   ```

2. 求めた版のそれぞれについて、鍵の束にあるその版の鍵で、正規化したメールアドレスの HMAC を計算する（現在の版だけでなく、期間内に使われた旧い版も使う）。
3. **`email_key_version` と `email_key` の組み合わせ**で数える。

   ```sql
   SELECT COUNT(*) FROM {prefix}stageart_participant_invitation_sends
    WHERE production_id = %s AND requested_at >= %s
      AND ((email_key_version = 'v1' AND email_key = %s)
        OR (email_key_version = 'v2' AND email_key = %s)
        OR (email_key_version = 'v3' AND email_key = %s));
   ```

4. 期間内に使われている版の鍵が、鍵の束にない場合は、正しく数えられないため、招待の作成・再送を安全側で拒否する（500 `stageart_email_key_not_configured`）。少なく数えて送信を許すことはしない。
- インデックス：`KEY production_version_email_requested (production_id, email_key_version, email_key, requested_at)` を使う（§6.2）。手順1は `production_id` と `requested_at` の範囲で、手順3は版と `email_key` の組で、このインデックスを使える。版ごとに `email_key` の値が異なるため、版を含めなくても結果は同じだが、組で数えることを明確にし、版の偶然の一致を避けるため、版を含める。

**鍵の版を外せる（削除できる）条件**

- その版で作った送信の記録が、**すべて30日間の集計対象から外れた後**。つまり、その版の行の `requested_at` の最大値が、現在から30日より前になった後。
- 確認には、次の読み取りだけのSELECTを使う（Production での実行は、ユーザーの承認を得てから）。

  ```sql
  SELECT MAX(requested_at) FROM {prefix}stageart_participant_invitation_sends
   WHERE email_key_version = %s;   -- 結果が「現在 - 30日」より前（または行がない）なら外せる
  ```

- 外した版の行は、集計に使われないまま、§6.6 の保存期間（原則90日、最新の試行は保持）で削除される。外した後に、その版の行が集計の期間に入ることはない（新しい行は現在の版で作るため）。
- 30日の境界での時刻の誤差に備え、実際には1日程度の余裕を見てから外す【推奨】。

**ローテーションの手順**

1. 新しい版の鍵を `STAGEART_EMAIL_KEYS` に追加する。
2. `STAGEART_EMAIL_KEY_CURRENT` を新しい版に切り替える。以後の送信の記録は新しい版で作る。
3. 旧い版の鍵は、上の「外せる条件」を満たすまで、鍵の束に残す（30日以内に何回ローテーションしても、期間内に使われたすべての版が残る）。
4. 条件を満たした版から、鍵の束から外す。

**鍵が漏えいした場合（緊急のローテーション）**

1. 直ちに新しい版を追加して、現在の版に切り替える（上の手順1・2）。以後、漏えいした鍵で新しい記録を作らない。
2. 漏えいした鍵は、**集計のためだけに**、外せる条件を満たすまで鍵の束に残す。漏えいした鍵で計算済みの値は、既に漏えいの影響を受けていて、集計に使い続けても新しい危険は増えない。
3. どうしても直ちに外す必要がある場合は、外した版の行を集計に使えなくなり、手順4の安全側の動作で、その公演の招待の作成・再送が、最大30日間止まる。これを避けたい場合は、ユーザーの判断で、外した版の行を数えない（送信の上限が実質的に数え直される）運用に一時的に切り替える。どちらにするかは、緊急時にユーザーが判断する。
4. 漏えいした鍵で計算された行は、§6.6 の保存期間で削除されるまで残る。

**その他**

- 鍵の束の設定がない、または `STAGEART_EMAIL_KEY_CURRENT` の版の鍵がない場合：招待の作成・再送を、安全側で拒否する（500 `stageart_email_key_not_configured`。管理者には「サーバーの設定が不足しています」とだけ表示する）。リリースの手順に、鍵の設定を含める。
- 鍵の値は、ログ・エラーメッセージ・監査ログに出力しない。版の名前は出力してよい。

## 7. API仕様

StageArt API はアプリ専用である（U6）。以下の変更は、アプリ（Frontend）と同時に配布する前提で、外部の利用者との互換性は考慮しない。

### 7.1 変更・廃止

| API | 変更 |
|---|---|
| `POST /productions/{id}/participants` | `subject_type=PERSON`・`ORGANIZATION` を拒否する（422 `stageart_participant_requires_invitation`。D10）。NAME_ONLY（アカウントを持たない、公演側だけの記録）は残し、`enrollment_source=MANAGER_RECORD` を記録する |
| `POST /productions/{id}/participant-invitations` | 既存利用者の分岐を削除し、常に招待する。**宛先の人物の検索（`FindPersonByEmailUseCase`）もしない。アプリ内通知も送らない**（§9.2）。公演の状態（D5）・辞退後30日（D6）・送信の上限・レート制限を確認する。有効期間は7日（D4）。応答の `outcome` は `INVITATION_SENT`・`INVITATION_RESENT` だけにし、`participant` は返さない |
| `GET /productions/{id}/participant-invitations` | `effective_status`（PENDING／EXPIRED／PRODUCTION_CLOSED／INVITER_REVOKED／ACCEPTED／REJECTED／CANCELLED／CONSUMED）、`responded_at`、`close_reason`・`closed_at`（§5.1。REJECTED でも、無効化の後の辞退なら値を持つ）、`resend_count`・`resend_remaining`（この招待の残り）、`send_status`（最新の送信：PENDING／SENT／FAILED／UNKNOWN）、`last_sent_at`、`send_limit`（`sent_in_30_days`・`remaining_in_30_days`・`available_at`）、辞退の場合は再招待できる日時（`reinvite_available_at`）を追加する。招待先の人物ID・氏名は返さない |
| `POST /participant-invitations/{id}/cancel` | `closed_by_person_id`・`closed_at`・`close_reason=MANAGER` を記録する。既に CANCELLED(MANAGER) なら同じ結果を返す（冪等）。他の終端の状態なら409 |
| `POST /participant-invitations/{id}/resend` | 公演の状態（D5）・招待者の権限（INVITER_REVOKED なら409）・招待1件あたりの上限・30日間の上限を確認する。期限を再送の時点から7日に更新し、トークンを差し替え、送信の記録を追加する。上限を超えれば429 |
| `GET /participant-invitations/resolve?token=` | **廃止**。`POST /participant-invitations/registration-hint`（未ログイン、メールだけ）と `POST /participant-invitations/lookup`（ログイン必須、内容）に分ける（§7.2） |
| `PUT /participants/{id}` | ACTIVE 以外への `status` 変更（INACTIVE）は残し、ACTIVE 以外から ACTIVE への変更は拒否する（422 `stageart_participant_status_requires_consent`）。INACTIVE にした場合は担当者の役割を解除する（§4.6）。画面は `status` を送らないようにする |
| `DELETE /participants/{id}` | 既存どおり CANCELLED にする。担当者の役割を解除する（§4.6）。行・出欠・会計の記録は削除しない |
| `GET /productions/{id}/participants` | §7.3 のとおり、個人情報を絞る。各行に `enrollment_source`・`consent_status`（CONFIRMED／UNCONFIRMED／NOT_APPLICABLE）・`consented_at` を追加する（§4.5） |
| 参加申請の作成・承認・却下 | §5.4 の順にロックし、参加者の行を読み直してから処理する。承認・却下は、PENDING でなければ409 `stageart_participant_request_not_pending`（§4.7）。作成時に `enrollment_source=JOIN_REQUEST` を記録する |
| `POST /productions/{id}/delegates` | 対象者を、その公演の ACTIVE な PERSON 参加者で、同意が確認できる人に限る（422 `stageart_production_delegate_target_not_participant`／`stageart_production_delegate_target_not_consented`）。同じ役割の INACTIVE の行があれば ACTIVE に戻す |
| `GET /productions/{id}/delegates` | 各行に `effective`（bool）と `ineffective_reason`（`INACTIVE`／`NOT_ACTIVE_PARTICIPANT`／`CONSENT_UNCONFIRMED`）を追加する（§4.6） |
| `PUT /production-delegates/{id}`（担当者の役割・状態の変更） | ACTIVE に戻す場合も、対象者が同意を確認できる ACTIVE な PERSON 参加者であることを確認する |
| `GET /people?email=` | **削除**（F5、U6）。ルート・UseCase・Query を削除する |
| `GET /people/{id}` | **削除**（U6）。任意の人物IDから他人の情報を取得できるAPIは残さない |
| 自動解決（登録・メール確認・Googleサインイン） | 公演の招待については、`ResolveParticipantInvitationUseCase` の呼び出しを削除する。招待リンクから招待先と同じアドレスで登録した場合に、メールを確認済みにする処理は残す。**団体招待の自動解決（`ResolveOrganizationInvitationUseCase`）は、R14-Bでは変更しない**（D1） |

### 7.2 新設

**`GET /me/participant-invitations`**（ログイン必須）：自分宛ての招待一覧

- 本人の確認済みアドレス（ログイン用と通知用）に一致し、§5.1 の判定表で「本人の一覧に出す」ものを返す。具体的には、期限内の PENDING（公演の終了・招待者の権限喪失を含む）と、期限内の `CANCELLED(PRODUCTION_CLOSED／INVITER_REVOKED)`。後の2種類は辞退できるように含める（U7）。
- 応答の項目（D7）：`id`・`production_name`・`organization_name`・`participant_type`・`invited_by_display_name`（招待者の公演側の表示名。ない場合は「公演の管理者」）・`expires_at`・`already_participant`（本人が既に ACTIVE な参加者か。本人に関する情報なので本人には返す）・`can_accept`（bool）・`can_reject`（bool）・`unavailable_reason`（`PRODUCTION_CLOSED`／`INVITER_REVOKED`／null）。
- 返さない項目：招待先のメール、管理者が入力した氏名・備考。
- ホームの件数は、`can_accept=true` の件数を表示する。

**`POST /participant-invitations/lookup`**（ログイン必須）：リンクから招待を開く

- 本文：`{ "token": "..." }`。トークンはクエリではなく本文で送る（§9.2）。
- トークンが招待に一致し、招待のメールが本人の確認済みアドレスなら、§5.1 の判定表に従う。
  - 承諾か辞退のどちらかができる招待（期限内の PENDING と、期限内の `CANCELLED(PRODUCTION_CLOSED／INVITER_REVOKED)`）：上と同じ項目を返す（200）。公演の終了・招待者の権限喪失の場合は、`can_accept=false`・`can_reject=true`・`unavailable_reason` を返す（照会と辞退の扱いを一致させる。U7）。
  - 期限切れ：410。承諾も辞退もできない処理済みの招待（ACCEPTED・REJECTED・`CANCELLED(MANAGER)`・理由 NULL の CANCELLED・CONSUMED）：409（本人には状態を伝える）。
- トークンは再送のたびに差し替わるため、古いメールのリンクのトークンは、どの招待にも一致しない（404 `link_invalid`。§11.3）。
- 招待が PENDING で、本人宛てでない場合は403 `stageart_participant_invitation_not_for_you`（「このアカウント宛ての招待ではありません。招待を受け取ったメールアドレスでログインし直してください」）。招待の内容は返さない。
- トークンが一致しない場合、または本人宛てでない招待が PENDING 以外の場合は、すべて同じ404 `stageart_participant_invitation_link_invalid` を返す（§9.2）。
- 応答に `Cache-Control: no-store` を付ける。

**`POST /participant-invitations/registration-hint`**（ログイン不要）：登録画面の初期値（【確定】U14）

- 本文：`{ "token": "..." }`。
- トークンが PENDING かつ期限内の招待に一致する場合だけ、`{ "email": "..." }` を返す。公演名・団体名・氏名・役割・期限・状態などは返さない（D7）。
- それ以外（不一致・期限切れ・処理済み・取消）は、すべて同じ404 `stageart_participant_invitation_link_invalid`。
- レート制限の対象（§9.2）。応答に `Cache-Control: no-store` を付ける。

**`POST /participant-invitations/{id}/accept`**（ログイン必須）

- 本文：なし（任意で `token`。リンクから開いた場合の確認用。ただし、権限の根拠にはしない）。
- 処理（1トランザクション。ロックは §5.4）：
  1. 公演を FOR UPDATE でロックする。
  2. 招待を FOR UPDATE でロックする。
  3. 招待が既に ACCEPTED で、`invitee_person_id` が本人なら、最初の結果をそのまま返す（冪等。通知・監査ログは追加しない）。
  4. §4.3 の承諾の条件を確認する。公演の終了（D5）・招待者の権限喪失（D3）なら、招待を CANCELLED にし、`close_reason`・`closed_at` を記録する（§5.1）。INVITER_REVOKED で `revocation_notified_at` が NULL なら、それも記録する（§11.5）。監査ログ `INVITATION_CLOSED` を記録して、コミットしてから409を返す。コミットの後に、PrimaryManager へ通知する（このトランザクションで `revocation_notified_at` を記録した場合だけ）。
  5. 参加者の行を FOR UPDATE で読み、同じ (公演, PERSON, 本人, 種別) の行の有無と状態に応じて、作成・再有効化・承認・同意の記録をする（§5.2）。`display_name` には、管理者が入力した `invited_name` を使う（既存の方針）。`enrollment_source` は、新しく行を作る場合だけ INVITATION にする。
  6. 参加者が ACTIVE でない状態から ACTIVE になった場合は、本人のこの公演での担当者の行のうち ACTIVE のものを INACTIVE にする（§4.6 `DORMANT_ON_ENROLLMENT`）。
  7. 招待を ACCEPTED にし、`invitee_person_id`・`responded_at`・`participant_id` を記録する。
  8. 監査ログを記録する（`ACCEPTED`、必要に応じて `CONSENT_RECORDED`・`DELEGATE_DEACTIVATED`）。
- コミットした後に、招待者へ通知する（§11）。
- 応答：200。項目は `participant_id`・`production_id`・`status=ACTIVE`。
- エラー：403（宛先が違う・アカウントが無効・未確認。内容は返さない）、404（`link_invalid` と同じ扱い）、409 `stageart_participant_invitation_not_pending`（取消済み・辞退済み・他人が処理済み）、409 `stageart_participant_invitation_closed`（`close_reason` 付き。公演の終了・招待者の権限喪失）、410 `stageart_participant_invitation_expired`、429（レート制限）。

**`POST /participant-invitations/{id}/reject`**（ログイン必須）

- ロックは §5.4（公演 → 招待）。条件は §4.3 の辞退の条件と §5.1 の判定表（【確定】U7）。**公演の終了・招待者の権限喪失の後でも、期限内なら辞退できる。**承諾を試みて `CANCELLED(PRODUCTION_CLOSED／INVITER_REVOKED)` になった招待も辞退できる。`CANCELLED(MANAGER)` は409 `not_pending`。
- 招待を REJECTED にし、`invitee_person_id`・`responded_at` を記録する。参加者は作らず、既存の参加者の行も変更しない。参加の権限は発生しない。
- `close_reason`・`closed_at` の扱い（§5.1）：
  - CANCELLED（PRODUCTION_CLOSED・INVITER_REVOKED）から辞退した場合：元の `close_reason`・`closed_at` をそのまま保持する。
  - PENDING の招待で、辞退の時点で公演の終了・招待者の権限喪失を**初めて検出した**場合：その理由を `close_reason`、検出した日時を `closed_at` に記録する。INVITER_REVOKED で `revocation_notified_at` が NULL なら、それも記録する（§11.5）。
  - 監査ログ `REJECTED` の `detail` に、辞退の前の `status` と、`close_reason`（あれば）を記録する。辞退の時点で初めて検出した場合は、`INVITATION_CLOSED` も記録する。
- 既に REJECTED で本人の辞退なら、同じ結果を返す（冪等）。
- コミットした後に、通知する（§11）。辞退者の氏名は通知に含めず、招待時に管理者が入力した氏名を使う。

**`POST /participants/{id}/withdraw`**（ログイン必須）：本人による参加辞退

- 対象の参加者が PERSON で、`subject_id` が本人であり、ACTIVE の場合だけ実行できる。WITHDRAWN にする。
- 同じトランザクションで（ロックは §5.4）、本人のこの公演での担当者の行をすべて INACTIVE にする（D9）。行は削除しない。出欠・会計などの記録も変更しない。
- PrimaryManager 本人は辞退できない（409 `stageart_participant_withdraw_primary_manager`。「先に管理者を引き継いでください」）。
- コミットした後に、管理者（PrimaryManager と、有効な PARTICIPANT_MANAGER）に通知する。
- 他人の参加者IDでは403。既に WITHDRAWN なら同じ結果を返す（冪等）。

### 7.3 `ParticipantResult` の個人情報（【確定】D11・U3）

公演の状態によって、閲覧できる範囲を分ける。**データの保持（削除しない）と、閲覧の権限は別に扱う**（D11）。

**公演が PLANNING／ACTIVE の場合**

| 項目 | 管理者（canManage） | 閲覧のみ（REHEARSAL_MANAGER） | 本人 |
|---|---|---|---|
| `display_name`（公演側の氏名） | ○ | ○ | ○ |
| `person_family_name`／`person_given_name` | ACTIVE・INACTIVE のみ | ACTIVE・INACTIVE のみ | ○ |
| `email` | ACTIVE・INACTIVE のみ | × | ○ |
| `enrollment_source`・`consent_status`・`consented_at` | ○ | × | ○ |
| CANCELLED／WITHDRAWN／REJECTED の行 | `display_name` と状態のみ | 行を返さない | — |

**公演が COMPLETED／ARCHIVED／CANCELLED の場合**（【確定】U3：メールアドレスは PrimaryManager だけ）

| 項目 | PrimaryManager | その他の管理者 | 閲覧のみ | 本人 |
|---|---|---|---|---|
| `display_name` | ○ | ○ | ○ | ○ |
| 姓・名 | ACTIVE・INACTIVE のみ | ACTIVE・INACTIVE のみ | × | ○ |
| `email` | ACTIVE・INACTIVE のみ | × | × | ○ |

- 精算・実績集計・活動履歴（参加公演の一覧）は、参加者の行（ID・`display_name`・状態）を従来どおり参照できる。これらはメールアドレスを必要としない。

### 7.4 UseCase の構成

- `AcceptParticipantInvitationUseCase`・`RejectParticipantInvitationUseCase`・`ListMyParticipantInvitationsUseCase`・`LookupParticipantInvitationUseCase`・`GetInvitationRegistrationHintUseCase`・`WithdrawParticipationUseCase` を新設する。
- `InvitationRecipientVerifier`（Application）を新設する。`FindPersonByEmailUseCase` を使って「この招待のメールの持ち主が本人か」を判定し、UserAccount の状態も確認する。招待の取得系と承諾系で共有する。比較は §6.4 の正規化で行う。招待の作成では使わない。
- `InvitationSendService`（仮称）を新設し、送信の上限の確認、送信の記録の追加、コミット後の送信と結果の記録（§11.3）を1か所にまとめる。作成と再送から使う。
- 参加者の作成は、`CreateParticipantUseCase` の重複確認と作成部分を、権限確認を含まない内部サービス（例：`ParticipantEnrollmentService::enrollFromInvitation()`）に分けて共有する。外部APIの `CreateParticipantUseCase` は、NAME_ONLY 専用に縮小する。
- `ParticipantDelegateRoleReleaser`（仮称）を新設し、参加者が ACTIVE でなくなったとき・ACTIVE になったときの担当者の行の INACTIVE 化（§4.6）を1か所にまとめる。withdraw・DELETE・PUT・accept・参加申請の承認から呼ぶ。
- `ParticipantEventRecorder`（仮称）を新設し、監査ログの記録を1か所にまとめる。
- `ProductionAuthorizationService` の `activeDelegateFor()`・`activeDelegatesFor()` に、ACTIVE な PERSON 参加者であることと、同意が確認できることの確認を加える。`GetMyDashboardUseCase`・`ListProductionsUseCase` も同じ判定を使う。参加者の確認を毎回全件読み込みで行うと遅くなるため、`existsConsentedActivePersonParticipant(productionId, personId)` のような Repository のメソッドを追加する【推奨】。
- `ResolveParticipantInvitationUseCase` は削除する。呼び出し元の3か所からも外す（団体招待の解決の呼び出しは残す）。
- `SearchPersonByEmailUseCase`・`SearchPersonByEmailQuery`・`GetPersonByIdUseCase`・`GetPersonByIdQuery`・`PersonRestController` は削除する。`PersonSummaryResult` は `FindPersonByEmailUseCase` が使うため残す。

## 8. 権限マトリクス

「担当者」は、§4.6 の**有効な担当者**（行が ACTIVE で、公演の ACTIVE な PERSON 参加者で、同意が確認できる人）を指す。条件を満たさない担当者の行は、行が ACTIVE でも、表のどの担当者の列にも当たらない（「無効な担当者の行だけを持つ人」の列）。

| 操作 | PrimaryManager | 代理人（PARTICIPANT＋REHEARSAL） | PARTICIPANT_MANAGER | REHEARSAL_MANAGER など他の担当者 | ACTIVE 参加者（本人） | 招待された本人 | 無効な担当者の行だけを持つ人 | その他のログイン利用者 | 未ログイン |
|---|---|---|---|---|---|---|---|---|---|
| 招待の作成・再送・取消・同意の確認の依頼 | ○ | ○ | ○ | × | × | × | × | × | × |
| 招待一覧（公演） | ○ | ○ | ○ | × | × | × | × | × | × |
| 自分宛ての招待一覧・内容確認（lookup） | 本人宛てのみ | 同左 | 同左 | 同左 | 同左 | ○ | 本人宛てのみ | 本人宛てのみ（なければ空） | × |
| 登録画面の初期値（registration-hint） | — | — | — | — | — | — | — | — | メールだけ（有効なトークン） |
| 承諾 | 本人宛てのみ | 同左 | 同左 | 同左 | 同左 | ○（確認済みアドレス・ACTIVE アカウント・受付中の招待） | 本人宛てのみ | 本人宛てのみ | × |
| 辞退 | 本人宛てのみ | 同左 | 同左 | 同左 | 同左 | ○（公演終了・招待者の権限喪失の後も可） | 本人宛てのみ | 本人宛てのみ | × |
| 参加者一覧（メールあり） | ○ | ○（公演の終了後は×） | ○（公演の終了後は×） | × | × | × | × | × | × |
| 参加者一覧（メールなし） | ○ | ○ | ○ | REHEARSAL_MANAGER のみ | × | × | × | × | × |
| 役割・備考・公演側の氏名の変更 | ○ | ○ | ○ | × | × | × | × | × | × |
| 管理者による削除（CANCELLED）・INACTIVE | ○ | ○ | ○ | × | × | × | × | × | × |
| 本人による辞退（WITHDRAWN） | ×（引き継ぎが先） | 本人のみ | 本人のみ | 本人のみ | 本人のみ | — | 本人のみ（ACTIVE 参加者なら） | × | × |
| 参加申請の承認・却下 | ○ | ○ | ○ | × | × | × | × | × | × |
| NAME_ONLY の追加 | ○ | ○ | ○ | × | × | × | × | × | × |
| 人物ID・団体IDでの直接追加 | × | × | × | × | × | × | × | × | × |
| 担当者の任命・再有効化（対象は同意が確認できる ACTIVE な PERSON 参加者のみ） | ○（全役割） | ○（4役割） | × | × | × | × | × | × | × |
| 担当者一覧（有効・無効の区別） | ○ | ○ | × | × | × | × | × | × | × |
| メールからの人物検索・人物IDからの氏名取得 | × | × | × | × | × | × | × | × | × |
| 監査ログの閲覧 | ×（APIなし） | × | × | × | × | × | × | × | × |

- 管理者の権限は、既存の `canManageParticipants`・`canReadParticipants`・`canManageProductionDelegateRole` を使う。担当者の有効性の判定（§4.6）が、これらの内部に加わる。
- **PrimaryManager は、担当者の有効性の判定とは独立して、すべての操作ができる**（既存どおり）。
- 「無効な担当者の行だけを持つ人」は、担当者としての公演の閲覧（`canReadProduction`）や管理の操作ができない。ACTIVE な PERSON 参加者であれば、参加者としての閲覧（`isProductionMember` による稽古の閲覧など）は、同意の有無にかかわらずこれまでどおりできる。
- 団体の OWNER・ORGANIZATION_MANAGER であることだけでは、公演の参加者を管理できない（既存どおり、PrimaryManager または有効な担当者である必要がある）。

## 9. 個人情報の取得・表示ルールと不正利用対策

### 9.1 個人情報の取得・表示

| 時点 | 管理者が見られるもの | 招待された本人が見られるもの |
|---|---|---|
| 招待前 | 自分で入力した氏名・メールだけ。アカウントの有無・人物の氏名は分からない（F5の廃止と、招待作成の処理・応答の統一） | — |
| 招待中 | 招待の状態（承諾待ち・期限切れ・無効）、入力した氏名・メール・役割・備考、送信の状態と回数 | ログイン後、本人宛てと確認できた場合だけ：公演名・団体名・役割・招待者の公演側の表示名・期限（D7）。未ログインでは、登録画面の初期値のメールアドレスだけ |
| 辞退後 | 「辞退」という状態と、再招待できる日時だけ（辞退した人物のID・氏名・アカウントの有無は分からない） | — |
| 取消・期限切れ・無効化の後 | 状態と理由だけ | 承諾できない招待は「辞退」だけ可能（U7）。期限切れ・処理済みは、本人宛てなら状態を表示する |
| 承諾後（ACTIVE） | 公演側の氏名（`display_name`）、人物の姓・名、通知先のメール（`PersonEmailResolver`）、同意の状態。承諾画面で、共有されることを本人に明示する | 自分の参加公演 |
| 本人の辞退・管理者の削除の後 | `display_name` と状態だけ（姓・名・メールは返さない） | — |
| 公演の終了後 | §7.3（メールは PrimaryManager だけ。U3） | 自分の参加公演（履歴） |

- 他の公演・団体の管理者は、この公演の招待・参加者情報を取得できない（各APIで公演単位の権限を確認する。既存の構造を維持する）。
- 人物の情報（姓・名・メール）は、権限を確認する専用のAPI（参加者一覧・担当者一覧・`/me`）からだけ取得する。任意の人物IDやメールアドレスを指定して、他人の情報を取得できるAPIは置かない（§13）。
- 未確認のアドレスは、本人の判定にも、検索にも使わない（F6・F5）。
- 承諾前の招待者への通知に、本人の氏名やアカウント情報は入れない。
- ログ（`error_log`）にメールアドレスを出力している箇所（`ResolveParticipantInvitationUseCase`）は、削除によりなくなる。新しいコードでは、IDだけを出力する。

### 9.2 招待APIの不正利用対策

**レート制限**（【確定】U4：提案の値を基本として採用する）

| 対象 | 単位 | 上限 | 超えた場合 |
|---|---|---|---|
| 招待の作成・再送 | 操作する人物ごと | 1時間に30件 | 429 `stageart_rate_limited`（`retry_after` 付き） |
| 招待の作成・再送 | 公演ごと | 1日に100件 | 同上 |
| `registration-hint`・`lookup` | 接続元IPごと | 10分に20回 | 同上 |
| トークンの照合の失敗（`link_invalid`） | 接続元IPごと | 10分に10回 | 同上（以後は一致するトークンでも429） |
| `accept`・`reject` の失敗（403・404） | 人物ごと | 10分に10回 | 同上 |

- 現在、プラグインにレート制限の仕組みはない（§1.7）。新しく作る。
- **保存先は、DBの表とする**【推奨】（`stageart_rate_limit_buckets`：キーのハッシュ・時間枠・回数。複数のPHPプロセスで正しく数えるため。transient はオブジェクトキャッシュがない環境で `wp_options` に書き込み続けるため採用しない）。スキーマ1.49.0に含める。
- **接続元IPの判定**：ConoHa などのリバースプロキシの環境では、`X-Forwarded-For` などのヘッダーは利用者が偽装できる。**信頼するプロキシのアドレスを設定で明示し、そのプロキシから来た接続の場合だけヘッダーを使う**。設定がなければ `REMOTE_ADDR` だけを使う。本番の構成（プロキシの有無とアドレス）は、実装時に読み取りだけで確認し、ユーザーの承認を得て設定する（§16.2 U4）。
- 送信の上限（招待1件あたり5回、同じ公演・メールで30日間に6回）と辞退後30日の制限は、レート制限とは別に、業務上の制限として常に適用する。

**トークンの扱い**

- トークンは、ログ（`error_log`・アクセスログ・例外メッセージ・エラー報告）と監査ログに記録しない。新しいコードで、トークン・メールアドレスを含む例外メッセージを作らない。Frontend でも、トークンを `console` に出力しない。
- APIへは、トークンをクエリではなく、POSTの本文で送る（`lookup`・`registration-hint`）。
- **招待メールのリンクでは、トークンをURLのフラグメント（`/participant-invitations/open#token=...`）に入れる**（【確定】U5の方針）。フラグメントはサーバーに送られないため、Webサーバーのアクセスログに残らない。
  - Expo Router の Web とネイティブのディープリンクの両方で、フラグメントを読めること、読んだ後に消せることを、実装の最初に検証する。
  - **フラグメントを安全に扱えない場合は、安易にクエリ文字列に切り替えず、設計を再検討する**（§16.2 U5）。
- 画面がトークンを読んだら、すぐに `router.replace` などで、URLと閲覧履歴からトークンを取り除く。
- トークンは、画面の状態（メモリ）と、ログイン後に戻るための一時保存（Webでは `sessionStorage`、ネイティブでは `SecureStore`）にだけ置く。`return_to` などのURLに入れない。招待の処理が終わったら一時保存から消す。
- 古いメールのリンク（`/register?token=...`）は、クエリにトークンが入ったままのため、Frontendを配信するサーバーのアクセスログに残りうる。古いリンクの期限（24時間）が過ぎれば無効になる。画面は、開いた直後にURLからトークンを取り除く。

**画面とHTTPヘッダー**

- 招待のリンク画面・招待確認画面・登録画面では、第三者（解析・広告など）へ情報を送るスクリプトを読み込まない（現行の Frontend には、外部のエラー報告・解析サービスは見つからない。§1.7）。
- 招待のリンク画面・招待確認画面（Web）に `Referrer-Policy: no-referrer`（またはmetaタグ）を設定し、外部リンクへ移動したときにURLが送られないようにする。
- `lookup`・`registration-hint`・`accept`・`reject`・`GET /me/participant-invitations` の応答に `Cache-Control: no-store` を付ける。

**照合に失敗したときの応答**

- 一致しないトークン、本人宛てでない処理済みの招待は、すべて同じ404 `link_invalid` を返す。招待先の存在・アカウントの有無・招待の状態を推測できる情報を返さない。
- `registration-hint` は、有効なトークンにだけメールアドレスを返す。それ以外は同じ404。
- 本人宛てでない PENDING の招待に `lookup` した場合だけ、403 `not_for_you` を返す。リンクを持っている人に「別のアドレスでログインし直す」ことを案内するために必要で、アカウントの有無は含まない。

**アカウントの有無の推測の防止**（【確定】第3版、U8）

- **招待の作成では、宛先の人物の検索も、アプリ内通知の作成もしない。**宛先が登録済みかどうかで、処理の内容・副作用が変わらない。
- 招待メールは、登録済み・未登録にかかわらず、同じ方式・同じ文面で送る。
- 応答は、宛先のアカウントの有無にかかわらず、同じ形・同じ状態コードにする。
- 登録済みの人は、ログイン後に `GET /me/participant-invitations`（ホームの件数）で招待を確認する。これは本人のリクエストに対する処理で、管理者の操作には影響しない。
- 承諾・辞退の時点の招待者への通知は、従来どおり送る（招待者は自分の招待の結果を知る必要があり、承諾・辞退は本人の意思で行われるため、アカウントの有無の推測には当たらない）。

## 10. Frontend画面遷移

| 画面 | 変更 |
|---|---|
| 公演のメンバー管理（`productions/[id]/participants.tsx`） | 「メンバーを追加」を「メンバーを招待」に変える。結果の文言は「招待メールを送信しました。本人が承諾するとメンバーに追加されます」と「承諾待ちの招待に再送しました」の2種類にする（「メンバーを追加しました」をなくす）。「招待中のメンバー」の表に、承諾待ち・期限切れ・無効（公演終了／招待者の権限なし）・辞退（再招待できる日時）・取消の状態、送信の状態（送信中・送信済み・送信失敗・送信結果不明）、この招待の再送の残り回数、30日間の残り送信回数、再送・取消の操作を表示する。参加者の一覧に「同意未確認（旧方式）」の表示と、「同意の確認を依頼」の操作を加える（§4.5）。更新時に `status: 'ACTIVE'` を送らないようにする。PERSON・ORGANIZATION を直接追加する操作をなくす（D10） |
| 送信の上限に達したとき | 作成・再送のボタンを無効にし、「このメールアドレスへの送信は30日間で6回までです。{available_at}以降に送信できます」と表示する。招待1件の上限では「この招待の再送は5回までです。取り消して新しく招待しても、30日間の送信回数は数え直されません」と表示する |
| 送信の状態の表示 | 送信済み：「送信しました（相手に届いたかどうかは確認できません）」。送信失敗：「送信に失敗しました。再送してください（再送も送信回数に数えます）」。送信結果不明：「送信の結果を確認できませんでした。メールが届いていない場合は再送してください」 |
| 担当者（`productions/[id]/delegates.tsx`） | 人物IDの自由入力欄を削除し、同意が確認できる ACTIVE な PERSON 参加者の候補からの選択だけにする。一覧を「有効な担当者」と「無効な担当者」に分け、無効な理由（同意未確認・メンバーではない・解除済み）と、次の操作（同意の確認を依頼・招待）を表示する（§4.6） |
| 招待のリンク（新設：`/participant-invitations/open`） | URLのフラグメントからトークンを読み、すぐにURLから取り除く。ログイン済みなら `lookup` を呼んで招待確認画面へ。未ログインなら、公演名などを出さずに「ログイン」「登録」を選ぶ画面を表示する（§4.2） |
| 招待確認（新設：`(app)/participant-invitations/[id].tsx`） | 公演名・団体名・役割・招待者・期限・共有される情報の説明と、「参加する」「参加しない」のボタン（D7）。既に参加者なら、その旨を表示する（§4.5）。`can_accept=false` のときは「この招待は現在承諾できません」と表示し、「参加しない」だけを出す（U7）。宛先が違う場合（403）は、「招待を受け取ったアドレスでログインし直してください」と表示し、ログアウトして入り直す操作を出す。期限切れ（410）・処理済み（409）は、それぞれの文言を表示する |
| 自分宛ての招待（ホーム） | 承諾できる招待があれば、ホームに件数とリンクを表示する（新しい一覧画面は作らず、確認画面への導線だけにする）。招待作成時のアプリ内通知はないため、これが登録済みの人への主な導線になる |
| 登録（`register.tsx`） | 招待リンクから来た場合は、`registration-hint` で取得したメールアドレスだけを初期値にする（公演名・氏名の事前表示はやめる。D7）。登録が完了したら、自動では参加させず、招待確認画面に移動する。古いリンク（`/register?token=`）で開かれた場合は、URLからトークンを取り除き、同じ導線に乗せる |
| ログイン（既存） | 招待リンクから来た場合は、ログイン後に招待のリンクの画面へ戻る（`return_to`）。`return_to` は、アプリ内のパスだけを受け付けるよう検証する（R13 Medium の修正を同時に行う）。トークンは `return_to` に入れず、一時保存から読む（§9.2） |
| 参加公演（`participating-productions.tsx`） | 各公演に「参加をやめる」（`withdraw`）を追加し、確認ダイアログを表示する。担当者を兼ねている場合は「担当者の役割も解除されます」と書く（D9）。PrimaryManager の公演には表示しない（引き継ぎの案内を出す） |
| 公演の画面（担当者本人） | 自分の担当者権限が無効な場合は、その理由（同意未確認など）を表示する【推奨】（§4.6） |
| アプリ内通知（`my-notifications.tsx`） | 承諾・辞退・参加辞退・招待の無効化の通知から、公演のメンバー管理へ移動する。招待作成の通知はない |
| 招待メール | リンク先を `/participant-invitations/open#token=...`（§9.2、U5）に変える。本文に「承諾するまで参加者にはなりません」「有効期限は7日間です」「このメールが複数届いた場合は、最も新しいメールのリンクを使ってください」と書く（§11.3）。登録済みかどうかで文面を変えない |
| 無効なリンク（404 `link_invalid`） | 「このリンクは無効です。最新の招待メールのリンクを開くか、ログインしてホームから招待を確認してください」と表示する（§11.3） |

団体メンバー管理（`organizations/[id]/members.tsx`・`invite.tsx`）とは画面を分けたままにする。公演の参加者は、団体のメンバーかどうかにかかわらず、公演ごとに招待する。団体招待の画面と動作は、R14-Bでは変更しない（D1）。

## 11. 通知仕様

### 11.1 通知の種類

既存の `NotificationDispatcherInterface` を使う（アプリ内とメール）。type は既存の命名（小文字のスネークケース）に合わせる。

**招待の作成・再送では、アプリ内通知を送らない**（第3版、§9.2）。招待先へは招待メールだけを送る。

| type | 宛先 | 契機 | message（例） | target |
|---|---|---|---|---|
| `participant_invitation_accepted` | 招待者 | 承諾 | 「{招待時の氏名}さんが『{公演名}』への招待を承諾しました」 | `PRODUCTION`／公演ID |
| `participant_invitation_rejected` | 招待者（権限がなければ PrimaryManager） | 辞退 | 「{招待時の氏名}さんが『{公演名}』への招待を辞退しました」。辞退の時点で招待者の権限喪失を初めて検出した場合は、「（招待者の権限がなくなっていたため、この招待は無効になっていました）」を加え、§11.5 の通知を兼ねる | 同上 |
| `participant_invitation_closed` | PrimaryManager（U10） | 承諾の時点での招待者の権限喪失（INVITER_REVOKED）の初めての検出（§11.5） | 「{招待時の氏名}さん宛ての招待は、招待者の権限がなくなったため無効になりました。必要なら改めて招待してください」 | 同上 |
| `participant_withdrawn` | PrimaryManager と、有効な PARTICIPANT_MANAGER | 本人による辞退 | 「{公演側の氏名}さんが『{公演名}』の参加をやめました」 | 同上 |

- 招待メール（未登録者を含む全員宛て）は、既存の `ParticipantInvitationMailer` を使い、文面を1種類にする。
- 通知の文面には、管理者が入力した氏名（`invited_name`）を使う。本人の氏名・メールを、承諾前に管理者側へ出さない。
- 承諾の通知は、D3により承諾が成立するのは招待者に権限がある場合だけなので、常に招待者に送る。
- 公演の終了（PRODUCTION_CLOSED）で招待が無効になった場合は、通知しない（管理者は公演の状態を知っているため）。

### 11.2 送信の順序

- **招待・承諾・辞退・参加辞退のDB更新を、先にコミットする。**メールの送信とアプリ内通知の作成は、コミットの後に行う。
- 通知の失敗によって、招待や承諾のDB処理をロールバックしない。APIの応答も成功のままにする。
- 招待の作成・再送の場合も、招待の行（新しいトークンのハッシュ・期限・`resend_count`）と送信の記録（PENDING）をコミットしてから、メールを送る。メールの送信が失敗しても、招待は作られたまま残る。

### 11.3 送信の状態と送信失敗の扱い（第3版で変更）

**流れ**

1. 作成・再送のAPIが受け付けたら、トランザクションの中で（公演 → 招待の順にロック、§5.4）、新しいトークンを作って招待の `token_hash` を差し替え、送信の記録（§6.2）を `status=PENDING`、新しい `id`（送信の試行の識別子）で追加し、招待の `current_send_id` をその `id` にする。`resend_count`（再送の場合）を増やす。ここで回数が確定する。**`current_send_id` とトークンは、このロックの中でだけ、同時に更新する。**トークンの平文は、このリクエストのメモリにだけ置く。
2. コミットする。
3. **送信の直前の確認**（第4版）：招待の `current_send_id` をDBから読み直す（ロックは取らない）。自分の試行の `id` と違えば、より新しい試行が受け付けられているため、メールを送らず、送信の記録を `SUPERSEDED` にして終わる（手順4の記録だけを行う）。自分の試行のトークンは、既に無効になっているため。
4. 一致すれば、メールを送る（`StageArtMail::send()`）。
5. 結果を、短い別のトランザクションで記録する。
   - 送信の記録：`UPDATE ... SET status = 'SENT' または 'FAILED', completed_at = 現在 WHERE id = {試行のID} AND status = 'PENDING'`。**自分の試行の行だけ**を、PENDING のときだけ更新する。
   - 失敗の場合は、監査ログに `SEND_FAILED`（`detail` に試行のID）を記録する。
   - 招待の表示用の状態は、`current_send_id` が指す試行の状態から導く。古い試行の結果が後から届いても、`current_send_id` は新しい試行を指しているため、表示の状態は上書きされない。
6. 5の記録ができなかった場合（プロセスの停止など）は、送信の記録が PENDING のまま残り、10分後から「結果不明（UNKNOWN）」として表示される。

**再送の競合**（第4版）

同じ招待への再送が重なった場合（管理者が続けて押した、2人の管理者が同時に押したなど）の扱い。

- 受け付けは公演の行ロックで直列化され、後の試行が `current_send_id` とトークンを上書きする。回数は、どちらの試行も1回ずつ数える（計上のルールは変えない）。
- 先の試行は、手順3の確認で自分が最新でないことを知り、メールを送らない（SUPERSEDED）。古いトークンのメールが、新しいメールより後に届くことを、多くの場合で防げる。
- **完全には防げない競合**：先の試行が手順3の確認を通った直後に、後の試行が受け付けられた場合は、先の試行も古いトークンのメールを送る。この場合、受信者には2通のメールが届き、先のメールのリンク（古いトークン）は無効になっている。メールの配送の順序は保証されないため、古いメールが後に届くこともある。確認と送信の間をロックで守ることはしない（送信の間、公演の行をロックし続けることになり、他の操作を止めるため）。
- **利用者への案内**
  - 招待メールの本文に「このメールが複数届いた場合は、最も新しいメールのリンクを使ってください」と書く。
  - 無効なリンクを開いた画面（404 `link_invalid`）に「このリンクは無効です。最新の招待メールのリンクを開くか、ログインしてホームから招待を確認してください」と表示する。登録済みの人は、トークンなしでホームから招待を確認できる。
  - 管理者の画面では、送信中（PENDING）の招待の再送ボタンを無効にし、続けて押すことを防ぐ。

**回数の扱い**

- 回数は、手順1で受け付けた時点で確定する。`wp_mail()` の成功・失敗や、SUPERSEDED によって、回数を戻さない。
- 送信の状態（PENDING・SENT・FAILED・SUPERSEDED・UNKNOWN）は、回数と別に管理する。招待一覧に表示するのは `current_send_id` が指す最新の試行の状態なので、SUPERSEDED が表示されることは通常ない。

**管理者への表示と再送**

- 招待一覧に、最新の送信の状態を表示する（§10）。
- 失敗・結果不明の場合は、管理者が再送できる。再送も1回として数える（送信の上限の範囲内）。上限に達した場合は、`available_at` を表示する。
- `wp_mail()` が true を返しても、相手に届いたことは保証されない（メールサーバーが受け付けただけ）。「送信済み」は配達の完了を意味しないことを、画面に明記する。

**その他の通知**

- アプリ内通知・通知メール（`NotificationDispatcher`）の失敗は、既存どおりログにだけ記録する（トークン・メールアドレスはログに出さない）。招待メールと違い、通知が届かなくても、招待者は招待一覧で結果を確認できる。

### 11.4 二重送信と状態の二重更新の防止

- 承諾・辞退・取消・参加辞退は冪等にする。2回目の呼び出しは、状態を変えず、通知も監査ログも追加しない（通知は、実際に状態が変わった1回だけ送る）。
- 再送は、毎回トークンを差し替えるため、古いメールのリンクは無効になる。同じ再送のリクエストが二重に届いた場合は、公演の行ロックで直列化され、2回の送信として数えられる。先の試行は、多くの場合 SUPERSEDED になってメールを送らない（§11.3 の再送の競合）。状態の矛盾は起きない。Frontend は、再送のボタンを送信中は無効にする。
- 非同期の仕組み（キュー）は導入しない。現在の通知基盤は同期で送る方式で、WP-Cron は稽古のリマインダーにしか使われていない（§1.6）。存在しない仕組みを前提にしない。

### 11.5 招待者の権限喪失の PrimaryManager への通知（【確定】U10、第5版）

**通知の契機**

- 招待者の権限喪失（INVITER_REVOKED）を**最初に検出した書き込み処理**で、PrimaryManager へ**一度だけ**通知する。
- 対象の書き込み処理は、本人の**承諾**と**辞退**（§7.2）。承諾では `participant_invitation_closed`、辞退では `participant_invitation_rejected`（権限喪失の説明を加えたもの）を送り、どちらも1通で済ませる。
- **読み取り処理では通知しない**：`GET /me/participant-invitations`・`lookup`・管理者の招待一覧は、`effective_status=INVITER_REVOKED` を導いて返すだけで、DBに書き込まず、通知もしない。
- 管理者の再送・取消でも通知しない（再送は409を返すだけで状態を変えない。操作した管理者は応答で知る。取消は管理者の意思による `MANAGER` の無効化）。
- **公演の終了（PRODUCTION_CLOSED）による無効化は通知しない**（管理者は公演の状態を知っているため）。

**二重通知の防止**

1. 承諾・辞退の処理は、§5.4 の順に公演 → 招待をロックする。
2. 招待者の権限喪失を検出し、招待の `revocation_notified_at` が NULL の場合だけ、同じトランザクションで `revocation_notified_at` を現在の日時にし、「このトランザクションで通知する」と覚える。NULL でなければ、既に通知が決まっているため、通知しない。
3. コミットした後に、手順2で覚えた場合だけ通知を送る。
- 招待の行ロックで直列化されるため、承諾と辞退が同時に来ても、`revocation_notified_at` を記録できるのは1つの処理だけで、通知は1回になる。
- 例：承諾で権限喪失を検出して `CANCELLED(INVITER_REVOKED)` になり通知した後、本人が辞退した場合は、権限喪失の通知は送らない（`revocation_notified_at` が記録済み）。辞退の通知（`participant_invitation_rejected`、権限喪失の説明なし）は、辞退の事実を知らせるため通常どおり送る。
- 通知の送信が失敗しても、`revocation_notified_at` は戻さない（通知は既存どおり失敗をログに記録するだけ。§11.3）。再送はしない。

**監査ログ**

- 初めて検出したトランザクションで `INVITATION_CLOSED` を記録する（`detail`：`close_reason=INVITER_REVOKED`、検出した操作 `ACCEPT` または `REJECT`、`primary_manager_notified=true`）。
- 通知が決まらなかった場合（既に通知済み）は、`INVITATION_CLOSED` を重ねて記録しない。

## 12. 既存データとの互換性

| 既存データ | 扱い |
|---|---|
| 直接追加された ACTIVE な PERSON 参加者（過去の P1・P3・P4）を含む、既存のすべての参加者 | **変更しない**（D2）。`enrollment_source`・`consent_source`・`consented_at` は NULL のままで、「同意未確認（旧方式）」と表示する。同意済みとはみなさない。参加者としての機能はこれまでどおり。本人は参加公演の画面から辞退できる。管理者は「同意の確認を依頼」で再確認できる |
| 既存の担当者の行 | **行は変更しない。**参加者でない人物の行は `NOT_ACTIVE_PARTICIPANT`、同意未確認の参加者の行は `CONSENT_UNCONFIRMED` として、権限を有効にしない（§4.6）。PrimaryManager 以外の既存の担当者は、リリースの時点で全員が無効になる |
| PENDING の招待（R14-B以前に作成） | 承諾待ちとして扱う。期限（24時間）は既存の値のまま（D4）。古いメールのリンク（`/register?token=`）からも、確認画面への導線に乗せる。`resend_count` は0から数え、送信の記録もR14-B以降の送信から数える |
| CONSUMED の招待 | 終端のまま残す。一覧では「参加済み（旧方式）」と表示する |
| CANCELLED の招待 | `close_reason` は NULL（理由不明）のまま |
| ORGANIZATION の参加者 | 変更しない（D10）。新しい直接追加だけを止める |
| 参加申請中（PENDING）の行 | 変更しない。`enrollment_source` は NULL のまま。管理者が承認しても同意は記録しない（§4.5）。本人が招待を承諾すれば同意を記録する |
| 出欠・精算・会計の記録 | 変更しない |
| 監査ログ・送信の記録 | 1.49.0以前の操作は記録されない |
| 団体の招待・メンバー | R14-Bでは変更しない（D1、R14-C） |

既存データの確認用SQL（読み取りのみ。**本書の作成時点では、Dev・Productionとも未実行**）。

- **U9（照合順序）の実行許可**（ユーザー判断、第4版）：U9の確認のための読み取り専用のSELECTの実行が許可されている。Dev DB は許可、Production DB は最後の `information_schema.COLUMNS` の照合順序のSELECTだけ。SELECTだけを実行し、メールアドレスなどの実データは取得しない。UPDATE・INSERT・DELETE・DDL・設定変更・バックアップ・復元はしない。実装開始後の事前確認（§15の0）として行い、結果を記録して §6.4 との整合性を確認する。
- U9以外の目的のSELECT（既存の担当者の件数など、§16.3.1 で使うもの）は、Dev・Production とも、引き続き実行の前に承認が必要。Dev で U9 の照合順序のSELECT以外を実行してよいかの範囲は、実行の前にユーザーに確認する。

```sql
-- 承諾待ちの招待（R14-B導入時に残っているもの）
SELECT status, COUNT(*) FROM wp_stageart_participant_invitations GROUP BY status;
-- PERSON 参加者の件数（状態別）。R14-B以降は、すべて同意未確認として扱われる。
SELECT status, COUNT(*) FROM wp_stageart_participants WHERE subject_type = 'PERSON' GROUP BY status;
-- 既存の ACTIVE な担当者（PrimaryManager を除き、R14-B以降は全員が無効になる）
SELECT pr.status AS production_status, d.role, COUNT(*) FROM wp_stageart_production_delegates d
  JOIN wp_stageart_productions pr ON pr.id = d.production_id
 WHERE d.status = 'ACTIVE' AND d.person_id <> pr.primary_manager_person_id
 GROUP BY pr.status, d.role;
-- 上のうち、参加者でない人物の担当者（再招待と再任命が必要）
SELECT d.production_id, d.person_id, d.role FROM wp_stageart_production_delegates d
 WHERE d.status = 'ACTIVE' AND NOT EXISTS (
   SELECT 1 FROM wp_stageart_participants p
    WHERE p.production_id = d.production_id AND p.subject_type = 'PERSON'
      AND p.subject_id = d.person_id AND p.status = 'ACTIVE');
-- ORGANIZATION の参加者
SELECT production_id, subject_id, status FROM wp_stageart_participants WHERE subject_type = 'ORGANIZATION';
-- 大文字を含むメールアドレスの招待（正規化の影響の把握）
SELECT COUNT(*) FROM wp_stageart_participant_invitations WHERE BINARY email <> LOWER(email);
-- 照合順序の確認（§6.4、U9）
SELECT TABLE_NAME, COLUMN_NAME, COLLATION_NAME FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'email'
   AND TABLE_NAME LIKE 'wp_stageart_%';
-- Join Key の申請で UNIQUE キーに当たりうる行（U13の影響の把握：CANCELLED・REJECTED の PERSON 行）
SELECT status, COUNT(*) FROM wp_stageart_participants
 WHERE subject_type = 'PERSON' AND status IN ('CANCELLED', 'REJECTED') GROUP BY status;
```

テーブル名・列名は `Installer` で確認済み（`stageart_production_delegates` の `person_id`・`role`・`status`、`stageart_productions` の `primary_manager_person_id`）。接頭辞 `wp_` は環境に合わせる。

## 13. F5との統合方針（【確定】U6）

- **StageArt API はアプリ専用であり、外部の利用者との互換性を理由に、不要なAPIを残さない。**
- §1.5 の調査で、アプリ内（Backend の他の機能・Frontend の画面・テスト・文書・検証用スクリプト）に `/people` の2つのAPIの利用箇所がないことを確認した。2つのエンドポイントを削除する。
- **削除の対象**

| 区分 | 対象 |
|---|---|
| Backend | `Presentation/Rest/PersonRestController.php`（ルートの登録）、`Application/Person/SearchPersonByEmailUseCase.php`・`SearchPersonByEmailQuery.php`・`GetPersonByIdUseCase.php`・`GetPersonByIdQuery.php`、`Presentation/Plugin.php` の該当箇所（`use` 文 127・128・222行、生成・登録 632・827・1031行。`c6480c2` 時点） |
| Backend（残す） | `FindPersonByEmailUseCase`・`PersonSummaryResult`（招待の宛先判定に使う内部用） |
| Frontend | `mobile-rn/src/features/person/api.ts`（ファイルごと。他から import されていない）、`types/api.ts` の `PersonSummary` 型、`components/schedule-comment-list.tsx` のコメントの記述 |
| テスト | `plugin/tests/Application/Person/GetPersonByIdUseCaseTest.php` は、UseCase の削除に合わせて、ルートが存在しないことを確認するテスト（T23）に書き換える（仕様変更による書き換えで、テストを減らして通すものではない） |
| 文書 | `docs/08-API/` に `/people` の記述はないため、変更はない。本書と、R14-Bの確定版の文書に削除を記録する |

- **人物情報の取得の方針**：必要な人物の情報は、権限を確認する専用のAPIから取得する。
  - 自分の情報：`/me` 系のAPI（`useCurrentPerson` など、既存）
  - 公演の参加者の氏名・メール：`GET /productions/{id}/participants`（§7.3 の権限と範囲）
  - 担当者の氏名：`GET /productions/{id}/delegates`（PrimaryManager と代理人）
  - 任意の人物IDやメールアドレスを指定して、他人の情報を取得できるAPIは残さない。将来必要になった場合も、閲覧者と対象者の関係（同じ公演の参加者など）を確認する専用のAPIとして設計する。
- `FindPersonByEmailUseCase` は内部用に限る（本人宛ての招待の判定と検索だけに使う）。外部APIの応答には、その結果（アカウントの有無・人物ID・氏名）を一切出さない。招待の作成では使わない（§9.2）。
- 管理者が人物を特定できるのは、本人が承諾した後の参加者一覧だけになる。
- 団体側（`CreateOrganizationInvitationUseCase` の既存利用者分岐、`PARTICIPANT_ADDED` 相当の応答）にも同じ問題が残る。R14-Cで対応する（D1）。

## 14. テスト計画

### 14.1 PHPUnit（Application・Domain）

**招待・承諾・辞退**

| # | ケース | 期待 |
|---|---|---|
| T1 | 招待先の本人（確認済みアドレス、ACTIVE）が承諾する | ACTIVE の参加者が1件でき、招待は ACCEPTED（`invitee_person_id`・`participant_id`・`responded_at`）。`enrollment_source=INVITATION`・`consent_source=INVITATION`・`consented_at` が入る。招待者に通知される。監査ログ `ACCEPTED` |
| T2 | 別のアカウント（アドレスが異なる）が、IDやトークンを使って承諾する | 403。参加者は作られず、招待は PENDING のまま |
| T3 | 招待先と同じアドレスを未確認で登録したアカウントが承諾する | 403（F6と一致） |
| T4 | 招待先のアドレスが、確認済みの通知先メールとしてだけ一致する（Googleアカウント） | 承諾できる |
| T5 | アドレスが複数の人物に一致する | 403 |
| T6 | SUSPENDED・DISABLED のアカウントが承諾・辞退する | 403。招待は PENDING のまま |
| T7 | 本人が辞退する | 参加者は作られず、参加の権限も発生しない。招待は REJECTED。招待者に辞退の通知が届く |
| T8 | 取消済みの招待を承諾・辞退する | 409 |
| T9 | 期限切れの招待を承諾・辞退する | 410。再送後は承諾できる |
| T10 | 同じ公演・宛先・種別で2回招待する | 招待は1件で、再送になる（`resend_count=1`、送信の記録は2行）。承諾後も参加者は1件 |
| T11 | 同じ招待を2回承諾する（逐次・同時） | 2回目も200で同じ結果。参加者は1件（UNIQUE とロック）。通知・監査ログは1回だけ |
| T12 | 既に ACTIVE な参加者（既存データ）が承諾する | 新しい行は作られず、招待は ACCEPTED。`enrollment_source` は NULL のまま、`consent_source=INVITATION`・`consented_at` が記録される（`CONSENT_RECORDED`）。状態は ACTIVE のまま |
| T13 | CANCELLED・WITHDRAWN・REJECTED の行がある人が承諾する | 同じ行が ACTIVE に戻る（UNIQUE 違反にならない）。`enrollment_source` は変わらない |
| T14 | 参加申請中（PENDING）の人が承諾する | ACTIVE になり、`enrollment_source=JOIN_REQUEST` のまま、`consent_source=INVITATION`。監査ログの `detail` に `superseded_join_request=true` |
| T15 | 招待者が承諾前に権限を失った（D3） | 409 `closed`。招待は CANCELLED(INVITER_REVOKED)、参加者は作られない。PrimaryManager の権限で代わりに成立しない。PrimaryManager に `participant_invitation_closed` が通知される |
| T16 | 公演が COMPLETED／ARCHIVED／CANCELLED（D5） | 招待・再送は409。承諾も409で、招待は CANCELLED(PRODUCTION_CLOSED)。一覧の `effective_status` は PRODUCTION_CLOSED（承諾前でも） |
| T17 | 権限のない人が招待・再送・取消・一覧を実行する | 403 |
| T18 | 他の公演の管理者が、この公演の招待を取消・一覧する | 403 |
| T19 | `POST /productions/{id}/participants` に PERSON・ORGANIZATION の任意のIDを送る | 422。参加者は作られない（D10） |
| T20 | `PUT /participants/{id}` で CANCELLED・REJECTED・WITHDRAWN を ACTIVE にする | 422 |
| T21 | 担当者の任命で、参加者でない人物IDを送る | 422 `target_not_participant` |
| T22 | 既存利用者のアドレスで招待する | 応答は未登録者の場合と同じ形（`INVITATION_SENT`）。人物ID・氏名は含まない |
| T23 | `GET /people?email=`・`GET /people/{id}` | ルートが存在しない（404）。`PersonRestController` が登録されない |
| T24 | 参加者一覧を REHEARSAL_MANAGER が取得する | メールなし。CANCELLED などの行は含まない |
| T25 | 本人が参加をやめる | WITHDRAWN になり、管理者に通知される。他人の参加者IDでは403。PrimaryManager は409。出欠・会計の記録は変わらない |
| T26 | 招待リンクから登録する | メールは確認済みになり、参加者は作られない（招待は PENDING のまま） |
| T27 | 自己登録の後にメールを確認する／Googleでサインインする | 参加者は作られない（自動解決の廃止）。F6のテスト（確認前に招待を取得しない）も成り立つ。団体招待の自動解決は従来どおり動く（D1） |
| T28 | 自分宛ての招待一覧 | 確認済みアドレスに一致する PENDING・期限内のものだけを返す。他人宛ては含まない。項目は D7 の範囲だけ（氏名・備考・メールなし）。公演終了の招待は `can_accept=false`・`can_reject=true` |
| T29 | `lookup` を別のアカウントで開く | PENDING なら403で、内容を返さない。処理済みなら404 `link_invalid` |
| T30 | Migration 1.48.0 → 1.49.0 | 列とテーブルが追加される。既存の行は変化しない（`resend_count` の既定値0を除く）。2回実行しても同じ結果。新規インストールと一致する |

**有効期間・送信の上限・再招待（D4・D6・第3版）**

| # | ケース | 期待 |
|---|---|---|
| T31 | 新しい招待の期限 | 作成から7日 |
| T32 | 再送 | トークンが変わり、古いトークンでは `lookup`・`registration-hint` が404。期限は再送の時点から7日 |
| T33 | 5回再送した後に、6回目の再送をする | 429 `resend_limit`。送信は最大6回（初回＋再送5回） |
| T34 | 同じ宛先・種別の PENDING がある状態で、招待の作成（再送扱い）を繰り返す | T33と同じく上限で429 |
| T35 | 辞退から29日後に、同じ公演・同じメール（別の種別を含む）で招待する | 409 `recently_rejected`、`available_at` 付き |
| T36 | 辞退から30日後に招待する | 新しい招待ができる（30日間の送信上限の範囲内で）。辞退した招待の行は残る |
| T37 | 辞退した招待のメールを大文字に変えて招待する | 409（正規化で同じ宛先と判定） |
| T38 | 大文字を含むアドレスで招待され、小文字の確認済みアドレスを持つ本人が承諾する | 承諾できる |
| T39 | 既存の招待（24時間で作られた PENDING）の期限 | Migrationの後も変わらない。再送すると7日になる |
| T67 | 招待を作成・再送して計6回送った後、取り消して新しい招待を作る | 429 `send_limit`。`available_at` は最も古い送信から30日後。取消・期限切れ・辞退済みの招待の送信も数える |
| T68 | 種別（CAST・STAFF）を変えて、同じ公演・同じメールに送る | 送信の回数は合算される |
| T69 | 大文字小文字を変えたメールで送る | 同じ宛先として合算される（`email_key`） |
| T70 | 最も古い送信から30日が過ぎる | 再び送信できる |
| T71 | 別の公演から同じメールに送る | 公演ごとに数える（影響しない） |

**送信の状態（第3版）**

| # | ケース | 期待 |
|---|---|---|
| T60 | 招待メールの送信が失敗する（`StageArtMail::send` が false） | 招待は作られたまま、送信の記録は FAILED、監査ログ `SEND_FAILED`。応答は成功。送信の回数は戻らない |
| T61 | 送信が失敗した招待を再送する | 再送でき、回数に数える（`resend_count` と30日間の回数が増える） |
| T62 | 承諾後の通知が失敗する | 承諾はロールバックされない |
| T72 | 送信の結果を記録する前に処理が止まる（結果の記録をしない） | 送信の記録は PENDING のまま。10分後に `send_status=UNKNOWN`。回数は数えたまま |
| T73 | 古い試行の結果が、新しい再送の後に記録される | 古い試行の行だけが更新され、招待の `send_status` は新しい試行の状態のまま |
| T74 | 同じ試行の結果を2回記録する | 2回目は更新されない（PENDING のときだけ更新） |
| T94 | 同じ招待に再送を2回続けて受け付け、先の試行の送信の直前の確認を、後の試行の受け付けの後に行う | 先の試行はメールを送らず SUPERSEDED。後の試行だけが送られる。回数は2回分数える |
| T95 | 先の試行が送信の直前の確認を通った後に、後の試行を受け付ける | 2通送られる（防げない競合）。先のメールのトークンでは `lookup` が404。招待の表示は後の試行の状態 |

**email_key・保存期間（第4版）**

| # | ケース | 期待 |
|---|---|---|
| T96 | 同じメールアドレス（大文字小文字・前後の空白の違いを含む）から `email_key` を計算する | 同じ鍵なら、常に同じ値になる |
| T97 | 異なるメールアドレスから計算する | 異なる値になる（集計が混ざらない） |
| T98 | 鍵を変えて計算する | 同じアドレスでも値が変わる（鍵なしの SHA-256 とも一致しない） |
| T99 | 鍵のローテーション（旧い版を鍵の束に残したまま、現在の版を新しい版に切り替える） | 切り替えの前後の送信が、30日間の回数として合算される（複数回のローテーションは T104） |
| T100 | 鍵が設定されていない | 招待の作成・再送が500 `email_key_not_configured`。送信の記録は作られない。鍵の値はログに出ない |
| T101 | 保存期間による削除 | 2年を過ぎた監査ログと、90日を過ぎた送信記録（最新の試行を除く）だけが削除される。最新の試行・期間内の行・参加者・招待・出欠・会計の行は残る |
| T102 | 削除の処理の件数の上限 | 1回の実行で上限の件数までしか削除せず、残りは次回に削除される |
| T103 | 90日を過ぎた最新の試行がある招待の一覧 | 最新の送信状態が表示される |
| T104 | 30日以内に v1 → v2 → v3 と2回ローテーションし、各版で同じ公演・同じメールに送信する | v1・v2・v3 の送信回数が合算される（例：各2回なら6回で、7回目は429） |
| T105 | v1 の最後の記録が30日間の集計の対象から外れた後に、v1 を鍵の束から外す | 集計はエラーにならず、v2・v3 の記録だけで正しく数えられる。v1 の行は集計に使われず、§6.6 の期間で削除される |
| T106 | 30日以内に使われた版の鍵が、鍵の束にない | 招待の作成・再送が500 `email_key_not_configured`（少なく数えて送らない） |
| T107 | `STAGEART_EMAIL_KEY_CURRENT` の版の鍵がない | 招待の作成・再送が500。送信の記録は作られない |
| T108 | 現在の版で計算した値と、旧い版で計算した値 | 同じアドレスでも版ごとに値が異なり、版と `email_key` の組でだけ一致する |
| T109 | 鍵なしの SHA-256 で計算した値 | どの版の `email_key` とも一致せず、集計に数えられない |

**担当者（D9・第3版）**

| # | ケース | 期待 |
|---|---|---|
| T40 | 参加者でない人物の ACTIVE な担当者の行が残っている | その人物の `canManageParticipants`・`canReadProduction`・`hasProductionCapability` などがすべて false。行は削除・変更されない |
| T41 | 同上の人物が、担当者の権限を使うAPI（参加者一覧・招待の作成・稽古の作成・会計など）を呼ぶ | 403 |
| T42 | 同上の人物の、ダッシュボードと自分の公演一覧 | 担当者としては、その公演が含まれない |
| T43 | 同上の人物が招待を承諾して ACTIVE な参加者になる | 古い担当者の行は INACTIVE になり、権限は有効にならない。監査ログ `DELEGATE_DEACTIVATED`（`DORMANT_ON_ENROLLMENT`） |
| T44 | T43の後、管理者が改めて任命する | 同じ行が ACTIVE に戻り、権限が有効になる（新しい行は作られない） |
| T45 | 同意済みの ACTIVE 参加者で担当者の人が参加をやめる（WITHDRAWN） | 担当者の行がすべて INACTIVE になり、権限が止まる。行は削除されない |
| T46 | 管理者が担当者を兼ねる参加者を削除（CANCELLED）・INACTIVE にする | 担当者の権限が止まる |
| T47 | PrimaryManager（参加者でない・同意の記録なし）の権限 | §4.6 の変更の前後で変わらない |
| T48 | 他の正当な担当者（同意済みの ACTIVE 参加者）の権限 | 変わらない |
| T49 | 担当者一覧 | `effective` と `ineffective_reason`（INACTIVE・NOT_ACTIVE_PARTICIPANT・CONSENT_UNCONFIRMED）が正しい |
| T50 | 会計モジュールの通知先（`CoreProductionCapabilityDirectoryAdapter`） | 無効な担当者は含まれない |
| T75 | 同意未確認（既存データ）の ACTIVE 参加者の担当者 | 権限は無効（`CONSENT_UNCONFIRMED`）。参加者としての稽古の閲覧などはできる |
| T76 | T75の人が「同意の確認を依頼」の招待を承諾する | `consented_at` が記録され、ACTIVE な担当者の行は再任命なしに有効になる（U15） |
| T86 | T75の人が、ACTIVE と INACTIVE の担当者の行を持ったまま同意を記録する | ACTIVE の行だけが有効になる。INACTIVE の行は INACTIVE のまま（U15） |
| T87 | 担当者だった人が参加をやめ（WITHDRAWN）、後で招待を承諾して再び参加者になる | 担当者の行は INACTIVE のままで、権限は戻らない。管理者が任命すると有効になる（U15） |
| T88 | 管理者に削除（CANCELLED）された担当者が、招待を承諾して再び参加者になる | T87と同じ |
| T77 | 同意未確認の参加者を担当者に任命する | 422 `target_not_consented` |
| T78 | 参加申請（R14-B以降）を承認された人を任命する | 同意（JOIN_REQUEST）があるため任命でき、権限が有効になる |
| T79 | R14-B以前の参加申請を、R14-B以降に承認する | 同意は記録されず、任命は422 |

**参加申請との競合**

| # | ケース | 期待 |
|---|---|---|
| T51 | 招待の承諾の後に、管理者が同じ申請を承認・却下する | 409 `request_not_pending`。行は ACTIVE のまま |
| T52 | 管理者の承認の後に、本人が招待を承諾する | 200。招待は ACCEPTED、行は ACTIVE のまま。`consent_source` は INVITATION に更新される |
| T53 | 管理者の却下の後に、本人が招待を承諾する | 200。行は REJECTED → ACTIVE |
| T54 | 参加申請（R14-B以降）の承認 | `enrollment_source=JOIN_REQUEST`・`consent_source=JOIN_REQUEST`・`consented_at`（申請の日時）が入る。監査ログ `JOIN_APPROVED` |

**旧方式の参加者（D2）・個人情報（D7・D11・U3）・辞退（U7）**

| # | ケース | 期待 |
|---|---|---|
| T55 | 既存データの ACTIVE 参加者 | 一覧で `consent_status=UNCONFIRMED`。稽古・出欠・精算などの既存機能はこれまでどおり対象になる |
| T56 | 既存データの参加者が招待を辞退する | 招待は REJECTED、参加者の行は ACTIVE のまま |
| T57 | `lookup`・`GET /me/participant-invitations` の応答 | 公演名・団体名・役割・招待者の表示名・期限（と `can_accept` などの本人向けの項目）だけ。管理者が入力した氏名・備考・メールを含まない |
| T58 | `registration-hint` | 有効なトークンではメールだけを返す。無効・期限切れ・処理済みでは、すべて同じ404。`Cache-Control: no-store` |
| T59 | 公演の終了後の参加者一覧 | メールは PrimaryManager だけに返る。行は削除されない |
| T80 | 公演の終了後に、本人が `lookup` する | 200、`can_accept=false`・`can_reject=true`・`unavailable_reason=PRODUCTION_CLOSED` |
| T81 | 公演の終了後・招待者の権限喪失後に、本人が辞退する（PENDING のまま） | 200、招待は REJECTED。参加者は作られず、参加の権限も発生しない |
| T82 | 承諾を試みて `CANCELLED(PRODUCTION_CLOSED)`・`CANCELLED(INVITER_REVOKED)` になった招待を、期限内に辞退する | 200、招待は REJECTED。元の `close_reason`・`closed_at` は変わらない。参加者は作られない |
| T83 | 招待の取消を2回行う | 2回目も同じ結果（冪等） |
| T89 | `CANCELLED(MANAGER)` の招待を辞退する | 409 `not_pending` |
| T90 | 期限切れの `CANCELLED(PRODUCTION_CLOSED)` を辞退する | 410 |
| T91 | `CANCELLED(PRODUCTION_CLOSED／INVITER_REVOKED)` の招待を、再び承諾する | 409 `closed`。状態は変わらない |
| T92 | §5.1 の判定表の各行 | accept・reject・`/me`・`lookup`・管理者の一覧が、同じ判定（`effective_status`・`can_accept`・`can_reject`・一覧に出すか）を返す |
| T110 | PENDING の招待を、公演の終了の後に辞退する（初めての検出） | REJECTED、`close_reason=PRODUCTION_CLOSED`・`closed_at` が記録される。`closed_by_person_id` は NULL。PrimaryManager への権限喪失の通知はない |
| T111 | PENDING の招待を、招待者の権限喪失の後に辞退する（初めての検出） | REJECTED、`close_reason=INVITER_REVOKED`・`closed_at`・`revocation_notified_at` が記録される。PrimaryManager に、権限喪失の説明を加えた `participant_invitation_rejected` が1通だけ届く。監査ログ `INVITATION_CLOSED`（`primary_manager_notified=true`）と `REJECTED` |
| T112 | 承諾で権限喪失を検出した（通知済み）後に、本人が辞退する | `close_reason`・`closed_at`・`revocation_notified_at` は変わらない。権限喪失の通知は送られない（辞退の通知だけ）。`INVITATION_CLOSED` は重ねて記録されない |
| T113 | 権限喪失の招待に、承諾と辞退が同時に来る | どちらか一方だけが `revocation_notified_at` を記録し、権限喪失の通知は1回だけ |
| T114 | 権限喪失の招待を、本人が `lookup`・`/me` で開く／管理者が一覧で見る／管理者が再送する | `effective_status=INVITER_REVOKED` が返る。DBの `close_reason`・`revocation_notified_at` は変わらず、通知もない（再送は409） |
| T115 | 管理者の取消（`CANCELLED(MANAGER)`） | `close_reason=MANAGER`・`closed_at`・`closed_by_person_id` が記録される。本人の辞退は409 |
| T116 | 管理者の一覧の応答 | `status`・`effective_status`・`close_reason`・`closed_at` が §5.1 の組み合わせのとおりに返る（REJECTED で `close_reason` を持つ行を含む） |
| T93 | `CANCELLED(PRODUCTION_CLOSED)` の招待の `lookup`・`/me` | 期限内なら200で `can_accept=false`・`can_reject=true`。期限切れなら `lookup` は410、`/me` には出ない |

**アカウントの有無の推測・不正利用対策（第3版）**

| # | ケース | 期待 |
|---|---|---|
| T84 | 登録済み・未登録のアドレスで招待を作成する | どちらも、アプリ内通知が作られない。`FindPersonByEmailUseCase` が呼ばれない。応答の形・状態コードが同じ |
| T63 | 招待の作成をレート制限の上限まで行う | 上限を超えると429。超えるまでは通常どおり |
| T64 | 一致しないトークンで `lookup` を繰り返す | 上限を超えると429 |
| T65 | 各処理のログと監査ログ | トークン・メールアドレスを出力しない（ログの出力を差し替えて確認する） |
| T66 | `lookup`・`accept` などの応答ヘッダー | `Cache-Control: no-store` |
| T85 | 信頼するプロキシの設定がない状態で `X-Forwarded-For` を偽装する | レート制限は `REMOTE_ADDR` で数えられる |

### 14.2 Jest（画面）

- 招待の結果の文言が2種類で、「メンバーを追加しました」が出ない。
- 招待中の表に、承諾待ち・期限切れ・無効（公演終了／招待者の権限なし）・辞退（再招待できる日時）・取消、送信の状態（送信中・送信済み・送信失敗・結果不明）、残りの送信回数が表示される。
- 送信の上限に達したときに、ボタンが無効になり、`available_at` が表示される。
- 参加者の一覧に「同意未確認（旧方式）」と「同意の確認を依頼」が表示される。
- 招待のリンク画面：フラグメントからトークンを読み、URLから取り除く。未ログインでは公演名を出さず、ログイン・登録を選べる。ログイン済みなら確認画面へ進む。
- 招待確認画面：承諾・辞退・宛先違い（403）・期限切れ（410）・処理済み（409）・既に参加者・承諾できない招待（辞退だけ）の表示。
- 登録画面：招待リンクからの登録で、メールだけが初期値になる。登録後に招待確認画面へ移動する。古いリンク（`/register?token=`）でも同じ導線になり、URLからトークンが消える。
- ログイン：`return_to` にアプリ外のURLを渡しても、そこへ移動しない。
- 担当者画面：人物IDの入力欄がない。有効・無効の担当者が分けて表示され、無効の理由と次の操作が表示される。
- 参加公演：参加をやめる操作と、その確認（担当者の解除の説明）。PrimaryManager の公演には表示されない。
- 更新時に `status` を送らない。

### 14.3 実MySQL（隔離DB、REST）

- 承諾の同時実行（2接続で同じ招待を承諾し、参加者が1件になる）。
- 承諾と取消の競合（取消を確定すると承諾は409、ロールバックすると200）。
- 承諾と参加申請の承認・却下の競合（§4.7）。
- 再送の同時実行（送信の回数と30日間の上限が正しく数えられる。先の試行が SUPERSEDED になる）。
- 承諾を試みて CANCELLED になった招待の辞退と、同じ招待への承諾の再試行が同時に来た場合（どちらも §5.4 で直列化され、REJECTED か 409 になる）。
- §5.4 のロック順序で、参加者の更新・withdraw・担当者の任命を同時に行っても、デッドロックしない。
- 古い招待（R14-B以前の PENDING）からの承諾。
- 参加者でない担当者・同意未確認の担当者がいる公演で、その人物の担当者のAPIがすべて403になり、PrimaryManager は影響を受けない。
- Migration（アップグレードと新規インストールの一致、既存の行が変化しないこと、既存の検証DBのチェックサム）。
- レート制限の表で、複数のPHPプロセスから数え漏れがない。
- U13：CANCELLED・REJECTED の行がある人の Join Key の申請を再現する（§16.2）。

### 14.4 既存テストへの影響

次のテストは、仕様の変更に合わせて書き直す必要がある。削除・無効化はしない。

- PHPUnit：
  - `ParticipantInvitationUseCaseTest`（既存利用者の直接追加、期限24時間）
  - `ParticipantInvitationRegistrationConnectionTest`（自動解決）
  - `ParticipantUseCaseTest`（PERSON の直接作成、`PUT` の status）
  - `ProductionDelegateUseCaseTest`（任意の人物の任命）
  - `GetPersonByIdUseCaseTest`（ルートの削除の確認へ）
  - `Authentication` 系の招待解決の確認
  - `ProductionAuthorizationService`・ダッシュボード・公演一覧・会計・稽古などのテストのうち、担当者を準備データに使うもの（準備データに、同意済みの ACTIVE な参加者を加える）
  - 参加申請の作成・承認・却下のテスト（ロックの追加、`enrollment_source`）
- Jest：`web-production-participants-add-existing-person`、`-add-member-ui`、`-add-unregistered-person`、`-invitation-list`、`register-invitation-*`、`participating-productions-*`、担当者画面のテスト。

担当者の有効性の変更（§4.6、同意の条件を含む）は、担当者を準備データに使う多くのテストに影響する。実装の最初に、影響するテストを洗い出す。

## 15. 実装順序と影響範囲

PR #7上で、1項目を1コミットにする。実装を始めるかは、第3版の再レビューの後に判断する。

0. **事前の検証**（実装の前）
   - U5（Expo Router でのフラグメントの扱い）と U13（Join Key の申請の再現）を、隔離した環境で確認する。U5が成り立たない場合は、§9.2 の設計を再検討してから進む。
   - U9（照合順序）：許可された範囲（§12）で、Dev・Production の読み取り専用のSELECTを実行し、結果を記録する。`_ci` 以外なら、§6.4 を見直す。
1. **Domain**
   - 招待の状態（ACCEPTED／REJECTED）、`accept()`・`reject()`・`close(reason)`、再送の上限、期限7日、新しい列の属性
   - `ParticipantStatus::WITHDRAWN`、`Participant::withdraw()`・`reenroll()`・`recordConsent(source, at)`、`enrollmentSource`・`consentSource`・`consentedAt`
   - Domainテスト
2. **Schema 1.49.0**：列・インデックス・送信の記録・監査ログ・レート制限の表の追加。Repository（`findByIdForUpdate`、`findPendingForEmails`、`existsConsentedActivePersonParticipant`、辞退後30日・30日間の送信回数の集計）。`SchemaUpgradePathTest`。
3. **承諾・辞退・自分宛て一覧・lookup・registration-hint のAPI**
   - `InvitationRecipientVerifier`、`ParticipantEnrollmentService`、`ParticipantDelegateRoleReleaser`、`ParticipantEventRecorder`
   - 通知と監査ログ
   - 参加申請の作成・承認・却下へのロックと `enrollment_source`・同意の記録の追加
4. **直接追加の経路を閉じ、担当者の有効性を変える**
   - `POST /participants` の PERSON・ORGANIZATION
   - 招待作成の既存利用者分岐（と宛先の検索・アプリ内通知）の削除、公演の状態・30日・送信の上限（`InvitationSendService`）
   - 自動解決の削除（登録・確認・Google。団体招待の解決は残す）
   - `PUT` の status、担当者の任命の対象制限
   - **担当者の有効性の判定（§4.6、同意の条件を含む）**：`ProductionAuthorizationService`、ダッシュボード、公演一覧
   - 3と同時に出さないと、承諾の手段がないまま追加の手段だけがなくなるため、**3と4は同じリリースにする**。担当者の有効性の変更も、同意の確認の依頼・承諾・再任命の手段と同時に出す
5. **F5**：`/people` の2つのAPIと、Frontendの未使用のファイル・型を削除する（§13）。
6. **個人情報の絞り込み**：`ParticipantResult`（REHEARSAL_MANAGER、終了済みの行、公演の終了後。D11・U3）。
7. **本人による辞退**：API（担当者の役割の解除を含む）、参加公演の画面。
8. **不正利用対策・保存期間**：レート制限（信頼するプロキシの設定を含む）、`Cache-Control`、トークン・メールのログ出力の確認、`email_key` の鍵の設定（§6.7）、保存期間による削除の定期処理（§6.6）。
9. **Frontend**
   - メンバー管理の文言と状態表示、送信の状態と上限、同意未確認の表示と「同意の確認を依頼」
   - 招待のリンク画面（フラグメント）・招待確認画面、登録・ログインからの導線（`return_to` の検証）、ホームの件数
   - 担当者画面（有効・無効の区別と理由）
10. **文書**：`docs/08-API/ParticipantAPI.md`、`docs/21-MemberManagementScreen.md`、ドメインイベント文書、本書の確定版。
11. **検証**：PHPUnit・Jest・実MySQL。

**影響範囲（主なファイル）**

- Backend：
  - `Domain/Participant*`、`Domain/ParticipantInvitation/*`
  - `Application/Participant*`、`Application/ParticipantInvitation/*`、`Application/Person/*`、`Application/ProductionDelegate/*`
  - `Application/Production/ProductionAuthorizationService`、`Application/Production/ListProductionsUseCase`、`Application/Dashboard/GetMyDashboardUseCase`
  - `Application/Authentication/{RegisterWithEmail,VerifyEmail,AuthenticateWithGoogle}UseCase`
  - `Presentation/Rest/{Participant,ParticipantInvitation,Person,ProductionDelegate,ParticipationRequest}RestController`、`Presentation/Plugin.php`
  - `Infrastructure/WordPress/Persistence/WordPressParticipant*Repository`、`WordPressProductionDelegateRepository`、`Infrastructure/WordPress/Schema/{Installer,SchemaUpgrader}`、`WordPressParticipantInvitationMailer`
  - 送信の記録・レート制限（新規）
- Frontend：
  - `productions/[id]/participants.tsx`、`productions/[id]/delegates.tsx`、`register.tsx`、ログイン画面、`participating-productions.tsx`、`home.tsx`、`my-notifications.tsx`
  - 新しい招待のリンク画面・招待確認画面
  - `features/participantInvitation/*`、`features/participant/*`、`features/productionDelegate/*`、`features/person/api.ts`（削除）
  - `types/api.ts`、`utils/errorMessage.ts`
- 規模の目安：Backend 40〜50ファイル、Frontend 15〜22ファイル、テスト 45ファイル前後（第2版から、送信の記録・同意の条件の分を増やした）。

## 16. 判断事項・未解決事項・リスク

### 16.1 確定した判断

**ユーザー判断（D1〜D11、2026-11-16）**

| # | 事項 | 確定方針 | 反映した節 |
|---|---|---|---|
| D1 | 団体メンバーの招待 | R14-Cで本人同意の方式を導入する。公演参加者と団体メンバーの招待は別の機能として扱う。R14-Bでは団体招待の実装を変更せず、R14-Cまでの間は団体招待の既存の動作（既存利用者の即時追加・自動解決）が残る | §1.3、§7.1、§10、§13、§16.3 |
| D2 | 過去に同意なく追加された ACTIVE 参加者 | 既存データを維持する。一括で変更・削除しない。同意済みとみなさない。本人は辞退できる。管理者が同意未確認の参加者を認識できる。将来、本人への再確認ができる。既存の稽古・出欠・タイムテーブル・精算・実績集計に影響を与えない。一斉の再招待・無効化は対象外 | §4.5、§6.1、§7.1、§12 |
| D3 | 承諾時に招待者が権限を失っている | 招待を無効にする（`close_reason=INVITER_REVOKED`）。参加者は作らない。PrimaryManager が代わりに承諾を認める暗黙の処理はしない。再び招待する場合は、現在の権限を持つ管理者が新しい招待を作る | §4.3、§5.1、§5.3、§7.2 |
| D4 | 招待の有効期間 | 7日間。再送時は再送の時点から7日間に更新し、古いトークンは無効にする。既存の招待の期限はMigrationで変更しない | §4.1、§5.3、§6.1、§6.5 |
| D5 | 公演の終了・中止時の未処理の招待 | 承諾時に判定する。COMPLETED／ARCHIVED／CANCELLED なら承諾を拒否し、`close_reason=PRODUCTION_CLOSED`。一覧では無効と表示する。公演の状態の変更時に一括で更新する処理は追加しない。新しい招待・再送も拒否する | §4.3、§5.1、§5.3、§7.1 |
| D6 | 再送・再招待の上限 | 初回の送信は含めず、再送は最大5回（初回を含めて最大6回）。再送時はトークンを更新し、期限を7日間延長する。6回目の再送は429。辞退後30日間は、同じ公演・同じメールアドレスへの新しい招待を拒否し、30日を過ぎれば作れる。辞退の履歴は削除しない。メールアドレスの正規化を統一する | §5.3、§6.1、§6.4 |
| D7 | 招待先に表示する情報 | 公演名・団体名・役割・招待者の公演側の表示名・期限と、共有される情報の説明だけ。管理者が入力した氏名・備考・内部メモ、不要な個人情報は承諾前に表示しない。未ログインの登録画面でメールアドレスを初期値にすることは許容するが、トークンだけで招待の詳細を返さない | §4.2、§7.2、§9.1、§10 |
| D8 | 監査ログ | 導入する。追記専用。氏名・メールアドレスを保存しない。操作した人・対象・種別・日時を記録する。閲覧権限を限定する。保持期間は将来変更できるようにし、現時点では無期限保持も確定しない | §6.3 |
| D9 | 本人の辞退時の担当者の権限 | 本人が WITHDRAWN になった時点で、担当者としての有効な権限も止める。担当者の行は削除しない。PrimaryManager 本人は通常の辞退ができず、先に管理者の権限を引き継ぐ | §4.6、§7.2、§10 |
| D10 | ORGANIZATION の参加者 | 当面、新しい追加を禁止する（直接追加は拒否、画面にも操作を出さない）。既存のデータは変更・削除しない。将来、団体の OWNER による承諾の方式を別に設計する。団体のメンバーであることを理由に、公演の参加者として扱わない | §4.4、§7.1、§10、§12 |
| D11 | 公演の終了後の参加者情報 | 保持する（削除しない）。精算・実績集計・活動履歴から参照できる状態を維持する。保持と閲覧権限を分け、終了後のメールアドレスなどの閲覧は必要最小限にする | §7.3、§9.1 |

**第3版で確定した事項（ユーザー回答・第2版レビューの指示）**

| 事項 | 確定方針 | 反映した節 |
|---|---|---|
| U6 外部API | StageArt API はアプリ専用。外部の利用者との互換性を理由に、不要なAPIを残さない。`/people` の2つのAPIを削除する | 冒頭、§1.5、§7、§13 |
| 登録経路と同意の分離 | `enrollment_source`（最初の経路、変更しない）、`consent_source`・`consented_at`（本人の同意）。既存データは同意済みとみなさない | §4.5、§6.1 |
| 担当者と本人の同意 | 有効な担当者は、行が ACTIVE・ACTIVE な PERSON 参加者・`consented_at IS NOT NULL` のすべてを満たす人。PrimaryManager は別扱い | §4.6、§8 |
| 招待をまたいだ送信の上限 | 同じ公演・同じメールアドレスで、初回を含めて直近30日間に6回。取消して作り直しても数え直さない。正規化したアドレスで集計する | §5.3、§6.2 |
| 招待作成時のアプリ内通知の廃止 | 招待作成では、宛先の検索もアプリ内通知もしない。招待メールは誰にでも同じ方式で送る。承諾・辞退の通知は維持する | §4.1、§9.2、§11 |
| 送信の数え方 | APIが受け付けた時点で数え、`wp_mail()` の結果で戻さない。送信の状態は試行ごとの識別子で管理し、古い結果で新しい状態を上書きしない | §6.2、§11.3 |

**第4版で確定した事項（ユーザー判断・第3版レビューの指示）**

| 事項 | 確定方針 | 反映した節 |
|---|---|---|
| U15 同意後の担当者権限 | 同意未確認の ACTIVE 参加者に付いている ACTIVE な担当者の行は、同意の記録で再任命なしに有効になる。INACTIVE の行は自動で戻さない。参加者でなかった人物が承諾した場合は、既存の担当者の行を INACTIVE にして改めて任命する。WITHDRAWN・CANCELLED などから再参加した場合も、以前の担当者の権限は自動で戻さない。PrimaryManager は別扱い | §4.6 |
| U9 照合順序の確認 | Dev は許可、Production は `information_schema.COLUMNS` の照合順序のSELECTだけ許可。SELECTだけで実データを取得しない。更新・DDL・設定変更・バックアップ・復元は禁止。実装開始後の事前確認として行い、本書の改訂ではDBに接続しない | §6.4、§12、§15 |
| U12 保存期間 | 監査ログは2年、送信記録は原則90日（最新の試行は保持）、同意の情報は参加者・公演の履歴と同じ期間。監査ログの `detail` にメール・氏名・トークンを保存しない。期間を限定した削除で、参加者・会計の履歴は削除しない | §6.2、§6.3、§6.6 |
| 辞退の状態遷移 | `CANCELLED(PRODUCTION_CLOSED／INVITER_REVOKED)` から本人の辞退を認め、`CANCELLED(MANAGER)` からは認めない。判定を1つの表にまとめる | §4.3、§5.1、§7.2 |
| email_key | サーバー側の秘密鍵（DBに保存しない）による HMAC-SHA-256 | §6.2、§6.7 |
| 再送の競合 | 送信の直前に最新の試行かを確認し、古い試行のメールを送らない。防げない競合は案内で補う。回数の計上ルールは変えない | §11.3 |
| 既存担当者の移行 | リリース前・リリース後の運用手順 | §16.3.1 |

**第5版で確定した事項（ユーザー判断・第4版レビューの指示）**

| 事項 | 確定方針 | 反映した節 |
|---|---|---|
| U10 PrimaryManager への通知 | 招待者の権限喪失を最初に検出した書き込み処理（承諾・辞退）で、一度だけ通知する。読み取り処理・管理者の再送・取消、公演の終了による無効化では通知しない。`revocation_notified_at` で二重通知を防ぐ | §11.5 |
| `close_reason` の意味 | `status`＝現在の状態、`close_reason`・`closed_at`＝過去の無効化の記録（変更しない）、`closed_by_person_id`＝管理者が無効化した場合の実行者。無効化の後の辞退は REJECTED で、元の記録を保持する。`CANCELLED(MANAGER)` からの辞退は禁止 | §5.1、§6.1、§7 |
| email_key と鍵のローテーション | HMAC-SHA-256 だけ（SHA-256 単独は不可）。複数の版を持つ鍵の束で、期間内に使われたすべての版で集計する。旧い版は、その版の記録がすべて30日間の集計から外れた後に外せる | §6.2、§6.4、§6.7 |

### 16.2 未解決事項の状態（第4版）

| # | 事項 | 状態 | 扱い・理由 | 決定者 |
|---|---|---|---|---|
| U1 | 取消して作り直すと再送の上限を回避できる | **解決** | 同じ公演・同じメールで、招待をまたいで30日間に6回までにした（§5.3、§6.2） | — |
| U2 | 同意未確認の参加者を担当者に任命できるか | **解決** | 有効な担当者の条件に同意を加えたため、任命できない（422）。同意の確認の依頼を承諾した後に任命できる（§4.6） | — |
| U3 | 公演の終了後のメールアドレスの閲覧範囲 | **解決** | PrimaryManager だけ（§7.3） | — |
| U4 | レート制限の値と保存先、接続元IPの判定 | **一部未解決** | 値は提案どおり採用し、保存先はDBの表（§9.2）。**残り**：本番の構成（プロキシの有無とアドレス）を確認し、信頼するプロキシを設定する必要がある。確認は読み取りだけで行い、設定の変更は承認を得てから行う | 実装担当（確認）、ユーザー（本番の設定の承認） |
| U5 | 招待リンクのトークンをURLのフラグメントに入れられるか | **未解決（検証待ち）** | フラグメント方式を優先する。Expo Router の Web とネイティブのディープリンクの両方で、読めること・消せることを、実装の最初に検証する（§15の0）。安全に扱えない場合は、クエリ文字列に安易に切り替えず、設計を再検討して再レビューを受ける | 実装担当（検証）、ユーザー（検証が失敗した場合の方式の判断） |
| U6 | `/people` の外部の利用者 | **解決** | ユーザー回答：外部からの利用は想定しない。削除する（§13） | — |
| U7 | 公演の終了後・招待者の権限喪失後に辞退できるか | **解決** | 正当な招待対象者は辞退できる。承諾はできない。照会画面も辞退だけを出す（§4.2、§4.3、§7.2） | — |
| U8 | 処理時間の差によるアカウントの有無の推測 | **解決** | 招待作成時のアプリ内通知と宛先の検索をやめ、処理を同じにした（§9.2） | — |
| U9 | DBの照合順序と、アプリ側のメールアドレスの正規化の整合性 | **実行許可済み・確認待ち** | ユーザーが読み取り専用のSELECTの実行を許可した（Dev：許可、Production：照合順序のSELECTだけ。§12）。実装開始後の事前確認（§15の0）で実行して結果を記録する。本書の改訂ではDBに接続していない。`_ci` 以外なら、比較の方法を見直し、本書を改訂する | 実装担当（実行と記録）、ユーザー（結果が `_ci` 以外だった場合の判断） |
| U10 | 招待者の権限喪失で招待が無効になったときの PrimaryManager への通知 | **解決** | ユーザー判断（第5版）：最初に検出した書き込み処理（承諾・辞退）で、PrimaryManager に一度だけ通知する。読み取り処理と、公演の終了による無効化では通知しない。`revocation_notified_at` で二重通知を防ぐ（§11.5）。管理者が改めて招待できるようにするため | — |
| U11 | 送信に失敗した招待の再送を回数に数えるか | **解決** | 受け付けた時点で数え、失敗しても戻さない（§5.3、§11.3） | — |
| U12 | 監査ログ（と送信の記録）の保持期間と削除の方針 | **解決** | ユーザー判断：監査ログ2年、送信記録は原則90日（最新の試行は保持）、同意の情報は参加者・公演の履歴と同じ期間。削除の定期処理を R14-B に含める（§6.6） | — |
| U13 | Join Key の申請時に、CANCELLED・REJECTED の行があると UNIQUE キーに当たる可能性 | **未解決（再現確認待ち）** | 実装の最初に、隔離したテストDBで再現を確認する（§15の0、§14.3）。**再現した場合は、参加者のライフサイクル（R14-B）のリリース前に必ず対応する**（WITHDRAWN が増え、再参加の経路として使われるため）。対応の範囲は再現の結果を見て判断する | 実装担当（再現）、ユーザー（対応範囲の判断） |
| U14 | `registration-hint` を置くか | **解決** | 置く。有効な招待トークンに対して、メールアドレスだけを返す。レート制限・`Cache-Control: no-store`・無効なトークンへの同じ404（§7.2、§9.2） | — |
| U15 | 同意未確認の参加者に付いている担当者の行は、本人が同意を記録した後、再任命なしで有効になるか（第3版で追加） | **解決** | ユーザー判断：ACTIVE な担当者の行は再任命なしに有効になる。INACTIVE の行と、再参加の前の担当者の権限は自動で戻さない。参加者でなかった人物が承諾した場合は、INACTIVE にして改めて任命する（§4.6） | — |

### 16.3 リスク

- **既存の担当者の権限の停止**（§4.6）：既存データの参加者はすべて同意未確認になるため、**R14-Bのリリースと同時に、PrimaryManager 以外の既存の担当者は全員、担当者の権限を失う**。進行中の公演で、担当者が実務（参加者の管理・稽古・会計・受付など）をしていれば、作業が止まる。権限を戻すには、担当者本人が「同意の確認を依頼」の招待を承諾する（U15により再任命は不要）。参加者でない担当者は、招待・承諾・再任命が必要。PrimaryManager は影響を受けないため、PrimaryManager が依頼を出せる。運用の手順は §16.3.1。
- **機能の後退**：既存利用者を即座に追加できなくなり、承諾を待つ運用になる。稽古の出欠・タイムテーブル・精算は ACTIVE な参加者を前提にしているため、承諾されるまで対象にならない。公演の準備期間中の周知が必要。
- **招待に気づかない**：招待作成時のアプリ内通知をやめたため、登録済みの人が招待に気づく手段は、招待メールとホームの件数だけになる。メールが届かない場合は、管理者が再送する（送信の上限の範囲内）。
- **リリースの順序**：承諾API（§15の3）と直接追加の廃止・担当者の有効性の変更（4）を分けてリリースすると、参加者・担当者を増やす手段がなくなる。BackendとFrontendも同時に配布する必要がある（R11・R12と同じ制約。APIはアプリ専用のため、古いアプリとの互換性は考慮しない）。
- **団体招待の問題が残る期間**（D1）：R14-Cまでの間、団体招待では既存利用者の即時追加と、Googleサインインを含む自動解決が残る。団体のメンバーになっても公演の参加者にはならない（D10、§4.4）ため、公演の参加者情報には影響しないが、団体のメンバー一覧でメールアドレスが見える問題は残る。
- **メールアドレスの大文字小文字**（R13 Medium）：招待と認証情報の照合が、DBの照合順序（`_ci`）に依存している。§6.4 の方針に統一し、U9で照合順序を確認する。
- **`return_to` の未検証**（R13 Medium）：ログイン後に招待のリンクの画面へ戻す導線を追加するため、同時に検証を直さないと、オープンリダイレクトの経路が増える。
- **アクセストークンの有効期間**（R13 Low）：SUSPENDED にしたアカウントでも、発行済みのアクセストークンの期限までは有効。承諾APIでは、UserAccount の状態を都度確認して防ぐ（§4.3）。
- **URLのフラグメント**（U5）：Expo Router で扱えない場合、トークンの受け渡しの設計を見直す必要があり、実装の開始が遅れる。
- **レート制限の新設**（U4）：既存の仕組みがないため、新しく作る。接続元IPの判定を誤ると、正当な利用者がまとめて制限される、または制限が効かない。
- **送信の結果不明**：PHPの処理が止まると、送信の記録が PENDING のまま残り、「結果不明」と表示される。メールが送られたかどうかは分からないため、管理者が再送の要否を判断する。
- **担当者の有効性の判定の性能**：権限の判定のたびに参加者と同意の確認が加わる。現在の `isActivePersonParticipant()` は公演の参加者を全件読むため、専用のクエリに置き換える（§7.4）。
- **既存テストの書き直し**：§14.4のとおり、多数のテストが現行の仕様（直接追加・自動解決・参加者でない担当者・同意のない担当者）を固定している。仕様変更として書き直す（削除・無効化はしない）。
- **古い招待メールのリンク**：`/register?token=` を、確認画面への導線に転送し続ける必要がある。クエリのトークンがアクセスログに残る（期限24時間で無効になる）。
- **メールの到達**：`wp_mail()` の成功は配達を保証しない。届かない場合は、管理者の再送で対応する（送信の上限の範囲内）。
- **email_key の鍵の設定漏れ**：鍵が設定されていないと、招待の作成・再送が止まる（§6.7。安全側の動作）。リリースの手順に、Dev・Production の `wp-config.php` への鍵の設定を含める（設定の変更は、ユーザーの承認を得て行う）。
- **削除の定期処理**：WP-Cron はアクセスを契機に動くため、削除が遅れることがある。削除の対象は監査ログと送信の記録だけに限定し、誤って他の表を削除しないことをテストで確認する（T101）。
- **MySQL 8.0／MariaDB での未検証**：既存のラウンドと同じ。

#### 16.3.1 既存の担当者の移行手順（第4版で追加）

R14-Bのリリースで、PrimaryManager 以外の既存の担当者は、担当者の権限が無効になる（§4.6）。進行中の公演の作業が止まらないよう、次の手順で移行する。

**リリース前**

1. **件数の把握**：§12 の「既存の ACTIVE な担当者」「参加者でない人物の担当者」のSELECTで、公演の状態別・役割別の件数を把握する。実行は読み取りだけで、Production での実行は事前にユーザーの承認を得る（U9の許可の範囲外のため）。
2. **影響の評価**：進行中（PLANNING・ACTIVE）の公演ごとに、担当者の権限がなくなると止まる作業（参加者の管理・稽古・会計・受付など）を確認する。
3. **事前の周知**：進行中の公演の PrimaryManager に、次を知らせる。
   - リリースの日時と、その時点で担当者の権限が無効になること
   - 同意が済むまで、担当者の権限は無効のままであること
   - PrimaryManager 自身の権限は変わらず、すべての操作（同意の確認の依頼・招待・任命）ができること
   - 復旧の手順（下記の「リリース後」）
4. **適用の判断**：公演の本番の直前・当日など、担当者の権限の停止で運用に支障が出る公演がある場合は、**無理に本番環境へ適用しない**。リリースの時期をずらすなどを、ユーザーが判断する。

**リリース後**

1. **識別**：担当者の一覧（§10）で、同意未確認の担当者（`CONSENT_UNCONFIRMED`）と、参加者でない担当者（`NOT_ACTIVE_PARTICIPANT`）を、理由つきで表示する。
2. **同意の確認の依頼**：PrimaryManager（または有効な担当者）が、同意未確認の担当者に「同意の確認を依頼」の招待を送る。本人が承諾すると、ACTIVE な担当者の行は再任命なしに有効になる（U15）。
3. **参加者でない担当者**：招待して、本人が承諾した後に、改めて任命する。
4. **同意が済むまでの間**は、その担当者の権限は無効のままで、PrimaryManager が代わりに作業する。PrimaryManager は、担当者の権限の有無にかかわらず、すべての作業と復旧の操作ができる。
5. **確認**：担当者の一覧で、無効な担当者が残っていないかを公演ごとに確認する。

**この手順で行わないこと**

- 既存の担当者の行・参加者の行を、一括で変更・有効化しない（同意済みとみなさない。D2）。
- 本人の同意なしに、担当者の権限を有効にする例外の操作は設けない。
