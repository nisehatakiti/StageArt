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
- Regulation
- Regulation Version
- Annual Plan
- Equipment
- Equipment History
- Accounting Period
- Journal Entry
- Journal Entry Line

### Project / Production

- Project
- Production
- Assignment
- Budget
- Budget Item
- Rehearsal Candidate
- Rehearsal Availability
- Rehearsal
- Rehearsal Attendance
- Timetable
- Timetable Item
- Announcement
- Document
- External Storage Reference

### Ticket / Audience

- Ticket Type
- Ticket
- Reservation
- Reservation Item
- Check In
- Survey
- Survey Response

### Public Information

- Organization Public Profile
- Production Public Page
- Social Post Reference
- Public Testimonial

### Accounting / Analysis

- Production Actual
- Budget vs Actual
- Financial Report

### Integration

- Google Calendar Integration
- Google Drive Integration

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

Personは複数のOrganizationに所属できる。

また、Organizationに所属していなくても、
StageArt上で自身のProfileを作成することができる。

---

## Profile

Profileは、
Person自身が作成・管理できる個人プロフィールを表す。

ProfileはPersonによって任意に作成できる。

主な情報：

- Display Name
- Biography
- Profile Image
- Activity Information
- Public Status

ProfileはPersonに紐付く。

出演履歴や所属履歴などのFactそのものはProfileに保存しない。

出演実績はProject / Assignment等のFactから生成・参照する。

Profileでは、
それらの実績を公開プロフィール上に表示する。

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

Organizationは、
複数のProjectを管理する。

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
OrganizationまたはProjectにおける利用者の役割・権限を表す。

基本的なRoleとして、

- Manager
- Delegate
- Member
- Reception

などを扱う。

RoleはPersonそのものに付与しない。

MembershipまたはProject上の権限に対して付与する。

必要に応じて複数Roleを持つことができる。

---

# 4. Project / Production Domain

## Project

Projectは、
Organizationが行う一つの制作・活動単位を表す。

Projectは内部的な制作全体を管理するDomainである。

例えば、

- 公演制作
- ライブ制作
- 朗読公演制作
- セミナー開催

などを一つのProjectとして扱う。

主な情報：

- Project ID
- Organization
- Name
- Description
- Status
- Start Date
- End Date
- Internal Information

Projectには一つのProductionが紐付く。

Projectは、

- キャスティング
- 稽古
- タイムテーブル
- ファイル
- 内部連絡
- 予算
- 実績
- 収支

など、制作全体に関する情報を管理する。

利用者は通常、
Projectという内部Domainを意識する必要はない。

「公演を作る」などの操作によって、
StageArtがProjectとProductionを生成する。

---

## Production

Productionは、
Projectに紐付く観客向けの公演・活動実施単位を表す。

Projectが「制作全体」を表すのに対し、
Productionは「観客へ公開される公演・活動」を表す。

主な情報：

- Production ID
- Project
- Name
- Description
- Start Date
- End Date
- Venue
- Scheduled End Time
- Status
- Public Information

Productionは、

- 公演ページ
- チケット
- 予約
- 受付
- アンケート
- 公開実績

など、観客との接点となるDomainを管理する。

---

## Project and Production Rule

基本構造は、

Organization
↓
Project
↓
Production

とする。

ProjectとProductionを同一Domainとして扱わない。

Projectは制作・運営の内部単位であり、
Productionは観客向けの公演単位である。

再演を行う場合、
初演と再演は別Projectとして管理する。

例：

Organization
└ Project「作品A 初演」
   └ Production「作品A」

Organization
└ Project「作品A 再演」
   └ Production「作品A 再演」

---

# 5. Assignment Domain

## Assignment

Assignmentは、
PersonがProjectに参加する事実を表す。

Organizationへの所属とは別に管理する。

主な情報：

- Assignment ID
- Project
- Person
- Role
- Assignment Type
- Status

例：

Person A

Organization
→ Manager

Project X
→ Actor

Person B

Organization
→ Member

Project X
→ Staff

客演など、
Organization Membershipを持たないPersonがProjectへ参加することも許容する。

Assignmentから、
Production Public Pageへ出演者情報を生成できる。

---

# 6. Rehearsal Domain

## Rehearsal Candidate

Rehearsal Candidateは、
稽古日程を決定するために提示された候補日を表す。

主な情報：

- Candidate ID
- Project
- Date
- Start Time
- End Time
- Location
- Status

Candidateは、
日程調整を行う場合に使用する。

すべてのRehearsalがCandidateから生成される必要はない。

---

