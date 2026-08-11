# StageArt Blueprint

# 04 - Domain Model

Version : 3.0

---

## Purpose

Domain Modelは、
StageArtが管理する「事実」と「業務上の概念」を定義する。

Domain ModelはUIやDatabase Schemaから独立して設計する。

StageArtにおけるすべてのBusiness Flowは、
Domain Modelを操作することで実現する。

---

# 1. Domain Architecture

StageArtのDomainは、
大きく以下の領域に分ける。

## Person Domain

個人Identityおよび個人に関する情報を管理する。

- Account
- Person
- Profile

---

## Organization Domain

団体と所属関係を管理する。

- Organization
- Membership
- Role
- DelegateRole
- Organization Invitation
- Organization Membership Request

---

## Project Domain

団体が行う活動・制作を管理する。

- Project
- Production
- Participant
- Subject
- Production Delegate

---

## Rehearsal Domain

稽古および稽古日程を管理する。

- Rehearsal Candidate
- Rehearsal Availability
- Rehearsal
- Rehearsal Attendance
- Timetable
- Timetable Item

---

## Ticket Domain

公演のチケットおよび予約を管理する。

- Ticket
- Ticket Type
- Ticket Price
- Performance
- Reservation
- Issued Ticket
- Check In

---

## Communication Domain

公演関係者への連絡を管理する。

- Announcement
- Announcement Recipient
- Announcement Delivery

---

## Document Domain

公演関係ファイルおよび外部ストレージとの連携を管理する。

- Document
- Document Share
- External Connection
- External Storage Reference

---

## Promotion Domain

団体・公演の公開情報およびSNS連携を管理する。

- Organization Public Profile
- Production Public Page
- Social Post
- Social Post Reference
- Tag
- External Connection

---

## Accounting Domain

団体会計および公演の予算・実績を管理する。

- Accounting Period
- Account
- Journal Entry
- Journal Entry Line
- Budget
- Budget Item
- Production Actual
- Budget vs Actual
- Production Settlement

---

## Equipment Domain

団体が保有・管理する備品を管理する。

- Equipment
- Equipment History

備品は資産価値を管理するためのDomainではない。

---

## Regulation Domain

団体規約をVersion管理する。

- Regulation
- Regulation Version

---

## Survey Domain

公演終了後のアンケートを管理する。

- Survey
- Survey Response
- Public Testimonial

---

# 2. Person Axis

PersonはStageArtにおける個人Identityである。

PersonはOrganizationとは独立して存在する。

一人のPersonは複数のOrganizationに所属できる。

---

## Account

StageArtへの認証Identityを表す。

Accountは、

- Google Account
- Email Account

などの認証手段を管理する。

AccountとPersonは同一概念ではない。

Accountは認証、
PersonはStageArt上の個人Identityを表す。

---

## Person

StageArt上の個人を表す。

PersonはOrganizationに直接所属しない。

Organizationへの所属はMembershipによって表現する。

Personは、

- Profile
- Membership
- Participant
- Reservation
- Rehearsal Attendance

などのDomainから参照される。

---

## Profile

Person自身が作成・管理するプロフィール情報。

Profileは自動生成された出演履歴そのものではない。

利用者自身が、

- 氏名
- 表示名
- 写真
- 自己紹介
- 経歴
- その他プロフィール情報

などを入力・編集できる。

出演実績などの履歴情報は、
ParticipantおよびProductionの情報から生成・表示する。

---

# 3. Organization Axis

Organizationは、
劇団・団体などの活動主体を表す。

Personとは独立したDomainである。

---

## Organization

団体そのものを表す。

主な情報：

- 団体名
- 団体タイプ
- 代表者
- 沿革
- 公開情報
- SNS情報
- 規約
- 年度計画

OrganizationはProjectを保持する。

---

## Membership

PersonとOrganizationの所属関係を表す。

Person
↓
Membership
↓
Organization

一人のPersonは複数のOrganizationにMembershipを持つことができる。

MembershipごとにRoleを持つ。

---

## Role

OrganizationにおけるPersonの権限を表す。

同じPersonでも、
Organizationごとに異なるRoleを持つことができる。

例：

劇団A
→ Manager

劇団B
→ Cast

---

## DelegateRole

管理者が、
他のPersonへ委任する権限を表す。

Delegateは管理者と同等の権限を持つ場合と、
個別の権限を組み合わせて付与する場合の
両方に対応できる構造とする。

---

# 4. Project / Production Axis

人物軸と団体軸を混在させない。

基本構造は、

Organization
↓
Project
↓
Production

とする。

---

## Project

Organizationが行う活動・制作の内部単位。

ProjectはOrganizationに所属する。

Projectは一つ以上のProductionを持つことができる。

Projectは、
利用者が必ずしも意識する必要のない内部Domainである。

---

## Production

具体的な公演・活動を表す。

ProductionはProjectに所属する。

Productionには、

- 公演情報
- 公演日
- Performance
- Participant
- Ticket
- Reservation
- Rehearsal
- Budget
- Survey
- Public Page
- Internal Page

などが関連する。

Productionは、
StageArtにおける一つの活動Lifecycleの中心となるDomainである。

