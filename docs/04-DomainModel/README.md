# StageArt Blueprint
# 04 - Domain Model

Version : 4.0

---

# Purpose

Domain ModelはStageArtが管理する業務上の概念（ドメイン）を定義する。

StageArtはデータベースや画面を中心に設計するのではなく、
舞台芸術における業務そのものをドメインとして表現する。

Business Flowが利用者の行動を定義するのに対し、
Domain ModelはStageArt内部で管理される概念と責務を定義する。

---

# Domain Structure

Organization
│
└── Project
    │
    └── Production
        ├── PrimaryManager
        │   └── Person
        │
        ├── ProductionDelegate
        │   ├── Person
        │   └── DelegateRole
        │
        ├── Performance
        │   ├── Seats
        │   └── Reservation
        │       ├── Booker
        │       ├── HandledParticipant
        │       └── Companion
        │
        ├── Participant
        │   │
        │   └── Subject
        │       ├── Person
        │       └── Organization
        │
        ├── Category
        ├── Genre
        └── Tag

Subject
│
└── History

---

# Domain Overview

## Organization

Organizationは劇団・ユニット・制作会社・企業などの団体を表す。

一つのOrganizationは複数のProjectを持つ。

OrganizationとPersonの所属関係はMembershipによって管理する。

---

## Project

Projectは公演制作プロジェクトを表す。

利用者はProjectを直接意識しない。

利用者が「公演を作る」を実行すると、
StageArtは内部でProjectを自動生成する。

Projectには将来的に以下が紐付く。

- 稽古
- スケジュール
- タスク
- ドキュメント
- 予算
- 収支
- 助成金

ProjectはStageArt内部で制作全体を管理するInternal Domainである。

Projectは公開APIには出さない。

---

## Production

Productionは利用者・観客へ公開される「公演」を表す。

Productionには以下が含まれる。

- タイトル
- 公演概要
- 公演画像
- Category
- Genre
- Tag
- Participant
- Performance
- PrimaryManager
- ProductionDelegate

一つのProductionは複数のPerformanceを持つ。

Productionは公開Business Resourceである。

---

## PrimaryManager

PrimaryManagerはProductionの管理責任者を表す。

一つのProductionには一人のPrimaryManagerを設定する。

PrimaryManagerはPersonを参照する。

PrimaryManagerはProductionに関する全権限を持つ。

PrimaryManagerはDelegateRoleによる制限を受けない。

PrimaryManagerはProduction単位の管理責任者であり、
Person自身のRoleを表すものではない。

Production
│
└── PrimaryManager
    └── Person

---

## ProductionDelegate

ProductionDelegateは、
Productionに対して管理権限を委任されたPersonを表す。

Productionの子Entityとして管理する。

一つのProductionには、
必要に応じて複数のProductionDelegateを設定できる。

ProductionDelegateは以下を参照する。

- Production
- Person
- DelegateRole

Production
│
└── ProductionDelegate
    ├── Person
    └── DelegateRole

ProductionDelegateは、
DelegateRoleによって定義された権限のみを持つ。

同一Personを複数のProductionのDelegateとして登録できる。

また、同一Personであっても、
Productionごとに異なるDelegateRoleを設定できる。

ProductionDelegateの権限は、
登録されたProductionに対してのみ有効である。

---

## DelegateRole

DelegateRoleは、
ProductionDelegateへ付与する権限セットを定義するMaster Domainである。

DelegateRoleはPersonへ直接付与しない。

ProductionDelegateを介して、
Production単位でPersonへ適用する。

例えば、

Production A
│
└── ProductionDelegate
    ├── Person A
    └── REHEARSAL_MANAGER

Production B
│
└── ProductionDelegate
    ├── Person A
    └── RESERVATION_MANAGER

という設定を許可する。

DelegateRoleは、
Productionごとに作成するものではない。

あらかじめ定義されたDelegateRoleを
複数のProductionで利用する。

---

## Performance

Performanceは実際の公演回を表す。

例）

- 8/1 14:00
- 8/1 18:00
- 8/2 13:00

Performanceには以下が紐付く。

- Seats
- Reservation

一つのProductionは複数のPerformanceを持つ。

---

## Seats

SeatsはPerformanceに存在する座席を表す。

Seatsは座席情報のみを保持する。

予約状態は保持しない。

座席の予約状況はReservationから判断する。

自由席の場合、
ReservationSeatを持たない予約として管理する。

指定席の場合、
ReservationSeatからSeatを参照する。

SeatはCheck Inの対象ではない。

Check InはReservation単位で管理する。

---

## Reservation

ReservationはPerformanceに対する予約を表す。

ReservationはAggregate Rootとして予約情報の整合性を管理する。

Reservationは以下を管理する。

