# PR #7 R14-B：本人の同意に基づく公演参加者登録（N4・F5）設計

- 作成：2026-11-15
- 対象：Head `c6480c2`（R14-A後）、スキーマ1.48.0
- 状態：**設計のみ（未実装）**。レビュー後に実装ラウンドへ渡す。
- 確定方針（R14-B指示書 §3）：
  - 公演参加者への登録は、招待と本人の承諾を経由する方式に統一する。
  - 管理者が人物IDを指定して直接登録する方式は採用しない。
  - 同じ団体のメンバーであることだけを理由に、承諾なしで登録する方式も採用しない。
  - 団体メンバー管理と公演参加者管理は別の概念として扱う。

本文中の「管理者」は、PrimaryManager、または `Participant.Update` 権限を持つ ACTIVE な ProductionDelegate（PARTICIPANT_MANAGER）を指す（`ProductionAuthorizationService::canManageParticipants()`）。

---

## 1. 現状の仕組み

### 1.1 公演参加者（Participant）

- **Entity**：`Domain/Participant/Participant`
  - 主な属性：`subjectType`（PERSON／ORGANIZATION／NAME_ONLY）、`subjectId`、`participantType`（CAST／STAFF）、`status`、`displayName`（公演側の氏名）、`remarks`
  - 状態（`ParticipantStatus`）：DRAFT／ACTIVE／INACTIVE／CANCELLED／PENDING／REJECTED
- **テーブル** `stageart_participants`：`UNIQUE (production_id, subject_type, subject_id, participant_type)`。同じ人物・同じ種別の行は、状態にかかわらず1行しか持てない。
- **API**（`ParticipantRestController`、いずれもログイン必須）
  - `GET /productions/{id}/participants`：`canReadParticipants`（PrimaryManager、PARTICIPANT_MANAGER、REHEARSAL_MANAGER）。全状態の行と、人物の姓・名・メールを返す。
  - `POST /productions/{id}/participants`：`canManageParticipants`。PERSON／ORGANIZATIONは**任意のID**、NAME_ONLYは氏名だけで、即座に ACTIVE の行を作る。
  - `GET|PUT|DELETE /participants/{id}`：`canManageParticipants`。PUTは `status` を任意の値に変更できる（CANCELLED・REJECTED → ACTIVE も可）。DELETEは CANCELLED にする（物理削除ではない）。
- **参加申請**（`ParticipationRequestRestController`）：本人がJoin Keyで申請すると PENDING の行ができ、管理者が承認すると ACTIVE、却下すると REJECTED になる。**本人の意思が起点の既存経路**。
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
- **API**（`ParticipantInvitationRestController`）
  - `POST /productions/{id}/participant-invitations`：`CreateParticipantInvitationUseCase` が、メールで3通りに分岐する。
    1. **メールが既存利用者の確認済みアドレスなら、`CreateParticipantUseCase` で即座に ACTIVE の参加者にする**（N4）。応答 `outcome=PARTICIPANT_ADDED` には、参加者の姓・名・メールが入る。
    2. 同じ公演・メール・種別に有効な PENDING があれば、再送する（`INVITATION_RESENT`）。
    3. それ以外は新しい招待を作成し、メールを送る（`INVITATION_SENT`）。
  - `GET /productions/{id}/participant-invitations`：一覧（`canManageParticipants`）。
  - `POST /participant-invitations/{id}/resend|cancel`：PENDINGのみ。再送はトークンと期限を同じ行で差し替える。
  - `GET /participant-invitations/resolve?token=`：**認証なし**。公演名・メール・招待時の氏名・種別・状態を返す（登録画面の初期値に使う）。
- **自動解決**：`ResolveParticipantInvitationUseCase` が、メールに一致する有効な招待をすべて、招待者の権限で `CreateParticipantUseCase` に通して ACTIVE の参加者にする。呼び出し元は次の3つで、**本人が承諾する操作はない**。
  - `RegisterWithEmailUseCase`：招待リンクから登録した直後
  - `VerifyEmailUseCase`：メール確認の完了時（R14-A F6）
  - `AuthenticateWithGoogleUseCase`：Googleでサインインするたび（Googleがメールを確認済みとした場合）
- **招待メール**：`WordPressParticipantInvitationMailer`。リンクは `{registrationBaseUrl}/register?token=...`。

### 1.3 団体メンバー（Membership）と団体招待（OrganizationInvitation）

- **Membership**：役割は `RoleKey`（OWNER／ORGANIZATION_MANAGER／MEMBER）。参加申請（`/membership-requests`、Join Key）と承認・却下がある。
- **OrganizationInvitation**：公演側と同じ形をしている（PENDING／CONSUMED／CANCELLED、24時間、SHA-256、`by-token`、resend／cancel）。`CreateOrganizationInvitationUseCase` も、**既存利用者ならそのまま Membership（MEMBER）を作る**。`ResolveOrganizationInvitationUseCase` も、登録・確認・Googleサインインの各時点で自動的に解決する。
- **団体代理人の任命**（`AssignOrganizationDelegateUseCase`）は、ACTIVE な Membership を持つ人物に限られている。これは公演側にない制約。
- 団体メンバー一覧（`organizations/[id]/members.tsx`）にもメールが表示される。

### 1.4 公演担当者（ProductionDelegate）

- `POST /productions/{id}/delegates`（`CreateProductionDelegateUseCase`）は、**存在する任意の人物ID**を担当者にできる。参加者である必要はない。応答には対象者の姓・名が入る。
- 画面 `productions/[id]/delegates.tsx` には、参加者の候補のほかに、人物IDの自由入力欄がある。

### 1.5 人物情報の取得（F5）

- `GET /people?email=&production_id=`（`SearchPersonByEmailUseCase`）：管理者は、確認済みメールに一致する人物のID・姓・名を取得できる。一致しなければ404、複数の人物に一致すれば409。**アカウントの有無と氏名が分かる**。
- `GET /people/{id}`（`GetPersonByIdUseCase`）：**ログインしていれば誰でも**、任意の人物IDの姓・名を取得できる。
- 画面からの利用：`features/person/api.ts` に `searchPersonByEmail`・`fetchPersonById` が定義されているが、現行の画面からは呼ばれていない（R14-Bで `mobile-rn/src` を検索して確認）。
- `FindPersonByEmailUseCase` は、ログイン用の確認済み EmailCredential と、確認済みの NotificationEmail だけを対象にする（R14-A F6以降）。Googleアカウントのメールは ExternalIdentity に保存されないため、NotificationEmail 経由で一致する。

### 1.6 通知基盤

