# StageArt Blueprint
# API : Reservation

Version : 2.1

---

# Purpose

Reservation APIはReservationドメインを操作するためのREST APIを定義する。

ReservationはPerformanceに対する観客の予約を表すBusiness Resourceである。

ReservationはAggregate Rootとして予約情報の整合性を管理する。

CompanionおよびReservationSeatはReservationの内部Entityとして管理する。

Business RuleはDomain Layerが管理し、
APIはApplication Layerの公開インターフェースとして機能する。

---

# Resource

ReservationはPerformance配下のResourceとして公開する。

```
/api/v1/performances/{performanceId}/reservations
```

Reservation固有の操作はReservation Resourceとして公開する。

```
/api/v1/reservations/{reservationId}
```

---

# Public Resource

Reservation APIが公開するResource

- Reservation

CompanionおよびReservationSeatは
Reservation Resourceへ集約して公開する。

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

```
POST /api/v1/performances/{performanceId}/reservations
```

### Request Body

```json
{
  "booker": {
    "personId": "person-001"
  },
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
```

### Success

```
201 Created
```

### Business Rules

- Reservationを作成する。
- ReservationNumberを採番する。
- QRCodeを生成する。
- ReservationCreatedを発行する。
- Companionを生成する。
- ReservationSeatを生成する。

---

# Get Reservation

## Request

```
GET /api/v1/reservations/{reservationId}
```

取得可能情報

- Reservation
- Booker
- Companion
- ReservationSeat
- TicketType
- QRCode
- Status

---

# Update Reservation

## Request

```
PUT /api/v1/reservations/{reservationId}
```

更新可能項目

- Companion
- TicketType
- ReservationSeat
- Memo

ReservationIdは変更できない。

CompanionおよびReservationSeatは
Reservation全体の更新として変更する。

---

# Check In

## Request

```
PATCH /api/v1/reservations/{reservationId}/check-in
```

### Business Rules

- ReservationStatusをCheckedInへ変更する。
- ReservationCheckedInを発行する。

Historyの生成はReservationの責務ではない。

---

# Cancel Reservation

## Request

```
PATCH /api/v1/reservations/{reservationId}/cancel
```

### Business Rules

- ReservationStatusをCancelledへ変更する。
- ReservationCancelledを発行する。

Historyの更新はReservationの責務ではない。

---

# List Reservations

## Request

Performance配下の予約一覧

```
GET /api/v1/performances/{performanceId}/reservations
```

---

# Search

検索対象

- Reservation Number
- Booker
- Companion
- Status

---

# Authorization

Reservationの作成は認証済み利用者のみ可能とする。

予約一覧・受付・更新は
Organization Membershipによって認可する。

Roleに応じて利用可能な操作を制御する。

---

# Domain Events

Reservation APIは以下のDomain Eventを利用する。

- ReservationCreated
- ReservationUpdated
- ReservationCheckedIn
- ReservationCancelled

APIはDomain Eventを発行する。

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

- ReservationはAggregate Rootである。
- CompanionはReservation経由でのみ操作する。
- ReservationSeatはReservation経由でのみ操作する。
- Companion APIは公開しない。
- ReservationSeat APIは公開しない。
- Check InはReservationStatusで管理する。
- ReservationはHistoryを管理しない。
- APIはDomain Eventを発行する。
- Business RuleはDomain Layerが管理する。
- APIはRESTを採用する。
