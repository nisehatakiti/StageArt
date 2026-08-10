# StageArt Blueprint
# API : Person

Version : 3.0

---

# Purpose

Person APIはPersonドメインを操作するためのREST APIを定義する。

PersonはStageArtに登録された人物を表すBusiness Resourceである。

Personは認証情報(Account)とは独立したDomainであり、
人物情報を公開する責務を持つ。

Person APIはPersonを中心とした情報を集約して提供する。

Business RuleはDomain Layerが管理し、
APIはApplication Layerの公開インターフェースとして機能する。

---

# Resource

/api/v1/persons

Person固有の操作はPerson Resourceとして公開する。

/api/v1/persons/{personId}

---

# Public Resource

Person APIが公開する情報

- Person Profile
- Public Organizations
- History

Historyは独立したDomainである。

公開APIではPerson Resourceへ集約して提供する。

Accountは公開しない。

---

# Create Person

Person APIからPersonを直接作成することは原則として行わない。

Personは以下の場合に生成される。

- Account作成時
- 招待承認時（将来）
- Companionとの統合時（将来）

Person作成はDomain Layerが管理する。

---

# Get Person

## Request

GET /api/v1/persons/{personId}

### Response

取得可能情報

- Display Name
- Biography
- Profile Image
- Website
- SNS
- Public Organizations
- History

Historyには以下が含まれる。

- HistoryType
- ParticipantType
- Production
- Performance
- EventDateTime

Historyは読み取り専用である。

---

# Update Person

## Request

PUT /api/v1/persons/{personId}

更新可能項目

- Display Name
- Biography
- Profile Image
- Website
- SNS
- Public Settings

PersonIdは変更できない。

Historyは更新できない。

---

# List Persons

## Request

GET /api/v1/persons

### Query Parameters

page

pageSize

keyword

organization

tag

sort

---

# Search

検索対象

- Display Name
- Biography
- Organization
- Tag

Historyは検索条件としない。

---

# Authorization

Person情報の閲覧は公開設定に従う。

更新は本人のみ可能とする。

Organization MembershipによるPerson情報の更新権限は持たない。

Historyは更新できない。

---

# History

HistoryはPerson APIが直接管理するResourceではない。

Historyは独立したDomainとして管理する。

Person APIでは、
Personに関連する公開Historyを読み取り専用で取得できる。

Historyの生成・更新・削除は、
Person APIから直接実行しない。

---

# History Relationship

PersonとHistoryの関係は以下のようになる。

Person
    ↓
History

HistoryはPersonに関連する活動・観劇履歴を表現する。

Historyの生成は、
各Domainで発生したBusiness Eventを契機として
History Domainが管理する。

Person APIはHistoryの生成ルールを管理しない。

---

# Participant History

Participantに関するHistoryが必要な場合は、
Participant Domain Eventを契機としてHistory Domainが処理する。

ParticipantAdded
ParticipantUpdated
ParticipantRemoved

Person APIはこれらのEventを直接処理しない。

---

# Audience History

観客としての観劇履歴は、
ReservationのCheck Inを契機として生成される。

Reservation
    ↓
ReservationCheckedIn
    ↓
History Domain
    ↓
Audience History

Audience HistoryのSubjectは、
ReservationのBookerである。

HandledParticipantは、
Audience HistoryのSubjectにはならない。

---

# Reservation Relationship

Person APIはReservationを直接管理しない。

ReservationはReservation Domainが管理する。

PersonはReservationにおいて、
以下の役割を持つことができる。

- Booker
- CreatedBy
- UpdatedBy

また、PersonがParticipantのSubjectとなる場合、
ParticipantとしてProductionに参加することができる。

これらはそれぞれ異なるDomain上の責務として管理する。

---

# Booker

PersonがReservationのBookerとなる場合、
Bookerは「誰の予約か」を表す。

BookerはReservation Domainで管理する。

Person APIはBooker情報そのものをReservationとして管理しない。

---

# Reservation Created By

PersonがReservationを作成した場合、
ReservationのCreatedByとして記録される。

例えば、

Booker
    = Person A

CreatedBy
    = Person A

とすることができる。

Participant本人が自分扱いの予約を入力する場合など、
BookerとCreatedByが異なるケースも存在する。