- `NotificationDispatcherInterface::dispatch(PersonId, type, payload)` → `WordPressNotificationDispatcher` が、アプリ内通知（`InAppNotificationAdapter`、`stageart_notifications`）とメール（`WordPressEmailNotificationAdapter`）に配信する。payload には `message`・`production_id`・`target_type`・`target_id` を入れられる。配信の失敗はログに記録するだけで、処理は止めない。
- 画面：`my-notifications.tsx`（`GET /me/notifications`）。

## 2. 問題となるAPI・画面・権限

| # | 経路 | 問題 |
|---|---|---|
| P1 | `POST /productions/{id}/participants`（subject_type=PERSON） | 任意の人物IDを、同意なく ACTIVE の参加者にできる。応答に姓・名・メールが入る。公演は誰でも作れるため、実質ログイン済みの誰でもできる（N4の本体） |
| P2 | 同上（subject_type=ORGANIZATION） | 任意の団体を、同意なく参加団体にできる |
| P3 | `POST .../participant-invitations` の既存利用者分岐 | 確認済みメールを入力するだけで即座に参加者になる。応答 `PARTICIPANT_ADDED` と姓・名・メールで、アカウントの有無と氏名が分かる |
| P4 | 自動解決（登録・メール確認・Googleサインイン） | 本人の承諾操作なしに参加者になる。Googleでは、サインインするたびに未処理の招待がすべて解決される |
| P5 | `PUT /participants/{id}` の `status` | 本人が抜けた行（CANCELLED）や、却下・申請中の行を、管理者が ACTIVE に戻せる |
| P6 | `POST /productions/{id}/delegates` | 参加者でない任意の人物を担当者にできる（参加者一覧・稽古などの閲覧権限が付く）。応答に姓・名が入る |
| P7 | `GET /people?email=`（F5） | メールからアカウントの有無と氏名が分かる |
| P8 | `GET /people/{id}` | ログイン済みの誰でも、任意の人物の氏名を取得できる |
| P9 | `GET /participant-invitations/resolve?token=` | 認証なしで、招待先のメール・氏名を返す（トークンの所持が前提のため影響は小さい。承諾画面の設計に合わせて縮小する） |
| P10 | `GET /productions/{id}/participants` | REHEARSAL_MANAGER（閲覧のみ）にもメールを返す。CANCELLED・REJECTED の行にも、人物の姓・名・メールを返す |
| P11 | 団体招待の既存利用者分岐と自動解決 | P3・P4と同じ問題が団体メンバーにもある（§16 未決事項 D1） |

## 3. 既存招待基盤の再利用可能な部分

新しいテーブル・Entityは作らない。`ParticipantInvitation` を「承諾待ちの招待」として拡張する。

| 再利用するもの | 用途 |
|---|---|
| `stageart_participant_invitations` と `ParticipantInvitation` | 招待の本体。列を追加し、状態を拡張する（§6） |
| トークンの生成・SHA-256保存・`findByTokenHash` | 招待メールのリンク。変更しない |
| `rotateToken()` と `ResendParticipantInvitationUseCase` | 再送。変更は最小限 |
| `CancelParticipantInvitationUseCase` | 管理者による取消。理由と実行者を記録するよう拡張 |
| `ListParticipantInvitationsUseCase` と画面の「招待中のメンバー」表 | 招待一覧。状態の表示を拡張 |
| `CreateParticipantUseCase` の重複確認と `Participant::create()` | 承諾時の参加者作成。管理者の権限確認の代わりに、本人の承諾を条件にする内部メソッドへ分ける（§7.4） |
| `Participant::approve()` | 参加申請中（PENDING）の行に、本人が承諾した場合の扱い |
| `FindPersonByEmailUseCase`（確認済みメールのみ） | 「この招待のメールが本人の確認済みアドレスか」の判定。外部APIからは使わない |
| `RegisterWithEmailUseCase` の招待トークン経路 | 招待リンクから登録した人のメールを確認済みにする。参加者は作らない |
| `NotificationDispatcherInterface` | 招待・承諾・辞退の通知（アプリ内とメール） |
| `ProductionRepositoryInterface::findByIdForUpdate` | 承諾・招待作成の直列化（ロック順序：公演 → 招待 → 参加者） |
| `TransactionManagerInterface` | 承諾の一連の処理を1トランザクションにまとめる |

## 4. 新しい業務フロー

### 4.1 招待（管理者）

1. 管理者が、公演の「メンバー管理」画面で、氏名・メール・役割（CAST／STAFF）・備考を入力し、「招待する」を押す。
2. `POST /productions/{id}/participant-invitations` は、**アカウントの有無にかかわらず、常に招待を作る**（同じ宛先の招待が承諾待ちなら再送する）。応答は「招待した」「再送した」の2種類だけで、**アカウントの有無も、人物の氏名も返さない**。
3. 招待メールを送る。
   - 宛先がStageArtの確認済みアドレスを持つ人物に一致する場合は、その人物にアプリ内通知も送る。ただし、通知したかどうかは管理者に返さない。
   - メールの文面は、登録済みかどうかにかかわらず1種類にする。リンクを開くと、ログイン済みなら承諾画面、未ログインならログインまたは登録の選択に進む。

### 4.2 承諾・辞退（本人）

1. 本人がリンクまたはアプリ内通知から、招待確認画面を開く。
2. 本人のアカウントでログインしていることを求める。未登録なら登録し、招待リンクからの登録ではメールを確認済みにする（既存のR14-A動作）。
3. 確認画面には、公演名・団体名・役割・招待者（公演側の表示名）・有効期限と、「承諾すると公演の管理者にあなたの氏名・メールアドレスが共有されます」という説明を表示する。
4. 本人が「参加する（承諾）」または「参加しない（辞退）」を選ぶ。
5. 承諾した場合だけ、参加者（ACTIVE）にする。辞退した場合は、参加者にしない。
6. 招待者（`invited_by_person_id`）に結果を通知する。招待者が管理権限を失っている場合は、現在のPrimaryManagerに通知する。

### 4.3 承諾の条件（すべて満たす場合だけ）

- 認証済みの StageArt アカウントであり、UserAccount が ACTIVE であること（SUSPENDED・DISABLED は不可）。
- 招待のメールが、**本人の確認済みアドレス**であること（ログイン用の確認済み EmailCredential、または確認済み NotificationEmail。`FindPersonByEmailUseCase` が本人の PersonId を返すこと）。
  - 未確認のアドレスしか持たないアカウントは承諾できない（F6と一致）。
  - 複数の人物に一致する場合（Ambiguous）は承諾できない。
- 招待が PENDING で、期限内であること。
- 公演が招待を受け付ける状態であること（§5.3）。
- 招待者が承諾の時点でも参加者を管理できる権限を持っていること（§5.3、未決事項 D3）。

