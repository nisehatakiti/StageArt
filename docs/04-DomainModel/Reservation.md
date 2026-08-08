# StageArt Blueprint
# Domain Model : Reservation

Version : 1.0

---

# Purpose

Reservationは観客による公演予約を管理するドメインである。

Reservationは予約情報だけでなく、

受付状態、

QRコード、

同行者、

座席指定

までを統一的に管理する。

StageArtではReservationを受付単位として扱う。

---

# Concept

Reservationは一回の予約を表す。

一人で複数枚予約する場合でもReservationは一つである。

同行者はCompanionによって管理する。

---

# Identity

ReservationはReservationIDによって一意に識別する。

予約番号は表示用情報であり、

識別子ではない。

QRコードも識別子ではない。

---

# Relationship

Reservationは必ず一つのPerformanceへ所属する。

```
Performance
    │
    └── Reservation
            ├── ReservationSeat
            └── Companion
```

---

# Reservation Information

Reservationは以下を保持する。

- Reservation Number
- Performance
- Representative
- Ticket Type
- Quantity
- Price
- QR Code

QRコードはReservation生成時に自動生成する。

---

# Reservation Seat

指定席の場合、

ReservationはReservationSeatを保持する。

自由席の場合、

ReservationSeatは存在しない。

座席状態はSeatではなくReservationによって決定される。

---

# Companion

Reservationは複数のCompanionを保持できる。

CompanionはStageArt利用者である必要はない。

後からPersonまたはAccountへ紐付けることができる。

これにより観劇履歴を統合できる。

---

# Check In

受付はReservation単位で管理する。

Reservationは以下の状態を持つ。

- Reserved
- Checked In
- Cancelled
- No Show

QRコード読取、

予約番号検索、

氏名検索

すべてReservationへ到達するための検索手段である。

---

# Price

Reservationは予約時点の料金を保持する。

チケット種別変更後も、

過去Reservationの料金は変更しない。

---

# History

公演終了後、

Reservationは削除しない。

ReservationはHistory生成の元データとなる。

Personの観劇履歴はReservationから生成される。

---

# Design Decisions

Reservationは受付単位である。

QRコードはReservationが保持する。

SeatはReservation状態を保持しない。

受付はReservationのStatus変更で表現する。

CompanionはReservationへ所属する。

---

# Future

将来的に以下へ対応する。

- キャンセル待ち
- リセール
- 電子もぎり
- 入場時間管理
- 複数QRコード発行
- NFC受付

---

# Design Principles

- Reservationは受付単位である。
- QRコードはReservationが保持する。
- CheckInドメインは持たない。
- Ticketドメインは持たない。
- Seatは予約状態を保持しない。
- CompanionはReservationへ所属する。
- Reservationは削除しない。
