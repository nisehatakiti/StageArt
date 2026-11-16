# PR #7 R14-B：本人の同意に基づく公演参加者登録（N4・F5）設計

- 作成：2026-11-15（第1版、`f13196c`）
- 改訂：2026-11-16（第2版）。ChatGPTの設計レビューと、ユーザーの判断（D1〜D11）を反映した。
- 対象：Head `c6480c2`（R14-A後）、スキーマ1.48.0
- 状態：**設計のみ（未実装）**。レビュー後に実装ラウンドへ渡す。
- 確定方針（R14-B指示書 §3）：
  - 公演参加者への登録は、招待と本人の承諾を経由する方式に統一する。
  - 管理者が人物IDを指定して直接登録する方式は採用しない。
  - 同じ団体のメンバーであることだけを理由に、承諾なしで登録する方式も採用しない。
  - 団体メンバー管理と公演参加者管理は別の概念として扱う。

**表記**

- 【確定】：ユーザーが判断した事項（D1〜D11、2026-11-16）と、R14-B指示書の確定方針。
- 【推奨】：設計者の提案。レビューで否定されなければ実装する。
- 【未解決】：実装前に確認または判断が必要な事項。§16.2 に一覧がある（U1〜U14）。

本文中の「管理者」は、PrimaryManager、または `Participant.Update` 権限を持つ**有効な** ProductionDelegate（PARTICIPANT_MANAGER など）を指す（`ProductionAuthorizationService::canManageParticipants()`）。「有効な担当者」の定義は §4.6 で変更する。

### 第2版の主な変更点

| 項目 | 内容 | 主な節 |
|---|---|---|
| D1〜D11 | 未決事項を【確定】に変更し、本文・API・データモデル・画面・テストに反映 | §16.1 |
| 既存の担当者 | 参加者でない人物の担当者の行は残すが、**権限は有効にしない**（第1版の「変更しない」を撤回） | §4.6、§7.1、§8 |
| 旧方式の参加者 | 登録経路と同意日時の列を追加し、「旧方式（同意未確認）」を区別できるようにする | §4.5、§6.1 |
| 参加申請との競合 | 招待の承諾と参加申請の承認・却下の排他と、経路の記録 | §4.7、§5.2 |
| 未ログインの導線 | トークンだけでは招待の詳細を返さない。§7.1・§7.2・§10を統一 | §4.2、§7 |
| 通知の失敗 | DBのコミットを先にし、送信の失敗を記録して再送できるようにする | §11 |
| 不正利用対策 | レート制限、トークンの露出防止、照合失敗時の応答の統一 | §9.2 |
| F5 | 廃止前に利用箇所を調査した結果を追記 | §1.5、§13 |

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
  - 申請時に同じ人物・種別の ACTIVE または PENDING の行があれば拒否する。CANCELLED・REJECTED の行がある場合は新しい行を作ろうとするため、UNIQUEキーに当たる可能性がある（R14-Bの調査で気づいた点。動作は未確認で、R14-Bの範囲外）。
  - **本人の意思が起点の既存経路**である。
- **本人による辞退**：APIも画面もない。
- **自分の参加公演**：`GET /me/participating-productions`。

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

- `POST /productions/{id}/delegates`（`CreateProductionDelegateUseCase`）は、**存在する任意の人物ID**を担当者にできる。参加者である必要はない。応答には対象者の姓・名が入る。
- 画面 `productions/[id]/delegates.tsx` には、参加者の候補のほかに、人物IDの自由入力欄がある。
- テーブル `stageart_production_delegates`：`person_id`・`role`・`status`（ACTIVE／INACTIVE）・`created_by`／`updated_by`・`created_at`／`updated_at`。公演・人物・役割で一意。
- **権限の判定**（R14-Bで調査）
  - `ProductionAuthorizationService` の `activeDelegateFor()`・`activeDelegatesFor()` が、`status=ACTIVE` の行を「有効な担当者」として返す。`hasProductionPermission()`・`hasProductionCapability()`・`canReadProduction()`・`isProductionMember()`・`canManageParticipants()` などは、すべてこの2つを経由する。
  - `CoreProductionCapabilityDirectoryAdapter`（会計モジュールの通知先など）は、行を直接読むが、最後に `hasProductionCapability()` で確認する。
  - **`GetMyDashboardUseCase`（関係する公演の一覧）と `ListProductionsUseCase`（自分の公演一覧）は、行の `isActive()` だけを見ている**。`ProductionAuthorizationService` を経由しない。
  - いずれも、担当者が参加者かどうかは見ていない。

### 1.5 人物情報の取得（F5）と利用箇所の調査

- `GET /people?email=&production_id=`（`SearchPersonByEmailUseCase`）：管理者は、確認済みメールに一致する人物のID・姓・名を取得できる。一致しなければ404、複数の人物に一致すれば409。**アカウントの有無と氏名が分かる**。
- `GET /people/{id}`（`GetPersonByIdUseCase`）：**ログインしていれば誰でも**、任意の人物IDの姓・名を取得できる。
- `FindPersonByEmailUseCase` は、ログイン用の確認済み EmailCredential と、確認済みの NotificationEmail だけを対象にする（R14-A F6以降）。Googleアカウントのメールは ExternalIdentity に保存されないため、NotificationEmail 経由で一致する。

**廃止前の利用箇所の調査（第2版で追加、`c6480c2` のリポジトリ全体を検索）**

| 範囲 | 結果 |
|---|---|
| Backend のルート | `PersonRestController` だけが2つのルートを登録している（`Plugin.php` で生成） |
| Backend の UseCase・Repository からの呼び出し | `SearchPersonByEmailUseCase`・`GetPersonByIdUseCase` を呼ぶのは `PersonRestController` だけ。他の UseCase からは呼ばれていない。`FindPersonByEmailUseCase` は内部用で、招待作成・招待解決などから使われている（残す） |
| Frontend | `features/person/api.ts` の `searchPersonByEmail`・`fetchPersonById` が定義されているだけで、画面・hook からの呼び出しはない。`types/api.ts` の型定義と、`components/schedule-comment-list.tsx` のコメントに言及がある |
| テスト | PHPUnit：`tests/Application/Person/GetPersonByIdUseCaseTest.php`。`SearchPersonByEmailUseCase` 専用のテストは見つからなかった（呼び出しを含む他のテストの有無は、実装時に再確認する）。Jest：呼び出しなし |
| 文書 | `docs/` 以下に `/people` のAPI仕様の記述はない（`docs/08-API/` にも記述なし） |
| 外部連携・他のAPI利用者 | リポジトリ内には、外部システムやスクリプトからの利用は見つからない。**リポジトリ外の利用者（独自のスクリプト・他のアプリ）の有無は、コードからは確認できない**（§16.2 U6） |

この結果から、【推奨】どおり2つのルートを廃止する（§13）。外部の利用者が不明な点はリスクとして残す（§16.3）。

### 1.6 通知基盤

- `NotificationDispatcherInterface::dispatch(PersonId, type, payload)` → `WordPressNotificationDispatcher` が、アプリ内通知（`InAppNotificationAdapter`、`stageart_notifications`）とメール（`WordPressEmailNotificationAdapter`）に配信する。payload には `message`・`production_id`・`target_type`・`target_id` を入れられる。配信の失敗はログに記録するだけで、処理は止めない。失敗の記録をDBに残す仕組みはない。
- 招待メールは、通知基盤ではなく `ParticipantInvitationMailer` で直接送る。宛先がStageArtの利用者でなくても送れる。
- **非同期の仕組み**：キューはない。WP-Cron の `wp_schedule_single_event()` を、稽古のリマインダー（`WordPressRehearsalReminderScheduler`）だけが使っている。WP-Cron はサイトへのアクセスを契機に動くため、実行時刻は保証されない。
- 画面：`my-notifications.tsx`（`GET /me/notifications`）。

### 1.7 不正利用対策の現状（第2版で追加）

- **レート制限**：プラグイン全体に、レート制限の仕組みはない（`rate limit`・`throttle`・429 を検索して該当なし）。
- **トークンの受け渡し**：招待トークンは、メールのリンクのクエリ（`?token=`）と、`resolve?token=` のクエリで送られる。Webのアクセスログ・ブラウザの履歴・Referer に残る可能性がある。
- **ログ**：`ResolveParticipantInvitationUseCase` が `error_log` にメールアドレスを出力している。`CreateParticipantInvitationUseCase` の入力エラーの例外メッセージにもメールアドレスが入る。
- **Referrer-Policy**：Frontend（Expo Web）に設定は見つからない。

## 2. 問題となるAPI・画面・権限

| # | 経路 | 問題 |
|---|---|---|
| P1 | `POST /productions/{id}/participants`（subject_type=PERSON） | 任意の人物IDを、同意なく ACTIVE の参加者にできる。応答に姓・名・メールが入る。公演は誰でも作れるため、実質ログイン済みの誰でもできる（N4の本体） |
| P2 | 同上（subject_type=ORGANIZATION） | 任意の団体を、同意なく参加団体にできる |
| P3 | `POST .../participant-invitations` の既存利用者分岐 | 確認済みメールを入力するだけで即座に参加者になる。応答 `PARTICIPANT_ADDED` と姓・名・メールで、アカウントの有無と氏名が分かる |
| P4 | 自動解決（登録・メール確認・Googleサインイン） | 本人の承諾操作なしに参加者になる。Googleでは、サインインするたびに未処理の招待がすべて解決される |
| P5 | `PUT /participants/{id}` の `status` | 本人が抜けた行（CANCELLED）や、却下・申請中の行を、管理者が ACTIVE に戻せる |
| P6 | `POST /productions/{id}/delegates` | 参加者でない任意の人物を担当者にできる（参加者一覧・稽古などの閲覧権限が付く）。応答に姓・名が入る |
| P6b | 既存の担当者の行（第2版で追加） | P6で過去に作られた、参加者でない人物の担当者の行が ACTIVE のまま残っていれば、権限も有効なまま残る。新しい任命を止めるだけでは閉じない |
| P7 | `GET /people?email=`（F5） | メールからアカウントの有無と氏名が分かる |
| P8 | `GET /people/{id}` | ログイン済みの誰でも、任意の人物の氏名を取得できる |
| P9 | `GET /participant-invitations/resolve?token=` | 認証なしで、招待先のメール・氏名・公演名を返す。トークンがクエリに入る |
| P10 | `GET /productions/{id}/participants` | REHEARSAL_MANAGER（閲覧のみ）にもメールを返す。CANCELLED・REJECTED の行にも、人物の姓・名・メールを返す |
| P11 | 団体招待の既存利用者分岐と自動解決 | P3・P4と同じ問題が団体メンバーにもある。【確定】R14-Cで対応する（D1） |
| P12 | 招待の作成・再送・トークン照合（第2版で追加） | レート制限も再送の上限もなく、同じ相手に招待メールを送り続けられる。トークンの総当たりも制限されない |

## 3. 既存招待基盤の再利用可能な部分

新しい招待のテーブル・Entityは作らない。`ParticipantInvitation` を「承諾待ちの招待」として拡張する。

| 再利用するもの | 用途 |
|---|---|
| `stageart_participant_invitations` と `ParticipantInvitation` | 招待の本体。列を追加し、状態を拡張する（§6） |
| トークンの生成・SHA-256保存・`findByTokenHash` | 招待メールのリンク。生成と保存の方式は変更しない |
| `rotateToken()` と `ResendParticipantInvitationUseCase` | 再送。期限を7日にし、回数の上限を加える |
| `CancelParticipantInvitationUseCase` | 管理者による取消。理由と実行者を記録するよう拡張 |
| `ListParticipantInvitationsUseCase` と画面の「招待中のメンバー」表 | 招待一覧。状態の表示を拡張 |
| `CreateParticipantUseCase` の重複確認と `Participant::create()` | 承諾時の参加者作成。管理者の権限確認の代わりに、本人の承諾を条件にする内部サービスへ分ける（§7.4） |
| `Participant::approve()` | 参加申請中（PENDING）の行に、本人が承諾した場合の扱い |
| `FindPersonByEmailUseCase`（確認済みメールのみ） | 「この招待のメールが本人の確認済みアドレスか」の判定。外部APIからは使わない |
| `RegisterWithEmailUseCase` の招待トークン経路 | 招待リンクから登録した人のメールを確認済みにする。参加者は作らない |
| `NotificationDispatcherInterface` | 招待・承諾・辞退の通知（アプリ内とメール） |
| `ProductionRepositoryInterface::findByIdForUpdate` | 承諾・招待作成・参加申請の承認の直列化（ロック順序：公演 → 招待 → 参加者 → 担当者） |
| `TransactionManagerInterface` | 承諾の一連の処理を1トランザクションにまとめる |
| `ProductionAuthorizationService::isActivePersonParticipant()` | 有効な担当者の判定（§4.6） |
| `ProductionDelegate` の INACTIVE と `updated_by`／`updated_at` | 辞退時の担当者の役割の解除（行は削除しない） |

