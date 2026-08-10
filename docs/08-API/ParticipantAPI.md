# StageArt Blueprint
# API : Participant

Version : 3.0

---

# Purpose

Participant APIはParticipantドメインを操作するためのREST APIを定義する。

ParticipantはProductionへ参加する活動主体を表すBusiness Resourceである。

活動主体はSubjectによって表現する。

SubjectはPersonまたはOrganizationを参照する共通Referenceであり、
ParticipantはSubjectを通じて活動主体を管理する。

ParticipantはProductionにおける
「誰が、どのような立場で参加しているか」を表現する。

ParticipantはReservationにおけるHandledParticipantとして
予約の「○○扱い」を表現することができる。

Business RuleはDomain Layerが管理し、
APIはApplication Layerの公開インターフェースとして機能する。

---

# Resource

ParticipantはProduction配下のResourceとして公開する。

/api/v1/productions/{productionId}/participants

Participant固有の操作はParticipant Resourceとして公開する。

/api/v1/participants/{participantId}

---

# Public Resource

Participant APIが公開する情報

- Participant
- Subject
- ParticipantType
- Role
- CreditOrder
- Visibility
- Status

SubjectはPersonまたはOrganizationを表す。

---

# Create Participant

## Request

POST /api/v1/productions/{productionId}/participants

### Request Body

{
  "subject": {
    "type": "PERSON",
    "id": "person-001"
  },
  "participantType": "CAST",
  "role": "主演",
  "creditOrder": 1
}

### Success

201 Created

### Business Rules

- Participantを作成する。
- Productionへ紐付ける。
- Subjectを登録する。
- ParticipantAddedを発行する。

ParticipantAddedは、
ParticipantがProductionへ参加した事実を表現する。

Participantの作成によって、
Reservationを自動生成しない。

Participantの作成によって、
Reservationを自動変更しない。

Historyの生成・更新はParticipant API自身の責務としない。

---

# Get Participant

## Request

GET /api/v1/participants/{participantId}

取得可能情報

- Participant
- Subject
- ParticipantType
- Role
- CreditOrder
- Visibility
- Status

---

# Update Participant

## Request

PUT /api/v1/participants/{participantId}

更新可能項目

- ParticipantType
- Role
- CreditOrder
- Visibility
- Status

Subjectは変更できない。

活動主体を変更する場合は、
Participantを作り直す。

Participantの更新によって、
既存ReservationのHandledParticipantを自動変更しない。

既存ReservationのHandledParticipantを変更する場合は、
ReservationのBusiness Ruleに従ってReservation APIから変更する。

---

# Remove Participant

## Request

DELETE /api/v1/participants/{participantId}

### Business Rules

- ParticipantをProductionから削除する。
- ParticipantRemovedを発行する。

Participantの削除によって、
既存Reservationを自動削除しない。

既存ReservationにHandledParticipantとして
設定されているParticipantを削除する場合は、
Reservationとの整合性をApplication Layerで確認する。

Historyの直接更新は行わない。

---

# List Participants

## Request

GET /api/v1/productions/{productionId}/participants

---

# Search

検索対象

- Subject
- ParticipantType
- Role
- Status

---

# Handled Participant

Participantは、
ReservationにおけるHandledParticipantとして
指定することができる。

HandledParticipantは、
予約における「○○扱い」を表す。

例えば、

Participant
    = 山田

Reservation
    ↓
HandledParticipant
    = 山田

という関係になる。

HandledParticipantは、
BookerおよびCreatedByとは異なる概念である。

Booker
    = 誰の予約か

HandledParticipant
    = 誰扱いの予約か

CreatedBy
    = 誰が予約を入力したか

---

# Participant Reservation Page

Participantは、
自分扱いの予約を受け付けるための
専用予約ページを持つことができる。

この専用予約ページは、
Participant自身が自分扱いの予約を入力・管理するために使用する。

ただし、
予約そのものはReservation Domainが管理する。

Participant APIは、
Participant専用予約ページのUIや
Portalの画面仕様を直接管理しない。

PortalはParticipantを参照し、
Participantに対応する予約受付機能を提供する。

---

# Participant and Reservation

ParticipantとReservationの関係は以下のようになる。

Production
    ↓
Participant
    ↓
HandledParticipant
    ↓
Reservation

Reservationは、
Participantが担当する「○○扱い」の予約を表現できる。

