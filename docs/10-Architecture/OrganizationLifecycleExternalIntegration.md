# StageArt Blueprint

# 10 - Architecture : Organization / Production Lifecycle / External Integration

Version : 1.0

---

# Purpose

本書は、既存のOrganization / Production / Frontend / Document / Timetable設計を横断して、
以下の運用方針を正式に定義する。

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

Organizationが複数存在する場合の初期選択UIは、DropdownよりもTile/Card形式を基本候補とする。

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

初回・主要な選択画面ではTile/Card形式を基本とする。

画面内での簡易切替についてはDropdown等のUIを使用してもよい。

ただし、現在選択中のOrganizationを常に明確に表示し、異なるOrganizationのBusiness Dataを誤操作しないUIを優先する。

Organization ContextはUI上の状態であり、AuthorizationのSecurity Boundaryではない。

API側では必ずServer SideでOrganization Scopeを再評価する。

---

# 3. Production Lifecycle

Productionは、公演当日だけを管理する単位ではない。

Productionは、企画・予算策定から、公演・活動、精算、決算完了までを一貫して管理するBusiness Unitである。

基本的な概念Flow：

企画
    ↓
Production作成
    ↓
予算策定
    ↓
制作・準備
    ↓
稽古・広報・販売等
    ↓
小屋入り・本番
    ↓
公演終了
    ↓
精算・決算
    ↓
決算完了
    ↓
Archive

ProductionのLifecycleは、「本番終了」を終了条件としない。

決算が完了するまでは、Productionを会計・精算上のActiveな管理対象として扱える構造とする。

既存Production Statusの具体的な値や遷移については、別途Lifecycle Ruleとして整理する。
本書では、ProductionのBusiness Lifecycleの終点を「決算完了」とすることを正式方針とする。

---

# 4. Production and Accounting Lifecycle

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
Production Archive

公演が終了していても、未払い・精算・決算が残っている場合はProductionを完全終了させない。

---

# 5. Public / Internal Boundary

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

# 6. External Integration Principle

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

# 7. Google Drive Document Integration

Documentについては、Google Driveを外部Storageとして利用できる。

StageArtはGoogle Drive上の実ファイルを必ずしもコピー・二重保存しない。

StageArt内のDocumentにGoogle Drive上のファイルまたはフォルダへのAlias / External Referenceを登録し、StageArtのDocument画面から対象資料へアクセスできる構造を基本とする。

基本構造：

StageArt Document
    ↓
Google Drive Alias / External Reference
    ↓
Google Drive File / Folder

StageArtは、資料そのもののStorageではなく、資料の業務上の関連付け・表示・入口として機能できる。

---

# 8. Google Drive Permission Notification

Google Drive上の資料をStageArtのDocumentとして登録する場合、対象者がGoogle Drive側で閲覧権限を持っていることを保証する必要がある。

StageArtがGoogle Driveの権限を無断で変更することを基本方針としない。

StageArt側で、対象資料について閲覧権限が必要と判断された場合、Document管理者へ通知できる構造とする。

基本Flow：

Document管理者
    ↓
Google Drive資料をAlias登録
    ↓
StageArtが関連対象者を認識
    ↓
閲覧権限が必要な可能性をDocument管理者へ通知
    ↓
Document管理者がGoogle Drive側で権限を確認・付与

通知は権限付与そのものではなく、「確認すべき事項」をDocument管理者へ知らせるためのものとする。

---

# 9. Google Calendar Integration

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

---

# 10. Google Calendar Event Information

Google Calendarへ登録するEventには、必要に応じて以下を反映できる。

- Event Title
- Start / End DateTime
- Venue / Location
- Description
- StageArt Production information
- StageArt Rehearsal / Timetable reference
- Online Meeting URL等の関連情報

Google CalendarからStageArtへ予定を逆同期することは初期方針としない。

---

# 11. Google Calendar Future Synchronization

将来的には、StageArt側で確定済みRehearsal / Timetableが変更された場合に、既にGoogle Calendarへ登録したEventを更新・削除する同期機能を追加できる。

この場合もStageArtを正本とし、外部Calendar Eventとの対応関係を識別できる構造を必要とする。

初期実装では、まず「StageArtからGoogle Calendarへの一括登録」を優先する。

---

# 12. Google Calendar Attendees

Google CalendarのAttendees機能による一斉招待は、初期の必須Requirementとしない。

StageArtのPersonとGoogle Accountの対応関係、Google側の招待・通知仕様、権限等を別途整理した上で将来拡張できる。

初期の基本操作は、各利用者が自分自身のGoogle Calendarへ選択した予定を登録する方式とする。

---

# 13. Offline Strategy

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

# 14. Offline Display

Offline時には、利用者が表示内容を最新情報と誤認しないよう、画面上に明確なOffline Mode表示を行う。

最低限、以下を表示できることをRequirementとする。

- Offline Modeであること
- 最終更新日時

例：

OFFLINE
最終更新：2026/08/17 14:32

Onlineへ復帰した場合は、必要に応じてCacheを更新し、最新のServer Dataを表示する。

---

# 15. Offline Read Scope

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

# 16. Cache Freshness

Cacheには、取得日時等のFreshness情報を保持できる構造とする。

利用者が古い情報を判断できるよう、重要なSchedule表示では最終更新日時を確認できることを基本とする。

Cacheの存在だけを理由にServerの最新Business Factを上書きしてはならない。

---

# 17. Source of Truth Principle

以下を共通原則とする。

- Organization情報の正本はStageArt
- Production情報の正本はStageArt
- Rehearsal / Timetableの正本はStageArt
- Accounting / Journal Entryの正本はStageArt
- Google DriveはDocumentのExternal Storage
- Google Calendarは個人予定のExternal Calendar
- Mobile Local CacheはOffline Read Model

External ServiceおよびLocal Cacheは、StageArtのBusiness Factを置き換えない。

---

# 18. Design Principles

- Organization Contextを利用者に明示する。
- 複数Organizationに所属するPersonが安全に操作対象を切り替えられること。
- Productionは企画から決算完了までを一貫して管理する。
- 公演終了とProduction Lifecycle終了を同一視しない。
- Public / Internal情報を同一Organization / Production内で明確に分離する。
- Public ClientからInternal Dataへアクセスさせない。
- External ServiceをStageArtの正本にしない。
- Google DriveはDocumentの外部Storageとして利用し、StageArtからAliasで参照できる。
- Google Driveの閲覧権限に関する確認事項はDocument管理者へ通知できる。
- Google CalendarへRehearsal / Timetableを複数選択して一括登録できる。
- Google CalendarはStageArtのScheduleの外部コピーとして扱う。
- MobileはOffline時にも最後に正常取得したSchedule等を参照できる。
- Offline Modeであることと最終更新日時を利用者に明示する。
- Local CacheをBusiness Factの正本としない。

---

# 19. Scope

本書では、既存Domainを横断するArchitecture / UX / Integration方針を定義する。

具体的なGoogle OAuth実装、Google Drive API実装、Google Calendar API実装、Offline Cache Library、同期処理、Push通知等の実装詳細は後続Phaseで設計・実装する。

Productionの具体的なStatus値・遷移Ruleについても、既存Production Domainとの整合を確認した上で後続のLifecycle設計に反映する。
