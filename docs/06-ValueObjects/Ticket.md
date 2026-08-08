# StageArt Blueprint
# Value Object : Ticket

Version : 1.0

---

# Purpose

TicketはStageArt全体で利用するチケット関連のValue Objectを定義する。

チケット種別、予約番号、QRコードなど、
予約・受付に共通する概念を表現する。

---

# Ticket Type

TicketTypeはチケットの種別を表す。

例）

- 一般
- 学生
- 高校生
- 小中学生
- 前売
- 当日
- 招待
- 関係者

TicketTypeは表示名であり、
販売価格はPriceによって管理する。

---

# Reservation Number

ReservationNumberは予約番号を表す。

利用者が受付で提示する識別番号である。

ReservationIDとは異なる。

ReservationNumberは表示用途に利用する。

---

# QR Code

QRCodeは予約確認用QRコードを表す。

QRCodeはReservation生成時に自動生成される。

QRCode自体はBusiness Dataを持たない。

QRコードを読み取ることでReservationを取得する。

---

# Seat Number

SeatNumberは座席番号を表す。

例）

- A-12
- B-08
- 自由席

SeatNumberはSeatが保持する表示用Value Objectである。

---

# Ticket Count

TicketCountは予約枚数を表す。

1以上の整数のみを許可する。

0以下は許可しない。

---

# Design Principles

- TicketTypeは種別のみを表す。
- ReservationNumberは表示用識別子である。
- QRCodeはReservationを取得する手段である。
- SeatNumberは表示情報である。
- TicketCountは予約枚数を表す。
- Ticket関連Value ObjectはImmutableである。