**承諾は、本人がAPIを明示的に呼んだときだけ成立する**。メールの一致やログイン、登録、メール確認、Googleサインインだけでは成立しない（自動解決は廃止する）。トークンは招待を見つけるためのもので、承諾の権限の根拠にはしない。リンクを転送されても、別のアカウントでは承諾できない。

### 4.4 公演参加者の管理（承諾後）

- 管理者による削除（既存の `DELETE /participants/{id}`）は CANCELLED にする。本人による辞退（新設）は WITHDRAWN にして、区別する（§5.2）。
- 役割（CAST／STAFF）・備考・公演側の氏名は、管理者が変更できる（既存の `PUT`）。ただし、`status` を ACTIVE 以外から ACTIVE に戻す操作はできなくする（P5）。参加者に戻すには、もう一度招待して承諾してもらう。
- 担当者（ProductionDelegate）に任命できるのは、その公演の ACTIVE な PERSON 参加者だけにする（P6）。

## 5. 招待状態遷移

### 5.1 招待（ParticipantInvitation）

既存の命名（`ParticipantStatus` の REJECTED、招待の CANCELLED）に合わせる。

| 状態 | 保存 | 意味 |
|---|---|---|
| PENDING | ○ | 承諾待ち |
| ACCEPTED | ○（新設） | 本人が承諾し、参加者になった |
| REJECTED | ○（新設） | 本人が辞退した |
| CANCELLED | ○（既存） | 招待が無効になった（理由は `close_reason`：MANAGER＝管理者の取消、PRODUCTION_CLOSED＝公演の終了・中止、INVITER_REVOKED＝招待者の権限喪失、ACCOUNT_INACTIVE は使わない（§5.3）） |
| EXPIRED | ×（導出） | PENDING かつ `expires_at <= 現在`。既存の方針どおり保存せず、APIでは `effective_status` として返す |
| CONSUMED | ○（既存のみ） | R14-B以前に自動解決された招待。新しくは作らない。表示上は「参加済み（旧方式）」とする |

```
            resend（期限切れでも可。トークンと期限を差し替え）
              ┌──────┐
              ▼      │
 create ──► PENDING ─┴─► accept（本人）───────────► ACCEPTED
              │      ├─► reject（本人）───────────► REJECTED
              │      ├─► cancel（管理者）─────────► CANCELLED(MANAGER)
              │      ├─► 公演がCOMPLETED/ARCHIVED/CANCELLED ─► CANCELLED(PRODUCTION_CLOSED)
              │      └─► 承諾時に招待者が権限を喪失 ─► CANCELLED(INVITER_REVOKED)
              └─(expires_at経過)─► EXPIRED（導出。resendでPENDINGに戻る）
```

- ACCEPTED・REJECTED・CANCELLED・CONSUMED は終端の状態。同じ宛先にもう一度招待する場合は、新しい行を作る。
- REJECTED の後に再招待することは妨げない。ただし、管理者が同じ相手に繰り返し送り続けることを防ぐ上限は、未決事項 D6 とする。

### 5.2 参加者（Participant）

`ParticipantStatus` に **WITHDRAWN（本人による辞退）** を追加する。列は VARCHAR(20) なので、DDLの変更は不要。

| 遷移 | 実行者 | 経路 |
|---|---|---|
| （行なし）→ ACTIVE | 本人の承諾 | `POST /participant-invitations/{id}/accept` |
| CANCELLED／WITHDRAWN／REJECTED／INACTIVE → ACTIVE | 本人の承諾 | 同上（UNIQUEキーにより同じ行を再び ACTIVE にする） |
| PENDING（参加申請中）→ ACTIVE | 本人の承諾、または管理者の承認 | 承諾 API、または既存の承認 API |
| ACTIVE → CANCELLED | 管理者 | `DELETE /participants/{id}`（既存） |
| ACTIVE → WITHDRAWN | 本人 | `POST /participants/{id}/withdraw`（新設） |
| ACTIVE → INACTIVE | 管理者 | `PUT`（既存。ACTIVE への復帰は不可） |
| PENDING → REJECTED | 管理者 | 既存の却下 API |

### 5.3 招待の制約

| 場面 | 扱い |
|---|---|
| 同じ公演・同じメール・同じ種別に PENDING（期限内）がある | 新しく作らず、再送する（既存どおり）。作成は公演の行をロックして直列化し、二重に作らない |
| 同じ公演・同じメールで、種別が異なる | 別の招待として作れる（参加者のUNIQUEキーが種別ごとのため、既存どおり） |
| 再送 | 同じ行のトークンと期限を差し替える。古いリンクは無効になる。`last_sent_at`・`send_count` を記録する |
| 期限切れ | 承諾・辞退はできない（410）。管理者は再送できる |
| 既に ACTIVE な参加者への招待 | 作成時点では誰の招待か判定しない（アカウントの有無を漏らさないため）。承諾時に既に ACTIVE なら、新しい行は作らず、招待を ACCEPTED にする（冪等） |
| 公演が COMPLETED／ARCHIVED／CANCELLED | 招待の作成・再送はできない（409）。承諾時に該当すれば、招待を CANCELLED(PRODUCTION_CLOSED) にして409を返す。公演の状態が変わったときに未処理の招待をまとめて閉じるかどうかは、未決事項 D5 |
| 招待者が権限を失った | 承諾時に、招待者が `canManageParticipants` でなければ CANCELLED(INVITER_REVOKED) にして409を返す（既存の自動解決と同じ考え方。未決事項 D3）。再送・取消は、その時点の管理者なら誰でもできる（既存どおり） |
| 対象者のアカウントが SUSPENDED／DISABLED | 承諾できない（403）。招待は PENDING のまま残す（管理者に対象者の状態を知らせないため）。期限が来れば EXPIRED になる |
| 同時に2回承諾した | 公演 → 招待（FOR UPDATE）の順にロックし、2回目は「既に処理済み」として最初の結果を返す（冪等）。参加者のUNIQUEキーで二重登録も防ぐ |

## 6. データモデル・Migration案

### 6.1 `stageart_participant_invitations` に列を追加（スキーマ1.49.0、追加のみ）

| 列 | 型 | 用途 |
|---|---|---|
| `invitee_person_id` | CHAR(36) NULL | 承諾・辞退した本人。応答した時点で記録する |
| `responded_at` | DATETIME NULL | 承諾・辞退の日時 |
| `participant_id` | CHAR(36) NULL | 承諾で作成・再有効化した参加者 |
| `closed_at` | DATETIME NULL | 取消・無効化の日時 |
| `closed_by_person_id` | CHAR(36) NULL | 取消した管理者（自動の無効化ではNULL） |
| `close_reason` | VARCHAR(30) NULL | MANAGER／PRODUCTION_CLOSED／INVITER_REVOKED |
| `last_sent_at` | DATETIME NULL | 最後に送信した日時 |
| `send_count` | INT UNSIGNED NOT NULL DEFAULT 1 | 送信回数（再送の上限に使う、D6） |
| `updated_at` | DATETIME NULL | 更新日時 |