## Rehearsal Availability

Rehearsal Availabilityは、
Rehearsal Candidateに対する日程調整のための回答を表す。

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
「候補日のうち、どの日なら参加可能か」
を確認するための情報である。

---

## Rehearsal

Rehearsalは、
Projectにおける具体的な予定を表す。

主な情報：

- Rehearsal ID
- Project
- Date
- Start Time
- End Time
- Location
- Content
- Memo
- Status

Rehearsalは、

1. Rehearsal Candidateから確定する
2. 直接作成する

の両方を許容する。

したがって、
日程調整を必要としない予定も直接登録できる。

例：

- 最初から日程が決まっている稽古
- 本番日程
- 小屋入り
- ゲネプロ
- その他の制作予定

Rehearsal Candidateは、
Rehearsalを作成するための唯一の入口ではない。

---

## Rehearsal Attendance

Rehearsal Attendanceは、
確定したRehearsalへの参加確認を表す。

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

Rehearsal Availabilityは
「日程調整のための回答」、

Rehearsal Attendanceは
「確定した予定への参加確認」

を表す。

---

# 7. Timetable Domain

## Timetable

Timetableは、
稽古日や小屋入り後などの具体的な進行予定を表す。

主な情報：

- Timetable ID
- Project
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

Rehearsalが「予定」を表すのに対し、
Timetableは「その日の具体的な進行」を表す。

---

# 8. Communication Domain

## Announcement

Announcementは、
OrganizationまたはProjectの関係者へ伝達する内部連絡を表す。

主な情報：

- Announcement ID
- Organization
- Project
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

# 9. File Domain

## Document

Documentは、
OrganizationまたはProjectに関連付けられたファイル情報を表す。

StageArt自身が実ファイルを管理することを前提としない。

主な情報：

- Document ID
- Organization
- Project
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

# 10. Budget Domain

## Budget

Budgetは、
Projectに対する予算計画を表す。

一つのProjectに対して、
複数のBudgetを作成できる。

Budgetには、
利用者が識別しやすい名前を付ける。

例：

- A会場案
- B会場案
- 一日2公演案
- 一日1公演案

Budgetは、
単なる一つの予算金額ではなく、
一つのProjectに対する予算シナリオとして扱う。

主な情報：

- Budget ID
- Project
- Name
- Description
- Status
- Total Revenue Budget
- Total Expense Budget

複数のBudgetのうち、
実際に採用する予算を選択できる。

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

Budget Itemは、
予算シナリオごとの金額を保持する。

---

# 11. Ticket / Audience Domain

## Ticket Type

Ticket Typeは、
チケットの種別を表す。

例：

- 一般
- 学生
- その他

Ticket Typeそのものは、
価格を持たない。

---

## Ticket

Ticketは、
Productionごとに設定されるチケットマスタを表す。

Ticket TypeとPriceを一つの組み合わせとして管理する。

例：

Production A

- 一般 / 3,000円
- 学生 / 2,000円
- 当日券 / 3,500円

主な情報：

- Ticket ID
- Production
- Ticket Type
- Price
- Name
- Description
- Status

同じTicket Typeでも、
Productionごとに異なるPriceを設定できる。

Ticketは、
観客が購入・予約する際の基準となる。

---

## Reservation

Reservationは、
観客によるチケット予約という事実を表す。

主な情報：

- Reservation ID
- Production
- Customer Information
- Status
- Reservation Date

Reservationは、
一つ以上のReservation Itemを持つ。

---

## Reservation Item

Reservation Itemは、
一つのReservationにおけるチケット種別と数量を表す。

主な情報：

- Reservation
- Ticket
- Quantity
- Unit Price

Unit Priceは、
予約時点のTicket Priceを記録する。

これにより、
後からTicketマスタの価格を変更しても、
過去のReservationの金額が変化しない。

---

## Check In

Check Inは、
公演当日に観客が来場した事実を表す。

主な情報：

- Check In ID
- Reservation
- Production
- Checked In At
- Reception Staff

受付方法には、

- QR
- Reservation Number
- Name

などを使用できる。

QR TicketはReservationから生成されるArtifactとして扱う。

---

# 12. Survey Domain

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

# 13. Accounting Domain

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

## Journal Entry

Journal Entryは、
団体会計における一つの仕訳を表す。

主な情報：

- Journal Entry ID
- Organization
- Accounting Period
- Date
- Description
- Project
- Reference
- Status

Production単位ではなく、
Project単位で公演制作に関する会計を紐付ける。

---

## Journal Entry Line

