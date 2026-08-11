# StageArt Blueprint

# DomainMap

Version : 3.0

---

# Purpose

DomainMapはStageArt全体のDomain構造を表す。

ER図やDatabase設計ではなく、
StageArtというサービスを構成する概念同士の関係を定義する。

DomainMapは、
個々のDomainの詳細仕様を定義するものではなく、
StageArt全体のDomain構造を俯瞰するためのものである。

---

# 1. Core Domain Structure

StageArtの基本構造は、

Organization
↓
Project
↓
Production

とする。

PersonとOrganizationは別の軸として管理し、
Organizationへの所属はMembershipによって表現する。

Productionへの参加はParticipantによって表現する。

---

# 2. Domain Map

```text
Account
  │
  └── Person
       │
       ├── Profile
       │
       ├── Membership
       │      │
       │      └── Organization
       │
       ├── History
       │
       ├── Reservation
       │
       └── Rehearsal Attendance


Organization
  │
  ├── Membership
  │
  ├── Role
  │
  ├── Delegate Role
  │
  ├── Project
  │    │
  │    └── Production
  │         │
  │         ├── Category
  │         ├── Genre
  │         ├── Tag
  │         │
  │         ├── Participant
  │         │      │
  │         │      └── Subject
  │         │             ├── Person
  │         │             └── Organization
  │         │
  │         ├── Production Delegate
  │         │
  │         ├── Performance
  │         │      │
  │         │      └── Reservation
  │         │             │
  │         │             ├── Issued Ticket
  │         │             │      │
  │         │             │      └── Check In
  │         │             │
  │         │             └── Reservation Seat
  │         │
  │         ├── Ticket Master
  │         │      ├── Ticket Type
  │         │      └── Price
  │         │
  │         ├── Rehearsal Candidate
  │         │      └── Rehearsal Availability
  │         │
  │         ├── Rehearsal
  │         │      └── Rehearsal Attendance
  │         │
  │         ├── Timetable
  │         │      └── Timetable Item
  │         │
  │         ├── Budget
  │         │      └── Budget Item
  │         │
  │         ├── Production Actual
  │         │
  │         ├── Budget vs Actual
  │         │
  │         ├── Production Settlement
  │         │
  │         ├── Document
  │         │
  │         ├── Announcement
  │         │
  │         ├── Survey
  │         │      └── Survey Response
  │         │
  │         └── Production Public Page
  │
  ├── Regulation
  │      └── Regulation Version
  │
  ├── Accounting Period
  │      └── Journal Entry
  │             └── Journal Entry Line
  │
  ├── Equipment
  │      └── Equipment History
  │
  └── Organization Public Profile


Person
  │
  └── History


External Integration
  │
  ├── Google Drive
  │
  ├── Google Calendar
  │
  └── SNS
```

---

# 3. Person Axis

PersonはStageArt上の個人Identityを表す。

PersonはOrganizationとは独立して存在する。

一人のPersonは複数のOrganizationに所属できる。

---

## Account

StageArtへの認証Identityを表す。

Accountは、

- Google Account
- Email Account

などの認証手段を管理する。

Accountは認証を担当し、
Personそのものを表すものではない。

---

## Person

StageArt上の個人を表す。

Personは、

- 役者
- スタッフ
- 制作
- 観客
- その他舞台芸術関係者

などを区別せず表現する。

---

## Profile

Person自身が作成・編集するプロフィール情報。

ProfileはPersonの個人情報・公開プロフィールを管理する。

出演履歴などの活動履歴はProfileそのものには保存せず、
Historyから参照・表示する。

---

## History

PersonおよびOrganizationの活動履歴を管理する。

Personでは、

- 出演履歴
- スタッフ履歴
- 観劇履歴
- その他活動履歴

などを管理する。

Organizationでは、

- 公演履歴
- 協賛履歴
- 制作協力履歴
- その他活動履歴

などを管理する。

Historyには、

- AUTO
- MANUAL

の2種類を持つ。

StageArt上で発生したFactから自動生成される履歴と、
利用者が登録する過去の活動履歴の両方を扱う。

---

# 4. Organization Axis

Organizationは劇団・団体などの活動主体を表す。

Personとは独立したDomainである。

---

## Organization

団体そのものを表す。

例：

- 劇団
- 制作会社
- 協賛企業
- 芸能事務所
- その他団体

OrganizationはProjectを保持する。

---

## Membership

PersonとOrganizationの所属関係を表す。

一人のPersonは複数のOrganizationに所属できる。

MembershipごとにOrganization内でのRoleを持つ。

---

## Role

Organization内におけるPersonの権限・役割を表す。

同じPersonでも、
Organizationごとに異なるRoleを持つことができる。

例：

劇団A
→ 管理者

劇団B
→ キャスト

---

## Delegate Role

管理者が他のPersonへ委任する権限を表す。

管理者と同等の権限を付与する場合と、
個別の権限を組み合わせて付与する場合の両方に対応する。

---

# 5. Project / Production Axis

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

Projectは利用者が必ずしも意識する必要のないInternal Domainである。

---

## Production

具体的な公演・活動を表す。

ProductionはProjectに所属する。