---

# 5. Participant Axis

Participantは、
Productionへの参加という事実を表す。

Production
↓
Participant
↓
Subject
├─ Person
└─ Organization

Participantは、
Productionへの参加者を管理する正本である。

---

## Subject

Participantが参照する参加主体を表す。

Subjectは、

- Person
- Organization

のいずれかを表現できる。

これにより、

- 個人キャスト
- 個人スタッフ
- 客演者
- 外部劇団
- 制作会社
- その他の参加団体

などを同じParticipant構造で扱うことができる。

---

## Participant

Participantには、

- Subject
- Participant Type
- Role
- Credit Order
- Visibility
- Status

などを持たせる。

例：

Production
「StageArt公演」

Participant
  Subject = Person A
  Type = CAST
  Role = 主演

Participant
  Subject = Person B
  Type = STAFF
  Role = 音響

Participant
  Subject = Organization C
  Type = PARTNER

Participantが、
公演に誰がどのような立場で参加したかを表す。

---

# 6. Production Delegate

Production単位の管理権限を表す。

Organizationの管理者が、
特定のProductionについて他のPersonへ権限を委任できる。

Production
↓
Production Delegate
↓
Person

Delegateの権限は、
Organization全体のRoleとは別に管理する。

---

# 7. Rehearsal Domain

稽古は、
Rehearsal Candidateを経由する場合と、
直接作成する場合の両方を許容する。

---

## Rehearsal Candidate

日程調整を行うための候補日。

Rehearsal Candidate
↓
Rehearsal Availability
↓
Rehearsal

---

## Rehearsal Availability

PersonがRehearsal Candidateに対して回答した情報。

候補日の調整に利用する。

---

## Rehearsal

確定した稽古・予定。

Rehearsalは、

1. Rehearsal Candidateから生成する
2. 直接作成する

の両方に対応する。

直接作成の例：

- あらかじめ決まっている稽古
- ゲネプロ
- 本番前確認
- その他の確定予定

---

## Rehearsal Attendance

確定したRehearsalへの参加確認。

Rehearsal Candidateへの日程調整回答とは別のDomainとして扱う。

---

## Timetable

稽古・小屋入り・本番等の日別進行を管理する。

Timetableは、

- 時刻
- 内容
- 場所
- 担当
- 対象者
- 備考

などを管理する。

---

# 8. Ticket Domain

TicketはProductionごとに管理する。

Ticketの販売条件は、
公演ごとのTicket Masterとして保持する。

基本構造：

Production
↓
Ticket
├─ Ticket Type
└─ Price

同じTicket Typeでも、
ProductionごとにPriceを変更できる。

例：

一般 → 3,000円
学生 → 2,000円
当日 → 3,500円

---

## Performance

Productionにおける個別の公演回を表す。

例：

Production
「○○公演」

Performance
- 9/1 14:00
- 9/1 19:00
- 9/2 14:00

---

## Reservation

観客による予約という事実を表す。

Reservationは、

- Person
- Performance
- Ticket
- Quantity
- Reservation Status

などを参照する。

---

## Issued Ticket

予約成立後に発行されるチケット。

QRコードなどのTicket Artifactは、
Issued Ticketを元に生成する。

---

## Check In

公演当日の来場受付という事実を表す。

Check Inは、

- QR読取
- 予約番号検索
- 氏名検索

などによって実行される。

---

# 9. Budget / Accounting Domain

BudgetとAccountingは、
異なる目的を持つ。

Budgetは、
公演前の計画を管理する。

Accountingは、
実際に発生した収入・支出を管理する。

---

## Budget

Productionの予算案を表す。

一つのProductionに複数のBudgetを持つことができる。

Budgetには利用者が自由に名称を付ける。

例：

A会場案
B会場案
一日2公演案

---

## Budget Item

Budget内の費目を表す。

収入・支出を費目ごとに管理する。

---

## Journal Entry

会計上の一つの仕訳を表す。

---

## Journal Entry Line

仕訳内の費目ごとの行を表す。

費目ごとにLineを分けて管理する。

貸借区分はFlagで管理する。

is_debit = true
is_debit = false

---

## Production Actual

Productionに紐付く実績情報。

実際に発生した収入・支出を管理する。

---

## Budget vs Actual

BudgetとActualを比較する。

基本的な構造：

Budget
↓
Actual
↓
Variance

---

## Production Settlement

Production単位の最終的な収支を表す。

団体全体のAccountingとは別の視点で、
公演単位の収入・支出・損益を確認する。

---

# 10. Equipment Domain

Equipmentは、
団体が保有・管理する備品を表す。

金額・資産価値は管理しない。

主な情報：

- 名称
- 分類
- 保管場所
- 現在の管理者
- 状態
- 備考

状態の例：

- 使用可能
- 貸出中
- 不明
- 廃棄

---

## Equipment History

Equipmentの、

- 保管場所変更
- 管理者変更
- 状態変更

などの履歴を管理する。

目的は、

「以前の公演で使った備品が現在どこにあるか」

を追跡できることである。

---

# 11. Regulation Domain

## Regulation

Organizationの規約を表す。

---

