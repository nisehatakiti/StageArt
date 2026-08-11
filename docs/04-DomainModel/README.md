# StageArt Blueprint

# 04 - Domain Model

Version : 2.0

---

## Purpose

Domain Modelは、StageArtが扱う情報と、それらの関係を定義する。

StageArtでは、
UIやDatabaseを先に設計するのではなく、
Domain Modelを中心としてシステム全体を設計する。

Business Flowで定義された利用者の活動は、
Domain Modelによって表現される。

Domain Modelは、
WordPress、REST API、UI、外部サービスなどの実装技術から独立した概念モデルとして定義する。

---

# 1. Domain Structure

StageArtの基本Domainは以下の領域に分類する。

### Identity

- Person
- Profile

### Organization

- Organization
- Membership
- Role

### Production

- Production
- Assignment
- Rehearsal
- Rehearsal Availability
- Rehearsal Attendance
- Timetable

### Communication

- Announcement

### File

- Document
- External Storage Reference

### Public Information

- Organization Public Profile
- Production Public Page
- Social Post Reference
- Public Testimonial

### Ticket / Audience

- Ticket Type
- Reservation
- Ticket
- Check In
- Survey
- Survey Response

### Accounting

- Accounting Period
- Budget
- Budget Item
- Journal Entry
- Journal Entry Line
- Production Actual
- Financial Report

### Organization Management

- Regulation
- Regulation Version
- Annual Plan
- Equipment
- Equipment History

---

# 2. Identity Domain

## Person

Personは、
StageArtを利用する個人を表す。

PersonはStageArt全体で一つのIdentityを持つ。

Person自身はOrganizationに依存しない。

主な情報：

- Person ID
- Account ID
- Name
- Contact Information
- Profile

Personは複数のOrganizationに所属できる。

---

## Profile

Profileは、
Personが舞台活動を行う際に公開・利用するプロフィール情報を表す。

主な情報：

- Display Name
- Biography
- Profile Image
- Activity Information
- Public Status

ProfileはPersonに紐付く。

出演履歴そのものはProfileに保存しない。

出演履歴はProductionおよびAssignmentから生成・参照する。

---

# 3. Organization Domain

## Organization

Organizationは、
StageArtを利用する団体を表す。

演劇団体、バンド、朗読団体、セミナー団体などを共通のDomainとして扱う。

主な情報：

- Organization ID
- Organization Name
- Organization Type
- Representative
- Description
- History
- Public Information
- SNS Information

Organizationは複数のPersonをMembershipとして保持する。

---

## Membership

Membershipは、
PersonがOrganizationに所属している事実を表す。

Personそのものに所属情報を持たせない。

主な情報：

- Membership ID
- Organization
- Person
- Status
- Roles

一人のPersonが複数Organizationに所属できる。

Organizationごとに異なるRoleを持つことができる。

例：

Person A

Organization A
→ Manager

Organization B
→ Cast

Organization C
→ Reception

---

## Role

Roleは、
OrganizationまたはProductionにおける利用者の権限・役割を表す。

基本的なRoleとして、

- Manager
- Delegate
- Member
- Reception

などを扱う。

RoleはPersonそのものに付与しない。

MembershipまたはProduction Participationに対して付与する。

必要に応じて複数Roleを持つことができる。

---

# 4. Production Domain

## Production

Productionは、
Organizationが行う一つの活動・公演を表す。

演劇では「公演」、
音楽では「ライブ」、
セミナーでは「講座」など、
UI上の名称はOrganization Typeに応じて変更できる。

内部DomainではProductionとして統一する。

主な情報：

- Production ID
- Organization
- Name
- Description
- Start Date
- End Date
- Venue
- Scheduled End Time
- Status
- Public Information
- Internal Information

ProductionはStageArtにおける活動Lifecycleの中心となるDomainである。

---

## Assignment

Assignmentは、
PersonがProductionへ参加する事実を表す。

Organizationへの所属とは別に管理する。

主な情報：

- Assignment ID
- Production
- Person
- Role
- Assignment Type
- Status

例：

Person A

Organization
→ Manager

Production X
→ Actor

Person B

Organization
→ Cast

Production X
→ Staff

客演など、
Organization Membershipを持たないPersonがProductionへ参加することも許容する。