ProductionはStageArtにおける一つの活動Lifecycleの中心となるDomainである。

Productionには、

- 公演情報
- Category
- Genre
- Tag
- Participant
- Performance
- Ticket
- Reservation
- Rehearsal
- Timetable
- Budget
- Actual
- Document
- Announcement
- Survey

などが関連する。

---

# 6. Participant Axis

ParticipantはProductionへの参加という事実を表す。

Productionへの参加情報はParticipantを正本とする。

```text
Production
    ↓
Participant
    ↓
Subject
   ├── Person
   └── Organization
```

---

## Subject

Participantが参照する参加主体を表す。

Subjectは、

- Person
- Organization

のいずれかを表現する。

---

## Participant

Productionに誰がどのような立場で参加したかを表す。

例：

- キャスト
- スタッフ
- 制作
- 客演
- 協賛
- 制作協力
- 会場提供

など。

Participantには必要に応じて、

- Participant Type
- Role
- Credit Order
- Visibility
- Status

などを持つ。

---

# 7. Production Delegate

Production単位の管理権限を表す。

Organization全体のRoleとは別に、
特定のProductionについてPersonへ権限を委任できる。

```text
Production
    ↓
Production Delegate
    ↓
Person
```

---

# 8. Production Classification

## Category

Productionの公演形態・活動形態を表す。

例：

- 舞台
- ライブ
- 映画
- 配信

CategoryはProductionの属性として管理する。

---

## Genre

Productionの作品ジャンルを表す。

例：

- コメディ
- ホラー
- ミステリー

GenreはProductionの属性として管理する。

---

## Tag

検索・分類用タグ。

Tagは必要に応じて、

- Person
- Organization
- Production
- Performance

などへ付与できる。

---

# 9. Performance / Ticket / Reservation

## Performance

Productionにおける個別の公演回を表す。

例：

- 8/1 昼
- 8/1 夜
- 8/2 昼

Performanceは、

- Seats
- Reservation

と関連する。

---

## Ticket Master

Productionごとのチケット販売条件を管理する。

Ticket Masterは、

- Ticket Type
- Price

の組み合わせを管理する。

同じTicket Typeでも、
ProductionごとにPriceを変更できる。

---

## Ticket Type

チケット種別。

例：

- 一般
- 学生
- 当日

---

## Price

ProductionにおけるTicket Typeの販売価格。

---

## Reservation

観客によるチケット予約というFactを表す。

Reservationは、

- Person
- Performance
- Ticket
- Quantity
- Reservation Status

などと関連する。

---

## Issued Ticket

予約成立後に発行されるチケット。

QRチケットなどのArtifactはIssued Ticketから生成する。

---

## Check In

公演当日の来場受付というFactを表す。

Check Inは、

- QRコード
- 予約番号
- 氏名

などを利用して実行する。

Check In完了後、
観劇履歴などのHistory生成に利用する。

---

# 10. Seats

SeatsはPerformanceの座席を表す。

座席指定は将来実装する。

現在のBetaでは実装しない。

将来、

```text
Performance
    ↓
Seat
    ↓
Reservation Seat
```

という構造へ拡張する。

Seat自体は予約状態を保持しない。

---

## Reservation Seat

指定席予約時に、
ReservationとSeatの対応を保持する。

自由席の場合は使用しない。

現在のBetaでは実装しない。

---

# 11. Rehearsal Domain

稽古は、

```text
Rehearsal Candidate
        ↓
Rehearsal Availability
        ↓
Rehearsal
        ↓
Rehearsal Attendance
```

という流れで管理できる。

ただしRehearsalはCandidateを経由せず、
直接作成することもできる。

---

## Rehearsal Candidate

稽古候補日を表す。

日程調整を行うために利用する。

---

## Rehearsal Availability

PersonがRehearsal Candidateに対して回答した情報。

候補日の調整に利用する。

---

## Rehearsal

確定した稽古・予定を表す。

Candidateから生成する場合と、
直接作成する場合の両方に対応する。

---

## Rehearsal Attendance

確定したRehearsalへの参加確認を表す。

Rehearsal Candidateへの日程調整回答とは別に管理する。

---

# 12. Timetable

Timetableは、
稽古・小屋入り・本番などの日別進行を管理する。

Timetableは、

- 時刻
- 内容
- 場所
- 担当
- 対象者
- 備考

などを管理する。

---

## Timetable Item

Timetable内の個別項目を表す。

---

# 13. Budget / Accounting Domain

BudgetとAccountingは異なる目的を持つ。

Budgetは計画。

Accounting / Actualは実績。

---

## Budget

Productionの予算案を表す。

一つのProductionに複数のBudgetを持つことができる。

Budgetには利用者が自由に名称を付ける。

例：

- A会場案
- B会場案
- 一日2公演案

---

## Budget Item

Budget内の費目を表す。

---

## Production Actual

Productionにおける実績を表す。

実際に発生した収入・支出を管理する。

---

## Budget vs Actual

BudgetとActualを比較する。

費目ごとに、

- Budget
- Actual
- Variance

を確認できる。

---

## Production Settlement

