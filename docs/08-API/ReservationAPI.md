# StageArt Blueprint
# API : Reservation

Version : 4.0

---

# Purpose

Reservation APIはReservationドメインを操作するためのREST APIを定義する。

ReservationはPerformanceに対する来場予約を表すBusiness Resourceである。

ReservationはAggregate Rootとして予約情報の整合性を管理する。

CompanionおよびReservationSeatはReservationの内部Entityとして管理する。

Business RuleはDomain Layerが管理し、
APIはApplication Layerの公開インターフェースとして機能する。

---

# Resource

ReservationはPerformance配下のResourceとして公開する。

/api/v1/performances/{performanceId}/reservations

Reservation固有の操作はReservation Resourceとして公開する。

/api/v1/reservations/{reservationId}

---

# Public Resource

Reservation APIが公開するResource

- Reservation

Reservationには以下を含む。

- Booker
- HandledParticipant
- Companion
- ReservationSeat
- TicketType
- QRCode
- Status
- CreatedBy
- CreatedAt
- UpdatedBy
- UpdatedAt

CompanionおよびReservationSeatはReservation Resourceへ集約して公開する。

独立したAPIは提供しない。

---

# Aggregate Rule

ReservationはAggregate Rootである。

以下の子Entityは独立したAPIを持たない。

- Companion
- ReservationSeat

子EntityはReservationを経由してのみ変更できる。

---

# Create Reservation

## Request

POST /api/v1/performances/{performanceId}/reservations

### Request Body

{
  "booker": {
    "personId": "person-001"
  },
  "handledParticipantId": "participant-001",
  "ticketType": "GENERAL",
  "companions": [
    {
      "displayName": "山田 花子"
    }
  ],
  "seats": [
    "A-12",
    "A-13"
  ]
}

### Business Rules

- Reservationを作成する。
- ReservationNumberを採番する。
- QRCodeを生成する。
- Companionを生成する。
- ReservationSeatを生成する。
- CreatedByを認証済み利用者から設定する。
- CreatedAtを設定する。
- UpdatedByをCreatedByと同じ値に設定する。
- UpdatedAtをCreatedAtと同じ値に設定する。
- ReservationCreatedを発行する。

HandledParticipantは任意である。

指定されない場合は一般予約として扱う。

CreatedByはBookerとは独立して管理する。

そのため、予約を代理入力した場合でも、
BookerとCreatedByは異なる値を持つことができる。

---

# Get Reservation

## Request

GET /api/v1/reservations/{reservationId}

取得可能情報

- Reservation
- Booker
- HandledParticipant
- Companion
- ReservationSeat
- TicketType
- QRCode
- Status
- CreatedBy
- CreatedAt
- UpdatedBy
- UpdatedAt

---

# Update Reservation

## Request

PUT /api/v1/reservations/{reservationId}

更新可能項目

- Booker
- HandledParticipant
- Companion
- ReservationSeat
- TicketType
- Reservation Count
- Memo

ReservationIdは変更できない。

CompanionおよびReservationSeatはReservation全体の更新として変更する。

Reservation Countを変更した場合、
ReservationSeatとの整合性を確保する。

座席指定があるPerformanceでは、
人数変更によって連席を確保できない場合がある。

その場合、
既存の座席を維持したまま追加席を確保するなど、
予約変更時の座席調整を行う。

予約者には、
人数変更によって連席を確保できない場合があることを
事前に告知する。

UpdatedByは変更を実行した認証済み利用者から設定する。

UpdatedAtは変更日時に更新する。

ReservationUpdatedを発行する。

---

# Update Restrictions

Reservationの更新はCheck In前に限り可能とする。

ReservationStatusがCHECKED_INの場合、
Reservationを更新することはできない。

以下の変更を禁止する。

- Booker
- HandledParticipant
- Companion
- Reservation Count
- ReservationSeat
- TicketType
- Performance
- Memo

CHECKED_INのReservationに対してUpdate APIを実行した場合、
409 Conflictを返す。

---

# Check In

## Request

PATCH /api/v1/reservations/{reservationId}/check-in

### Business Rules

Check Inを開始する前に、
受付担当者はProductionおよびPerformanceを選択する。

Check In対象となるPerformanceは、
受付担当者が選択したPerformanceである。

ReservationのPerformanceと
受付中Performanceが一致することを確認する。

一致しない場合はCheck Inできない。

Check Inは以下の方法で実行できる。

- 予約一覧からの手動Check In
- QRコードによるCheck In

どちらも同じReservation Check Inとして扱う。

Check In時に、

- ReservationStatusをCHECKED_INへ変更する。
- UpdatedByをCheck In実行者に設定する。
- UpdatedAtをCheck In日時に更新する。
- ReservationCheckedInを発行する。

Check In完了後、
Reservationは変更不可となる。

ReservationはHistoryを管理しない。

---

# Check In by Manual Search

受付担当者は、
選択中PerformanceのReservation一覧から
予約者を検索する。

Reservationを確認した後、
Check Inを実行する。

Check In完了後、
Reservationは未チェックイン一覧から除外される。

---

# Check In by QR

QRコードを読み取ることで、
Reservationを特定する。

QRコードから特定されたReservationのPerformanceと、
受付中Performanceが一致することを確認する。

一致した場合のみCheck Inを実行する。

一致しない場合はCheck Inできない。

---

# Check In List