---

# 5. Rehearsal Domain

## Rehearsal Candidate

Rehearsal Candidateは、
稽古日程を決定するために提示された候補日を表す。

主な情報：

- Candidate ID
- Production
- Date
- Start Time
- End Time
- Location
- Status

候補日は、
確定したRehearsalとは別の情報として扱う。

---

## Rehearsal Availability

Rehearsal Availabilityは、
候補日に対する日程調整のための回答を表す。

主な情報：

- Candidate
- Person
- Availability
- Response Date

Availabilityには、

- Available
- Unavailable
- Unknown

などを使用する。

これは、
「その日なら稽古に参加できるか」を確認するための情報である。

---

## Rehearsal

Rehearsalは、
確定した稽古を表す。

主な情報：

- Rehearsal ID
- Production
- Date
- Start Time
- End Time
- Location
- Content
- Memo
- Status

RehearsalはRehearsal Candidateから生成される。

---

## Rehearsal Attendance

Rehearsal Attendanceは、
確定した稽古への参加予定・参加確認を表す。

主な情報：

- Rehearsal
- Person
- Attendance Status
- Response Date

Attendance Statusには、

- Attending
- Absent
- Unknown

などを使用する。

Rehearsal Availabilityとは別のDomainである。

---

# 6. Timetable Domain

## Timetable

Timetableは、
稽古日や小屋入り後などの具体的な進行予定を表す。

主な情報：

- Timetable ID
- Production
- Date
- Name
- Status

---

## Timetable Item

Timetable Itemは、
タイムテーブル内の一つの項目を表す。

主な情報：

- Timetable
- Start Time
- End Time
- Title
- Content
- Location
- Target Persons
- Memo

Timetableは、
Rehearsalそのものとは別のDomainとして扱う。

Rehearsalが「稽古という予定」を表すのに対し、
Timetableは「その日の進行」を表す。

---

# 7. Communication Domain

## Announcement

Announcementは、
団体またはProductionの関係者へ伝達する内部連絡を表す。

主な情報：

- Announcement ID
- Organization
- Production
- Sender
- Title
- Body
- Target
- Sent At
- Status

Targetには、

- All Members
- Cast
- Staff
- Role
- Selected Members

などを指定できる。

送信済みAnnouncementは履歴として保持する。

---

# 8. File Domain

## Document

Documentは、
ProductionまたはOrganizationに関連付けられたファイル情報を表す。

StageArt自身が実ファイルを管理することを前提としない。

主な情報：

- Document ID
- Organization
- Production
- Name
- Description
- External Storage Reference
- Shared Target
- Created By
- Created At

---

## External Storage Reference

External Storage Referenceは、
外部ストレージ上に存在するファイルへの参照を表す。

BetaではGoogle Driveを対象とする。

StageArtは、

- ファイルの関連付け
- ファイル情報
- 共有対象
- アクセス情報

などを管理する。

実ファイルの保存先はGoogle Driveとする。

---

# 9. Public Information Domain

## Organization Public Profile

Organization Public Profileは、
Organizationから生成される公開情報を表す。

公開対象：

- 団体名
- 沿革
- 代表
- 公開対象として選択されたメンバー
- 過去公演
- SNS情報

内部情報は含めない。

---

## Production Public Page

Production Public Pageは、
Productionから生成される公開ページを表す。

公開対象：

- 公演名
- 公演概要
- 日時
- 会場
- 出演者
- 公演画像
- チケット情報
- SNS情報
- 公開対象として選択されたお客様の声
- 公開対象となった実績情報

---

## Social Post Reference

Social Post Referenceは、
SNS上に存在する投稿をStageArt上で参照するための情報を表す。

StageArtではSNS投稿そのものを正本として管理しない。

SNSへの投稿はStageArtから一括投稿できる。

投稿の変更・削除は各SNS側で行う。

StageArtでは、

- SNS
- URL
- 投稿内容のピックアップ
- Productionとの関連

などを参照情報として扱う。

---

## Public Testimonial

Public Testimonialは、
アンケート回答のうち、
代表または権限を持つ者によって公開対象として選択されたものを表す。

元となる情報はSurvey Responseである。

公開対象として選択されていない回答はPublic Testimonialとして扱わない。

---

# 10. Ticket / Audience Domain

