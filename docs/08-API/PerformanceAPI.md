# StageArt Blueprint
# API : Performance

Version : 2.0

---

# Purpose

Performance APIはPerformanceドメインを操作するためのREST APIを定義する。

PerformanceはProductionに属する個々の公演回を表す。

観客による予約はPerformance単位で行われる。

Performanceは、
ReservationおよびCheck Inが対象とする公演回を提供する。

Business RuleはDomain Layerが管理し、
APIはApplication Layerの公開インターフェースとして機能する。

---

# Resource

PerformanceはProduction配下のResourceとして公開する。

/api/v1/productions/{productionId}/performances

Performance固有の操作はPerformance Resourceとして公開する。

/api/v1/performances/{performanceId}

---

# Public Resource

Performance APIが公開するResource

- Performance
- Seat

ReservationはPerformanceに関連するResourceである。

ReservationそのものはReservation APIが管理する。

SeatはPerformanceに属する座席情報として管理する。

ReservationSeatはReservationに属するため、
Performance APIから独立したReservationSeat APIは提供しない。

---

# Create Performance

## Request

POST /api/v1/productions/{productionId}/performances

### Request Body

{
  "startDateTime": "2026-10-12T18:00:00+09:00",
  "endDateTime": "2026-10-12T20:00:00+09:00",
  "venue": "〇〇劇場"
}

### Success

201 Created

### Business Rules

- Performanceを作成する。
- PerformanceCreatedを発行する。
- Productionへ紐付ける。
- 初期設定はDomain Eventによって実行する。

---

# Get Performance

## Request

GET /api/v1/performances/{performanceId}

取得可能情報

- Performance
- Seat Configuration
- Status

Reservation一覧は、
Reservation APIから取得する。

---

# Update Performance

## Request

PUT /api/v1/performances/{performanceId}

更新可能項目

- 開演日時
- 終演日時
- 会場
- 上演時間
- 座席設定
- 公開設定

PerformanceIdは変更できない。

既存Reservationが存在するPerformanceについて、
日時・会場・座席設定などを変更する場合は、
既存Reservationとの整合性を確認する。

Reservationの変更はReservation APIが管理する。

---

# Seat Configuration

Performanceは座席設定を保持する。

SeatはPerformanceに属する。

Performance
    ↓
Seat

Seatは、
そのPerformanceにおいて予約可能な座席を表す。

Seatには少なくとも、
座席を識別するための情報を持つ。

例）

- SeatId
- SeatNumber
- Row
- Section
- Status

Seatの予約状態は、
Reservationとの関係によって管理する。

---

# Reservation Seat Relationship

ReservationはPerformanceに対して作成される。

Reservation
    ↓
ReservationSeat
    ↓
Seat

ReservationSeatは、
Reservationが予約しているSeatを表す。

ReservationSeatはReservation Aggregate内部で管理する。

Performance APIは、
ReservationSeatを直接変更しない。

---

# Seat Availability

Seatの予約可能状態は、
Reservationの状態と連動する。

Reservationが予約中の場合、
そのReservationに紐づくSeatは予約済みとして扱う。

Reservationの人数変更によってSeatが追加された場合、
追加されたSeatを予約済み状態へ反映する。

Reservationの人数変更によって不要になったSeatは、
予約可能状態へ解放する。

Reservationがキャンセルされた場合、
そのReservationに紐づくSeatを予約可能状態へ解放する。

Seatの追加・解放を伴うReservation変更は、
Reservation APIから実行する。

---

# Seat and Check In

Seat自体はCheck Inの対象ではない。

Check InはReservation単位で行う。

例えば、

Reservation
    ├─ A-10
    ├─ A-11
    └─ A-12

という3席のReservationであっても、
A-10、A-11、A-12を個別にCheck Inすることはない。

Reservation全体をCheck Inする。

Reservation
    ↓
CHECKED_IN

という単位で来場を確定する。

---

# Publish Performance

## Request

PATCH /api/v1/performances/{performanceId}/publish

### Business Rules

- PerformanceStatusをPublishedへ変更する。
- PerformancePublishedを発行する。

---

# Cancel Performance

## Request

PATCH /api/v1/performances/{performanceId}/cancel

### Business Rules