- インデックス：`KEY production_email_type (production_id, email, participant_type)`、`KEY invitee_person_id (invitee_person_id)`。
- 一意制約は追加しない。同じ宛先の履歴の行が並ぶため。PENDING の一意性は、公演の行ロックで保証する。
- `status` の値に ACCEPTED／REJECTED を追加する。VARCHAR(20) なので、DDLの変更は不要。
- `TOKEN_LIFETIME_SPEC` は、24時間から7日に延ばすことを推奨する（D4）。既存の利用者が承諾するまでに時間がかかるため。

### 6.2 参加者の監査ログ（新テーブル、追加のみ）

既存に汎用の監査ログはない（会計の移行監査だけ）。参加者の状態変更を記録する、追記専用の表を設ける。

```sql
CREATE TABLE {prefix}stageart_participant_events (
  id CHAR(36) NOT NULL,
  production_id CHAR(36) NOT NULL,
  participant_id CHAR(36) NULL,
  invitation_id CHAR(36) NULL,
  event_type VARCHAR(40) NOT NULL,   -- INVITED / RESENT / INVITATION_CANCELLED / ACCEPTED / REJECTED /
                                     -- REMOVED_BY_MANAGER / WITHDRAWN / TYPE_CHANGED / DEACTIVATED / JOIN_APPROVED / JOIN_REJECTED
  actor_person_id CHAR(36) NULL,     -- 操作した人（自動の場合はNULL）
  subject_person_id CHAR(36) NULL,   -- 対象の人物（承諾前は不明なのでNULL）
  detail TEXT NULL,                  -- JSON（変更前後の値など。メール・氏名は入れない）
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY production_id (production_id, created_at),
  KEY participant_id (participant_id)
);
```

- 監査ログには、メールアドレスと氏名を入れない（招待・参加者の行IDで追跡する）。
- 保持期間は定めない（既存の冪等性キーと同じ方針）。削除の要否は D8。
- 監査ログの追加を今回見送る場合でも、招待の列（6.1）と参加者の `updated_at` で、最低限の追跡はできる。

### 6.3 Migration（`Installer`、スキーマ1.48.0 → 1.49.0）

1. `dbDelta` で、6.1の列とインデックス、6.2のテーブルを追加する。既存の行は変更しない。
2. **データの更新はしない**。既存の PENDING の招待は、そのまま承諾待ちとして扱う。既存の CONSUMED は終端として残す。既存の ACTIVE な参加者には触れない。
3. 冪等にする（列・テーブルが既にあれば何もしない）。失敗時はバージョンを上げずに、次回もう一度実行する（R14-A N2と同じ形）。
4. 削除を伴う処理はない。

## 7. API仕様

### 7.1 変更・廃止

| API | 変更 |
|---|---|
| `POST /productions/{id}/participants` | `subject_type=PERSON`・`ORGANIZATION` を拒否する（422 `stageart_participant_requires_invitation`）。NAME_ONLY（アカウントを持たない、公演側だけの記録）は残す |
| `POST /productions/{id}/participant-invitations` | 既存利用者の分岐を削除し、常に招待する。公演の状態を確認する。応答の `outcome` は `INVITATION_SENT`・`INVITATION_RESENT` だけにし、`participant` は返さない |
| `GET /productions/{id}/participant-invitations` | 状態（`effective_status`）、`responded_at`、`close_reason` を追加する。招待先の人物ID・氏名は返さない（承諾後は参加者一覧で確認する） |
| `POST /participant-invitations/{id}/cancel` | `closed_by_person_id`・`close_reason=MANAGER` を記録する |
| `POST /participant-invitations/{id}/resend` | 公演の状態を確認する。`send_count`・`last_sent_at` を記録する。上限（D6）を超えれば429 |
| `GET /participant-invitations/resolve?token=` | **認証必須に変更**し、§7.2 の `GET /participant-invitations/by-token` に統合する。未ログインの場合は、登録画面の初期値に使う招待先メールだけを返す（氏名・公演名は返さない）。返すかどうかは D7 |
| `PUT /participants/{id}` | ACTIVE 以外への `status` 変更（INACTIVE）は残し、ACTIVE 以外から ACTIVE への変更は拒否する（422）。画面は `status` を送らないようにする |
| `GET /productions/{id}/participants` | メールは `canManageParticipants` の場合だけ返す（REHEARSAL_MANAGER には返さない）。人物の姓・名・メールは、ACTIVE と INACTIVE の行だけに付け、CANCELLED・WITHDRAWN・REJECTED の行には付けない（`display_name` だけ） |
| `POST /productions/{id}/delegates` | 対象者を、その公演の ACTIVE な PERSON 参加者に限る（422 `stageart_production_delegate_target_not_participant`）。既存の担当者の行は変更しない |
| `GET /people?email=` | **廃止**（F5）。ルートを削除し、Frontendの未使用関数も削除する |
| `GET /people/{id}` | **廃止**（現行の画面では使っていない）。必要になった場合は、閲覧者と共通の公演・団体がある場合に限って再設計する |
| 自動解決（登録・メール確認・Googleサインイン） | 公演の招待については、`ResolveParticipantInvitationUseCase` の呼び出しを削除する。招待リンクから登録した場合に、メールを確認済みにする処理は残す |

### 7.2 新設

すべてログイン必須で、本人の認証済みアカウントで実行する。

**`GET /me/participant-invitations`**：自分宛ての承諾待ちの招待一覧

- 本人の確認済みアドレス（ログイン用と通知用）に一致し、PENDING かつ期限内のものを返す。
- 応答の項目：`id`・`production_name`・`organization_name`・`participant_type`・`invited_by_display_name`（招待者の公演側の表示名。ない場合は「公演の管理者」）・`expires_at`。招待先のメール・管理者が入力した氏名・備考は返さない。管理者の内部メモになりうるため（D7）。

**`GET /participant-invitations/by-token?token=`**：リンクから招待を開く

- ログイン済みで、招待のメールが本人の確認済みアドレスなら、上と同じ項目を返す。
- 一致しない場合は403 `stageart_participant_invitation_not_for_you`（「このアカウント宛ての招待ではありません。招待を受け取ったアドレスでログインしてください」）。招待の内容は返さない。
- 無効・期限切れ・処理済みは、同じ404・410で返す。

**`POST /participant-invitations/{id}/accept`**