## 4. 新しい業務フロー

### 4.1 招待（管理者）

1. 管理者が、公演の「メンバー管理」画面で、氏名・メール・役割（CAST／STAFF）・備考を入力し、「招待する」を押す。
2. `POST /productions/{id}/participant-invitations` は、**アカウントの有無にかかわらず、常に招待を作る**（同じ宛先の招待が承諾待ちなら再送する）。応答は「招待した」「再送した」の2種類だけで、**アカウントの有無も、人物の氏名も返さない**。
3. 次の場合は招待を作らない（§5.3）。
   - 公演が COMPLETED／ARCHIVED／CANCELLED（【確定】D5）
   - 同じ公演・同じメールアドレスの招待が、本人によって辞退されてから30日以内（【確定】D6）
   - 再送の上限を超えた（【確定】D6）
   - レート制限を超えた（§9.2）
4. 招待メールを送る。
   - 宛先がStageArtの確認済みアドレスを持つ人物に一致する場合は、その人物にアプリ内通知も送る。ただし、通知したかどうかは管理者に返さない。
   - メールの文面は、登録済みかどうかにかかわらず1種類にする。「承諾するまで参加者にはなりません」「有効期限は7日間です」と書く。
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
   - 登録を選んだ場合は、`POST /participant-invitations/registration-hint`（未ログインで可）で、招待先のメールアドレスだけを取得し、登録画面の初期値にする（D7で許容された範囲）。無効なトークンでは何も返さない（§9.2）。
   - 招待リンクから、招待先と同じアドレスで登録した場合は、メールを確認済みにする（R14-A F6の既存動作。参加者は作らない）。
3. 登録またはログインの後、招待確認画面に戻る（`return_to`。§10）。ここで初めて `lookup` を呼び、確認済みメールを照合する。
4. 一致した場合だけ、招待確認画面を表示する。
5. 一致しない場合は、招待を受け取ったアドレスでログインし直すよう案内する。

**アプリ内通知・ホームから開いた場合**：ログイン済みなので、`GET /me/participant-invitations` の内容から招待確認画面を開く（トークンは使わない）。

**招待確認画面の表示**（【確定】D7）

- 公演名
- 団体名
- 役割（CAST／STAFF）
- 招待者の公演側の表示名
- 招待の期限
- 「承諾すると、公演の管理者にあなたの氏名・メールアドレスが共有されます」という説明
- 既にこの公演の参加者（旧方式の登録を含む）である場合は、「あなたは既にこの公演のメンバーです。承諾すると、参加への同意が記録されます」と表示する（§4.5）

承諾前には、管理者が入力した氏名・備考・内部メモと、招待先のメールアドレスを含む不要な個人情報を表示しない。

**承諾と辞退**

1. 本人が「参加する（承諾）」または「参加しない（辞退）」を選ぶ。
2. 承諾した場合だけ、参加者（ACTIVE）にする。辞退した場合は、参加者にしない。
3. 結果を招待者に通知する（§11）。

### 4.3 承諾の条件（すべて満たす場合だけ）

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

**辞退の条件**【推奨】：アカウント・宛先・PENDING・期限内の条件は承諾と同じ。公演の終了・招待者の権限喪失は確認せず、本人の辞退として記録する（辞退は参加者を作らず、害がないため）。ただし、通知は §11 のとおり、その時点で権限のある人に送る。この扱いの確認は §16.2 U7。

### 4.4 公演参加者の管理（承諾後）

- 管理者による削除（既存の `DELETE /participants/{id}`）は CANCELLED にする。本人による辞退（新設）は WITHDRAWN にして、区別する（§5.2）。
- 役割（CAST／STAFF）・備考・公演側の氏名は、管理者が変更できる（既存の `PUT`）。ただし、`status` を ACTIVE 以外から ACTIVE に戻す操作はできなくする（P5）。参加者に戻すには、もう一度招待して承諾してもらう。
- 参加者が ACTIVE でなくなった時点（本人の辞退・管理者の削除・INACTIVE への変更）で、その人物のこの公演での担当者の役割も解除する（§4.6）。
- ORGANIZATION の参加者は、新しく追加できない（【確定】D10）。既存の行は変更・削除しない。団体のメンバーであることを理由に、公演の参加者として扱わない。将来必要になった場合は、団体の OWNER による承諾の方式を別に設計する。

### 4.5 旧方式で登録された参加者（【確定】D2）

R14-B以前に、直接追加・招待作成時の即時追加・自動解決などで ACTIVE になった参加者の扱い。

- **既存の ACTIVE な参加者を、一括で変更・削除しない。**稽古・出欠・タイムテーブル・精算・実績集計への影響はない。
- **今回の変更によって「同意済み」とはみなさない。**参加者の行に、登録経路（`enrollment_source`）と同意日時（`consented_at`）の列を追加する（§6.1）。既存の行は、どちらも NULL のまま（Migrationでは値を入れない）で、「旧方式（同意未確認）」を表す。
- **管理者が認識できるようにする。**参加者一覧で、PERSON の参加者のうち `consented_at` が NULL の行に「旧方式（同意未確認）」と表示する（§7.3、§10）。
- **本人は辞退できる。**参加公演の画面から、参加をやめられる（§7.2 withdraw）。
- **将来の再確認に使える。**管理者が旧方式の参加者のメールアドレスに通常の招待を送り、本人が承諾すると、既存の行の状態は ACTIVE のまま、`consented_at` と `enrollment_source=INVITATION` を記録する（§5.2）。本人が辞退しても、参加者の行は変更しない（参加をやめる場合は withdraw を使う）。
- 既存データへの一斉の再招待と、一斉の無効化は、今回の対象外とする。

R14-B以降の新しい参加者は、経路ごとに次のとおり記録する。

| 経路 | `enrollment_source` | `consented_at` |
|---|---|---|
| 招待の承諾 | INVITATION | 承諾の日時 |
| 参加申請の承認（R14-B以降に申請されたもの） | JOIN_REQUEST | 申請の日時（本人の意思表示の時点） |
| NAME_ONLY の追加（アカウントを持たない人の記録） | MANAGER_RECORD | NULL（同意の対象外） |
| R14-B以前の行 | NULL | NULL |

R14-B以前に申請されて、R14-B以降に承認された行は、申請の日時を区別できないため、`enrollment_source=JOIN_REQUEST`、`consented_at` には行の `created_at`（申請時に作られた日時）を使う【推奨】。

### 4.6 担当者（ProductionDelegate）の有効性（第2版で変更、レビュー指摘5.1・【確定】D9）

第1版では「既存の担当者の行は変更しない」としていたが、行が ACTIVE のまま権限も有効であれば、P6（P6b）の問題が残る。次のとおり改める。

**有効な担当者の定義**

担当者の行が次の両方を満たす場合だけ、担当者としての権限を与える。

1. 行の `status` が ACTIVE であること（既存）。
2. その人物が、当該公演の **ACTIVE な PERSON 参加者**であること（新設）。

- **DB上の行が ACTIVE で残っていることを理由に、既存の権限を維持してはならない。**
- 判定は `ProductionAuthorizationService::activeDelegateFor()`・`activeDelegatesFor()` で行う。これを経由するすべての権限判定（`canReadProduction`・`canManageParticipants`・`hasProductionCapability` など）に、自動的に適用される。
- `ProductionAuthorizationService` を経由せずに行を読んでいる `GetMyDashboardUseCase`・`ListProductionsUseCase` も、同じ判定に切り替える（§1.4）。切り替えないと、権限のない公演が一覧に出る。
- `CoreProductionCapabilityDirectoryAdapter` は、最後に `hasProductionCapability()` を通るため、自動的に適用される（実装時にテストで確認する）。
- **PrimaryManager の権限は、担当者の行とは独立しているため、影響を受けない**（PrimaryManager が参加者でなくても、従来どおりすべての操作ができる）。
- 他の正当な管理者（ACTIVE 参加者である担当者）の権限にも影響しない。

**既存の行**

- 既存の担当者の行は削除しない。Migrationで状態も変更しない。
- 参加者でない人物の担当者の行は、行が ACTIVE でも「無効（参加者ではない）」として扱い、権限を与えない。
- 担当者の一覧（`GET /productions/{id}/delegates`）と画面では、有効な担当者と、無効な担当者（履歴）を区別して表示する（§7.1、§10）。

**任命**

- 新しく任命できるのは、その公演の ACTIVE な PERSON 参加者だけ（P6）。
- 本人が招待を承諾して ACTIVE な参加者になった後に、管理者が改めて任命できる。同じ公演・人物・役割の行が INACTIVE で残っている場合は、新しい行を作らず、その行を ACTIVE に戻す（一意キーのため）。

**役割の解除（D9）**

- 参加者が ACTIVE でなくなった時点（本人の辞退 WITHDRAWN・管理者の削除 CANCELLED・INACTIVE への変更）で、その人物のこの公演での担当者の行をすべて INACTIVE にする。行は削除しない。`updated_by` に操作した人、監査ログに `DELEGATE_DEACTIVATED` を記録する。
- 参加者が ACTIVE になった時点（招待の承諾・参加申請の承認）で、その人物の担当者の行が ACTIVE のまま残っていれば（参加者でない間は無効だった行）、それも INACTIVE にする。**古い任命が、承諾によって自動的に有効にならないようにするため**である。担当者にするには、管理者が改めて任命する。
- PrimaryManager 本人は、通常の辞退操作ができない。PrimaryManager が参加をやめる場合は、先に管理者の権限を引き継ぐ（既存の `ChangePrimaryManagerUseCase`）。

旧方式（同意未確認）の ACTIVE 参加者を担当者に任命できるかは、§16.2 U2。

### 4.7 参加申請と招待の承諾が重なる場合（第2版で追加、レビュー指摘5.2）

参加申請（Join Key）によって PENDING の参加者の行があるときに、同じ人物が招待を承諾する場合。

- **招待の承諾で、その行を ACTIVE にする。**`enrollment_source=INVITATION`、`consented_at` は承諾の日時とする。
- **申請の履歴は削除しない。**申請専用のテーブルはないため（§1.1）、監査ログで経路を区別する。
  - 申請時：`JOIN_REQUESTED`
  - 承諾時：`ACCEPTED`（`detail` に `previous_status=PENDING`、`superseded_join_request=true`）
  - 管理者の承認・却下：`JOIN_APPROVED`・`JOIN_REJECTED`
- **排他制御**：招待の承諾、参加申請の承認・却下、参加申請の作成は、いずれも最初に**公演の行を FOR UPDATE でロック**してから、参加者の行を読み直す。ロックを取得した順に処理し、後から来た処理は、その時点の行の状態で判定する。
  - 承諾が先：行は ACTIVE になる。その後の管理者の承認・却下は、PENDING でないため409 `stageart_participant_request_not_pending`（「この申請は既に処理されています」）。
  - 承認が先：行は ACTIVE になる。その後の承諾は「既に ACTIVE」として、招待を ACCEPTED にし、`consented_at` が NULL なら記録する（§5.3、冪等）。
  - 却下が先：行は REJECTED になる。その後の承諾は、管理者が出した招待への本人の承諾として有効で、REJECTED → ACTIVE にする。却下と招待は、どちらも管理者の操作であり、招待が取り消されていない限り、本人の承諾を優先する。
- 参加申請の承認・却下に、現在はロックがない（§1.1）。R14-Bで、上の順序でロックを追加する。

## 5. 招待状態遷移

### 5.1 招待（ParticipantInvitation）

既存の命名（`ParticipantStatus` の REJECTED、招待の CANCELLED）に合わせる。

| 状態 | 保存 | 意味 |
|---|---|---|
| PENDING | ○ | 承諾待ち |
| ACCEPTED | ○（新設） | 本人が承諾した（参加者になった、または既存の参加者として同意を記録した） |
| REJECTED | ○（新設） | 本人が辞退した |
| CANCELLED | ○（既存） | 招待が無効になった。理由は `close_reason`（下記） |
| EXPIRED | ×（導出） | PENDING かつ `expires_at <= 現在`。保存せず、APIでは `effective_status` として返す |
| PRODUCTION_CLOSED | ×（導出） | PENDING かつ公演が COMPLETED／ARCHIVED／CANCELLED。保存せず、一覧では「無効（公演終了）」と表示する（D5） |
| CONSUMED | ○（既存のみ） | R14-B以前に自動解決された招待。新しくは作らない。表示上は「参加済み（旧方式）」とする |

`close_reason`（CANCELLED のときだけ値を持つ）