Journal Entry Lineは、
Journal Entryを構成する費目ごとの行を表す。

一つの費目につき一つのLineを持つ。

主な情報：

- Journal Entry
- Account / Category
- Amount
- Is Debit

`Is Debit`によって、
そのLineが借方か貸方かを表現する。

例：

Journal Entry

「会場費を支払った」

↓

Line 1
会場費
50,000円
Is Debit = true

↓

Line 2
現金
50,000円
Is Debit = false

Journal Entry Line単位で費目を分けることで、
費目別集計およびProject別集計を可能にする。

---

# 14. Production Actual Domain

## Production Actual

Production Actualは、
Project / Productionに対する実際の収入・支出を表す。

実績は、
Journal Entryから参照・集計する。

実績を別途手入力して、
同じ金額を二重管理しない。

Projectに紐付いたJournal Entryから、
公演制作の実績を集計できる。

---

# 15. Budget vs Actual Domain

## Budget vs Actual

Budget vs Actualは、
Budgetと実績を比較した結果を表す。

これは独立したFactとして二重保存しない。

Budget

＋

Journal Entryから集計したActual

から生成するArtifactとして扱う。

比較項目：

- Budget
- Actual
- Difference

比較は費目単位で行う。

例：

| 費目 | 予算 | 実績 | 差額 |
|---|---:|---:|---:|
| 会場費 | 100,000 | 110,000 | +10,000 |
| 消耗品費 | 30,000 | 24,000 | -6,000 |
| 人件費 | 80,000 | 80,000 | 0 |

---

# 16. Financial Report Domain

## Financial Report

Financial Reportは、
会計情報から生成される財務レポートを表す。

主なもの：

- Project Profit and Loss
- Organization Profit and Loss
- Balance Sheet
- Budget vs Actual

レポートは会計Factから生成する。

レポートそのものを会計情報の正本として扱わない。

---

# 17. Organization Management Domain

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
- Project Plans
- Policy
- Status

Annual Planは、
団体活動の年間計画を管理するために利用する。

公民館等への団体登録・活動申請に利用できる形式を想定する。

---

# 18. Equipment Domain

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

---

# 19. Public Information Domain

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
- Organization / Productionとの関連

などを参照情報として扱う。

---

## Public Testimonial

Public Testimonialは、
アンケート回答のうち、
代表または権限を持つ者によって公開対象として選択されたものを表す。

元となる情報はSurvey Responseである。

公開対象として選択されていない回答はPublic Testimonialとして扱わない。

---

# 20. External Integration Domain

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

または、

Rehearsal直接作成

↓

Google Calendar Event生成

Google Calendarへの登録対象は、
実際の稽古参加者に限定しない。

予定を把握する必要がある関係者を登録対象とする。

---

## Google Drive Integration

Google Drive Integrationは、
StageArtとGoogle Driveを接続するためのIntegrationである。

StageArtからGoogle Drive上のファイルを扱えるようにする。

StageArtは実ファイルの正本ではない。

---

# 21. Person-Centric Relationship

人物を中心としたDomain Relationshipは以下とする。

Person
│
├── Profile
│
├── Membership
│      └── Organization
│
└── Assignment
       └── Project
              └── Production

PersonはStageArt全体で一つのIdentityを持つ。

Organizationへの所属はMembershipで表現する。

Projectへの参加はAssignmentで表現する。

ProfileはPerson自身が作成・管理できる。

出演実績はAssignmentおよびProductionから生成・参照する。

---

# 22. Organization-Centric Relationship

団体を中心としたDomain Relationshipは以下とする。

Organization
│
├── Membership
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
└── Project
      │
      ├── Production
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
      └── Project Actual

Production
│
├── Ticket
│     └── Reservation
│            └── Reservation Item
│
├── Check In
│
├── Survey
│     └── Survey Response
│
└── Production Public Page

---

# 23. Project / Production Relationship

StageArtの基本構造は、

Organization
↓
Project
↓
Production

とする。

Projectは、
一つの制作・活動全体を管理する。

Productionは、
そのProjectにおける観客向けの公演・活動実施を表す。

Projectは、

- キャスティング
- 稽古
- タイムテーブル
- ファイル
- 内部連絡
- 予算
- 実績
- 収支

などを管理する。

Productionは、

- 公開情報
- 公演日程
- 会場
- チケット
- 予約
- 受付
- アンケート
- 観客向け実績

などを管理する。

---

# 24. Accounting Relationship

公演会計と団体会計は、
別々の会計システムとして作らない。

Projectに関連する実際の収入・支出は、
Journal Entryとして団体会計へ記録される。