Production単位の最終的な収入・支出・損益を表す。

---

# 14. Organization Accounting

## Accounting Period

Organizationの会計期間を表す。

---

## Account

会計科目を表す。

---

## Journal Entry

会計上の一つの仕訳を表す。

---

## Journal Entry Line

仕訳内の費目ごとの行を表す。

費目ごとにLineを分ける。

貸借区分はFlagで管理する。

```text
is_debit = true
is_debit = false
```

固定資産管理や減価償却は対象としない。

---

# 15. Equipment Domain

Equipmentは団体が保有・管理する備品を表す。

資産価値や取得金額を管理するDomainではない。

主な目的は、

- 何があるか
- どこにあるか
- 誰が持っているか
- 使用可能か
- 廃棄されたか
- 不明になったか

を明らかにすることである。

---

## Equipment

備品本体を表す。

---

## Equipment History

備品の、

- 保管場所変更
- 管理者変更
- 状態変更

などの履歴を管理する。

---

# 16. Regulation Domain

## Regulation

Organizationの規約を表す。

---

## Regulation Version

規約のVersionを表す。

既存Versionを上書きせず、
変更時には新しいVersionを作成する。

---

# 17. Document Domain

## Document

公演・活動に関連するファイル情報を管理する。

実ファイルはGoogle Driveで管理する。

StageArtでは、

- ファイル情報
- 公演との関連
- 共有対象
- 外部ファイルへの参照

などを管理する。

---

## Document Share

Documentの共有対象を管理する。

---

# 18. Communication Domain

## Announcement

内部関係者への一括連絡を表す。

管理者または権限を持つ代理人が作成できる。

対象者は、

- キャスト
- スタッフ
- 制作
- その他関係者

などから指定する。

---

## Announcement Recipient

Announcementの送信対象を表す。

---

## Announcement Delivery

Announcementの送信履歴を表す。

---

# 19. Survey Domain

## Survey

Production終了後に実施するアンケートを表す。

Productionには終了予定時刻を設定する。

終了予定時刻を基準として、
公演直後にアンケート依頼メールを送信する。

---

## Survey Response

観客からのアンケート回答を表す。

---

## Public Testimonial

代表者等が公開対象として選択した回答を表す。

アンケート回答は原則非公開とする。

---

# 20. Public Domain

## Organization Public Profile

Organizationの公開情報を表す。

主な情報：

- 団体名
- 沿革
- 代表
- メンバー
- 過去公演情報
- SNS情報

内部情報は公開しない。

---

## Production Public Page

Productionの公開ページ。

ProductionのDomain Factから生成される公開Artifactとして扱う。

---

# 21. External Integration

外部サービスはStageArt Domainの正本ではない。

StageArt内部のDomain Factを正本とし、
外部サービスは連携先として扱う。

主な連携先：

- Google Drive
- Google Calendar
- SNS

---

## Google Drive

Documentの実ファイル保存先として利用する。

---

## Google Calendar

確定したRehearsalをGoogle Calendarへ連携する。

Calendarへの登録対象は、
Rehearsal参加者に限定しない。

---

## SNS

OrganizationおよびProductionの広報を目的としてSNS連携・投稿機能を提供する。

SNS投稿本文などの投稿内容そのものは、
StageArtのDomainとして管理しない。

---

# 22. Public / Internal Separation

StageArtには、

- Public
- Internal

の2つの利用領域が存在する。

## Public

一般公開する情報。

## Internal

団体・Production関係者向けの情報。

主なInternal機能：

- 稽古管理
- タイムテーブル
- ファイル共有
- 内部お知らせ
- 公演関連情報

一般観客はInternal Portalを利用しない。

---

# 23. Audience Domain

一般観客は、
StageArtのInternal Portalへ入る必要はない。

一般観客は、

- 公演ページ閲覧
- チケット予約・購入
- QRチケット利用
- 公演当日の受付

などを利用できる。

StageArtユーザーとして登録した観客はPersonとして管理される。

StageArtユーザーの観劇履歴は、

Reservation
↓
Issued Ticket
↓
Check In
↓
History

というFactの流れから生成・蓄積する。

---

# 24. Domain Principles

- PersonとOrganizationは別軸として管理する。
- Organizationの下にProjectがあり、その下にProductionが存在する。
- Productionへの参加はParticipantを正本とする。
- ParticipantのSubjectはPersonまたはOrganizationとする。
- ProfileはPerson自身が作成・編集できる。
- Historyは必須Domainとして扱う。
- ReservationにCompanionは持たせない。
- CategoryとGenreはProductionの属性として管理する。
- Ticket TypeとPriceはProduction単位で管理する。
- RehearsalはRehearsal Candidateを経由せず直接作成できる。
- BudgetはProductionごとに複数案を持つことができる。
- BudgetとActualは別の概念として管理する。
- Equipmentは資産管理を目的としない。
- Seats / Reservation Seatは将来実装する。
- SNS投稿内容はStageArtの正本として管理しない。
- 外部サービスはStageArt Domainの正本ではない。
- Domain MapはDatabase Schemaではない。
- 詳細な属性・制約・状態遷移は各Domainの仕様で定義する。