| 値 | 意味 | 記録する時点 |
|---|---|---|
| MANAGER | 管理者が取り消した | 取消の操作 |
| PRODUCTION_CLOSED | 公演が終了・中止になっていた | 本人が承諾しようとした時点（D5） |
| INVITER_REVOKED | 招待者が参加者を管理する権限を失っていた | 本人が承諾しようとした時点（D3） |
| NULL | R14-B以前に取り消された（理由不明） | — |

```
            resend（期限切れでも可。トークンを差し替え、期限を再送時点から7日に更新。上限5回）
              ┌──────┐
              ▼      │
 create ──► PENDING ─┴─► accept（本人）───────────────────────► ACCEPTED
              │      ├─► reject（本人）───────────────────────► REJECTED
              │      ├─► cancel（管理者）─────────────────────► CANCELLED(MANAGER)
              │      ├─► accept時に公演がCOMPLETED/ARCHIVED/CANCELLED ─► CANCELLED(PRODUCTION_CLOSED)
              │      └─► accept時に招待者が権限を喪失 ─────────► CANCELLED(INVITER_REVOKED)
              ├─(expires_at経過)─► EXPIRED（導出。resendでPENDINGに戻る）
              └─(公演が終了・中止)─► PRODUCTION_CLOSED（導出。保存はしない。resend不可）
```

- ACCEPTED・REJECTED・CANCELLED・CONSUMED は終端の状態。同じ宛先にもう一度招待する場合は、新しい行を作る（辞退後30日の制限がある。§5.3）。
- 公演の状態が変わったときに、未処理の招待をまとめて更新する処理は追加しない（【確定】D5）。

### 5.2 参加者（Participant）

`ParticipantStatus` に **WITHDRAWN（本人による辞退）** を追加する。列は VARCHAR(20) なので、DDLの変更は不要。

| 遷移 | 実行者 | 経路 | 記録 |
|---|---|---|---|
| （行なし）→ ACTIVE | 本人の承諾 | `POST /participant-invitations/{id}/accept` | `enrollment_source=INVITATION`、`consented_at` |
| CANCELLED／WITHDRAWN／REJECTED／INACTIVE → ACTIVE | 本人の承諾 | 同上（UNIQUEキーにより同じ行を再び ACTIVE にする） | 同上 |
| PENDING（参加申請中）→ ACTIVE | 本人の承諾 | 同上（§4.7） | 同上 |
| PENDING（参加申請中）→ ACTIVE | 管理者の承認 | 既存の承認 API（ロックを追加） | `enrollment_source=JOIN_REQUEST`、`consented_at`（§4.5） |
| ACTIVE → ACTIVE（同意の記録だけ） | 本人の承諾 | 承諾 API（既に ACTIVE の場合） | `consented_at` が NULL なら記録。`enrollment_source` が NULL なら INVITATION |
| ACTIVE → CANCELLED | 管理者 | `DELETE /participants/{id}`（既存） | 担当者の役割を解除（§4.6） |
| ACTIVE → WITHDRAWN | 本人 | `POST /participants/{id}/withdraw`（新設） | 担当者の役割を解除（D9） |
| ACTIVE → INACTIVE | 管理者 | `PUT`（既存。ACTIVE への復帰は不可） | 担当者の役割を解除 |
| PENDING → REJECTED | 管理者 | 既存の却下 API（ロックを追加） | — |

管理者の操作で ACTIVE にする経路は、参加申請の承認だけになる（本人の申請が起点のため）。

### 5.3 招待の制約

| 場面 | 扱い |
|---|---|
| 同じ公演・同じメール・同じ種別に PENDING（期限内）がある | 新しく作らず、再送する（既存どおり。再送の上限に数える）。作成は公演の行をロックして直列化し、二重に作らない |
| 同じ公演・同じメールで、種別が異なる | 別の招待として作れる（参加者のUNIQUEキーが種別ごとのため、既存どおり） |
| 有効期間 | 新しい招待は**作成から7日間**（D4） |
| 再送 | 同じ行のトークンを差し替え、**期限を再送の時点から7日間に更新する**。古いトークン（古いリンク）は無効になる。`last_sent_at`・`resend_count` を記録する |
| 再送の上限（D6） | 初回の送信は再送の回数に含めない。**再送は最大5回**（初回を含めて最大6回の送信）。6回目の再送は429 `stageart_participant_invitation_resend_limit`。期限切れの招待も、上限の範囲内なら再送できる |
| 送信に失敗した招待の再送 | 再送できる。直前の送信が失敗（`last_send_status=FAILED`）だった場合は、再送の回数に数えない【推奨】（§11.3、§16.2 U11） |
| 辞退の後の再招待（D6） | 本人が辞退（REJECTED）した日時から**30日間**は、同じ公演・同じメールアドレス（種別を問わない）への新しい招待を409 `stageart_participant_invitation_recently_rejected` で拒否する。応答に再招待できる日時（`available_at`）を入れる。30日を過ぎれば作れる。辞退の履歴は削除しない |
| メールアドレスの比較 | 同じ宛先かどうかの判定（重複・30日の制限・本人の判定）は、すべて同じ正規化で比較する（§6.3）。大文字小文字の違いで制限を回避できないようにする |
| 期限切れ | 承諾・辞退はできない（410）。管理者は上限の範囲内で再送できる |
| 既に ACTIVE な参加者への招待 | 作成時点では誰の招待か判定しない（アカウントの有無を漏らさないため）。承諾時に既に ACTIVE なら、新しい行は作らず、招待を ACCEPTED にし、同意を記録する（冪等、§4.5） |
| 公演が COMPLETED／ARCHIVED／CANCELLED（D5） | 招待の作成・再送は409 `stageart_participant_invitation_production_closed`。承諾時に該当すれば、招待を CANCELLED(PRODUCTION_CLOSED) にして409。一覧では「無効（公演終了）」と表示する |
| 招待者が権限を失った（D3） | 承諾時に、招待者が `canManageParticipants` でなければ CANCELLED(INVITER_REVOKED) にして409。参加者は作らない。再送・取消は、その時点の管理者なら誰でもできる（既存どおり）。再送しても招待者は変わらないため、権限のある管理者が新しい招待を作る必要がある【推奨】 |
| 対象者のアカウントが SUSPENDED／DISABLED | 承諾できない（403）。招待は PENDING のまま残す（管理者に対象者の状態を知らせないため）。期限が来れば EXPIRED になる |
| 同時に2回承諾した | 公演 → 招待（FOR UPDATE）の順にロックし、2回目は「既に処理済み」として最初の結果を返す（冪等）。参加者のUNIQUEキーで二重登録も防ぐ。通知は最初の1回だけ送る |
| 承諾と取消が同時 | 同じロック順で直列化する。取消が先なら承諾は409、承諾が先なら取消は409 `stageart_participant_invitation_not_pending` |
| 承諾と参加申請の承認・却下が同時 | §4.7 |

**招待の作成を拒否する場合の応答とアカウントの有無**：上の拒否理由（公演の状態・辞退後30日・再送の上限・レート制限）は、いずれも管理者が既に知っている情報（公演の状態、自分の招待一覧に表示される辞退・送信回数）から判断できるもので、宛先のアカウントの有無には依存しない。

## 6. データモデル・Migration案

### 6.1 列の追加（スキーマ1.49.0、追加のみ）

**`stageart_participant_invitations`**

| 列 | 型 | 用途 |
|---|---|---|
| `invitee_person_id` | CHAR(36) NULL | 承諾・辞退した本人。応答した時点で記録する |
| `responded_at` | DATETIME NULL | 承諾・辞退の日時（30日の制限の起点） |
| `participant_id` | CHAR(36) NULL | 承諾で作成・再有効化・同意記録した参加者 |
| `closed_at` | DATETIME NULL | 取消・無効化の日時 |
| `closed_by_person_id` | CHAR(36) NULL | 取消した管理者（自動の無効化ではNULL） |
| `close_reason` | VARCHAR(30) NULL | MANAGER／PRODUCTION_CLOSED／INVITER_REVOKED |
| `last_sent_at` | DATETIME NULL | 最後に送信を試みた日時 |
| `resend_count` | INT UNSIGNED NOT NULL DEFAULT 0 | 再送の回数（初回は含めない。上限5、D6） |
| `last_send_status` | VARCHAR(20) NULL | 最後の送信の結果：SENT／FAILED（§11.3） |
| `updated_at` | DATETIME NULL | 更新日時 |

- インデックス：`KEY production_email_type (production_id, email, participant_type)`、`KEY production_email_status (production_id, email, status, responded_at)`（30日の制限の判定）、`KEY invitee_person_id (invitee_person_id)`。
- 一意制約は追加しない。同じ宛先の履歴の行が並ぶため。PENDING の一意性は、公演の行ロックで保証する。
- `status` の値に ACCEPTED／REJECTED を追加する。VARCHAR(20) なので、DDLの変更は不要。
- `TOKEN_LIFETIME_SPEC` を `'PT24H'` から `'P7D'` に変更する（D4）。既存の招待の `expires_at` は変更しない。
- `resend_count` の既存行は、列の追加によって既定値の0になる（UPDATE文は実行しない）。R14-B以前の再送の回数は分からないため、R14-B以降の再送から数える。

**`stageart_participants`**（D2）

| 列 | 型 | 用途 |
|---|---|---|
| `enrollment_source` | VARCHAR(20) NULL | INVITATION／JOIN_REQUEST／MANAGER_RECORD。NULL は R14-B以前の行（旧方式） |
| `consented_at` | DATETIME NULL | 本人の同意（承諾・申請）の日時。NULL は同意の記録なし |

- 既存の行は NULL のまま（値を入れない）。

**`stageart_production_delegates`**：列の追加はしない。役割の解除には既存の `status`（INACTIVE）・`updated_by`・`updated_at` を使う。

### 6.2 参加者の監査ログ（新テーブル、追加のみ。【確定】D8）

既存に汎用の監査ログはない（会計の移行監査だけ）。参加者・招待・担当者の状態変更を記録する、**追記専用**の表を設ける。