## Ticket Type

Ticket Typeは、
Productionにおけるチケット種別を表す。

例：

- 一般
- 学生
- その他

具体的な券種はProductionごとに設定する。

---

## Reservation

Reservationは、
観客によるチケット予約という事実を表す。

主な情報：

- Reservation ID
- Production
- Customer Information
- Ticket Type
- Quantity
- Status
- Reservation Date

Reservationは、
公演当日の受付対象となる。

---

## Ticket

Ticketは、
Reservationに基づいて発行されるチケットを表す。

主な情報：

- Ticket ID
- Reservation
- QR Information
- Status

QR TicketはTicketから生成されるArtifactとして扱う。

---

## Check In

Check Inは、
公演当日に観客が来場した事実を表す。

主な情報：

- Check In ID
- Ticket
- Production
- Checked In At
- Reception Staff

受付方法には、

- QR
- Reservation Number
- Name

などを使用できる。

---

# 11. Survey Domain

## Survey

Surveyは、
Production終了後に観客へ送信するアンケートを表す。

主な情報：

- Survey ID
- Production
- Questions
- Send Timing
- Response Deadline
- Status

SurveyはProductionごとに設定する。

---

## Survey Response

Survey Responseは、
観客がアンケートへ回答した事実を表す。

主な情報：

- Survey
- Respondent
- Answers
- Submitted At

回答には、

- 評価
- 感想
- 推しキャスト
- Production固有の質問

などを含める。

回答期間はProduction終了後3日間とする。

---

# 12. Accounting Domain

## Accounting Period

Accounting Periodは、
Organizationの会計期間を表す。

主な情報：

- Accounting Period ID
- Organization
- Start Date
- End Date
- Status

Accounting Periodは、
団体全体の会計を集計する単位となる。

---

## Budget

Budgetは、
ProductionまたはOrganizationに対して設定された予算計画を表す。

Betaでは、
Production Budgetを中心として扱う。

主な情報：

- Budget ID
- Organization
- Production
- Status
- Total Revenue Budget
- Total Expense Budget

---

## Budget Item

Budget Itemは、
Budget内の個別の予算項目を表す。

収入：

- チケット収入
- 協賛金
- 拠出金
- その他収入

支出：

- 消耗品費
- 会場費
- 人件費
- 機器レンタル費用
- チケットバック
- その他支出

などを扱う。

主な情報：

- Budget
- Category
- Description
- Amount

---

## Journal Entry

Journal Entryは、
団体会計における一つの仕訳を表す。

主な情報：

- Journal Entry ID
- Organization
- Accounting Period
- Date
- Description
- Production
- Reference
- Status

Productionに関連する仕訳は、
Productionの実績として利用できる。

---

## Journal Entry Line

Journal Entry Lineは、
Journal Entryを構成する個々の勘定行を表す。

主な情報：

- Journal Entry
- Account
- Debit
- Credit
- Category

StageArtでは、
高度な会計機能を目的としない。

Betaでは、
必要な収入・支出を簡易的に管理できることを優先する。

---

## Production Actual

Production Actualは、
Productionに対する実際の収入・支出を表す。

Production Actualは、
Journal Entryから参照・集計できる。

Production Actualを別途手入力することで、
同じ実績を二重管理しない。

---

## Budget vs Actual

Budget vs Actualは、
Productionの予算と実績を比較した結果を表す。

これは独立したFactとして二重保存せず、

Budget

＋

Production Actual

から生成するArtifactとして扱う。

比較項目：

- 予算
- 実績
- 差額

---

## Financial Report

Financial Reportは、
会計情報から生成される財務レポートを表す。

主なもの：

- Production Profit and Loss
- Organization Profit and Loss
- Balance Sheet
- Budget vs Actual

レポートは会計Factから生成する。

レポートそのものを会計情報の正本として扱わない。

---

# 13. Organization Management Domain

## Regulation

Regulationは、
Organizationの団体規約を表す。

Regulation自体を直接上書きしない。

Version管理を行う。

---

## Regulation Version

Regulation Versionは、
規約の一つの版を表す。

主な情報：

- Regulation Version ID
- Organization
- Version Number
- Content
- Created At
- Effective Date
- Created By

過去Versionは削除せず保持する。