Check In Portalでは、
ProductionおよびPerformanceを選択した後、
対象Performanceの受付画面を表示する。

受付画面では以下を確認できる。

- 予約人数
- 未チェックイン人数
- チェックイン済み人数

通常画面には未チェックインのReservation一覧を表示する。

ReservationがCheck Inされると、
未チェックイン一覧から消える。

---

# Checked In List

Check In済みReservationは、
チェックイン済み一覧から確認できる。

チェックイン済み一覧では、
少なくとも以下を確認できる。

- Booker
- Reservation Count
- HandledParticipant
- Check In日時

---

# Cancel Reservation

## Request

PATCH /api/v1/reservations/{reservationId}/cancel

### Business Rules

ReservationはCheck In前であればキャンセルできる。

キャンセルされたReservationは削除しない。

ReservationStatusをCANCELLEDへ変更する。

UpdatedByはキャンセルを実行した認証済み利用者から設定する。

UpdatedAtはキャンセル日時に更新する。

ReservationCancelledを発行する。

---

# Cancel Restrictions

ReservationStatusがCHECKED_INの場合、
Reservationをキャンセルすることはできない。

CHECKED_INのReservationに対してCancel APIを実行した場合、
409 Conflictを返す。

---

# List Reservations

## Request

Performance配下の予約一覧

GET /api/v1/performances/{performanceId}/reservations

---

# Search

検索対象

- ReservationNumber
- Booker
- HandledParticipant
- Companion
- Status

Check In Portalでは、
選択中PerformanceのReservationのみを検索対象とする。

---

# Authorization

Reservationの作成は認証済み利用者のみ可能とする。

Reservationの更新・キャンセルは、
認証済み利用者かつ許可されたRoleを持つ利用者のみ可能とする。

予約一覧・受付・更新は
Organization Membershipによって認可する。

Roleに応じて利用可能な操作を制御する。

CreatedByおよびUpdatedByには、
実際にAPI操作を実行した認証済み利用者を設定する。

---

# Domain Events

Reservation APIに関連するDomain Event

- ReservationCreated
- ReservationUpdated
- ReservationCheckedIn
- ReservationCancelled

Business ProcessはDomain Eventを契機として開始する。

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

# Conflict Cases

以下の場合は409 Conflictを返す。

- CHECKED_INのReservationを更新しようとした場合
- CHECKED_INのReservationをキャンセルしようとした場合
- 受付中PerformanceとReservationのPerformanceが一致しない場合
- 既にCheck In済みのReservationを再度Check Inしようとした場合
- CANCELLEDのReservationをCheck Inしようとした場合

---

# Reservation Change and Seat Adjustment

Reservation Countを変更する場合、
座席指定があるPerformanceではReservationSeatも連動して調整する。

例えば、

2名
    ↓
3名

へ変更した場合、
3名分の座席を確保する必要がある。

既存の2席を維持して追加席を確保する場合、
連続した座席を確保できない可能性がある。

そのため、
座席指定公演では人数変更によって
連席にならない可能性があることを
予約者へ事前に告知する。

Reservation CountとReservationSeatの整合性が
確保された状態でReservationUpdatedを発行する。

---

# Reservation Lifecycle

Reservationの基本的な状態遷移

RESERVED
    │
    ├── Update
    │      ↓
    │   RESERVED
    │
    ├── Cancel
    │      ↓
    │   CANCELLED
    │
    └── Check In
           ↓
       CHECKED_IN

CHECKED_IN
    │
    └── 変更不可

CANCELLED
    │
    └── Check In不可

---

# History

Reservation APIはHistoryを直接操作しない。

ReservationCheckedInを契機として、
History DomainがAudience Historyを生成する。

Reservation
    ↓
ReservationCheckedIn
    ↓
History Domain
    ↓
Audience History

ReservationCreated、
ReservationUpdated、
ReservationCancelledでは
Audience Historyを生成しない。

---

# Future

将来的に以下へ対応する。

- キャンセル待ち
- リセール
- 招待予約
- 団体予約
- QRコード再発行

Aggregate構造は変更しない。

---

# Design Principles

- ReservationはPerformanceへの予約を表すBusiness Resourceである。
- ReservationはAggregate Rootである。
- Bookerは予約者を表す。
- HandledParticipantは予約担当Participantを表す。
- HandledParticipantは任意である。
- CreatedByはReservationを作成した主体を表す。
- CreatedAtはReservation作成日時を表す。
- UpdatedByはReservationを最後に変更した主体を表す。
- UpdatedAtはReservationの最終更新日時を表す。
- CompanionはReservation経由でのみ操作する。
- ReservationSeatはReservation経由でのみ操作する。
- Companion APIは公開しない。
- ReservationSeat APIは公開しない。
- Reservation CountとReservationSeatの整合性を維持する。
- Check In前に予約内容を確定する。
- Check In後はReservationを変更しない。
- Check In前に人数や座席を修正する。
- 座席指定公演では人数変更によって連席を確保できない場合がある。
- 手動Check InとQR Check Inは同じBusiness Eventとして扱う。
- Check In前にProductionおよびPerformanceを選択する。
- ReservationのPerformanceと受付中Performanceが一致しない場合はCheck Inできない。
- ReservationはHistoryを管理しない。
- APIはDomain Eventを契機とするBusiness Processから分離される。
- Business RuleはDomain Layerが管理する。
- APIはRESTを採用する。