```sql
CREATE TABLE {prefix}stageart_participant_events (
  id CHAR(36) NOT NULL,
  production_id CHAR(36) NOT NULL,
  participant_id CHAR(36) NULL,
  invitation_id CHAR(36) NULL,
  delegate_id CHAR(36) NULL,
  event_type VARCHAR(40) NOT NULL,
  enrollment_path VARCHAR(20) NULL,  -- INVITATION / JOIN_REQUEST / MANAGER_RECORD（ACTIVEになった経路）
  actor_person_id CHAR(36) NULL,     -- 操作した人（自動の場合はNULL）
  subject_person_id CHAR(36) NULL,   -- 対象の人物（承諾前は不明なのでNULL）
  detail TEXT NULL,                  -- JSON（変更前後の状態・close_reasonなど。メール・氏名・トークンは入れない）
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
- メールアドレス・氏名・トークンを保存しない。招待・参加者・担当者・人物の行IDで追跡する。
- 記録する項目：操作した人（`actor_person_id`）、対象（参加者・招待・担当者・人物のID）、イベントの種別、日時。
- 閲覧権限を限定する。R14-Bでは、監査ログを閲覧するAPIも画面も作らない。DBを直接参照できる運用者だけが見られる。将来、APIを作る場合は、PrimaryManager に限るなど、別途設計する。
- **保持期間は、現時点で確定しない（無期限保持も確定しない）。**将来の運用・プライバシーの方針に基づいて決める（§16.2 U12）。将来、期間を決めたときに `created_at` の範囲で削除できるよう、`created_at` にインデックスを付け、他のテーブルから外部キーで参照しない。R14-Bでは削除の処理を作らない。
- 監査ログの書き込みは、状態の変更と同じトランザクションで行う（記録だけが失われないように）。

### 6.3 メールアドレスの正規化（D6）

- **方針**【推奨】：比較には「前後の空白を除き、全体を小文字にした値」を使う。保存する値は、入力された値（`trim` 後）のままにする。
- 比較の場所：招待の重複確認（作成・再送）、辞退後30日の制限、本人の判定（`FindPersonByEmailUseCase` との照合）、`GET /me/participant-invitations`。
- DBの照合順序が `_ci`（大文字小文字を区別しない）であれば、SQLの `=` でも同じ結果になる。PHP側で比較する場合は `mb_strtolower` を使い、SQLとPHPの結果が食い違わないようにする（R13 Medium「メールアドレスの大文字小文字の照合」）。
- 既存の行を正規化して書き換えることはしない（Migrationでデータを変更しない）。
- **照合順序の確認が必要**：Dev・Productionの該当テーブルの照合順序を確認するSELECT（§12）を、承認を得てから実行する（§16.2 U9）。`_bin` などの照合順序だった場合は、比較の方法を見直す。

### 6.4 Migration（`Installer`、スキーマ1.48.0 → 1.49.0）

1. `dbDelta` で、6.1の列とインデックス、6.2のテーブル（とレート制限の表を採用する場合はその表、§9.2）を追加する。
2. **データの更新はしない**（UPDATE・DELETE文を実行しない）。
   - 既存の PENDING の招待は、そのまま承諾待ちとして扱う。期限（24時間で作られたもの）は既存の値のまま（D4）。
   - 既存の CONSUMED は終端として残す。
   - 既存の ACTIVE な参加者には触れない。`enrollment_source`・`consented_at` は NULL のまま（D2）。
   - 既存の担当者の行には触れない。権限の判定だけが変わる（§4.6）。
   - `NOT NULL DEFAULT 0` の列（`resend_count`）は、列の追加によって既存行が既定値になる。これはDDLの結果で、データの書き換えではない。
3. 冪等にする（列・テーブルが既にあれば何もしない）。失敗時はバージョンを上げずに、次回もう一度実行する（R14-A N2と同じ形）。
4. 削除を伴う処理はない。

## 7. API仕様

### 7.1 変更・廃止

| API | 変更 |
|---|---|
| `POST /productions/{id}/participants` | `subject_type=PERSON`・`ORGANIZATION` を拒否する（422 `stageart_participant_requires_invitation`。D10）。NAME_ONLY（アカウントを持たない、公演側だけの記録）は残し、`enrollment_source=MANAGER_RECORD` を記録する |
| `POST /productions/{id}/participant-invitations` | 既存利用者の分岐を削除し、常に招待する。公演の状態（D5）・辞退後30日（D6）・レート制限を確認する。有効期間は7日（D4）。応答の `outcome` は `INVITATION_SENT`・`INVITATION_RESENT` だけにし、`participant` は返さない。応答の形は、宛先のアカウントの有無で変えない |
| `GET /productions/{id}/participant-invitations` | `effective_status`（PENDING／EXPIRED／PRODUCTION_CLOSED／ACCEPTED／REJECTED／CANCELLED／CONSUMED）、`responded_at`、`close_reason`、`resend_count`・`resend_remaining`、`last_send_status`、辞退の場合は再招待できる日時（`reinvite_available_at`）を追加する。招待先の人物ID・氏名は返さない（承諾後は参加者一覧で確認する） |
| `POST /participant-invitations/{id}/cancel` | `closed_by_person_id`・`closed_at`・`close_reason=MANAGER` を記録する |
| `POST /participant-invitations/{id}/resend` | 公演の状態を確認する（D5）。期限を再送の時点から7日に更新し、トークンを差し替える。`resend_count`・`last_sent_at`・`last_send_status` を記録する。上限を超えれば429（D6） |
| `GET /participant-invitations/resolve?token=` | **廃止**。`POST /participant-invitations/registration-hint`（未ログイン、メールだけ）と `POST /participant-invitations/lookup`（ログイン必須、内容）に分ける（§7.2） |
| `PUT /participants/{id}` | ACTIVE 以外への `status` 変更（INACTIVE）は残し、ACTIVE 以外から ACTIVE への変更は拒否する（422 `stageart_participant_status_requires_consent`）。INACTIVE にした場合は担当者の役割を解除する（§4.6）。画面は `status` を送らないようにする |
| `DELETE /participants/{id}` | 既存どおり CANCELLED にする。担当者の役割を解除する（§4.6） |
| `GET /productions/{id}/participants` | §7.3 のとおり、個人情報を絞る。各行に `enrollment_source`・`consent_status`（CONFIRMED／UNCONFIRMED_LEGACY／NOT_APPLICABLE）を追加する（D2） |
| 参加申請の承認・却下 | 公演の行をロックし、参加者の行を読み直してから処理する。PENDING でなければ409 `stageart_participant_request_not_pending`（§4.7） |
| `POST /productions/{id}/delegates` | 対象者を、その公演の ACTIVE な PERSON 参加者に限る（422 `stageart_production_delegate_target_not_participant`）。同じ役割の INACTIVE の行があれば ACTIVE に戻す |
| `GET /productions/{id}/delegates` | 各行に `effective`（bool）と `ineffective_reason`（`INACTIVE`／`NOT_ACTIVE_PARTICIPANT`）を追加する。有効な担当者と履歴を区別する（§4.6） |
| `PUT /production-delegates/{id}`（担当者の役割・状態の変更） | ACTIVE に戻す場合も、対象者が ACTIVE な PERSON 参加者であることを確認する |
| `GET /people?email=` | **廃止**（F5）。ルートを削除し、Frontendの未使用関数も削除する（§1.5の調査結果） |
| `GET /people/{id}` | **廃止**（現行の画面では使っていない）。必要になった場合は、閲覧者と共通の公演・団体がある場合に限って再設計する |
| 自動解決（登録・メール確認・Googleサインイン） | 公演の招待については、`ResolveParticipantInvitationUseCase` の呼び出しを削除する。招待リンクから招待先と同じアドレスで登録した場合に、メールを確認済みにする処理は残す。**団体招待の自動解決（`ResolveOrganizationInvitationUseCase`）は、R14-Bでは変更しない**（D1） |

### 7.2 新設

**`GET /me/participant-invitations`**（ログイン必須）：自分宛ての承諾待ちの招待一覧

- 本人の確認済みアドレス（ログイン用と通知用）に一致し、PENDING かつ期限内で、公演が終了・中止でないものを返す。
- 応答の項目（D7）：`id`・`production_name`・`organization_name`・`participant_type`・`invited_by_display_name`（招待者の公演側の表示名。ない場合は「公演の管理者」）・`expires_at`・`already_participant`（本人が既に ACTIVE な参加者か。本人に関する情報なので本人には返す）。
- 返さない項目：招待先のメール、管理者が入力した氏名・備考。

**`POST /participant-invitations/lookup`**（ログイン必須）：リンクから招待を開く

- 本文：`{ "token": "..." }`。トークンはクエリではなく本文で送る（§9.2）。
- トークンが PENDING の招待に一致し、招待のメールが本人の確認済みアドレスなら、上と同じ項目を返す。
- 招待が PENDING で、本人宛てでない場合は403 `stageart_participant_invitation_not_for_you`（「このアカウント宛ての招待ではありません。招待を受け取ったメールアドレスでログインし直してください」）。招待の内容は返さない。
- 本人宛てで、期限切れ・処理済み・取消・公演終了の場合は、それぞれ410・409を返す（本人には状態を伝える）。
- トークンが一致しない場合、または本人宛てでない招待が PENDING 以外の場合は、すべて同じ404 `stageart_participant_invitation_link_invalid` を返す（§9.2）。

**`POST /participant-invitations/registration-hint`**（ログイン不要）：登録画面の初期値

- 本文：`{ "token": "..." }`。
- トークンが PENDING かつ期限内の招待に一致する場合だけ、`{ "email": "..." }` を返す。公演名・団体名・氏名・役割・期限などは返さない（D7）。
- それ以外（不一致・期限切れ・処理済み・取消・公演終了）は、すべて同じ404 `stageart_participant_invitation_link_invalid`。
- レート制限の対象（§9.2）。このAPIを置かない（初期値なしで登録させる）案もある（§16.2 U14）。

**`POST /participant-invitations/{id}/accept`**（ログイン必須）

- 本文：なし（任意で `token`。リンクから開いた場合の確認用。ただし、権限の根拠にはしない）。
- 処理（1トランザクション）：
  1. 公演を FOR UPDATE でロックする。
  2. 招待を FOR UPDATE でロックする。
  3. 招待が既に ACCEPTED で、`invitee_person_id` が本人なら、最初の結果をそのまま返す（冪等。通知・監査ログは追加しない）。
  4. §4.3 の条件を確認する。公演の終了（D5）・招待者の権限喪失（D3）なら、招待を CANCELLED にし、監査ログ `INVITATION_CLOSED` を記録して、コミットしてから409を返す。
  5. 参加者の行を FOR UPDATE で読み、同じ (公演, PERSON, 本人, 種別) の行の有無と状態に応じて、作成・再有効化・承認・同意の記録をする（§5.2）。`display_name` には、管理者が入力した `invited_name` を使う（既存の方針）。
  6. 参加者が ACTIVE になった場合は、本人のこの公演での担当者の行のうち ACTIVE のものを INACTIVE にする（§4.6 DORMANT_ON_ENROLLMENT）。
  7. 招待を ACCEPTED にし、`invitee_person_id`・`responded_at`・`participant_id` を記録する。
  8. 監査ログを記録する（`ACCEPTED`、必要に応じて `CONSENT_RECORDED`・`DELEGATE_DEACTIVATED`）。
- コミットした後に、招待者へ通知する（§11）。
- 応答：200。項目は `participant_id`・`production_id`・`status=ACTIVE`。
- エラー：403（宛先が違う・アカウントが無効・未確認。内容は返さない）、404（`link_invalid` と同じ扱い）、409 `stageart_participant_invitation_not_pending`（取消済み・辞退済み・他人が処理済み）、409 `stageart_participant_invitation_closed`（`close_reason` 付き。公演の終了・招待者の権限喪失）、410 `stageart_participant_invitation_expired`、429（レート制限）。

**`POST /participant-invitations/{id}/reject`**（ログイン必須）

- アカウント・宛先・PENDING・期限内の条件と、ロックの順序は accept と同じ。公演の終了・招待者の権限喪失は確認しない【推奨】（§4.3、U7）。
- 招待を REJECTED にし、`invitee_person_id`・`responded_at` を記録する。参加者は作らず、既存の参加者の行も変更しない。
- 既に REJECTED で本人の辞退なら、同じ結果を返す（冪等）。
- 招待者に「辞退されました」と通知する。辞退者の氏名は通知に含めず、招待時に管理者が入力した氏名を使う。

**`POST /participants/{id}/withdraw`**（ログイン必須）：本人による参加辞退

- 対象の参加者が PERSON で、`subject_id` が本人であり、ACTIVE の場合だけ実行できる。WITHDRAWN にする。
- 同じトランザクションで、本人のこの公演での担当者の行をすべて INACTIVE にする（D9）。行は削除しない。
- PrimaryManager 本人は辞退できない（409 `stageart_participant_withdraw_primary_manager`。「先に管理者を引き継いでください」）。
- コミットした後に、管理者（PrimaryManager と、有効な PARTICIPANT_MANAGER）に通知する。
- 他人の参加者IDでは403。既に WITHDRAWN なら同じ結果を返す（冪等）。

### 7.3 `ParticipantResult` の個人情報（【確定】D11と整合）

公演の状態によって、閲覧できる範囲を分ける。**データの保持（削除しない）と、閲覧の権限は別に扱う**（D11）。

**公演が PLANNING／ACTIVE の場合**

| 項目 | 管理者（canManage） | 閲覧のみ（REHEARSAL_MANAGER） | 本人 |
|---|---|---|---|
| `display_name`（公演側の氏名） | ○ | ○ | ○ |
| `person_family_name`／`person_given_name` | ACTIVE・INACTIVE のみ | ACTIVE・INACTIVE のみ | ○ |
| `email` | ACTIVE・INACTIVE のみ | × | ○ |
| `enrollment_source`・`consent_status` | ○ | × | ○ |
| CANCELLED／WITHDRAWN／REJECTED の行 | `display_name` と状態のみ | 行を返さない | — |

**公演が COMPLETED／ARCHIVED／CANCELLED の場合**【推奨】（§16.2 U3で確認）

| 項目 | PrimaryManager | その他の管理者 | 閲覧のみ | 本人 |
|---|---|---|---|---|
| `display_name` | ○ | ○ | ○ | ○ |
| 姓・名 | ACTIVE・INACTIVE のみ | ACTIVE・INACTIVE のみ | × | ○ |
| `email` | ACTIVE・INACTIVE のみ | × | × | ○ |

- 精算・実績集計・活動履歴（参加公演の一覧）は、参加者の行（ID・`display_name`・状態）を従来どおり参照できる。これらはメールアドレスを必要としない。
- 終了後もメールが必要な業務（精算の連絡など）があるかは、U3で確認する。

### 7.4 UseCase の構成

- `AcceptParticipantInvitationUseCase`・`RejectParticipantInvitationUseCase`・`ListMyParticipantInvitationsUseCase`・`LookupParticipantInvitationUseCase`・`GetInvitationRegistrationHintUseCase`・`WithdrawParticipationUseCase` を新設する。
- `InvitationRecipientVerifier`（Application）を新設する。`FindPersonByEmailUseCase` を使って「この招待のメールの持ち主が本人か」を判定し、UserAccount の状態も確認する。招待の取得系と承諾系で共有する。比較は §6.3 の正規化で行う。
- 参加者の作成は、`CreateParticipantUseCase` の重複確認と作成部分を、権限確認を含まない内部サービス（例：`ParticipantEnrollmentService::enrollFromInvitation()`）に分けて共有する。外部APIの `CreateParticipantUseCase` は、NAME_ONLY 専用に縮小する。
- `ParticipantDelegateRoleReleaser`（仮称）を新設し、参加者が ACTIVE でなくなったとき・ACTIVE になったときの担当者の行の INACTIVE 化（§4.6）を1か所にまとめる。withdraw・DELETE・PUT・accept・参加申請の承認から呼ぶ。
- `ParticipantEventRecorder`（仮称）を新設し、監査ログの記録を1か所にまとめる。
- `ProductionAuthorizationService` の `activeDelegateFor()`・`activeDelegatesFor()` に、ACTIVE な PERSON 参加者であることの確認を加える。`GetMyDashboardUseCase`・`ListProductionsUseCase` も同じ判定を使う。参加者の確認を毎回全件読み込みで行うと遅くなるため、`existsActivePersonParticipant(productionId, personId)` のような Repository のメソッドを追加する【推奨】。
- `ResolveParticipantInvitationUseCase` は削除する。呼び出し元の3か所からも外す（団体招待の解決の呼び出しは残す）。
- `SearchPersonByEmailUseCase`・`GetPersonByIdUseCase`・`PersonRestController` は削除する。

## 8. 権限マトリクス

「担当者」は、§4.6 の**有効な担当者**（行が ACTIVE で、かつ公演の ACTIVE な PERSON 参加者）を指す。参加者でない人物の担当者の行は、行が ACTIVE でも、表のどの担当者の列にも当たらない（「その他のログイン利用者」として扱う）。

| 操作 | PrimaryManager | 代理人（PARTICIPANT＋REHEARSAL） | PARTICIPANT_MANAGER | REHEARSAL_MANAGER など他の担当者 | ACTIVE 参加者（本人） | 招待された本人 | 無効な担当者の行だけを持つ人 | その他のログイン利用者 | 未ログイン |
|---|---|---|---|---|---|---|---|---|---|
| 招待の作成・再送・取消 | ○ | ○ | ○ | × | × | × | × | × | × |
| 招待一覧（公演） | ○ | ○ | ○ | × | × | × | × | × | × |
| 自分宛ての招待一覧・内容確認（lookup） | 本人宛てのみ | 同左 | 同左 | 同左 | 同左 | ○ | 本人宛てのみ | 本人宛てのみ（なければ空） | × |
| 登録画面の初期値（registration-hint） | — | — | — | — | — | — | — | — | メールだけ（有効なトークン） |
| 承諾・辞退 | 本人宛てのみ | 同左 | 同左 | 同左 | 同左 | ○（確認済みアドレス・ACTIVE アカウント） | 本人宛てのみ | 本人宛てのみ | × |
| 参加者一覧（メールあり） | ○ | ○（公演の終了後は×） | ○（公演の終了後は×） | × | × | × | × | × | × |
| 参加者一覧（メールなし） | ○ | ○ | ○ | REHEARSAL_MANAGER のみ | × | × | × | × | × |
| 役割・備考・公演側の氏名の変更 | ○ | ○ | ○ | × | × | × | × | × | × |
| 管理者による削除（CANCELLED）・INACTIVE | ○ | ○ | ○ | × | × | × | × | × | × |
| 本人による辞退（WITHDRAWN） | ×（引き継ぎが先） | 本人のみ | 本人のみ | 本人のみ | 本人のみ | — | — | × | × |
| 参加申請の承認・却下 | ○ | ○ | ○ | × | × | × | × | × | × |
| NAME_ONLY の追加 | ○ | ○ | ○ | × | × | × | × | × | × |
| 人物ID・団体IDでの直接追加 | × | × | × | × | × | × | × | × | × |
| 担当者の任命・再有効化（対象は ACTIVE な PERSON 参加者のみ） | ○（全役割） | ○（4役割） | × | × | × | × | × | × | × |
| 担当者一覧（有効・無効の区別） | ○ | ○ | × | × | × | × | × | × | × |
| メールからの人物検索・人物IDからの氏名取得 | × | × | × | × | × | × | × | × | × |
| 監査ログの閲覧 | ×（APIなし） | × | × | × | × | × | × | × | × |

- 管理者の権限は、既存の `canManageParticipants`・`canReadParticipants`・`canManageProductionDelegateRole` を使う。担当者の有効性の判定（§4.6）が、これらの内部に加わる。
- 「無効な担当者の行だけを持つ人」は、公演の閲覧（`canReadProduction`）・稽古の閲覧（`isProductionMember`）もできなくなる。ただし、その人が ACTIVE な PERSON 参加者になれば、参加者としての閲覧はできる（担当者の権限は、改めて任命されるまで戻らない）。
- 団体の OWNER・ORGANIZATION_MANAGER であることだけでは、公演の参加者を管理できない（既存どおり、PrimaryManager または有効な担当者である必要がある）。

## 9. 個人情報の取得・表示ルールと不正利用対策

### 9.1 個人情報の取得・表示

| 時点 | 管理者が見られるもの | 招待された本人が見られるもの |
|---|---|---|
| 招待前 | 自分で入力した氏名・メールだけ。アカウントの有無・人物の氏名は分からない（F5の廃止と、招待作成の応答の統一） | — |
| 招待中 | 招待の状態（承諾待ち・期限切れ・無効）、入力した氏名・メール・役割・備考、再送の回数、送信の結果 | ログイン後、本人宛てと確認できた場合だけ：公演名・団体名・役割・招待者の公演側の表示名・期限（D7）。未ログインでは、登録画面の初期値のメールアドレスだけ |
| 辞退後 | 「辞退」という状態と、再招待できる日時だけ（辞退した人物のID・氏名・アカウントの有無は分からない） | — |
| 取消・期限切れ・無効化の後 | 状態と理由だけ | 招待は一覧から消える。リンクから開いた場合は、本人宛てなら状態を表示する |
| 承諾後（ACTIVE） | 公演側の氏名（`display_name`）、人物の姓・名、通知先のメール（`PersonEmailResolver`）、同意の状態。承諾画面で、共有されることを本人に明示する | 自分の参加公演 |
| 本人の辞退・管理者の削除の後 | `display_name` と状態だけ（姓・名・メールは返さない） | — |
| 公演の終了後 | §7.3（メールは PrimaryManager だけ【推奨】） | 自分の参加公演（履歴） |

- 他の公演・団体の管理者は、この公演の招待・参加者情報を取得できない（各APIで公演単位の権限を確認する。既存の構造を維持する）。
- 未確認のアドレスは、本人の判定にも、検索にも使わない（F6・F5）。
- 承諾前の招待者への通知に、本人の氏名やアカウント情報は入れない。
- ログ（`error_log`）にメールアドレスを出力している箇所（`ResolveParticipantInvitationUseCase`）は、削除によりなくなる。新しいコードでは、IDだけを出力する。

### 9.2 招待APIの不正利用対策（第2版で追加、レビュー指摘5.6）

**レート制限**【推奨】（具体的な数値と保存先は §16.2 U4）

| 対象 | 単位 | 上限の例 | 超えた場合 |
|---|---|---|---|
| 招待の作成・再送 | 操作する人物ごと | 1時間に30件 | 429 `stageart_rate_limited`（`retry_after` 付き） |
| 招待の作成・再送 | 公演ごと | 1日に100件 | 同上 |
| `registration-hint`・`lookup` | 接続元IPごと | 10分に20回 | 同上 |
| トークンの照合の失敗（`link_invalid`） | 接続元IPごと | 10分に10回 | 同上（以後は一致するトークンでも429） |
| `accept`・`reject` の失敗（403・404） | 人物ごと | 10分に10回 | 同上 |

- 現在、プラグインにレート制限の仕組みはない（§1.7）。新しく作る必要がある。
- 保存先の候補：WordPressの transient（オブジェクトキャッシュがなければ `wp_options` に書き込む）、または新しい小さな表（`stageart_rate_limit_buckets`：キーのハッシュ・時間枠・回数）。複数のPHPプロセスで正しく数えるには、表のほうが確実【推奨】。表を採用する場合は、スキーマ1.49.0に含める。
- 接続元IPは、リバースプロキシの構成（ConoHa）によって `REMOTE_ADDR` が正しくない場合がある。実装時に確認する。
- 再送の上限（D6）と辞退後30日の制限は、レート制限とは別に、業務上の制限として常に適用する。

**トークンの扱い**

- トークンは、ログ（`error_log`・アクセスログ・例外メッセージ・エラー報告）に出力しない。新しいコードで、トークン・メールアドレスを含む例外メッセージを作らない。
- APIへは、トークンをクエリではなく、POSTの本文で送る（`lookup`・`registration-hint`）。
- 招待メールのリンクでは、トークンをURLのフラグメント（`/participant-invitations/open#token=...`）に入れ、Webサーバーのアクセスログに残らないようにする【推奨】。Expo Router（Web・ネイティブのディープリンク）でフラグメントを読めるかは、実装時に確認する（§16.2 U5）。読めない場合はクエリを使い、画面を開いた直後に `router.replace` でURLからトークンを取り除く。
- 古いメールのリンク（`/register?token=...`）は、クエリにトークンが入ったままのため、Frontendを配信するサーバーのアクセスログに残りうる。古いリンクの期限（24時間）が過ぎれば無効になる。
- トークンは照合したら、画面の状態（メモリ）とログイン後に戻るための一時保存（Webでは `sessionStorage`、ネイティブでは `SecureStore`）にだけ置き、URLの `return_to` に入れない。