- Booker
- HandledParticipant
- Companion
- ReservationSeat
- TicketType
- QRCode
- Status

### Booker

Bookerは予約者を表すPersonである。

### HandledParticipant

HandledParticipantは予約を担当するParticipantを表す。

いわゆる「○○扱い」の予約を表現する。

HandledParticipantは任意である。

指定されない場合は一般予約として扱う。

HandledParticipantは予約作成後でも変更できる。

### Companion

Companionは同行者を表す。

CompanionはReservationに属する子Entityであり、
単独では存在しない。

CompanionはReservationを経由してのみ管理する。

### ReservationSeat

ReservationSeatは予約されたSeatを表す。

ReservationSeatはReservationに属する子Entityであり、
単独では存在しない。

### TicketType

TicketTypeは予約種別を表す。

例）

- GENERAL
- STUDENT
- INVITATION
- STAFF

TicketTypeは料金計算および集計で利用する。

HandledParticipantの有無とは独立して管理する。

### QRCode

QRCodeは受付用識別子を表す。

Reservation生成時に発行する。

QRCodeは変更しない。

### Status

ReservationStatusは予約状態を表す。

例）

- RESERVED
- CHECKED_IN
- CANCELLED
- NO_SHOW

受付はReservationのStatus変更で管理する。

CheckInという独立したDomainは持たない。

### History

ReservationはHistoryを管理しない。

ReservationCheckedInなどのDomain Eventを契機として、
History Domainが必要な履歴を生成・更新する。

---

## Participant

ParticipantはProductionへの参加を表すBusiness Domainである。

Participantは出演者だけではなく、

- キャスト
- スタッフ
- 演出
- 制作
- 主催
- 共催
- 協賛
- 後援
- 制作協力
- 会場提供

など、公演へ参加するすべての活動主体を表現する。

ParticipantはPersonまたはOrganizationを直接参照しない。

ParticipantはSubjectを通じて活動主体を参照する。

Participant
│
▼
Subject
├── Person
└── Organization

---

## Subject

SubjectはStageArtにおける活動主体を表す共通Referenceである。

Subjectは以下で構成される。

- SubjectType
- SubjectId

Version 1.0では以下をサポートする。

- PERSON
- ORGANIZATION

ParticipantはSubjectを必ず一つ持つ。

---

## ParticipantType

ParticipantTypeは公演への参加区分を表す。

例）

- CAST
- STAFF
- DIRECTOR
- PRODUCER
- ORGANIZER
- SPONSOR
- SUPPORTER

ParticipantTypeはBusiness Rule、検索、集計などのシステム処理に利用する。

---

## Role

Roleは公演内での具体的な役割名称を表す。

例）

- 主演
- 演出
- 音響
- 照明
- 舞台監督
- 主催
- 協賛

Roleは表示情報であり、
ParticipantTypeとは責務を分ける。

---

## CreditOrder

CreditOrderはクレジット表示順を表す。

小さい値ほど先に表示する。

---

## Visibility

VisibilityはParticipant情報の公開状態を表す。

例）

- PUBLIC
- PRIVATE

---

## Status

ParticipantStatusはParticipantの状態を表す。

例）

- ACTIVE
- INACTIVE

ParticipantはHistoryを管理しない。

ParticipantAdded、ParticipantUpdated、ParticipantRemovedなどの
Domain Eventを発行し、
History Domainが必要な履歴を生成・更新する。

通常はProduction単位でParticipantを登録する。

公演回ごとにParticipantを登録する必要はない。

---

## History

HistoryはStageArtにおける活動履歴を表す独立したDomainである。

HistoryはPersonやOrganizationの子Entityではない。

HistoryはSubjectを中心として活動履歴を管理する。

Subject
│
└── History

Historyは以下を管理する。

- Subject
- HistoryType
- ParticipantType
- Production
- Performance
- EventDateTime

### HistoryType

HistoryTypeは活動の種類を表す。

Version 1.0では以下をサポートする。

- PARTICIPATION
- AUDIENCE

ParticipantTypeはHistoryTypeとは異なる概念である。

### ParticipantType

ParticipantTypeはHistoryTypeがPARTICIPATIONの場合に、
公演への参加区分を表す。

例）

- CAST
- STAFF
- DIRECTOR
- PRODUCER
- ORGANIZER
- SPONSOR
- SUPPORTER

HistoryTypeがAUDIENCEの場合、
ParticipantTypeは保持しない。

### Production

Historyは活動対象となったProductionを参照する。

Productionは必須である。

### Performance

Historyは必要に応じてPerformanceを参照する。

Performanceは任意である。

Production単位の活動履歴ではPerformanceを持たない場合がある。

観劇履歴など、特定の公演回を記録する場合はPerformanceを保持する。

### EventDateTime

EventDateTimeは活動日時を表す。

