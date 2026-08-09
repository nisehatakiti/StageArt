# StageArt Blueprint
# Domain Model : Reservation

Version : 3.0

---

# Purpose

ReservationはPerformanceに対する予約を表すDomainである。

Reservationは観客による来場予約を管理する。

Reservationは予約情報の整合性を管理するAggregate Rootであり、
CompanionおよびReservationSeatを管理する。

Business RuleはReservationが管理する。

---

# Concept

ReservationはPerformanceへの予約を表す。

```
Performance
      │
      ▼
 Reservation
      ├── Booker
      ├── HandledParticipant
      ├── Companion
      └── ReservationSeat
```

ReservationはAggregate Rootとして
CompanionおよびReservationSeatを管理する。

HistoryはReservationの責務ではない。

---

# Responsibility

Reservationは以下を管理する。

- Booker
- HandledParticipant
- Companion
- ReservationSeat
- TicketType
- QRCode
- Status

観劇履歴(History)は管理しない。

---

# Identity

ReservationはReservationIdによって識別する。

ReservationIdは変更できない。

ReservationNumberは利用者へ表示する識別子とする。

---

# Booker

Bookerは予約者を表す。

BookerはPersonを参照する。

Bookerは予約内容の変更およびキャンセルを行う主体となる。

---

# Handled Participant

HandledParticipantは予約を担当するParticipantを表す。

HandledParticipantはParticipantを参照する。

HandledParticipantは任意である。

指定されない場合は一般予約として扱う。

HandledParticipantは予約作成後でも変更できる。

---

# Companion

Companionは同行者を表す。

CompanionはReservationに属する。

Companion単独では存在できない。

CompanionはAggregate内部でのみ管理する。

---

# Reservation Seat

ReservationSeatは予約座席を表す。

ReservationSeatはReservationに属する。

ReservationSeat単独では存在できない。

ReservationSeatはAggregate内部でのみ管理する。

---

# Ticket Type

TicketTypeは予約種別を表す。

例）

- GENERAL
- STUDENT
- INVITATION
- STAFF

TicketTypeは料金計算および集計で利用する。

HandledParticipantの有無とは独立して管理する。

---

# QR Code

QRCodeは受付用識別子を表す。

QRCodeはReservation生成時に発行する。

QRCodeは変更しない。

---

# Status

ReservationStatusは予約状態を表す。

例）

- RESERVED
- CHECKED_IN
- CANCELLED
- NO_SHOW

予約状態はReservationStatusで管理する。

---

# Business Rules

Reservationは必ず一つのPerformanceへ所属する。

ReservationはAggregate Rootである。

Bookerは必須である。

HandledParticipantは任意である。

CompanionはReservationを経由してのみ変更できる。

ReservationSeatはReservationを経由してのみ変更できる。

Check InはReservationStatusを変更する。

HandledParticipantは予約作成後でも変更できる。

ReservationはHistoryを生成・更新・削除しない。

HistoryはReservationが発行するDomain Eventを契機として
別Domainが生成・更新する。

---

# Domain Events

Reservationは以下のDomain Eventを発行する。

- ReservationCreated
- ReservationUpdated
- ReservationCheckedIn
- ReservationCancelled

ReservationはDomain Eventを発行するのみであり、
Business Processは保持しない。

---

# Design Decisions

ReservationはPerformanceへの予約を表す。

ReservationはAggregate Rootである。

HandledParticipantは予約担当者を表す。

HandledParticipantはParticipantを参照する。

ReservationはHistoryへ依存しない。

Historyは独立したDomainとして管理する。

CompanionおよびReservationSeatは
Aggregate内部で管理する。

---

# Design Principles

- ReservationはPerformanceへの予約を表すBusiness Domainである。
- ReservationはAggregate Rootである。
- Bookerは予約者を表す。
- HandledParticipantは予約担当Participantを表す。
- HandledParticipantは任意である。
- CompanionはReservation経由でのみ操作する。
- ReservationSeatはReservation経由でのみ操作する。
- ReservationはHistoryを管理しない。
- HistoryはDomain Eventによって管理する。
- Check InはReservationStatusで管理する。
- ReservationはBusiness Ruleのみを管理する。
