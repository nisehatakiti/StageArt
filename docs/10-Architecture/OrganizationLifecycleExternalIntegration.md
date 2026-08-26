# StageArt Blueprint

# 10 - Architecture : Organization / Production Lifecycle / External Integration

Version : 1.1

---

# Purpose

本書は、既存のOrganization / Production / Frontend / Document / Timetable設計を横断して、以下の運用方針を正式に定義する。

- Organization Contextの選択・切替
- Production Lifecycle
- Public / Internal境界
- External Service Integration
- Google Drive Document Integration
- Google Calendar Integration
- Mobile Offline Strategy

本書は新しいDomain Entityを機械的に追加するものではない。
既存Domainの正本と責務を維持しながら、UIおよびIntegrationの共通方針を定義する。

---

# 1. Organization Context

StageArtでは、一人のPersonが複数Organizationに所属できる。

そのため、ログイン後の入口では、利用者が現在操作するOrganizationを明示的に選択できることを基本とする。

複数Organizationが存在する場合の初期選択UIはTile / Card形式を基本とする。

例：

Organization Selection

[ Organization A ]    [ Organization B ]
[ Organization C ]    [ + New Organization ]

各Tileには、利用者が現在の操作対象を認識しやすい情報を表示できる。

例えば：

- Organization Name
- Logo
- Description
- 最近のProduction

など。

---

# 2. Organization Switcher

Organization選択後も、利用者は操作対象Organizationを変更できる。

初回・主要な選択画面ではTile / Card形式を基本とする。

画面内での簡易切替についてはDropdown等のUIを使用してもよい。

ただし、現在選択中のOrganizationを常に明確に表示し、異なるOrganizationのBusiness Dataを誤操作しないUIを優先する。

Organization ContextはUI上の状態であり、AuthorizationのSecurity Boundaryではない。

API側では必ずServer SideでOrganization Scopeを再評価する。

---

# 3. Production Lifecycle

Productionは、公演当日だけを管理する単位ではない。

Productionは、企画・予算策定から、制作、稽古・広報・販売、公演、精算、決算完了までを一貫して管理するBusiness Unitである。

Production LifecycleとProduction Statusの対応を以下のとおり確定する。

| Lifecycle | Production Status | 管理者による業務Action |
|---|---|---|
| 企画 | DRAFT | Productionを作成する |
| 予算策定 | PLANNING | 「予算策定を開始」 |
| 制作 | ACTIVE | 「公演準備を開始」 |
| 稽古・広報・販売 | ACTIVE | 継続中 |
| 公演 | ACTIVE | 継続中 |
| 精算 | ACTIVE | 継続中 |
| 決算完了 | COMPLETED | 「公演終了」 |
| Archive | ARCHIVED | 「アーカイブ」 |

基本Status Lifecycleは、

DRAFT
    ↓
PLANNING
    ↓
ACTIVE
    ↓
COMPLETED
    ↓
ARCHIVED

とする。

制作、稽古・広報・販売、公演、精算はACTIVEの内部に存在する活動段階であり、Production Statusとして個別に追加しない。

Production Statusは利用者が自由に直接編集するものではなく、業務上のLifecycle Actionによって変更する。

---

# 4. Production Lifecycle Actions

現在のStatusに応じて、次に実行可能な業務Actionを表示する。

### DRAFT → PLANNING

管理者が「予算策定を開始」を実行する。

### PLANNING → ACTIVE

予算が確定し、公演を正式にGOできる状態になった時点で、管理者が「公演準備を開始」を実行する。

このActionは自動実行しない。

### ACTIVE → COMPLETED

公演終了後の精算・会計処理・決算が完了した段階で、管理者が「公演終了」を実行する。

このActionは自動実行しない。

### COMPLETED → ARCHIVED

管理者が任意のタイミングで「アーカイブ」を実行する。

Archiveのタイミングはシステムが自動決定しない。

---