- 本文：なし（任意で `token`。リンクから開いた場合の確認用。ただし、権限の根拠にはしない）。
- 処理（1トランザクション）：
  1. 公演を FOR UPDATE でロックする。
  2. 招待を FOR UPDATE でロックする。
  3. §4.3 の条件を確認する。
  4. 参加者を、同じ (公演, PERSON, 本人, 種別) の行の有無と状態に応じて、作成・再有効化・承認する。`display_name` には、管理者が入力した `invited_name` を使う（既存の方針）。
  5. 招待を ACCEPTED にし、`invitee_person_id`・`responded_at`・`participant_id` を記録する。
  6. 監査ログを記録する。
- コミット後に、招待者へ通知する。
- 応答：200。項目は `participant_id`・`production_id`・`status=ACTIVE`。
- エラー：403（宛先が違う・アカウントが無効・未確認）、404（なし）、409 `stageart_participant_invitation_not_pending`（処理済み。ただし本人が既に ACCEPTED にしたものは200で同じ結果を返す）、409 `stageart_participant_invitation_closed`（公演の終了・招待者の権限喪失）、410 `stageart_participant_invitation_expired`。

**`POST /participant-invitations/{id}/reject`**

- 条件と同時実行の扱いは accept と同じ。
- 招待を REJECTED にし、`invitee_person_id`・`responded_at` を記録する。参加者は作らない。
- 招待者には「辞退されました」と通知する。辞退者の氏名は通知に含めず、招待時に管理者が入力した氏名を使う。

**`POST /participants/{id}/withdraw`**：本人による参加辞退

- 対象の参加者が PERSON で、`subject_id` が本人であり、ACTIVE の場合だけ実行できる。WITHDRAWN にする。
- 本人が担当者（ProductionDelegate）を兼ねている場合は、その担当者の行も停止する（D9）。PrimaryManager 本人は辞退できない（先に引き継ぎが必要）。
- 管理者（PrimaryManager と PARTICIPANT_MANAGER）に通知する。

### 7.3 `ParticipantResult` の個人情報

| 項目 | 管理者（canManage） | 閲覧のみ（REHEARSAL_MANAGER） | 本人 |
|---|---|---|---|
| `display_name`（公演側の氏名） | ○ | ○ | ○ |
| `person_family_name`／`person_given_name` | ACTIVE・INACTIVE のみ | ACTIVE・INACTIVE のみ | ○ |
| `email` | ACTIVE・INACTIVE のみ | × | ○ |
| CANCELLED／WITHDRAWN／REJECTED の行 | `display_name` のみ | 行を返さない | — |

### 7.4 UseCase の構成

- `AcceptParticipantInvitationUseCase`・`RejectParticipantInvitationUseCase`・`ListMyParticipantInvitationsUseCase`・`GetParticipantInvitationForInviteeUseCase`・`WithdrawParticipationUseCase` を新設する。
- `InvitationRecipientVerifier`（Application）を新設する。`FindPersonByEmailUseCase` を使って「この招待のメールの持ち主が本人か」を判定し、UserAccount の状態も確認する。招待の取得系と承諾系で共有する。
- 参加者の作成は、`CreateParticipantUseCase` の重複確認と作成部分を、権限確認を含まない内部サービス（例：`ParticipantEnrollmentService::enrollFromInvitation()`）に分けて共有する。外部APIの `CreateParticipantUseCase` は、NAME_ONLY 専用に縮小する。
- `ResolveParticipantInvitationUseCase` は削除する。呼び出し元の3か所からも外す。

## 8. 権限マトリクス

| 操作 | PrimaryManager | 代理人（PARTICIPANT＋REHEARSAL） | PARTICIPANT_MANAGER | REHEARSAL_MANAGER など他の担当者 | ACTIVE 参加者（本人） | 招待された本人 | その他のログイン利用者 | 未ログイン |
|---|---|---|---|---|---|---|---|---|
| 招待の作成・再送・取消 | ○ | ○ | ○ | × | × | × | × | × |
| 招待一覧（公演） | ○ | ○ | ○ | × | × | × | × | × |
| 自分宛ての招待一覧・内容確認 | 本人宛てのみ | 同左 | 同左 | 同左 | 同左 | ○ | 本人宛てのみ（なければ空） | × |
| 承諾・辞退 | 本人宛てのみ | 同左 | 同左 | 同左 | 同左 | ○（確認済みアドレス・ACTIVE アカウント） | × | × |
| 参加者一覧（メールあり） | ○ | ○ | ○ | × | × | × | × | × |
| 参加者一覧（メールなし） | ○ | ○ | ○ | REHEARSAL_MANAGER のみ | × | × | × | × |
| 役割・備考・公演側の氏名の変更 | ○ | ○ | ○ | × | × | × | × | × |
| 管理者による削除（CANCELLED） | ○ | ○ | ○ | × | × | × | × | × |
| 本人による辞退（WITHDRAWN） | ×（引き継ぎが先） | 本人のみ | 本人のみ | 本人のみ | 本人のみ | — | × | × |
| NAME_ONLY の追加 | ○ | ○ | ○ | × | × | × | × | × |
| 人物IDでの直接追加 | × | × | × | × | × | × | × | × |
| 担当者の任命（対象は ACTIVE 参加者のみ） | ○（全役割） | ○（4役割） | × | × | × | × | × | × |
| メールからの人物検索・人物IDからの氏名取得 | × | × | × | × | × | × | × | × |

- 管理者の権限は、既存の `canManageParticipants`・`canReadParticipants`・`canManageProductionDelegateRole` をそのまま使う。
- 団体の OWNER・ORGANIZATION_MANAGER であることだけでは、公演の参加者を管理できない（既存どおり、PrimaryManager または担当者として任命されている必要がある）。

## 9. 個人情報の取得・表示ルール

| 時点 | 管理者が見られるもの | 招待された本人が見られるもの |
|---|---|---|
| 招待前 | 自分で入力した氏名・メールだけ。アカウントの有無・人物の氏名は分からない（F5の廃止と、招待作成の応答の統一） | — |
| 招待中 | 招待の状態（承諾待ち・期限切れ）、入力した氏名・メール・役割・備考 | 公演名・団体名・役割・招待者の公演側の表示名・期限（管理者が入力した氏名・備考・メールは見えない） |
| 辞退後 | 「辞退」という状態だけ（辞退した人物のID・氏名・アカウントの有無は分からない） | — |
| 取消・期限切れ後 | 状態だけ | 招待は一覧から消える |
| 承諾後（ACTIVE） | 公演側の氏名（`display_name`）、人物の姓・名、通知先のメール（`PersonEmailResolver`）。承諾画面で、共有されることを本人に明示する | 自分の参加公演 |
| 本人の辞退・管理者の削除の後 | `display_name` だけ（姓・名・メールは返さない） | — |