そのJournal EntryをProject単位で集計することで、
Project Actualを取得する。

したがって、

Journal Entry

↓

Project Actual

↓

Budget vs Actual

↓

Project Result

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

# 25. Budget Relationship

Projectには複数のBudgetを持つことができる。

例えば、

Project「作品A」

├── Budget「A会場案」
├── Budget「B会場案」
└── Budget「一日2公演案」

のように管理する。

各BudgetにはBudget Itemが存在する。

Budgetは制作前の計画情報である。

実際に採用したBudgetと、
実際に発生したJournal Entryを比較することで、
Projectの予実を確認する。

---

# 26. Public Artifact Relationship

Public Pageは、
独立した情報源として活動内容を管理しない。

以下のDomainから必要な情報を取得して生成する。

Organization

↓

Organization Public Profile

Project / Production

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

# 27. External Service Relationship

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

# 28. Domain Rules

StageArtのDomainには以下の原則を適用する。

### Rule 1

PersonとOrganizationを直接一対一で結び付けない。

Membershipを介して所属を表現する。

### Rule 2

Organizationへの所属とProjectへの参加を分離する。

MembershipとAssignmentは別Domainとする。

### Rule 3

ProjectとProductionを分離する。

Projectは制作・運営の内部単位、
Productionは観客向けの公演単位とする。

### Rule 4

ProfileはPerson自身が作成・編集できる。

Profileを自動生成情報だけで構成しない。

### Rule 5

RehearsalはRehearsal Candidateからのみ生成されるものではない。

直接作成できる。

### Rule 6

TicketはProductionごとのチケットマスタとする。

Ticket TypeとPriceを一つの組み合わせとして管理する。

### Rule 7

BudgetはProjectごとに複数作成できる。

Budgetには利用者が識別できる名前を付ける。

### Rule 8

予算と実績を分離する。

Budgetは計画、
Journal Entryは実績のFactとする。

### Rule 9

Journal Entry Lineは費目ごとに分ける。

借方・貸方は`Is Debit`フラグで表現する。

### Rule 10

Project Actualを二重管理しない。

Journal EntryからProject Actualを生成・集計する。

### Rule 11

Budget vs ActualをFactとして保存しない。

BudgetとActualから生成するArtifactとする。

### Rule 12

Google Calendarを予定情報の正本としない。

Rehearsalを正本とする。

### Rule 13

Google DriveをStageArtのDocument Domainそのものとしない。

DocumentはStageArt上の関連情報、
実ファイルはGoogle Driveに存在する。

### Rule 14

Public Pageに内部情報を保存しない。

Public PageはDomain Factから生成する。

### Rule 15

規約を上書きしない。

Regulation Versionを生成し、
過去Versionを保持する。

### Rule 16

備品を資産会計として扱わない。

Equipmentは所在・管理者・状態を管理する。

---

# 29. Domain Model Scope

Betaでは、
以下のDomainを正式な対象とする。

### Identity

- Person
- Profile

### Organization

- Organization
- Membership
- Role
- Regulation
- Regulation Version
- Annual Plan
- Equipment
- Equipment History
- Accounting Period
- Journal Entry
- Journal Entry Line

### Project / Production

- Project
- Production
- Assignment
- Budget
- Budget Item
- Rehearsal Candidate
- Rehearsal Availability
- Rehearsal
- Rehearsal Attendance
- Timetable
- Timetable Item
- Announcement
- Document
- External Storage Reference

### Ticket / Audience

- Ticket Type
- Ticket
- Reservation
- Reservation Item
- Check In
- Survey
- Survey Response

### Public

- Organization Public Profile
- Production Public Page
- Social Post Reference
- Public Testimonial

### Accounting / Analysis

- Project Actual
- Budget vs Actual
- Financial Report

### Integration

- Google Calendar Integration
- Google Drive Integration

---

# 30. Out of Scope Domain

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

人物軸では、

Person
↓
Profile
↓
Membership / Assignment
↓
Organization / Project

という関係を持つ。

団体軸では、

Organization
↓
Project
↓
Production

を基本構造とする。

Projectは制作全体を管理し、
Productionは観客向けの公演を表す。

その上に、

予算
↓
実績
↓
予実比較
↓
収支

稽古候補
↓
日程調整
↓
稽古確定
↓
参加確認

予約
↓
受付
↓
アンケート

などのDomainが接続する。

StageArtは、
これらのFactを一貫して管理することで、
複雑な運営業務を利用者から隠し、
舞台活動そのものをシンプルに扱えるようにする。