# 5. Lifecycle Action Constraints

Lifecycle Actionには業務上の実行条件を持たせる。

### 「予算策定を開始」

- DRAFTでのみ実行可能。
- 実行後はPLANNINGとなる。

### 「公演準備を開始」

- PLANNINGでのみ実行可能。
- 予算が確定していることを実行条件とする。
- 実行後はACTIVEとなる。

### 「公演終了」

- ACTIVEでのみ実行可能。
- **決算が完了していなければ実行できない。**
- 決算未完了の場合は、Actionを実行不可として理由を利用者へ表示する。
- 決算完了後、管理者が明示的にActionを実行してCOMPLETEDとなる。

### 「アーカイブ」

- COMPLETEDでのみ実行可能。
- 管理者が任意のタイミングで実行する。
- 実行後はARCHIVEDとなる。

---

# 6. Lifecycle Action UX

Status値を直接編集するUIではなく、業務上の意味を持つLifecycle Actionとして操作する。

例：

PLANNING
    ↓
[ 公演準備を開始 ]

ACTIVE
    ↓
[ 公演終了 ]

COMPLETED
    ↓
[ アーカイブ ]

Lifecycle Actionを実行した後は、完了メッセージ等によって現在のStatus変更を利用者へ明示する。

すべてのActionで一律に確認ダイアログを表示することをRequirementとしない。

利用者が明示的にAction Buttonを押したこと自体を意思決定として扱い、通常の遷移では過剰な確認ダイアログを表示しない。

ただし、不可逆性が高いActionや重大な結果を伴うActionについては、個別に最終確認UIを設けることを許容する。

---

# 7. Production and Accounting Lifecycle

Production LifecycleとAccounting Lifecycleは密接に関連するが、同一Statusとして扱う必要はない。

基本的な関係：

Production
    ↓
公演終了
    ↓
Expense / 未払金の精算
    ↓
Journal Entry
    ↓
Actual / Budget vs Actual
    ↓
決算完了
    ↓
「公演終了」Action
    ↓
COMPLETED

公演が終了していても、未払い・精算・決算が残っている場合はProductionをCOMPLETEDにできない。

Productionの決算完了条件の詳細はAccounting Domainで定義するが、Production Lifecycle上は「決算完了していること」をCOMPLETEDへの必須条件とする。

---

# 8. Public / Internal Boundary

ProductionおよびOrganizationには、Public情報とInternal情報が共存する。

Public情報の例：

- 公演概要
- 公演日時
- 会場
- 出演者等の公開対象情報
- チケット情報
- 公開画像・宣伝素材
- Public Announcement

Internal情報の例：

- Budget
- Accounting
- Participantの内部情報
- Rehearsal / Timetableの内部運用情報
- 内部Document
- Internal Announcement
- Permission / Role
- Credential
- その他管理情報

同じOrganization / Productionの中でPublicとInternalを管理できる構造とする。

Public用にProductionとは別の重複した「公演Entity」を作成することを基本としない。

Public ClientはPublicとして公開された情報のみを取得でき、Internal Management Dataへ直接アクセスしてはならない。

---

# 9. External Integration Principle

External ServiceはStageArtのBusiness Factの正本ではない。

StageArt自身が管理するBusiness FactはStageArtを正本とする。

External Serviceとの連携は、Integration Adapter / Infrastructureによって分離する。

基本原則：

StageArt Domain / Application
    ↓
Integration Interface
    ↓
Infrastructure Adapter
    ↓
External Service

External Service側のデータをStageArtのDomain Factへ無条件に逆同期することを初期方針としない。

---

# 10. Document Distribution Model

StageArtのDocument管理は、Production Manager等の公演管理者からProduction Memberへ資料を提供するための機能として限定する。

資料管理は上意下達方式を基本とし、Production Member間で資料を交換・共有する機能は提供しない。

Production Memberは、管理者が公開・提供した資料をStageArt上で閲覧する側として扱う。