現在のVersionと過去Versionを区別する。

規約変更権限を持つのは、
Managerおよび規約変更権限を付与されたDelegateとする。

---

## Annual Plan

Annual Planは、
Organizationの年度計画を表す。

主な情報：

- Annual Plan ID
- Organization
- Fiscal Year
- Activities
- Production Plans
- Policy
- Status

Annual Planは、
団体活動の年間計画を管理するために利用する。

公民館等への団体登録・活動申請に利用できる形式を想定する。

---

# 14. Equipment Domain

## Equipment

Equipmentは、
Organizationが保有・管理する備品を表す。

備品管理の目的は、
金額や資産価値ではなく、
所在と管理者を明らかにすることである。

主な情報：

- Equipment ID
- Organization
- Name
- Category
- Location
- Custodian
- Status
- Memo

Statusには、

- Available
- Loaned
- Unknown
- Disposed

などを使用する。

取得価格や減価償却情報は保持しない。

---

## Equipment History

Equipment Historyは、
備品の所在や管理者などの変更履歴を表す。

主な情報：

- Equipment
- Changed At
- Changed By
- Previous Location
- New Location
- Previous Custodian
- New Custodian
- Status Change
- Memo

これにより、
「誰が持っているか」
「どこにあるか」
を現在情報として確認できるだけでなく、
変更履歴も追跡できる。

---

# 15. External Integration Domain

## Google Calendar Integration

Google Calendar Integrationは、
StageArtのRehearsalをGoogle Calendarへ連携するためのIntegrationである。

StageArt上のRehearsalが正本であり、
Google Calendar上の予定は外部Artifactとして扱う。

基本Flow：

Rehearsal Candidate

↓

Rehearsal Availability

↓

Rehearsal確定

↓

Google Calendar Event生成

↓

Rehearsal Attendance

---

## Google Drive Integration

Google Drive Integrationは、
StageArtとGoogle Driveを接続するためのIntegrationである。

StageArtからGoogle Drive上のファイルを扱えるようにする。

StageArtは実ファイルの正本ではない。

---

# 16. Domain Relationship

StageArtの基本的なDomain Relationshipは以下とする。

Person
│
├── Profile
│
└── Membership
       │
       └── Organization
              │
              ├── Regulation
              │     └── Regulation Version
              │
              ├── Annual Plan
              │
              ├── Accounting Period
              │     └── Journal Entry
              │            └── Journal Entry Line
              │
              ├── Equipment
              │     └── Equipment History
              │
              └── Production
                     │
                     ├── Assignment
                     │
                     ├── Budget
                     │     └── Budget Item
                     │
                     ├── Rehearsal Candidate
                     │     └── Rehearsal Availability
                     │
                     ├── Rehearsal
                     │     └── Rehearsal Attendance
                     │
                     ├── Timetable
                     │     └── Timetable Item
                     │
                     ├── Announcement
                     │
                     ├── Document
                     │
                     ├── Ticket Type
                     │     └── Reservation
                     │            └── Ticket
                     │                   └── Check In
                     │
                     ├── Survey
                     │     └── Survey Response
                     │
                     ├── Production Actual
                     │
                     ├── Public Page
                     │
                     └── Social Post Reference

---

# 17. Production as the Central Domain

Productionは、
StageArt Betaにおける活動Lifecycleの中心Domainとする。

Productionには、

- Assignment
- Budget
- Rehearsal
- Timetable
- Document
- Announcement
- Ticket
- Reservation
- Check In
- Survey
- Production Actual
- Public Page

などが関連する。

これにより、一つの公演について、

企画

↓

予算

↓

キャスティング

↓

稽古

↓

準備

↓

広報

↓

予約

↓

受付

↓

アンケート

↓

実績

↓

予実比較

↓

収支

↓

アーカイブ

までを一つのLifecycleとして扱う。

---

# 18. Accounting Relationship

公演会計と団体会計は、
別々の会計システムとして作らない。

Productionに関連する実際の収入・支出は、
Journal Entryとして団体会計へ記録される。

そのJournal EntryをProduction単位で集計することで、
Production Actualを取得する。

したがって、

Journal Entry

↓

Production Actual

↓

Budget vs Actual

↓

Production Result

という関係になる。

一方、

Journal Entry

↓