- 他の公演・団体の管理者は、この公演の招待・参加者情報を取得できない（各APIで公演単位の権限を確認する。既存の構造を維持する）。
- 未確認のアドレスは、本人の判定にも、検索にも使わない（F6・F5）。
- 承諾前の招待者の通知に、本人の氏名やアカウント情報は入れない。
- ログ（`error_log`）にメールアドレスを出力している箇所（`ResolveParticipantInvitationUseCase`）は、削除によりなくなる。新しいコードでは、IDだけを出力する。

## 10. Frontend画面遷移

| 画面 | 変更 |
|---|---|
| 公演のメンバー管理（`productions/[id]/participants.tsx`） | 「メンバーを追加」を「メンバーを招待」に変える。結果の文言は「招待メールを送信しました。本人が承諾するとメンバーに追加されます」と「承諾待ちの招待に再送しました」の2種類にする（「メンバーを追加しました」をなくす）。「招待中のメンバー」の表に、承諾待ち・期限切れ・辞退・取消の状態と、再送・取消の操作を表示する。更新時に `status: 'ACTIVE'` を送らないようにする |
| 担当者（`productions/[id]/delegates.tsx`） | 人物IDの自由入力欄を削除し、ACTIVE 参加者の候補からの選択だけにする |
| 招待確認（新設：`(app)/participant-invitations/[id].tsx`） | 公演名・団体名・役割・招待者・期限・共有される情報の説明と、「参加する」「参加しない」のボタン。リンク（`?token=`）からも、一覧からも開ける。宛先が違う場合は、「招待を受け取ったアドレスでログインし直してください」と表示する |
| 自分宛ての招待（ホーム・マイページ） | 承諾待ちの招待があれば、ホームに件数とリンクを表示する（新しい一覧画面は作らず、確認画面への導線だけにする）。アプリ内通知（`my-notifications.tsx`）からも確認画面に移動する（`target_type=PARTICIPANT_INVITATION`） |
| 登録（`register.tsx`） | 招待リンクからの登録では、メールの初期値だけを使う（公演名・氏名の事前表示はやめるか、D7に従う）。登録が完了したら、自動では参加させず、招待確認画面に移動する |
| ログイン（既存） | 招待リンクから来た場合は、ログイン後に招待確認画面へ戻る（`return_to`。R13で指摘した `return_to` の検証を、同時に直す必要がある） |
| 参加公演（`participating-productions.tsx`） | 各公演に「参加をやめる」（`withdraw`）を追加し、確認ダイアログを表示する |
| 招待メール | リンク先を `/participant-invitations/open?token=...`（未ログインならログインまたは登録を選ぶ画面）に変える。本文に「承諾するまで参加者にはなりません」と書く |

団体メンバー管理（`organizations/[id]/members.tsx`・`invite.tsx`）とは画面を分けたままにする。公演の参加者は、団体のメンバーかどうかにかかわらず、公演ごとに招待する。

## 11. 通知仕様

既存の `NotificationDispatcherInterface` を使う（アプリ内とメール）。type は既存の命名（小文字のスネークケース）に合わせる。

| type | 宛先 | 契機 | message（例） | target |
|---|---|---|---|---|
| `participant_invited` | 招待先のメールが確認済みアドレスとして一致する人物（いれば） | 招待の作成・再送 | 「『{公演名}』から{役割}として招待されています」 | `PARTICIPANT_INVITATION`／招待ID |
| `participant_invitation_accepted` | 招待者（権限がなければ PrimaryManager） | 承諾 | 「{招待時の氏名}さんが『{公演名}』への招待を承諾しました」 | `PRODUCTION`／公演ID |
| `participant_invitation_rejected` | 同上 | 辞退 | 「{招待時の氏名}さんが『{公演名}』への招待を辞退しました」 | 同上 |
| `participant_withdrawn` | PrimaryManager と PARTICIPANT_MANAGER | 本人による辞退 | 「{公演側の氏名}さんが『{公演名}』の参加をやめました」 | 同上 |

- 招待メール（未登録者を含む全員宛て）は、既存の `ParticipantInvitationMailer` を使い、文面を1種類にする。
- 通知はコミット後に送り、失敗しても処理は成功のままにする（既存の稽古通知と同じ）。
- 通知の文面には、管理者が入力した氏名（`invited_name`）を使う。本人の氏名・メールを、承諾前に管理者側へ出さない。

## 12. 既存データとの互換性

| 既存データ | 扱い |
|---|---|
| 直接追加された ACTIVE な PERSON 参加者（過去の P1・P3・P4） | **変更しない**。承諾の記録はないが、遡って同意を求めるかどうかは業務判断（D2）。少なくとも、本人が参加公演の画面から辞退できるようになる |
| PENDING の招待（R14-B以前に作成） | 承諾待ちとして扱う。リンク先が変わるため、古いメールのリンク（`/register?token=`）からも確認画面に移動するよう、登録画面で転送する。期限（24時間）は既存の値のまま |
| CONSUMED の招待 | 終端のまま残す。一覧では「参加済み（旧方式）」と表示する |
| CANCELLED の招待 | `close_reason` は NULL（理由不明）のまま |
| 参加者でない人物を任命した担当者の行 | 変更しない。新しい任命だけを制限する。必要なら、件数の確認用SELECT（下記）で把握する |
| ORGANIZATION の参加者 | 変更しない。新しい直接追加だけを止める |
| 監査ログ | 1.49.0以前の操作は記録されない |

既存データの確認用SQL（読み取りのみ。Dev・Productionでは未実行で、実行には承認が必要）。

```sql
-- 承諾待ちの招待（R14-B導入時に残っているもの）
SELECT status, COUNT(*) FROM wp_stageart_participant_invitations GROUP BY status;
-- PERSON 参加者の件数（状態別）。現行の記録からは、直接追加・招待・参加申請の
-- どれで作られたかを区別できない（招待の行に人物IDがないため）。
SELECT status, COUNT(*) FROM wp_stageart_participants WHERE subject_type = 'PERSON' GROUP BY status;
-- 参加者でない人物の担当者
SELECT d.production_id, d.person_id, d.role FROM wp_stageart_production_delegates d
 WHERE d.status = 'ACTIVE' AND NOT EXISTS (
   SELECT 1 FROM wp_stageart_participants p
    WHERE p.production_id = d.production_id AND p.subject_type = 'PERSON'
      AND p.subject_id = d.person_id AND p.status = 'ACTIVE');
-- ORGANIZATION の参加者
SELECT production_id, subject_id, status FROM wp_stageart_participants WHERE subject_type = 'ORGANIZATION';
```

テーブル名・列名は `Installer` で確認済み（`stageart_production_delegates` の `person_id`・`role`・`status`）。接頭辞 `wp_` は環境に合わせる。