- PerformanceStatusをCancelledへ変更する。
- PerformanceCancelledを発行する。
- 既存Reservationは保持する。
- 既存Reservationを自動削除しない。
- 払い戻し処理は別Business Processとする。

Performanceのキャンセルによる
Reservationへの対応は別Business Processとして扱う。

---

# Finish Performance

## Request

PATCH /api/v1/performances/{performanceId}/finish

### Business Rules

- PerformanceStatusをFinishedへ変更する。
- PerformanceFinishedを発行する。
- 観劇履歴生成などはDomain Eventによって実行する。

Audience Historyは、
PerformanceFinishedそのものではなく、
ReservationCheckedInを契機として生成する。

---

# Check In Context

Check Inを開始する際は、
受付担当者がProductionおよびPerformanceを選択する。

Production
    ↓
Performance
    ↓
Check In受付開始

選択されたPerformanceが、
その受付でCheck In対象となる公演回である。

ReservationのPerformanceと
受付中Performanceが一致する場合のみ、
ReservationのCheck Inを実行できる。

一致しない場合はCheck Inできない。

Check Inの実行自体はReservation APIが管理する。

---

# Reservation Relationship

PerformanceはReservationの対象となる。

関係は以下のようになる。

Production
    ↓
Performance
    ↓
Reservation
    ↓
ReservationSeat
    ↓
Seat

ReservationはPerformanceに対して作成される。

ただしReservationのAggregate RuleおよびBusiness Ruleは、
Reservation Domainが管理する。

Performance APIはReservationの作成・変更・キャンセル・Check Inを
直接管理しない。

---

# List Performances

## Request

Production配下の公演一覧

GET /api/v1/productions/{productionId}/performances

---

# Search

検索対象

- 開演日時
- 会場
- Status

---

# Child Resources

Performance配下で公開されるResource

GET /api/v1/productions/{productionId}/performances

GET /api/v1/performances/{performanceId}/seats

ReservationはPerformanceに関連するResourceとして
Reservation APIから公開する。

Reservation API

GET /api/v1/performances/{performanceId}/reservations

Reservationの作成・変更・キャンセル・Check Inも
Reservation APIが提供する。

---

# Authorization

Performanceの作成・更新・公開・キャンセルは
Organization Membershipによって認可する。

Roleに応じて利用可能な操作を制御する。

Seat Configurationの変更も、
Performanceを管理する権限によって認可する。

Reservationの作成・更新・キャンセル・Check Inに必要な権限は、
Reservation API側で管理する。

---

# Domain Events

Performance APIは以下のDomain Eventを利用する。

- PerformanceCreated
- PerformancePublished
- PerformanceCancelled
- PerformanceFinished

Reservationに関するDomain Eventは
Performance APIでは発行しない。

Reservation Domainが以下を発行する。

- ReservationCreated
- ReservationUpdated
- ReservationCheckedIn
- ReservationCancelled

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

Version 2.0ではVenueは文字列として扱う。

将来的にVenueドメインへ分離可能な設計とする。

---

# Design Principles

- PerformanceはProduction配下の公開Resourceである。
- ReservationはPerformanceに対する予約を表す。
- PerformanceはReservationの対象となる。
- ReservationのAggregate RuleはReservation Domainが管理する。
- SeatはPerformanceに属する。
- ReservationSeatはReservationに属する。
- ReservationSeatはReservation Aggregate内部で管理する。
- Seatは個別にCheck Inしない。
- Check InはReservation単位で行う。
- Reservationの人数変更時には必要なSeatを追加・解放する。
- Reservationのキャンセル時には予約済みSeatを解放する。
- Seatの追加・解放を伴うReservation変更はReservation APIが管理する。
- Performance APIはReservationを直接管理しない。
- Performance APIはReservationのCheck Inを直接管理しない。
- Check In対象Performanceは受付開始時に選択する。
- ReservationのPerformanceと受付中Performanceが一致しない場合はCheck Inできない。
- Reservationに関するBusiness RuleはReservation Domainが管理する。
- Audience HistoryはReservationCheckedInを契機としてHistory Domainが生成する。
- Business RuleはDomain Layerが管理する。
- Domain Eventを利用してBusiness Processを開始する。
- APIはRESTを採用する。