Accounting Period

↓

PL / BS

という経路によって、
団体全体の会計を生成する。

同じ会計Factを二重管理しない。

---

# 19. Public Artifact Relationship

Public Pageは、
独立した情報源として活動内容を管理しない。

以下のDomainから必要な情報を取得して生成する。

Organization

↓

Organization Public Profile

Production

↓

Production Public Page

Assignment

↓

出演者情報

Survey Response

↓

Public Testimonial

Social Post Reference

↓

SNS情報

これにより、
Public Pageに古い情報を個別保存する必要をなくす。

---

# 20. External Service Relationship

外部サービスは、
StageArtのDomain Modelそのものではない。

StageArt Domain

↓

Integration

↓

External Service

という構造とする。

例：

Rehearsal
↓
Google Calendar Integration
↓
Google Calendar Event

Document
↓
Google Drive Integration
↓
Google Drive File

外部サービスの情報をStageArtの正本としない。

---

# 21. Domain Rules

StageArtのDomainには以下の原則を適用する。

### Rule 1

PersonとOrganizationを直接一対一で結び付けない。

Membershipを介して所属を表現する。

### Rule 2

Organizationへの所属とProductionへの参加を分離する。

MembershipとAssignmentは別Domainとする。

### Rule 3

予算と実績を分離する。

Budgetは計画、
Journal Entryは実績のFactとする。

### Rule 4

Production Actualを二重管理しない。

Journal EntryからProduction Actualを生成・集計する。

### Rule 5

Budget vs ActualをFactとして保存しない。

BudgetとProduction Actualから生成するArtifactとする。

### Rule 6

Google Calendarを稽古情報の正本としない。

Rehearsalを正本とする。

### Rule 7

Google DriveをStageArtのDocument Domainそのものとしない。

DocumentはStageArt上の関連情報、
実ファイルはGoogle Driveに存在する。

### Rule 8

Public Pageに内部情報を保存しない。

Public PageはDomain Factから生成する。

### Rule 9

規約を上書きしない。

Regulation Versionを生成し、
過去Versionを保持する。

### Rule 10

備品を資産会計として扱わない。

Equipmentは所在・管理者・状態を管理する。

---

# 22. Domain Model Scope

Betaでは、
以下のDomainを正式な対象とする。

### Identity

- Person
- Profile

### Organization

- Organization
- Membership
- Role

### Production

- Production
- Assignment
- Rehearsal Candidate
- Rehearsal Availability
- Rehearsal
- Rehearsal Attendance
- Timetable
- Timetable Item

### Communication

- Announcement

### File

- Document
- External Storage Reference

### Public

- Organization Public Profile
- Production Public Page
- Social Post Reference
- Public Testimonial

### Ticket / Audience

- Ticket Type
- Reservation
- Ticket
- Check In
- Survey
- Survey Response

### Accounting

- Accounting Period
- Budget
- Budget Item
- Journal Entry
- Journal Entry Line
- Production Actual
- Budget vs Actual
- Financial Report

### Organization Management

- Regulation
- Regulation Version
- Annual Plan
- Equipment
- Equipment History

### Integration

- Google Calendar Integration
- Google Drive Integration

---

# 23. Out of Scope Domain

Betaでは以下のDomainを設計・実装対象としない。

- Ticket Resale
- Waiting List
- Fixed Asset
- Depreciation
- Advanced Inventory
- Fan Club
- Goods Sales
- Advanced CRM
- Advanced Tax Accounting
- Advanced SNS Management

将来的に必要性が確認された場合、
別VersionでDomain Modelを追加する。

---

# Final Domain Principle

StageArtのDomain Modelは、
「何を保存するか」ではなく、

「舞台活動において、何が事実として存在するか」

を中心として設計する。

Personが存在する。

Organizationに所属する。

Productionに参加する。

稽古を行う。

予算を立てる。

支出・収入が発生する。

予約を受ける。

観客が来場する。

アンケートが回答される。

公演が終了する。

その事実から、

Public Page
QR Ticket
Timetable
Financial Report
Budget vs Actual
活動履歴

などのArtifactを生成する。

StageArtは、
これらのFactを一貫して管理することで、
複雑な運営業務を利用者から隠し、
舞台活動そのものをシンプルに扱えるようにする。