## 13. F5との統合方針

- `GET /people?email=` と `SearchPersonByEmailUseCase` を削除する。
- `GET /people/{id}` と `GetPersonByIdUseCase` を削除する（現行の画面で未使用）。
- `FindPersonByEmailUseCase` は内部用に限る（招待の宛先判定と、通知の宛先の判定だけに使う）。外部APIの応答には、その結果（アカウントの有無・人物ID・氏名）を一切出さない。
- 招待作成の応答を2種類に統一し、アカウントの有無で応答時間が大きく変わらないようにする。既存利用者へのアプリ内通知は、応答に影響しない処理にする。
- 管理者が人物を特定できるのは、本人が承諾した後の参加者一覧だけになる。
- 団体側（`CreateOrganizationInvitationUseCase` の既存利用者分岐、`PARTICIPANT_ADDED` 相当の応答）にも同じ問題が残る。D1 の判断に合わせて揃える。

## 14. テスト計画

### 14.1 PHPUnit（Application・Domain）

| # | ケース | 期待 |
|---|---|---|
| T1 | 招待先の本人（確認済みアドレス、ACTIVE）が承諾する | ACTIVE の参加者が1件でき、招待は ACCEPTED（`invitee_person_id`・`participant_id`・`responded_at`）。招待者に通知される |
| T2 | 別のアカウント（アドレスが異なる）が、IDやトークンを使って承諾する | 403。参加者は作られず、招待は PENDING のまま |
| T3 | 招待先と同じアドレスを未確認で登録したアカウントが承諾する | 403（F6と一致） |
| T4 | 招待先のアドレスが、確認済みの通知先メールとしてだけ一致する（Googleアカウント） | 承諾できる |
| T5 | アドレスが複数の人物に一致する | 403 |
| T6 | SUSPENDED・DISABLED のアカウントが承諾する | 403 |
| T7 | 本人が辞退する | 参加者は作られず、招待は REJECTED。招待者に辞退の通知が届く |
| T8 | 取消済みの招待を承諾・辞退する | 409 |
| T9 | 期限切れの招待を承諾する | 410。再送後は承諾できる |
| T10 | 同じ公演・宛先・種別で2回招待する | 招待は1件で、再送になる。承諾後も参加者は1件 |
| T11 | 同じ招待を2回承諾する（逐次・同時） | 2回目も200で同じ結果。参加者は1件（UNIQUE とロック） |
| T12 | 既に ACTIVE な参加者が承諾する | 新しい行は作られず、招待は ACCEPTED |
| T13 | CANCELLED・WITHDRAWN・REJECTED の行がある人が承諾する | 同じ行が ACTIVE に戻る（UNIQUE 違反にならない） |
| T14 | 参加申請中（PENDING）の人が承諾する | 承認と同じく ACTIVE になる |
| T15 | 招待者が承諾前に権限を失った | 409。招待は CANCELLED(INVITER_REVOKED)、参加者は作られない |
| T16 | 公演が COMPLETED／ARCHIVED／CANCELLED | 招待・再送は409。承諾も409で、招待は CANCELLED(PRODUCTION_CLOSED) |
| T17 | 権限のない人が招待・再送・取消・一覧を実行する | 403 |
| T18 | 他の公演の管理者が、この公演の招待を取消・一覧する | 403 |
| T19 | `POST /productions/{id}/participants` に PERSON・ORGANIZATION の任意のIDを送る | 422。参加者は作られない |
| T20 | `PUT /participants/{id}` で CANCELLED・REJECTED を ACTIVE にする | 422 |
| T21 | 担当者の任命で、参加者でない人物IDを送る | 422 |
| T22 | 既存利用者のアドレスで招待する | 応答は未登録者の場合と同じ形（`INVITATION_SENT`）。人物ID・氏名は含まない |
| T23 | `GET /people?email=`・`GET /people/{id}` | ルートが存在しない（404） |
| T24 | 参加者一覧を REHEARSAL_MANAGER が取得する | メールなし。CANCELLED などの行は含まない |
| T25 | 本人が参加をやめる | WITHDRAWN になり、管理者に通知される。他人の参加者IDでは403。PrimaryManager は拒否 |
| T26 | 招待リンクから登録する | メールは確認済みになり、参加者は作られない（招待は PENDING のまま） |
| T27 | 自己登録の後にメールを確認する／Googleでサインインする | 参加者は作られない（自動解決の廃止）。F6のテスト（確認前に招待を取得しない）も成り立つ |
| T28 | 自分宛ての招待一覧 | 確認済みアドレスに一致する PENDING・期限内のものだけを返す。他人宛ては含まない |
| T29 | by-token を別のアカウントで開く | 403で、内容を返さない |
| T30 | Migration 1.48.0 → 1.49.0 | 列とテーブルが追加される。既存の行は変化しない。2回実行しても同じ結果。新規インストールと一致する |

### 14.2 Jest（画面）

- 招待の結果の文言が2種類で、「メンバーを追加しました」が出ない。
- 招待中の表に、承諾待ち・期限切れ・辞退・取消が表示される。
- 招待確認画面：承諾・辞退・宛先違い（403）・期限切れ（410）・取消（409）の表示。
- 登録画面：招待リンクからの登録後に、招待確認画面へ移動する。
- 担当者画面：人物IDの入力欄がない。
- 参加公演：参加をやめる操作と、その確認。
- 更新時に `status` を送らない。

### 14.3 実MySQL（隔離DB、REST）

- 承諾の同時実行（2接続で同じ招待を承諾し、参加者が1件になる）。
- 承諾と取消の競合（取消を確定すると承諾は409、ロールバックすると200）。
- 古い招待（R14-B以前の PENDING）からの承諾。
- Migration（アップグレードと新規インストールの一致、既存DBのチェックサム）。

### 14.4 既存テストへの影響

次のテストは、仕様の変更に合わせて書き直す必要がある。削除・無効化はしない。

- PHPUnit：`ParticipantInvitationUseCaseTest`（既存利用者の直接追加）、`ParticipantInvitationRegistrationConnectionTest`（自動解決）、`ParticipantUseCaseTest`（PERSON の直接作成、`PUT` の status）、`ProductionDelegateUseCaseTest`（任意の人物の任命）、`GetPersonByIdUseCaseTest`、`SearchPersonByEmail` 関連、`Authentication` 系の招待解決の確認。
- Jest：`web-production-participants-add-existing-person`、`-add-member-ui`、`-add-unregistered-person`、`-invitation-list`、`register-invitation-*`、`participating-productions-*`。

## 15. 実装順序と影響範囲

PR #7上で、1項目を1コミットにする。