**画面とHTTPヘッダー**

- 招待確認画面・登録画面では、第三者（解析・広告など）へ情報を送るスクリプトを読み込まない。現行のFrontendに解析タグがあるかは、実装時に確認する。
- 招待確認画面（Web）に `Referrer-Policy: no-referrer`（またはmetaタグ）を設定し、外部リンクへ移動したときにURLが送られないようにする。
- `lookup`・`registration-hint`・`accept`・`reject`・`GET /me/participant-invitations` の応答に `Cache-Control: no-store` を付ける。

**照合に失敗したときの応答**

- 一致しないトークン、本人宛てでない処理済みの招待は、すべて同じ404 `link_invalid` を返す。招待先の存在・アカウントの有無・招待の状態を推測できる情報を返さない。
- `registration-hint` は、有効なトークンにだけメールアドレスを返す。それ以外は同じ404。
- 本人宛てでない PENDING の招待に `lookup` した場合だけ、403 `not_for_you` を返す。リンクを持っている人に「別のアドレスでログインし直す」ことを案内するために必要で、アカウントの有無は含まない。

**アカウントの有無の推測の抑制**

- 招待作成の応答は、宛先のアカウントの有無にかかわらず、同じ形・同じ状態コードにする。
- 処理の内容も揃える：宛先の判定（`FindPersonByEmailUseCase`）は常に実行し、アプリ内通知の作成だけが既存利用者で増える。
- アプリ内通知の作成は、コミットの後に行う。応答を返した後に行う方法（WP-Cron の `wp_schedule_single_event()` で遅らせる、または `fastcgi_finish_request()` の後に行う）は、確実性と環境の制約があるため、R14-Bでは採用せず、応答の前に同期で行う【推奨】。時間差による推測の余地は残る（§16.3）。可能な限り抑えるための方式は §16.2 U8。

## 10. Frontend画面遷移

| 画面 | 変更 |
|---|---|
| 公演のメンバー管理（`productions/[id]/participants.tsx`） | 「メンバーを追加」を「メンバーを招待」に変える。結果の文言は「招待メールを送信しました。本人が承諾するとメンバーに追加されます」と「承諾待ちの招待に再送しました」の2種類にする（「メンバーを追加しました」をなくす）。「招待中のメンバー」の表に、承諾待ち・期限切れ・無効（公演終了）・辞退（再招待できる日時）・取消・送信失敗の状態、再送の残り回数、再送・取消の操作を表示する。参加者の一覧に「旧方式（同意未確認）」の表示を加える（D2）。更新時に `status: 'ACTIVE'` を送らないようにする。PERSON・ORGANIZATION を直接追加する操作をなくす（D10） |
| 担当者（`productions/[id]/delegates.tsx`） | 人物IDの自由入力欄を削除し、ACTIVE な PERSON 参加者の候補からの選択だけにする。一覧を「有効な担当者」と「無効な担当者（参加者ではない・解除済み）」に分けて表示する。無効な行には、参加者に招待するよう案内する |
| 招待のリンク（新設：`/participant-invitations/open`） | トークンを受け取り、ログイン済みなら `lookup` を呼んで招待確認画面へ。未ログインなら、公演名などを出さずに「ログイン」「登録」を選ぶ画面を表示する（§4.2） |
| 招待確認（新設：`(app)/participant-invitations/[id].tsx`） | 公演名・団体名・役割・招待者・期限・共有される情報の説明と、「参加する」「参加しない」のボタン（D7）。既に参加者なら、その旨を表示する（D2）。宛先が違う場合（403）は、「招待を受け取ったアドレスでログインし直してください」と表示し、ログアウトして入り直す操作を出す。期限切れ（410）・取消・公演終了（409）は、それぞれの文言を表示する |
| 自分宛ての招待（ホーム・マイページ） | 承諾待ちの招待があれば、ホームに件数とリンクを表示する（新しい一覧画面は作らず、確認画面への導線だけにする）。アプリ内通知（`my-notifications.tsx`）からも確認画面に移動する（`target_type=PARTICIPANT_INVITATION`） |
| 登録（`register.tsx`） | 招待リンクから来た場合は、`registration-hint` で取得したメールアドレスだけを初期値にする（公演名・氏名の事前表示はやめる。D7）。登録が完了したら、自動では参加させず、招待確認画面に移動する。古いリンク（`/register?token=`）で開かれた場合は、URLからトークンを取り除き、同じ導線に乗せる |
| ログイン（既存） | 招待リンクから来た場合は、ログイン後に招待のリンクの画面へ戻る（`return_to`）。`return_to` は、アプリ内のパスだけを受け付けるよう検証する（R13 Medium の修正を同時に行う）。トークンは `return_to` に入れず、一時保存から読む（§9.2） |
| 参加公演（`participating-productions.tsx`） | 各公演に「参加をやめる」（`withdraw`）を追加し、確認ダイアログを表示する。担当者を兼ねている場合は「担当者の役割も解除されます」と書く（D9）。PrimaryManager の公演には表示しない（引き継ぎの案内を出す） |
| 招待メール | リンク先を `/participant-invitations/open#token=...`（§9.2、U5）に変える。本文に「承諾するまで参加者にはなりません」「有効期限は7日間です」と書く。登録済みかどうかで文面を変えない |