## Regulation Version

規約の個別Versionを表す。

規約変更時には既存Versionを上書きせず、
新しいVersionを作成する。

Regulation
├─ Version 1
├─ Version 2
└─ Version 3

現在Versionと過去Versionを区別できる。

---

# 12. Document Domain

Documentは、
公演・団体に関連付けられたファイル情報を管理する。

実ファイルはGoogle Driveで管理する。

StageArtでは、

- ファイル情報
- 公演との関連
- 共有対象
- Google Drive上の参照情報

などを管理する。

---

# 13. Communication Domain

Announcementは、
内部関係者への連絡情報を表す。

管理者または権限を持つ代理人が作成できる。

対象者は、

- CAST
- STAFF
- 制作
- その他関係者

などから指定する。

送信履歴を保持する。

---

# 14. Promotion Domain

Organization Public PageおよびProduction Public Pageは、
Domain Factから生成される公開Artifactとして扱う。

SNS情報は、
StageArtがSNS投稿そのものを正本として保持するのではなく、
外部SNSへの参照情報として扱う。

---

## Social Post Reference

外部SNS上の投稿を参照するための情報。

---

## Tag

SNS情報とOrganization / Productionを関連付けるために利用する。

---

# 15. Survey Domain

## Survey

Production終了後に実施するアンケートを表す。

Productionには終了予定時刻を設定する。

終了予定時刻を基準として、
アンケート依頼を送信する。

---

## Survey Response

観客からのアンケート回答。

---

## Public Testimonial

代表者等が公開対象として選択した回答。

原則として、
アンケート回答は非公開とする。

---

# 16. External Integration Domain

外部サービスとの接続を管理する。

主な対象：

- Google Drive
- Google Calendar
- SNS

StageArt内部のDomainを正本とし、
外部サービスは連携先として扱う。

---

# 17. Domain Relationship

基本的な関係は以下とする。

Person
│
├─ Profile
│
├─ Membership ── Organization
│
└─ Participant
       │
       └─ Subject
              ├─ Person
              └─ Organization


Organization
│
└─ Project
     │
     └─ Production
          │
          ├─ Participant
          ├─ Production Delegate
          │
          ├─ Performance
          │    └─ Reservation
          │         └─ Issued Ticket
          │              └─ Check In
          │
          ├─ Ticket
          │
          ├─ Budget
          │    └─ Budget Item
          │
          ├─ Production Actual
          │
          ├─ Budget vs Actual
          │
          ├─ Rehearsal Candidate
          │    └─ Rehearsal Availability
          │
          ├─ Rehearsal
          │    └─ Rehearsal Attendance
          │
          ├─ Timetable
          │
          ├─ Document
          │
          ├─ Announcement
          │
          └─ Survey
               └─ Survey Response


Organization
│
├─ Regulation
│    └─ Regulation Version
│
├─ Accounting Period
│    └─ Journal Entry
│         └─ Journal Entry Line
│
└─ Equipment
     └─ Equipment History

---

# 18. Important Domain Rules

## PersonとOrganizationは別軸

Personは個人Identity。

Organizationは団体Identity。

PersonがOrganizationに所属する関係は、
Membershipで表現する。

---

## OrganizationとProjectは親子関係

基本構造は、

Organization
    ↓
Project
    ↓
Production

とする。

ProjectはOrganization配下の活動・制作単位である。

---

## Productionへの参加はParticipant

Productionへの参加情報はParticipantを正本とする。

ParticipantはPersonまたはOrganizationをSubjectとして参照する。

---

## Profileは本人が管理する

ProfileはPerson自身が作成・編集できる。

出演実績などの履歴は、
ParticipantおよびProductionから生成する。

---

## RehearsalはCandidate必須ではない

Rehearsalは、

Candidate
↓
Availability
↓
Rehearsal

という流れだけでなく、

直接
↓
Rehearsal

でも作成できる。

---

## TicketはProduction単位

Ticket TypeとPriceの組み合わせは、
ProductionごとのTicket Masterとして管理する。

---

## Budgetは複数案を持てる

一つのProductionに複数のBudgetを持てる。

Budgetには利用者が自由に名称を付ける。

---

## AccountingとBudgetは別Domain

Budgetは計画。

Accountingは実績。

両者をBudget vs Actualで比較する。

---

## Equipmentは資産管理ではない

Equipmentは、

「何を持っているか」
「どこにあるか」
「誰が管理しているか」
「使える状態か」

を管理する。

取得価格・資産価値・減価償却は管理しない。

---

## External Serviceは正本ではない

Google Drive、Google Calendar、SNSなどの外部サービスは、
StageArt Domainの正本ではない。

StageArt内のDomain Factを正本とし、
外部サービスは共有・保存・連携先として扱う。

---

# 19. Domain Model Principle

Domain Modelは、
UI、WordPress、Database Schemaに依存しない。

Domain Modelの変更は、
必要に応じてAPI、ER Diagram、UIなどへ反映する。

05 ER Diagramは、
本章で定義されたDomain Modelをデータ構造へ落とし込んだものである。

Domain ModelとER Diagramが矛盾する場合、
Domain Modelを正とする。