1. **Domain**
   - 招待の状態（ACCEPTED／REJECTED）、`accept()`・`reject()`・`close(reason)`、新しい列の属性
   - `ParticipantStatus::WITHDRAWN`、`Participant::withdraw()`・`reenroll()`
   - Domainテスト
2. **Schema 1.49.0**：列・インデックス・監査テーブルの追加。Repository（`findByIdForUpdate`、`findPendingForEmails`）。`SchemaUpgradePathTest`。
3. **承諾・辞退・自分宛て一覧・by-token のAPI**
   - `InvitationRecipientVerifier`、`ParticipantEnrollmentService`
   - 通知と監査ログ
4. **直接追加の経路を閉じる**
   - `POST /participants` の PERSON・ORGANIZATION
   - 招待作成の既存利用者分岐
   - 自動解決の削除（登録・確認・Google）
   - `PUT` の status、担当者の対象制限
   - 3と同時に出さないと、承諾の手段がないまま追加の手段だけがなくなるため、**3と4は同じリリースにする**
5. **F5**：`/people` の2つのAPIと、Frontendの未使用関数を削除する。
6. **個人情報の絞り込み**：`ParticipantResult`（REHEARSAL_MANAGER と終了済みの行）。
7. **本人による辞退**：API、参加公演の画面。
8. **Frontend**
   - メンバー管理の文言と状態表示
   - 招待確認画面、登録・ログインからの導線、ホームの件数、通知からの遷移、担当者画面
9. **文書**：`docs/08-API/ParticipantAPI.md`、`docs/21-MemberManagementScreen.md`、ドメインイベント文書、本書の確定版。
10. **検証**：PHPUnit・Jest・実MySQL。

**影響範囲（主なファイル）**

- Backend：
  - `Domain/Participant*`、`Domain/ParticipantInvitation/*`
  - `Application/Participant*`、`Application/ParticipantInvitation/*`、`Application/Person/*`、`Application/ProductionDelegate/CreateProductionDelegateUseCase`
  - `Application/Authentication/{RegisterWithEmail,VerifyEmail,AuthenticateWithGoogle}UseCase`
  - `Presentation/Rest/{Participant,ParticipantInvitation,Person,ProductionDelegate}RestController`、`Presentation/Plugin.php`
  - `Infrastructure/WordPress/Persistence/WordPressParticipant*Repository`、`Infrastructure/WordPress/Schema/{Installer,SchemaUpgrader}`、`WordPressParticipantInvitationMailer`
- Frontend：
  - `productions/[id]/participants.tsx`、`productions/[id]/delegates.tsx`、`register.tsx`、`participating-productions.tsx`、`home.tsx`、`my-notifications.tsx`
  - 新しい招待確認画面
  - `features/participantInvitation/*`、`features/participant/*`、`features/person/api.ts`
  - `types/api.ts`、`utils/errorMessage.ts`
- 規模の目安：Backend 25〜35ファイル、Frontend 12〜18ファイル、テスト 30ファイル前後。

## 16. 未決事項・リスク

### 未決事項（実装前にユーザー判断が必要）

| # | 事項 | 推奨 |
|---|---|---|
| D1 | 団体メンバーの招待（`CreateOrganizationInvitationUseCase` の既存利用者分岐と自動解決）にも、同じ同意の方式を適用するか | 適用する。ただし、別のラウンド（R14-C）に分けて、同じ形（承諾・辞退API）で実装する |
| D2 | 過去に同意なく追加された ACTIVE な参加者の扱い | 変更しない。本人が辞退できるようにし、必要なら管理者に再確認を促す |
| D3 | 承諾の時点で招待者が権限を失っていた場合 | 無効にする（現行の自動解決と同じ考え方）。代案：公演としての招待とみなし、その時点の管理者がいれば有効にする |
| D4 | 招待の有効期間 | 24時間から7日に延ばす |
| D5 | 公演が終了・中止になったときに、未処理の招待をまとめて閉じるか | 承諾時に判定するだけにする（状態変更の処理に手を入れない）。一覧では「無効」と表示する |
| D6 | 再送・再招待の上限（辞退した人に送り続けることの防止） | 1招待につき再送5回まで。辞退後30日は、同じ公演・宛先への新しい招待を受け付けない |
| D7 | 招待先の本人・未ログインの人に見せる項目（管理者が入力した氏名・備考を見せるか、登録画面に公演名を出すか） | 見せない（公演名・団体名・役割・期限だけ）。未ログインの人にはメールアドレスの初期値だけを返す |
| D8 | 監査ログの導入と保持期間 | 導入する。保持期間は定めない |
| D9 | 本人が辞退したときに、担当者の役割も外すか | 外す。PrimaryManager は辞退できない |
| D10 | ORGANIZATION の参加者（団体として公演に参加）の追加方法 | 当面は追加できないようにする（画面でも使っていない）。必要になれば、団体の OWNER による承諾の方式を設計する |
| D11 | 公演の終了後の参加者情報の保持 | 保持する（精算・実績集計が参照するため）。閲覧の範囲は §7.3 のまま |

### リスク

- **機能の後退**：既存利用者を即座に追加できなくなり、承諾を待つ運用になる。稽古の出欠・タイムテーブル・精算は ACTIVE な参加者を前提にしているため、承諾されるまで対象にならない。公演の準備期間中の周知が必要。
- **リリースの順序**：承諾API（§15の3）と直接追加の廃止（4）を分けてリリースすると、参加者を増やす手段がなくなる。BackendとFrontendも同時に配布する必要がある（R11・R12と同じ制約）。
- **メールアドレスの大文字小文字**（R13 Medium）：招待と認証情報の照合が、DBの照合順序（`_ci`）に依存している。PHP側の比較を加えると、結果が食い違うおそれがある。実装時に、比較の方法をどちらかに統一する。
- **`return_to` の未検証**（R13 Medium）：ログイン後に招待確認画面へ戻す導線を追加するため、同時に検証を直さないと、オープンリダイレクトの経路が増える。
- **アクセストークンの有効期間**（R13 Low）：SUSPENDED にしたアカウントでも、発行済みのアクセストークンの期限までは承諾できる。承諾APIでは、UserAccount の状態を都度確認して防ぐ（§4.3）。
- **応答時間からの推測**：既存利用者にだけアプリ内通知を作ると、招待作成の応答時間の差からアカウントの有無を推測できる余地がある。通知はコミット後に非同期的に送り、応答の形を統一する。完全な秘匿までは保証しない。
- **既存テストの書き直し**：§14.4のとおり、多数のテストが現行の仕様（直接追加・自動解決）を固定している。仕様変更として書き直す（削除・無効化はしない）。
- **古い招待メールのリンク**：`/register?token=` を、確認画面へ転送し続ける必要がある。
- **MySQL 8.0／MariaDB での未検証**：既存のラウンドと同じ。