団体メンバー管理（`organizations/[id]/members.tsx`・`invite.tsx`）とは画面を分けたままにする。公演の参加者は、団体のメンバーかどうかにかかわらず、公演ごとに招待する。団体招待の画面と動作は、R14-Bでは変更しない（D1）。

## 11. 通知仕様

### 11.1 通知の種類

既存の `NotificationDispatcherInterface` を使う（アプリ内とメール）。type は既存の命名（小文字のスネークケース）に合わせる。

| type | 宛先 | 契機 | message（例） | target |
|---|---|---|---|---|
| `participant_invited` | 招待先のメールが確認済みアドレスとして一致する人物（いれば） | 招待の作成・再送 | 「『{公演名}』から{役割}として招待されています」 | `PARTICIPANT_INVITATION`／招待ID |
| `participant_invitation_accepted` | 招待者 | 承諾 | 「{招待時の氏名}さんが『{公演名}』への招待を承諾しました」 | `PRODUCTION`／公演ID |
| `participant_invitation_rejected` | 招待者（権限がなければ PrimaryManager） | 辞退 | 「{招待時の氏名}さんが『{公演名}』への招待を辞退しました」 | 同上 |
| `participant_invitation_closed` | PrimaryManager【推奨】（U10） | 承諾時の無効化（INVITER_REVOKED） | 「{招待時の氏名}さん宛ての招待は、招待者の権限がなくなったため無効になりました。必要なら改めて招待してください」 | 同上 |
| `participant_withdrawn` | PrimaryManager と、有効な PARTICIPANT_MANAGER | 本人による辞退 | 「{公演側の氏名}さんが『{公演名}』の参加をやめました」 | 同上 |

- 招待メール（未登録者を含む全員宛て）は、既存の `ParticipantInvitationMailer` を使い、文面を1種類にする。
- 通知の文面には、管理者が入力した氏名（`invited_name`）を使う。本人の氏名・メールを、承諾前に管理者側へ出さない。
- 承諾の通知は、D3により承諾が成立するのは招待者に権限がある場合だけなので、常に招待者に送る。

### 11.2 送信の順序（第2版で追加、レビュー指摘5.3）

- **招待・承諾・辞退・参加辞退のDB更新を、先にコミットする。**メールの送信とアプリ内通知の作成は、コミットの後に行う。
- 通知の失敗によって、招待や承諾のDB処理をロールバックしない。APIの応答も成功のままにする。
- 招待の作成・再送の場合も、招待の行（新しいトークンのハッシュ・期限・`resend_count`）をコミットしてから、メールを送る。メールの送信が失敗しても、招待は作られたまま残る。
- 現在、招待の作成は、メールの送信をUseCaseの最後で行っている（トランザクションの外かどうかは、実装時に確認する）。R14-Bで、上の順序を明示する。

### 11.3 送信失敗の記録と再送

- 招待メールは、`StageArtMail::send()` の戻り値（`wp_mail()` の結果）で成否を判定し、招待の行の `last_send_status`（SENT／FAILED）と `last_sent_at` を、送信の後に別の短い更新で記録する。失敗した場合は、監査ログに `SEND_FAILED` を記録する。
- `wp_mail()` が true を返しても、相手に届いたことは保証されない（メールサーバーが受け付けただけ）。届かなかったことは、管理者からの再送の依頼で対応する。
- 管理者の招待一覧に「送信失敗」と表示し、再送できるようにする。直前の送信が失敗だった場合の再送は、再送の回数に数えない【推奨】（U11）。
- アプリ内通知・通知メール（`NotificationDispatcher`）の失敗は、既存どおりログにだけ記録する。招待メールと違い、通知が届かなくても、本人はホームの件数・招待メールから招待を確認できる。

### 11.4 二重送信と状態の二重更新の防止

- 承諾・辞退・参加辞退は冪等にする。2回目の呼び出しは、状態を変えず、通知も監査ログも追加しない（通知は、実際に状態が変わった1回だけ送る）。
- 再送は、毎回トークンを差し替えるため、古いメールのリンクは無効になる。同じ再送のリクエストが二重に届いた場合は、2通のメールが送られ、`resend_count` が2増える（公演の行ロックで直列化されるため、状態の矛盾は起きない）。
- 非同期の仕組み（キュー）は導入しない。現在の通知基盤は同期で送る方式で、WP-Cron は稽古のリマインダーにしか使われていない（§1.6）。存在しない仕組みを前提にしない。

## 12. 既存データとの互換性

| 既存データ | 扱い |
|---|---|
| 直接追加された ACTIVE な PERSON 参加者（過去の P1・P3・P4） | **変更しない**（D2）。`enrollment_source`・`consented_at` は NULL のままで、「旧方式（同意未確認）」と表示する。同意済みとはみなさない。本人は参加公演の画面から辞退できる。管理者は通常の招待で再確認できる |
| PENDING の招待（R14-B以前に作成） | 承諾待ちとして扱う。期限（24時間）は既存の値のまま（D4）。古いメールのリンク（`/register?token=`）からも、確認画面への導線に乗せる。`resend_count` は0から数える |
| CONSUMED の招待 | 終端のまま残す。一覧では「参加済み（旧方式）」と表示する |
| CANCELLED の招待 | `close_reason` は NULL（理由不明）のまま |
| 参加者でない人物の担当者の行（P6b） | **行は変更しない。権限は有効にしない**（§4.6）。一覧では「無効（参加者ではない）」と表示する。その人が招待を承諾した場合は、その時点で行を INACTIVE にし、改めて任命する |
| ACTIVE 参加者である担当者の行 | 変更しない。権限もそのまま |
| ORGANIZATION の参加者 | 変更しない（D10）。新しい直接追加だけを止める |
| 参加申請中（PENDING）の行 | 変更しない。管理者の承認・却下、または本人の招待の承諾で処理される（§4.7） |
| 監査ログ | 1.49.0以前の操作は記録されない |
| 団体の招待・メンバー | R14-Bでは変更しない（D1、R14-C） |

既存データの確認用SQL（読み取りのみ。**Dev・Productionでは未実行で、実行には承認が必要**）。

```sql
-- 承諾待ちの招待（R14-B導入時に残っているもの）
SELECT status, COUNT(*) FROM wp_stageart_participant_invitations GROUP BY status;
-- PERSON 参加者の件数（状態別）。現行の記録からは、直接追加・招待・参加申請の
-- どれで作られたかを区別できない（招待の行に人物IDがないため）。
SELECT status, COUNT(*) FROM wp_stageart_participants WHERE subject_type = 'PERSON' GROUP BY status;
-- 参加者でない人物の担当者（R14-B以降、権限が無効になる行）
SELECT d.production_id, d.person_id, d.role FROM wp_stageart_production_delegates d
 WHERE d.status = 'ACTIVE' AND NOT EXISTS (
   SELECT 1 FROM wp_stageart_participants p
    WHERE p.production_id = d.production_id AND p.subject_type = 'PERSON'
      AND p.subject_id = d.person_id AND p.status = 'ACTIVE');
-- 上のうち、公演が進行中（PLANNING・ACTIVE）のもの（運用への影響の把握）
SELECT d.production_id, pr.status, d.role, COUNT(*) FROM wp_stageart_production_delegates d
  JOIN wp_stageart_productions pr ON pr.id = d.production_id
 WHERE d.status = 'ACTIVE' AND pr.status IN ('PLANNING', 'ACTIVE') AND NOT EXISTS (
   SELECT 1 FROM wp_stageart_participants p
    WHERE p.production_id = d.production_id AND p.subject_type = 'PERSON'
      AND p.subject_id = d.person_id AND p.status = 'ACTIVE')
 GROUP BY d.production_id, pr.status, d.role;
-- ORGANIZATION の参加者
SELECT production_id, subject_id, status FROM wp_stageart_participants WHERE subject_type = 'ORGANIZATION';
-- 大文字を含むメールアドレスの招待（正規化の影響の把握）
SELECT COUNT(*) FROM wp_stageart_participant_invitations WHERE BINARY email <> LOWER(email);
-- 照合順序の確認（§6.3）
SELECT TABLE_NAME, COLUMN_NAME, COLLATION_NAME FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'email'
   AND TABLE_NAME LIKE 'wp_stageart_%';
```

テーブル名・列名は `Installer` で確認済み（`stageart_production_delegates` の `person_id`・`role`・`status`）。接頭辞 `wp_` は環境に合わせる。

## 13. F5との統合方針

- `GET /people?email=` と `SearchPersonByEmailUseCase` を削除する。
- `GET /people/{id}` と `GetPersonByIdUseCase` を削除する。
- 削除の前提は、§1.5 の利用箇所の調査（Backend・Frontend・テスト・文書で、画面や他の機能からの利用がない）。**リポジトリの外の利用者の有無は確認できない**ため、リスクとして §16.3 に記載し、U6でユーザーに確認する。
- Frontend の `features/person/api.ts` の2つの関数と、`types/api.ts` の対応する型を削除する。`GetPersonByIdUseCaseTest` は、ルートがないことを確認するテスト（T23）に置き換える（テストの削除ではなく、仕様変更による書き換え）。
- `FindPersonByEmailUseCase` は内部用に限る（招待の宛先判定と、通知の宛先の判定だけに使う）。外部APIの応答には、その結果（アカウントの有無・人物ID・氏名）を一切出さない。
- 招待作成の応答を2種類に統一し、アカウントの有無で処理と応答が大きく変わらないようにする（§9.2）。
- 管理者が人物を特定できるのは、本人が承諾した後の参加者一覧だけになる。
- 団体側（`CreateOrganizationInvitationUseCase` の既存利用者分岐、`PARTICIPANT_ADDED` 相当の応答）にも同じ問題が残る。R14-Cで対応する（D1）。

## 14. テスト計画

### 14.1 PHPUnit（Application・Domain）

**招待・承諾・辞退**