Production Member自身が別のMemberへ資料を配布すること、Member間でDocument Aliasを作成すること、Member個人のStorageをProduction Documentとして登録することは行わない。

---

# 11. Google Drive Document Integration

Google DriveをDocumentのExternal Storageとして利用する場合、対象はProduction Manager等の公演管理者に紐づくGoogle Driveに限定する。

Production Member個人のGoogle DriveをStageArtへ接続することは行わない。

基本構造：

Production Manager
    ↓
Manager's Google Drive
    ↓
StageArt Document Alias / External Reference
    ↓
Production Members

StageArt内のDocumentには、Google Drive上のファイルまたはフォルダへのAlias / External Referenceを登録できる。

実ファイルはGoogle Drive側に存在し、StageArtは資料の業務上の関連付け・表示・入口として機能する。

StageArtがProduction Memberごとに別々のGoogle Driveを統合して一つのDocument Repositoryを作ることはしない。

---

# 12. Google Drive Permission Notification

Google Drive上の資料をStageArtのDocumentとして登録する場合、Production MembersがGoogle Drive側で閲覧権限を持っている必要がある。

StageArtがGoogle Driveの権限を無断で変更することを基本方針としない。

対象資料について閲覧権限の確認・付与が必要な場合、Document管理者へ通知する。

基本Flow：

Document管理者
    ↓
管理者のGoogle Drive資料をAlias登録
    ↓
StageArtが対象Memberを認識
    ↓
閲覧権限確認が必要な場合にDocument管理者へ通知
    ↓
Document管理者がGoogle Drive側で権限を確認・付与
    ↓
MemberがStageArtから資料へアクセス

通知は権限付与そのものではなく、管理者が確認・対応すべき事項を知らせるためのものとする。

---

# 13. Google Calendar Integration

Rehearsal / Timetableの正本はStageArtに保持する。

Google Calendarは、利用者個人の予定管理を補助するExternal Serviceとして扱う。

StageArtから利用者が選択したRehearsal / Timetable ItemをGoogle Calendarへ登録できる。

複数の稽古予定を選択し、一括してGoogle Calendarへ登録できることをRequirementとする。

基本Flow：

StageArt Rehearsal / Timetable
    ↓
利用者が複数予定を選択
    ↓
Google OAuth / Calendar権限
    ↓
Google Calendar Eventsを作成

Google Calendar側のEventは、StageArtのScheduleをコピーした外部表現であり、StageArtの正本ではない。

Google CalendarからStageArtへ予定を逆同期することは初期方針としない。

---

# 14. Google Calendar Event Information

Google Calendarへ登録するEventには、必要に応じて以下を反映できる。

- Event Title
- Start / End DateTime
- Venue / Location
- Description
- StageArt Production information
- StageArt Rehearsal / Timetable reference
- Online Meeting URL等の関連情報

---

# 15. Google Calendar Future Synchronization

将来的には、StageArt側で確定済みRehearsal / Timetableが変更された場合に、既にGoogle Calendarへ登録したEventを更新・削除する同期機能を追加できる。

この場合もStageArtを正本とし、外部Calendar Eventとの対応関係を識別できる構造を必要とする。

初期実装では、まず「StageArtからGoogle Calendarへの一括登録」を優先する。

---

# 16. Google Calendar Attendees

Google CalendarのAttendees機能による一斉招待は、初期の必須Requirementとしない。

StageArtのPersonとGoogle Accountの対応関係、Google側の招待・通知仕様、権限等を別途整理した上で将来拡張できる。

初期の基本操作は、各利用者が自分自身のGoogle Calendarへ選択した予定を登録する方式とする。

---

# 17. Offline Strategy

Mobile Clientでは、通信不能時にも直近のSchedule等を確認できるよう、ローカルCacheを保持する。

CacheはBusiness Factの正本ではない。

StageArt Serverから最後に正常取得したDataを、Offline Read Modelとしてローカルに保持する。