BookerとCreatedByは別の概念である。

---

# Reservation Updated By

PersonがReservationを変更した場合、
ReservationのUpdatedByとして記録される。

Reservationの変更履歴およびUpdatedByは、
Reservation Domainが管理する。

Person APIはReservationの変更を直接実行しない。

---

# Participant Relationship

PersonはParticipantのSubjectとして参照されることがある。

Person
    ↓
Participant
    ↓
Production

ParticipantはProductionへの参加を表す。

Participant APIがParticipantを管理し、
Person APIはPerson自身の情報を管理する。

---

# Participant Reservation Relationship

PersonがParticipantのSubjectである場合、
そのParticipantはReservationのHandledParticipantとして
指定されることがある。

例えば、

Person
    = 山田

Participant
    = 山田

Reservation
    ↓
HandledParticipant
    = 山田

という関係になる。

HandledParticipantは、
予約における「○○扱い」を表す。

Person APIはHandledParticipantを直接管理しない。

---

# Participant Portal Relationship

Participantが自分扱いの予約を受け付ける場合、
Participant専用の予約ページを利用することができる。

Participant専用予約ページはPortal側で提供する。

Person APIは予約ページそのものを提供しない。

Participant本人が予約を入力した場合でも、
ReservationのCreatedByはReservation Domainで管理する。

---

# Domain Events

Person APIに関連するDomain Event

- PersonProfileUpdated

Personに関するProfile変更は、
PersonProfileUpdatedによってBusiness Processを開始する。

---

# Related Domain Events

Personに関連するHistoryは、
各DomainのDomain Eventを契機としてHistory Domainが処理する。

Participant関連

- ParticipantAdded
- ParticipantUpdated
- ParticipantRemoved

Reservation関連

- ReservationCreated
- ReservationUpdated
- ReservationCheckedIn
- ReservationCancelled

ただし、
すべてのReservation EventがAudience Historyを生成するわけではない。

Audience Historyを生成する契機は、

ReservationCheckedIn

である。

ReservationCreated
ReservationUpdated
ReservationCancelled

ではAudience Historyを生成しない。

---

# History Generation Rule

History生成の責務はHistory Domainにある。

Person APIはHistoryを生成・更新・削除しない。

特にAudience Historyについては、

Reservation
    ↓
ReservationCheckedIn
    ↓
History Domain
    ↓
Audience History

という流れで生成する。

これにより、
予約しただけでは観劇履歴として記録されない。

実際にCheck InされたReservationのみが
Audience Historyの対象となる。

---

# Error Response

代表例

400 Bad Request

401 Unauthorized

403 Forbidden

404 Not Found

409 Conflict

422 Validation Error

500 Internal Server Error

---

# Future

将来的に以下へ対応する。

- Portfolio
- Award History
- Skill
- Favorite
- Follow
- Verification Badge

Subjectの種類が追加されても、
Person APIは変更しない。

---

# Design Principles

- Personは人物を表すBusiness Resourceである。
- PersonはAccountとは独立したDomainである。
- Accountは公開APIとして提供しない。
- Historyは独立したDomainである。
- Person APIはHistoryを読み取り専用で公開する。
- Historyを操作するAPIは提供しない。
- PersonはParticipantのSubjectとして参照できる。
- ParticipantはProductionへの参加を表す。
- PersonはReservationのBookerになり得る。
- PersonはReservationのCreatedByになり得る。
- PersonはReservationのUpdatedByになり得る。
- BookerとCreatedByを混同しない。
- CreatedByとUpdatedByを混同しない。
- HandledParticipantはReservation Domainが管理する。
- Participant専用予約ページはPortal側で提供する。
- Person APIはReservationを直接管理しない。
- Person APIはCheck Inを直接管理しない。
- HistoryはDomain Eventを契機としてHistory Domainが管理する。
- ReservationCreatedではAudience Historyを生成しない。
- ReservationUpdatedではAudience Historyを生成しない。
- ReservationCancelledではAudience Historyを生成しない。
- ReservationCheckedInをAudience History生成の契機とする。
- Audience HistoryのSubjectはReservation.Bookerである。
- Business RuleはDomain Layerが管理する。
- APIはRESTを採用する。