| # | ケース | 期待 |
|---|---|---|
| T1 | 招待先の本人（確認済みアドレス、ACTIVE）が承諾する | ACTIVE の参加者が1件でき、招待は ACCEPTED（`invitee_person_id`・`participant_id`・`responded_at`）。`enrollment_source=INVITATION`・`consented_at` が入る。招待者に通知される。監査ログ `ACCEPTED` |
| T2 | 別のアカウント（アドレスが異なる）が、IDやトークンを使って承諾する | 403。参加者は作られず、招待は PENDING のまま |
| T3 | 招待先と同じアドレスを未確認で登録したアカウントが承諾する | 403（F6と一致） |
| T4 | 招待先のアドレスが、確認済みの通知先メールとしてだけ一致する（Googleアカウント） | 承諾できる |
| T5 | アドレスが複数の人物に一致する | 403 |
| T6 | SUSPENDED・DISABLED のアカウントが承諾する | 403。招待は PENDING のまま |
| T7 | 本人が辞退する | 参加者は作られず、招待は REJECTED。招待者に辞退の通知が届く |
| T8 | 取消済みの招待を承諾・辞退する | 409 |
| T9 | 期限切れの招待を承諾する | 410。再送後は承諾できる |
| T10 | 同じ公演・宛先・種別で2回招待する | 招待は1件で、再送になる（`resend_count=1`）。承諾後も参加者は1件 |
| T11 | 同じ招待を2回承諾する（逐次・同時） | 2回目も200で同じ結果。参加者は1件（UNIQUE とロック）。通知・監査ログは1回だけ |
| T12 | 既に ACTIVE な参加者が承諾する | 新しい行は作られず、招待は ACCEPTED。旧方式の行なら `consented_at` が記録される（`CONSENT_RECORDED`）。状態は ACTIVE のまま |
| T13 | CANCELLED・WITHDRAWN・REJECTED の行がある人が承諾する | 同じ行が ACTIVE に戻る（UNIQUE 違反にならない） |
| T14 | 参加申請中（PENDING）の人が承諾する | ACTIVE になり、`enrollment_source=INVITATION`。監査ログの `detail` に `superseded_join_request=true` |
| T15 | 招待者が承諾前に権限を失った（D3） | 409 `closed`。招待は CANCELLED(INVITER_REVOKED)、参加者は作られない。PrimaryManager の権限で代わりに成立しない |
| T16 | 公演が COMPLETED／ARCHIVED／CANCELLED（D5） | 招待・再送は409。承諾も409で、招待は CANCELLED(PRODUCTION_CLOSED)。一覧の `effective_status` は PRODUCTION_CLOSED（承諾前でも） |
| T17 | 権限のない人が招待・再送・取消・一覧を実行する | 403 |
| T18 | 他の公演の管理者が、この公演の招待を取消・一覧する | 403 |
| T19 | `POST /productions/{id}/participants` に PERSON・ORGANIZATION の任意のIDを送る | 422。参加者は作られない（D10） |
| T20 | `PUT /participants/{id}` で CANCELLED・REJECTED・WITHDRAWN を ACTIVE にする | 422 |
| T21 | 担当者の任命で、参加者でない人物IDを送る | 422 |
| T22 | 既存利用者のアドレスで招待する | 応答は未登録者の場合と同じ形（`INVITATION_SENT`）。人物ID・氏名は含まない |
| T23 | `GET /people?email=`・`GET /people/{id}` | ルートが存在しない（404） |
| T24 | 参加者一覧を REHEARSAL_MANAGER が取得する | メールなし。CANCELLED などの行は含まない |
| T25 | 本人が参加をやめる | WITHDRAWN になり、管理者に通知される。他人の参加者IDでは403。PrimaryManager は409 |
| T26 | 招待リンクから登録する | メールは確認済みになり、参加者は作られない（招待は PENDING のまま） |
| T27 | 自己登録の後にメールを確認する／Googleでサインインする | 参加者は作られない（自動解決の廃止）。F6のテスト（確認前に招待を取得しない）も成り立つ。団体招待の自動解決は従来どおり動く（D1） |
| T28 | 自分宛ての招待一覧 | 確認済みアドレスに一致する PENDING・期限内・公演が終了していないものだけを返す。他人宛ては含まない。項目は D7 の範囲だけ（氏名・備考・メールなし） |
| T29 | `lookup` を別のアカウントで開く | PENDING なら403で、内容を返さない。処理済みなら404 `link_invalid` |
| T30 | Migration 1.48.0 → 1.49.0 | 列とテーブルが追加される。既存の行は変化しない（`resend_count` の既定値0を除く）。2回実行しても同じ結果。新規インストールと一致する |

**有効期間・再送・再招待（D4・D6）**

| # | ケース | 期待 |
|---|---|---|
| T31 | 新しい招待の期限 | 作成から7日 |
| T32 | 再送 | トークンが変わり、古いトークンでは `lookup`・`registration-hint` が404。期限は再送の時点から7日 |
| T33 | 5回再送した後に、6回目の再送をする | 429 `resend_limit`。送信は最大6回（初回＋再送5回） |
| T34 | 同じ宛先・種別の PENDING がある状態で、招待を作成する（再送扱い）を6回目まで繰り返す | T33と同じく、再送の上限で429 |
| T35 | 辞退から29日後に、同じ公演・同じメール（別の種別を含む）で招待する | 409 `recently_rejected`、`available_at` 付き |
| T36 | 辞退から30日後に招待する | 新しい招待ができる。辞退した招待の行は残る |
| T37 | 辞退した招待のメールを大文字に変えて招待する | 409（正規化で同じ宛先と判定） |
| T38 | 大文字を含むアドレスで招待され、小文字の確認済みアドレスを持つ本人が承諾する | 承諾できる |
| T39 | 既存の招待（24時間で作られた PENDING）の期限 | Migrationの後も変わらない。再送すると7日になる |

**担当者（レビュー指摘5.1・5.4、D9）**

| # | ケース | 期待 |
|---|---|---|
| T40 | 参加者でない人物の ACTIVE な担当者の行が残っている | その人物の `canManageParticipants`・`canReadProduction`・`hasProductionCapability` などがすべて false。行は削除・変更されない |
| T41 | 同上の人物が、担当者の権限を使うAPI（参加者一覧・招待の作成・稽古の作成・会計など）を呼ぶ | 403 |
| T42 | 同上の人物の、ダッシュボードと自分の公演一覧 | その公演が含まれない（担当者としては。参加者でもなければ含まれない） |
| T43 | 同上の人物が招待を承諾して ACTIVE な参加者になる | 古い担当者の行は INACTIVE になり、権限は有効にならない。監査ログ `DELEGATE_DEACTIVATED`（`DORMANT_ON_ENROLLMENT`） |
| T44 | T43の後、管理者が改めて任命する | 同じ行が ACTIVE に戻り、権限が有効になる（新しい行は作られない） |
| T45 | ACTIVE 参加者で担当者の人が参加をやめる（WITHDRAWN） | 担当者の行がすべて INACTIVE になり、権限が止まる。行は削除されない |
| T46 | 管理者が担当者を兼ねる参加者を削除（CANCELLED）・INACTIVE にする | 担当者の権限が止まる |
| T47 | PrimaryManager（参加者でない）の権限 | §4.6 の変更の前後で変わらない |
| T48 | 他の正当な担当者（ACTIVE 参加者）の権限 | 変わらない |
| T49 | 担当者一覧 | `effective` と `ineffective_reason` が正しい |
| T50 | 会計モジュールの通知先（`CoreProductionCapabilityDirectoryAdapter`） | 無効な担当者は含まれない |

**参加申請との競合（レビュー指摘5.2）**

| # | ケース | 期待 |
|---|---|---|
| T51 | 招待の承諾の後に、管理者が同じ申請を承認・却下する | 409 `request_not_pending`。行は ACTIVE のまま |
| T52 | 管理者の承認の後に、本人が招待を承諾する | 200。招待は ACCEPTED、行は ACTIVE のまま |
| T53 | 管理者の却下の後に、本人が招待を承諾する | 200。行は REJECTED → ACTIVE |
| T54 | 参加申請の承認 | `enrollment_source=JOIN_REQUEST`・`consented_at` が入る。監査ログ `JOIN_APPROVED` |

**旧方式の参加者（D2）・個人情報（D7・D11）**

| # | ケース | 期待 |
|---|---|---|
| T55 | 旧方式の ACTIVE 参加者 | 一覧で `consent_status=UNCONFIRMED_LEGACY`。稽古・出欠・精算などの既存機能はこれまでどおり対象になる |
| T56 | 旧方式の参加者が招待を辞退する | 招待は REJECTED、参加者の行は ACTIVE のまま |
| T57 | `lookup`・`GET /me/participant-invitations` の応答 | 公演名・団体名・役割・招待者の表示名・期限だけ。管理者が入力した氏名・備考・メールを含まない |
| T58 | `registration-hint` | 有効なトークンではメールだけを返す。無効・期限切れ・処理済みでは、すべて同じ404 |
| T59 | 公演の終了後の参加者一覧（U3の推奨案で確定した場合） | メールは PrimaryManager だけに返る。行は削除されない |

**通知と不正利用対策（レビュー指摘5.3・5.6）**

| # | ケース | 期待 |
|---|---|---|
| T60 | 招待メールの送信が失敗する（`StageArtMail::send` が false） | 招待は作られたまま、`last_send_status=FAILED`、監査ログ `SEND_FAILED`。応答は成功 |
| T61 | 送信が失敗した招待を再送する | 再送でき、回数に数えない（U11の推奨案で確定した場合） |
| T62 | 承諾後の通知が失敗する | 承諾はロールバックされない |
| T63 | 招待の作成をレート制限の上限まで行う | 上限を超えると429。超えるまでは通常どおり |
| T64 | 一致しないトークンで `lookup` を繰り返す | 上限を超えると429 |
| T65 | 各処理のログ | トークン・メールアドレスを出力しない（ログの出力を差し替えて確認する） |
| T66 | `lookup`・`accept` などの応答ヘッダー | `Cache-Control: no-store` |

### 14.2 Jest（画面）

- 招待の結果の文言が2種類で、「メンバーを追加しました」が出ない。
- 招待中の表に、承諾待ち・期限切れ・無効（公演終了）・辞退（再招待できる日時）・取消・送信失敗、再送の残り回数が表示される。
- 参加者の一覧に「旧方式（同意未確認）」が表示される。
- 招待のリンク画面：未ログインでは公演名を出さず、ログイン・登録を選べる。ログイン済みなら確認画面へ進む。
- 招待確認画面：承諾・辞退・宛先違い（403）・期限切れ（410）・取消・公演終了（409）・既に参加者の表示。
- 登録画面：招待リンクからの登録で、メールだけが初期値になる。登録後に招待確認画面へ移動する。古いリンク（`/register?token=`）でも同じ導線になり、URLからトークンが消える。
- ログイン：`return_to` にアプリ外のURLを渡しても、そこへ移動しない。
- 担当者画面：人物IDの入力欄がない。有効・無効の担当者が分けて表示される。
- 参加公演：参加をやめる操作と、その確認（担当者の解除の説明）。PrimaryManager の公演には表示されない。
- 更新時に `status` を送らない。

### 14.3 実MySQL（隔離DB、REST）

- 承諾の同時実行（2接続で同じ招待を承諾し、参加者が1件になる）。
- 承諾と取消の競合（取消を確定すると承諾は409、ロールバックすると200）。
- 承諾と参加申請の承認・却下の競合（§4.7）。
- 再送の同時実行（`resend_count` が正しく数えられる）。
- 古い招待（R14-B以前の PENDING）からの承諾。
- 参加者でない担当者の行がある公演で、その人物のAPIがすべて403になり、PrimaryManager は影響を受けない。
- Migration（アップグレードと新規インストールの一致、既存の行が変化しないこと、既存の検証DBのチェックサム）。
- レート制限の表を採用した場合、複数のPHPプロセスで数え漏れがない。

### 14.4 既存テストへの影響

次のテストは、仕様の変更に合わせて書き直す必要がある。削除・無効化はしない。

- PHPUnit：
  - `ParticipantInvitationUseCaseTest`（既存利用者の直接追加、期限24時間）
  - `ParticipantInvitationRegistrationConnectionTest`（自動解決）
  - `ParticipantUseCaseTest`（PERSON の直接作成、`PUT` の status）
  - `ProductionDelegateUseCaseTest`（任意の人物の任命）
  - `GetPersonByIdUseCaseTest`（ルートの廃止の確認へ）
  - `SearchPersonByEmail` を呼ぶテスト（あれば）
  - `Authentication` 系の招待解決の確認
  - `ProductionAuthorizationService`・ダッシュボード・公演一覧のテストのうち、参加者でない人物を担当者として準備しているもの（準備データに ACTIVE な参加者を加える）
  - 参加申請の承認・却下のテスト（ロックの追加）
- Jest：`web-production-participants-add-existing-person`、`-add-member-ui`、`-add-unregistered-person`、`-invitation-list`、`register-invitation-*`、`participating-productions-*`、担当者画面のテスト。

担当者の有効性の変更（§4.6）は、担当者を準備データに使う多くのテストに影響する可能性がある。実装の最初に、影響するテストを洗い出す。

## 15. 実装順序と影響範囲

PR #7上で、1項目を1コミットにする。

1. **Domain**
   - 招待の状態（ACCEPTED／REJECTED）、`accept()`・`reject()`・`close(reason)`、再送の上限、期限7日、新しい列の属性
   - `ParticipantStatus::WITHDRAWN`、`Participant::withdraw()`・`reenroll()`・`recordConsent()`、`enrollmentSource`・`consentedAt`
   - Domainテスト
2. **Schema 1.49.0**：列・インデックス・監査テーブル（とレート制限の表）の追加。Repository（`findByIdForUpdate`、`findPendingForEmails`、`existsActivePersonParticipant`、辞退後30日の検索）。`SchemaUpgradePathTest`。
3. **承諾・辞退・自分宛て一覧・lookup・registration-hint のAPI**
   - `InvitationRecipientVerifier`、`ParticipantEnrollmentService`、`ParticipantDelegateRoleReleaser`、`ParticipantEventRecorder`
   - 通知と監査ログ、送信結果の記録
   - 参加申請の承認・却下へのロックの追加