### Generation

HistoryはDomain Eventを契機として自動生成・更新される。

代表的な契機は以下である。

- ParticipantAdded
- ParticipantUpdated
- ParticipantRemoved
- ReservationCheckedIn

Historyは利用者が直接編集するDomainではない。

Historyは読み取りを中心とした独立Domainとして管理する。

Person APIなどのBusiness Resource APIから、
SubjectがPersonであるHistoryを集約して公開する。

Organizationについても同様に、
SubjectがOrganizationであるHistoryを必要に応じて公開する。

---

## Category

CategoryはProductionの公開形態を表すマスターである。

例）

- 舞台
- ミュージカル
- 朗読劇
- ダンス
- 音楽ライブ
- 映像作品
- 配信
- ワークショップ

CategoryはProductionの分類に利用する。

Participantの参加区分には利用しない。

---

## Genre

Genreは作品ジャンルを表すマスターである。

例）

- コメディ
- ホラー
- ミステリー
- SF
- 青春
- 恋愛

GenreはProductionのジャンル分類に利用する。

---

## Tag

TagはStageArt全体で利用する検索・分類用データである。

Tagは以下へ付与できる。

- Person
- Organization
- Production
- Performance

初期状態ではStageArtがプリセットTagを提供する。

利用者は自由にTagを追加できる。

追加されたTagはTagマスターへ登録され、
以後すべての利用者が利用できる。

Tagの表記ゆれ・重複についてはシステムで厳密に制御しない。

管理者が定期的に整理・統合することで検索品質を維持する。

---

# Domain Classification

## Core Domain

- Person
- Organization
- Project
- Production
- Performance
- Participant
- Reservation
- History

## Supporting Domain

- ProductionDelegate
- DelegateRole
- Seats
- Category
- Genre
- Tag

ProductionDelegateとDelegateRoleは、
Productionに対する管理権限を実現するための
Supporting Domainとして扱う。

---

# Golden Rule

利用者はドメインモデルを意識しない。

利用者は、

- 劇団を作る
- 公演を作る
- 公演に参加する
- 公演を予約する

といった業務上の操作だけを行う。

Projectなどの内部ドメインはStageArtが自動生成・管理する。

Participant、Reservationなどの操作によって発生する活動履歴は、
可能な限りDomain Eventを通じて自動的に蓄積される。

Productionの管理権限についても、
利用者はPrimaryManagerやProductionDelegateという
内部構造を意識する必要はない。

利用者には、
自分が操作可能な業務だけを提供する。

---

# Future Domain

Version 1.5以降

- Rehearsal
- Finance
- FanClub
- Store
- Notification
- Messaging

ProductionDelegateのDelegateRoleは、
将来的にこれらのDomainに対する管理権限へ拡張できる。

---

# Identity Linking

StageArtではPersonおよびOrganizationは、
StageArt利用の有無に関わらず登録できる。

そのため、Person・OrganizationとAccountは独立した概念として管理する。

AccountはStageArt利用者を一意に識別するための認証情報であり、
PersonまたはOrganizationへ任意に紐付けられる。

Accountを持たないPerson・Organizationは「未紐付け」として管理する。

---

# Identity Linking Flow

Account登録後、StageArtはAIを利用して未紐付けPerson・Organizationとの照合を行う。

AIは以下の情報をもとに候補を抽出する。

- 氏名
- 団体名
- 出演履歴
- スタッフ参加履歴
- 協賛履歴
- 公演情報
- その他StageArt内の関連情報

AIは候補ごとに一致度を算出し、
運営へ確認候補として提示する。

AIはPerson・Organizationを自動で紐付けない。

---

# Human Review

運営はAIが提示した候補を確認し、
本人確認を実施する候補を選択する。

---

# Identity Confirmation

運営が候補を選択すると、
StageArtは本人確認メールを自動生成・送信する。

例）

件名

StageArt 登録情報確認のお願い

本文

StageArtへご登録ありがとうございます。

登録情報を確認したところ、
以下の公演参加履歴が見つかりました。

2026年 『夏の終わり』

スタッフ（照明）

こちらの参加履歴はご本人のもので間違いありませんか。

〖はい〗 〖いいえ〗

本人から承認を得た後、
運営がAccountとPersonまたはOrganizationを紐付ける。

---

# AI Policy

AIは以下を担当する。

- 候補検索
- 一致度算出
- 確認メール生成
- 運営への候補提示

AIは本人情報を自動で確定しない。

最終判断は運営および本人確認によって行う。

---

# Design Principle

StageArtでは、

「AIは決定しない。AIは提案する。」

を基本方針とする。

本人情報・履歴・参加情報など、
重要なデータの確定は必ず人間の確認を経て行う。

これにより、高いデータ品質と利用者の信頼性を維持する。