ただしReservationはParticipantの子Resourceではない。

ReservationはPerformanceに所属する独立したAggregateとして管理する。

そのため、

Participant
    ↓
Reservation

という直接的なAggregate関係は持たない。

---

# Reservation Creation by Participant

Participant本人が自分扱いの予約を入力する場合でも、
Reservationの作成はReservation APIのBusiness Ruleに従う。

例えば、

Booker
    = 観客A

HandledParticipant
    = Participant 山田

CreatedBy
    = Participant 山田

というReservationを作成できる。

BookerとHandledParticipantとCreatedByは
それぞれ異なる意味を持つ。

---

# Reservation Creation by Other User

Participant以外の利用者が
Participant扱いの予約を入力することもできる。

例えば、

Booker
    = 観客A

HandledParticipant
    = Participant 山田

CreatedBy
    = 制作スタッフ

というReservationを作成できる。

この場合でも、
Participant APIがCreatedByを管理することはない。

CreatedByはReservation側で管理する。

---

# Reservation Changes

Participantが自分扱いのReservationを管理する場合でも、
Reservationの変更ルールはReservation Domainが管理する。

Check In前であれば、
ReservationのBusiness Ruleに従って変更できる。

Check In後は、
Reservationを変更できない。

Participant APIは、
Reservationの変更を直接実行しない。

---

# Reservation Cancellation

Participantが自分扱いのReservationを管理する場合でも、
ReservationのキャンセルはReservation Domainが管理する。

Check In前であれば、
ReservationのBusiness Ruleに従ってキャンセルできる。

Check In後は、
Reservationをキャンセルできない。

Participant APIは、
Reservationのキャンセルを直接実行しない。

---

# Authorization

Participantの追加・更新・削除は
Organization Membershipによって認可する。

Roleに応じて利用可能な操作を制御する。

Participant本人による自分扱い予約の操作権限は、
Participant PortalおよびReservation側の認可ルールで管理する。

Participant APIの管理権限と、
Participant専用予約ページの利用権限は分離する。

---

# Domain Events

Participant APIは以下のDomain Eventを利用する。

- ParticipantAdded
- ParticipantUpdated
- ParticipantRemoved

Participant Domain Eventは、
Participantに関するBusiness Processを開始する契機として利用する。

Reservationに関するDomain Eventは、
Reservation Domainが発行する。

Reservation Domain Event

- ReservationCreated
- ReservationUpdated
- ReservationCheckedIn
- ReservationCancelled

Participant APIはReservation Domain Eventを発行しない。

---

# History

Participant APIはHistoryを直接更新しない。

Participantに関するHistory処理が必要な場合は、
Participant Domain Eventを契機として
History Domainが処理する。

Participant APIとHistory Domainを直接結合しない。

また、
ReservationのCheck InによるAudience Historyは、
ReservationCheckedInを契機としてHistory Domainが生成する。

Participantの存在だけでは、
Audience Historyを生成しない。

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

- 日替わり出演
- 出演期間
- 出演回指定
- グループ出演
- ゲスト出演
- SNSリンク

Subjectの種類が追加されても、
Participant APIは変更しない。

---

# Design Principles

- ParticipantはProductionへの参加を表すBusiness Resourceである。
- ParticipantはSubjectを通じて活動主体を参照する。
- SubjectはPersonまたはOrganizationを表す。
- ParticipantはPersonおよびOrganizationへ直接依存しない。
- ParticipantTypeはシステムが管理する参加区分である。
- Roleは表示用の役割である。
- Subjectは変更できない。
- ParticipantはReservationのHandledParticipantとして指定できる。
- HandledParticipantは予約の「○○扱い」を表す。
- BookerとHandledParticipantを分離する。
- HandledParticipantとCreatedByを分離する。
- Participant専用予約ページはPortal側で提供する。
- ReservationはReservation Domainが管理する。
- Participant APIはReservationを直接管理しない。
- Participantの変更によって既存Reservationを自動変更しない。
- Participantの削除によって既存Reservationを自動削除しない。
- Reservationの変更はReservation APIが管理する。
- ReservationのキャンセルはReservation APIが管理する。
- Check In済みReservationは変更・キャンセルできない。
- Participant APIはHistoryを直接更新しない。
- ReservationCheckedInによるAudience HistoryはHistory Domainが管理する。
- Business RuleはDomain Layerが管理する。
- APIはRESTを採用する。