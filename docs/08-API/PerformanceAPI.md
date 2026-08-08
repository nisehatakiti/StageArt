# StageArt Blueprint
# API : Performance

Version : 1.0

---

# Purpose

Performance APIはPerformanceドメインを操作するためのREST APIを定義する。

PerformanceはProductionに属する個々の公演回を表す。

観客による予約はPerformance単位で行われる。

Business RuleはDomain Layerが管理し、
APIはApplication Layerの公開インターフェースとして機能する。

---

# Resource

PerformanceはProduction配下のResourceとして公開する。

```
/api/v1/productions/{productionId}/performances
```

Performance固有の操作はPerformance Resourceとして公開する。

```
/api/v1/performances/{performanceId}
```

---

# Public Resource

Performance APIが公開するResource

- Performance
- Reservation
- Seat

PerformanceはReservationの親Resourceとなる。

---

# Create Performance

## Request

```
POST /api/v1/productions/{productionId}/performances
```

### Request Body

```json
{
  "startDateTime": "2026-10-12T18:00:00+09:00",
  "endDateTime": "2026-10-12T20:00:00+09:00",
  "venue": "〇〇劇場"
}
```

### Success

```
201 Created
```

### Business Rules

- Performanceを作成する。
- PerformanceCreatedを発行する。
- Productionへ紐付ける。
- 初期設定はDomain Eventによって実行する。

---

# Get Performance

## Request

```
GET /api/v1/performances/{performanceId}
```

---

# Update Performance

## Request

```
PUT /api/v1/performances/{performanceId}
```

更新可能項目

- 開演日時
- 終演日時
- 会場
- 上演時間
- 座席設定
- 公開設定

PerformanceIdは変更できない。

---

# Publish Performance

## Request

```
PATCH /api/v1/performances/{performanceId}/publish
```

### Business Rules

- PerformanceStatusをPublishedへ変更する。
- PerformancePublishedを発行する。

---

# Cancel Performance

## Request

```
PATCH /api/v1/performances/{performanceId}/cancel
```

### Business Rules

- PerformanceStatusをCancelledへ変更する。
- PerformanceCancelledを発行する。
- 既存Reservationは保持する。
- 払い戻し処理は別Business Processとする。

---

# Finish Performance

## Request

```
PATCH /api/v1/performances/{performanceId}/finish
```

### Business Rules

- PerformanceStatusをFinishedへ変更する。
- PerformanceFinishedを発行する。
- 観劇履歴生成などはDomain Eventによって実行する。

---

# List Performances

## Request

Production配下の公演一覧

```
GET /api/v1/productions/{productionId}/performances
```

---

# Search

検索対象

- 開演日時
- 会場
- Status

---

# Child Resources

Performance配下の公開Resource

```
GET    /performances/{performanceId}/reservations

POST   /performances/{performanceId}/reservations

GET    /performances/{performanceId}/seats
```

---

# Authorization

Performanceの作成・更新・公開は
Organization Membershipによって認可する。

Roleに応じて利用可能な操作を制御する。

---

# Domain Events

Performance APIは以下のDomain Eventを利用する。

- PerformanceCreated
- PerformancePublished
- PerformanceCancelled
- PerformanceFinished

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

将来的に以下を追加する。

- 入退場管理
- 開場時刻
- アフタートーク
- 配信情報
- リハーサル情報
- リアルタイム座席状況

Version 1.0ではVenueは文字列として扱う。将来的にVenueドメインへ分離可能な設計とする。
---

# Design Principles

- PerformanceはProduction配下の公開Resourceである。
- ReservationはPerformance配下のResourceである。
- SeatはPerformance配下で管理する。
- Business RuleはDomain Layerが管理する。
- Domain Eventを利用してBusiness Processを開始する。
- APIはRESTを採用する。