4. **直接追加の経路を閉じ、担当者の有効性を変える**
   - `POST /participants` の PERSON・ORGANIZATION
   - 招待作成の既存利用者分岐、公演の状態・30日・再送の上限
   - 自動解決の削除（登録・確認・Google。団体招待の解決は残す）
   - `PUT` の status、担当者の任命の対象制限
   - **担当者の有効性の判定（§4.6）**：`ProductionAuthorizationService`、ダッシュボード、公演一覧
   - 3と同時に出さないと、承諾の手段がないまま追加の手段だけがなくなるため、**3と4は同じリリースにする**。担当者の有効性の変更も、承諾・再任命の手段と同時に出す
5. **F5**：`/people` の2つのAPIと、Frontendの未使用関数を削除する。
6. **個人情報の絞り込み**：`ParticipantResult`（REHEARSAL_MANAGER、終了済みの行、公演の終了後。D11）。
7. **本人による辞退**：API（担当者の役割の解除を含む）、参加公演の画面。
8. **不正利用対策**：レート制限、`Cache-Control`、トークンのログ出力の確認。
9. **Frontend**
   - メンバー管理の文言と状態表示、旧方式の表示
   - 招待のリンク画面・招待確認画面、登録・ログインからの導線（`return_to` の検証）、ホームの件数、通知からの遷移
   - 担当者画面（有効・無効の区別）
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
  - レート制限（新規）
- Frontend：
  - `productions/[id]/participants.tsx`、`productions/[id]/delegates.tsx`、`register.tsx`、ログイン画面、`participating-productions.tsx`、`home.tsx`、`my-notifications.tsx`
  - 新しい招待のリンク画面・招待確認画面
  - `features/participantInvitation/*`、`features/participant/*`、`features/productionDelegate/*`、`features/person/api.ts`
  - `types/api.ts`、`utils/errorMessage.ts`
- 規模の目安：Backend 35〜45ファイル、Frontend 15〜22ファイル、テスト 40ファイル前後（第1版から、担当者の有効性・レート制限・監査ログの分を増やした）。

## 16. 判断事項・未解決事項・リスク

### 16.1 確定した判断（ユーザー判断、2026-11-16）

| # | 事項 | 確定方針 | 反映した節 |
|---|---|---|---|
| D1 | 団体メンバーの招待 | R14-Cで本人同意の方式を導入する。公演参加者と団体メンバーの招待は別の機能として扱う。R14-Bでは団体招待の実装を変更せず、R14-Cまでの間は団体招待の既存の動作（既存利用者の即時追加・自動解決）が残る | §1.3、§7.1、§10、§13、§16.3 |
| D2 | 過去に同意なく追加された ACTIVE 参加者 | 既存データを維持する。一括で変更・削除しない。同意済みとみなさない。本人は辞退できる。管理者が旧方式の参加者を認識できる。将来、本人への再確認ができる。既存の稽古・出欠・タイムテーブル・精算・実績集計に影響を与えない。一斉の再招待・無効化は対象外 | §4.5、§6.1、§7.1、§12 |
| D3 | 承諾時に招待者が権限を失っている | 招待を無効にする（`close_reason=INVITER_REVOKED`）。参加者は作らない。PrimaryManager が代わりに承諾を認める暗黙の処理はしない。再び招待する場合は、現在の権限を持つ管理者が新しい招待を作る | §4.3、§5.1、§5.3、§7.2 |
| D4 | 招待の有効期間 | 7日間。再送時は再送の時点から7日間に更新し、古いトークンは無効にする。既存の招待の期限はMigrationで変更しない | §4.1、§5.3、§6.1、§6.4 |
| D5 | 公演の終了・中止時の未処理の招待 | 承諾時に判定する。COMPLETED／ARCHIVED／CANCELLED なら承諾を拒否し、`close_reason=PRODUCTION_CLOSED`。一覧では無効と表示する。公演の状態の変更時に一括で更新する処理は追加しない。新しい招待・再送も拒否する | §4.3、§5.1、§5.3、§7.1 |
| D6 | 再送・再招待の上限 | 初回の送信は含めず、再送は最大5回（初回を含めて最大6回）。再送時はトークンを更新し、期限を7日間延長する。6回目の再送は429。辞退後30日間は、同じ公演・同じメールアドレスへの新しい招待を拒否し、30日を過ぎれば作れる。辞退の履歴は削除しない。メールアドレスの正規化を統一する | §5.3、§6.1、§6.3 |
| D7 | 招待先に表示する情報 | 公演名・団体名・役割・招待者の公演側の表示名・期限と、共有される情報の説明だけ。管理者が入力した氏名・備考・内部メモ、不要な個人情報は承諾前に表示しない。未ログインの登録画面でメールアドレスを初期値にすることは許容するが、トークンだけで招待の詳細を返さない。未ログインの導線は、リンク → 登録・ログイン → 確認済みメールの照合 → 一致すれば確認画面、不一致なら入り直しの案内 | §4.2、§7.1、§7.2、§9.1、§10 |
| D8 | 監査ログ | 導入する。追記専用。氏名・メールアドレスを保存しない。操作した人・対象・種別・日時を記録する。閲覧権限を限定する。保持期間は将来変更できるようにし、現時点では無期限保持も確定しない | §6.2 |
| D9 | 本人の辞退時の担当者の権限 | 本人が WITHDRAWN になった時点で、担当者としての有効な権限も止める。担当者の行は削除しない。PrimaryManager 本人は通常の辞退ができず、先に管理者の権限を引き継ぐ | §4.6、§7.2、§10 |
| D10 | ORGANIZATION の参加者 | 当面、新しい追加を禁止する（直接追加は拒否、画面にも操作を出さない）。既存のデータは変更・削除しない。将来、団体の OWNER による承諾の方式を別に設計する。団体のメンバーであることを理由に、公演の参加者として扱わない | §4.4、§7.1、§10、§12 |
| D11 | 公演の終了後の参加者情報 | 保持する（削除しない）。精算・実績集計・活動履歴から参照できる状態を維持する。保持と閲覧権限を分け、終了後のメールアドレスなどの閲覧は必要最小限にする | §7.3、§9.1 |

### 16.2 未解決事項（実装前に確認・判断が必要）

| # | 事項 | 推奨・現状 |
|---|---|---|
| U1 | 再送の上限（D6）は招待1件ごとに数える。管理者が招待を取り消してから新しく作れば、上限を回避できる | 【推奨】同じ公演・同じメールアドレスへの送信を、招待をまたいで30日間で6回までに制限する。R14-Bに含めるかの判断が必要 |
| U2 | 旧方式（同意未確認）の ACTIVE 参加者を、担当者に任命できるか | 【推奨】任命できる（ACTIVE な参加者で、本人はいつでも辞退できるため）。画面に「同意未確認」と表示する。任命の前に同意を求める案もある |
| U3 | 公演の終了後（COMPLETED／ARCHIVED／CANCELLED）のメールアドレスの閲覧範囲（D11） | 【推奨】PrimaryManager だけに返す（§7.3）。終了後もメールが必要な業務（精算の連絡など）があるかの確認が必要 |
| U4 | レート制限の具体的な数値と保存先 | 【推奨】§9.2 の数値と、新しい表（`stageart_rate_limit_buckets`）。ConoHa のリバースプロキシで接続元IPが正しく取れるかの確認が必要 |
| U5 | 招待リンクのトークンをURLのフラグメントに入れられるか | 【推奨】フラグメント。Expo Router の Web・ネイティブのディープリンクで読めるかを、実装時に確認する。読めない場合はクエリにし、開いた直後に取り除く |
| U6 | `/people` の2つのAPIを、リポジトリの外で使っている利用者（独自のスクリプト・他のアプリ）がいるか | コードからは確認できない。ユーザーの確認が必要。いなければ廃止する |
| U7 | 公演の終了後・招待者の権限喪失後の招待を、本人が辞退できるか | 【推奨】辞退できる（参加者を作らず、害がないため）。辞退も拒否して招待を無効にする案もある |
| U8 | 招待作成の処理時間の差による、アカウントの有無の推測を、どこまで抑えるか | 【推奨】R14-Bでは、応答の形の統一と処理の共通化までにする。アプリ内通知の作成を遅らせる（WP-Cron）案は、実行の確実性に課題がある |
| U9 | Dev・Production の `email` 列の照合順序 | §12 のSELECTで確認する（承認が必要）。`_ci` 以外なら、比較の方法を見直す |
| U10 | 招待者の権限喪失で招待が無効になったときに、PrimaryManager に通知するか | 【推奨】通知する（管理者が改めて招待できるように）。通知しない案もある |
| U11 | 送信に失敗した招待の再送を、再送の回数に数えるか | 【推奨】直前の送信が失敗だった場合は数えない。`wp_mail()` の成功は配達を保証しないため、届かない場合の再送は数える |
| U12 | 監査ログの保持期間と削除の方針（D8） | 将来の運用・プライバシーの方針で決める。R14-Bでは削除の処理を作らない |
| U13 | 参加申請（Join Key）の作成時に、CANCELLED・REJECTED の行があると UNIQUE キーに当たる可能性（§1.1） | 動作は未確認。R14-Bの範囲外とし、実装時に確認する。不具合なら別の指摘として扱う |
| U14 | 未ログインの登録画面の初期値のための `registration-hint` を置くか | 【推奨】置く（D7で許容された範囲）。置かずに、登録画面で本人にメールを入力させる案もある |

### 16.3 リスク

- **機能の後退**：既存利用者を即座に追加できなくなり、承諾を待つ運用になる。稽古の出欠・タイムテーブル・精算は ACTIVE な参加者を前提にしているため、承諾されるまで対象にならない。公演の準備期間中の周知が必要。
- **既存の担当者の権限の停止**（§4.6）：参加者でない人物として任命された担当者は、R14-Bのリリースと同時に権限を失う。進行中の公演で実務をしている人がいれば、作業が止まる。リリース前に §12 のSELECTで件数を把握し、該当者を招待して、承諾後に改めて任命する手順を、管理者に周知する必要がある。PrimaryManager の権限は影響を受けない。
- **リリースの順序**：承諾API（§15の3）と直接追加の廃止・担当者の有効性の変更（4）を分けてリリースすると、参加者・担当者を増やす手段がなくなる。BackendとFrontendも同時に配布する必要がある（R11・R12と同じ制約）。
- **団体招待の問題が残る期間**（D1）：R14-Cまでの間、団体招待では既存利用者の即時追加と、Googleサインインを含む自動解決が残る。団体のメンバーになっても公演の参加者にはならない（D10、§4.4）ため、公演の参加者情報には影響しないが、団体のメンバー一覧でメールアドレスが見える問題は残る。
- **メールアドレスの大文字小文字**（R13 Medium）：招待と認証情報の照合が、DBの照合順序（`_ci`）に依存している。PHP側の比較を加えると、結果が食い違うおそれがある。§6.3 の方針に統一し、U9で照合順序を確認する。
- **`return_to` の未検証**（R13 Medium）：ログイン後に招待のリンクの画面へ戻す導線を追加するため、同時に検証を直さないと、オープンリダイレクトの経路が増える。
- **アクセストークンの有効期間**（R13 Low）：SUSPENDED にしたアカウントでも、発行済みのアクセストークンの期限までは有効。承諾APIでは、UserAccount の状態を都度確認して防ぐ（§4.3）。
- **応答時間からの推測**：既存利用者にだけアプリ内通知を作るため、招待作成の処理時間の差からアカウントの有無を推測できる余地が残る。完全な秘匿までは保証しない（U8）。
- **`/people` の外部利用者**：リポジトリの外に利用者がいる場合、廃止で動かなくなる（U6）。
- **レート制限の新設**：既存の仕組みがないため、新しく作る。接続元IPの判定（リバースプロキシ）を誤ると、正当な利用者がまとめて制限される、または制限が効かない（U4）。
- **担当者の有効性の判定の性能**：権限の判定のたびに参加者の確認が加わる。現在の `isActivePersonParticipant()` は公演の参加者を全件読むため、専用のクエリに置き換える（§7.4）。
- **既存テストの書き直し**：§14.4のとおり、多数のテストが現行の仕様（直接追加・自動解決・参加者でない担当者）を固定している。仕様変更として書き直す（削除・無効化はしない）。
- **古い招待メールのリンク**：`/register?token=` を、確認画面への導線に転送し続ける必要がある。クエリのトークンがアクセスログに残る（期限24時間で無効になる）。
- **メールの到達**：`wp_mail()` の成功は配達を保証しない。届かない場合は、管理者の再送で対応する（再送の上限の範囲内）。
- **MySQL 8.0／MariaDB での未検証**：既存のラウンドと同じ。