基本Flow：

Online
    ↓
StageArt APIから取得
    ↓
Local Cache更新
    ↓
Offline
    ↓
最後に正常取得したDataを表示

---

# 18. Offline Display

Offline時には、利用者が表示内容を最新情報と誤認しないよう、画面上に明確なOffline Mode表示を行う。

最低限、以下を表示できることをRequirementとする。

- Offline Modeであること
- 最終更新日時

例：

OFFLINE
最終更新：2026/08/17 14:32

Onlineへ復帰した場合は、必要に応じてCacheを更新し、最新のServer Dataを表示する。

---

# 19. Offline Read Scope

初期Offline対応では、Readを中心とする。

Offline時に優先して参照可能とする候補：

- Production基本情報
- Rehearsal / Timetable
- 直近のNotification
- その他、事前取得済みの参照情報

Offline時の書き込みを無条件に許可しない。

特に以下は、初期Offline対応ではOnlineを要求することを基本とする。

- Timetable変更
- 会計入力
- Google Calendar連携
- Google Drive資料操作
- その他、Server上のBusiness Factを変更する操作

Offline書き込みのキューイングについては将来検討事項とする。

---

# 20. Cache Freshness

Cacheには、取得日時等のFreshness情報を保持できる構造とする。

利用者が古い情報を判断できるよう、重要なSchedule表示では最終更新日時を確認できることを基本とする。

Cacheの存在だけを理由にServerの最新Business Factを上書きしてはならない。

---

# 21. Source of Truth Principle

以下を共通原則とする。

- Organization情報の正本はStageArt
- Production情報の正本はStageArt
- Rehearsal / Timetableの正本はStageArt
- Accounting / Journal Entryの正本はStageArt
- Google DriveはProduction Manager等の公演管理者が管理するDocumentのExternal Storage
- Google Calendarは個人予定のExternal Calendar
- Mobile Local CacheはOffline Read Model

External ServiceおよびLocal Cacheは、StageArtのBusiness Factを置き換えない。

---

# 22. Design Principles

- Organization Contextを利用者に明示する。
- 複数Organizationに所属するPersonが安全に操作対象を切り替えられること。
- Productionは企画から決算完了までを一貫して管理する。
- Production Statusを利用者が直接編集するのではなく、業務ActionによってLifecycleを進める。
- 「公演準備を開始」は予算確定後に管理者が明示的に実行する。
- 「公演終了」は決算完了後に管理者が明示的に実行する。
- 決算未完了の場合、「公演終了」を実行できない。
- 「アーカイブ」はCOMPLETED後、管理者が任意のタイミングで実行する。
- Public / Internal情報を同一Organization / Production内で明確に分離する。
- Public ClientからInternal Dataへアクセスさせない。
- External ServiceをStageArtの正本にしない。
- Document管理はProduction Manager等の公演管理者からProduction Memberへの資料提供を基本とする。
- Production Member間の資料交換・共有を行わない。
- Google Drive連携対象は公演管理者に紐づくGoogle Driveに限定する。
- Google Driveの閲覧権限に関する確認事項はDocument管理者へ通知する。
- Google CalendarへRehearsal / Timetableを複数選択して一括登録できる。
- Google CalendarはStageArtのScheduleの外部コピーとして扱う。
- MobileはOffline時にも最後に正常取得したSchedule等を参照できる。
- Offline Modeであることと最終更新日時を利用者に明示する。
- Local CacheをBusiness Factの正本としない。

---

# 23. Scope

本書では、既存Domainを横断するArchitecture / UX / Integration方針を定義する。

具体的なGoogle OAuth実装、Google Drive API実装、Google Calendar API実装、Offline Cache Library、同期処理、Push通知等の実装詳細は後続Phaseで設計・実装する。

Productionの具体的なStatus値・遷移Ruleについても、本書の確定仕様に従って後続Phaseで実装する。
