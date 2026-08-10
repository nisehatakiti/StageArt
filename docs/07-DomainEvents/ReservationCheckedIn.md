# StageArt Blueprint
# Domain Event : ReservationCheckedIn

Version : 2.0

---

# Purpose

ReservationCheckedInは、
ReservationがCheck Inされたことを表すDomain Eventである。

Reservation DomainがReservationのCheck Inを確定した後に発行する。

ReservationCheckedInは、
「予約者が来場し、受付処理が完了した」というBusiness Eventを表現する。

ReservationCheckedIn自身はHistoryを直接操作しない。

History DomainはReservationCheckedInを受信し、
Audience Historyを生成する。

---

# Event

ReservationCheckedIn

---

# Publisher

Reservation

Reservation DomainがEventを発行する。

---

# Trigger

以下のBusiness Actionによって発生する。

Reservation
    │
    │ Check In
    ▼
ReservationCheckedIn

ReservationのCheck Inが正常に完了した場合に発行する。

---

# Check In Preparation

Check Inを開始する前に、
受付担当者は対象となるProductionおよびPerformanceを選択する。

Production
    ↓
Performance
    ↓
Check In受付開始

選択されたPerformanceが、
その受付でCheck In対象となる公演回である。

---

# Check In Methods

ReservationのCheck Inは、
以下の2つの方法で実行できる。

- 予約一覧からの手動Check In
- QRコードによるCheck In

どちらの方法でも、
最終的には同じReservationに対するCheck Inとして扱う。

そのため、Check In方法ごとに別のDomain Eventは発行しない。

Manual Check In
    ↓
Reservation
    ↓
ReservationCheckedIn

QR Check In
    ↓
Reservation
    ↓
ReservationCheckedIn

---

# Performance Validation

Check Inを実行する際は、
受付で選択されているPerformanceと
ReservationのPerformanceが一致していることを確認する。

一致しない場合はCheck Inを実行しない。

例えば、

受付中Performance
    = 10/10 14:00

Reservation
    = 10/10 18:00

の場合、

Check In
    = 不可

とする。

これにより、
別の公演回のReservationが誤ってCheck Inされることを防止する。

---

# Event Payload

{
  "eventId": "event-004",
  "eventType": "ReservationCheckedIn",
  "occurredAt": "2026-08-10T18:00:00+09:00",
  "reservationId": "reservation-001",
  "productionId": "production-001",
  "performanceId": "performance-001",
  "bookerId": "person-001",
  "handledParticipantId": "participant-001",
  "guestCount": 2,
  "updatedBy": "person-003",
  "updatedAt": "2026-08-10T18:00:00+09:00"
}

---

# Payload Definitions

| Field | Description |
|---|---|
| eventId | Eventを一意に識別するID |
| eventType | Event Type |
| occurredAt | Eventが発生した日時 |
| reservationId | Check InされたReservation |
| productionId | 観覧対象となったProduction |
| performanceId | 観覧対象となったPerformance |
| bookerId | 予約者であるPerson |
| handledParticipantId | Reservationに設定されたParticipant。未指定の場合はNULL |
| guestCount | Check InされたReservationの来場人数 |
| updatedBy | Check Inを実行した主体 |
| updatedAt | Check Inが完了した日時 |

---

# Booker

BookerはReservationの予約者を表す。

Audience HistoryのSubjectには、
ReservationのBookerを使用する。

BookerはHandledParticipantとは異なる。

Booker
    = 実際の予約者

HandledParticipant
    = 予約上の「○○扱い」

---

# Handled Participant

HandledParticipantは、
Reservationにおける「○○扱い」のParticipantを表す。

HandledParticipantは任意である。

HandledParticipantが設定されていない場合はNULLとする。

HandledParticipantは、
Audience HistoryのSubjectには使用しない。

Audience HistoryのSubjectはBookerである。

---

# Guest Count

GuestCountは、
Check InされたReservationに含まれる来場人数を表す。

Booker本人とCompanionを含めた人数を使用する。

例えば、

Booker
    = 1名

Companion
    = 1名

GuestCount
    = 2名

となる。

GuestCountは、
Check In Portalにおけるチェックイン済み人数の集計に使用する。

---

# Reservation Seat

ReservationSeatは、
Reservationに紐づく予約済み座席を表す。

ReservationSeatは、
Check Inの対象ではない。

Check InはReservation単位で行う。

例えば3席のReservationの場合、

Reservation
    ├─ Seat A-10
    ├─ Seat A-11
    └─ Seat A-12

という状態であっても、
A-10、A-11、A-12を個別にCheck Inすることはない。

3席を含むReservation全体をCheck Inする。

ReservationSeatは、
Reservationの人数変更や座席変更時に
予約状態を管理するために使用する。

---

# Reservation State

Check In前のReservationは、

RESERVED

である。

Check Inによって、

RESERVED
    ↓
CHECKED_IN

へ変更する。

Check In完了後、
Reservationは変更不可となる。

---

# Update Restrictions

CHECKED_INとなったReservationは、
以後変更できない。

以下の変更を禁止する。

- Booker
- HandledParticipant
- Companion
- GuestCount
- ReservationSeat
- TicketType
- Performance
- Cancel

Reservationの人数や座席に誤りがある場合は、
Check In前に受付担当者がReservationを修正する。

修正完了後にCheck Inを実行する。

---

# Updated By / Updated At

Check InはReservationの状態変更である。

そのためCheck In完了時に、

UpdatedBy
    = Check Inを実行した主体

UpdatedAt
    = Check In完了日時

としてReservationを更新する。

これにより、
誰がCheck Inを実行したかを記録する。

---

# Check In Portal

Check In Portalでは、
受付担当者が事前にProductionおよびPerformanceを選択する。

その後、
選択したPerformance専用の受付画面を表示する。

受付画面では以下を確認できる。

- 予約人数
- 未チェックイン人数
- チェックイン済み人数

---

# Unchecked In List

通常の受付画面では、
未チェックインのReservation一覧を表示する。

一覧には、
受付に必要な情報を表示する。

例）

山田太郎
2名
山田扱い

ReservationがCheck Inされると、
未チェックイン一覧から消える。

---

# Checked In List

Check In済みのReservationは、
チェックイン済み一覧から確認できる。

チェックイン済み一覧では、
少なくとも以下を確認できる。

- Booker
- GuestCount
- HandledParticipant
- Check In日時

---

# Manual Check In

受付担当者が、
選択中PerformanceのReservation一覧から
予約者を検索する。

Reservationの内容を確認する。

必要に応じて、
Check In前にReservationの人数や座席などを修正する。

Reservationの内容が正しいことを確認した後、
Check Inを実行する。

Check In完了後、
Reservationは未チェックイン一覧から消える。

---

# QR Check In

観客が提示するQRCodeを読み取る。

QRCodeからReservationを特定する。

ReservationのPerformanceと
受付中Performanceが一致することを確認する。

一致した場合、
Check Inを実行する。

一致しない場合は、
Check Inを実行しない。

---

# History Generation

ReservationCheckedInを契機として、
History DomainがAudience Historyを生成する。

ReservationCheckedIn
        ↓
History Domain
        ↓
Audience History

生成するHistoryは以下の内容とする。

HistoryType
    = AUDIENCE

Subject
    = Reservation.Booker

ParticipantType
    = NULL

Production
    = Reservation.Performance.Production

Performance
    = Reservation.Performance

EventDateTime
    = ReservationCheckedIn.occurredAt

---

# Subject

Audience HistoryのSubjectには、
ReservationのBookerを使用する。

HandledParticipantはSubjectにならない。

例えば、

Booker
    = 観客A

HandledParticipant
    = Participant 山田

の場合、

Audience History

Subject
    = 観客A

となる。

---

# Participant Type

Audience HistoryではParticipantTypeを使用しない。

ParticipantType
    = NULL

AudienceはProductionへの参加区分ではないため、
ParticipantTypeを設定しない。

---

# Idempotency

ReservationCheckedInの処理は冪等であること。

同じeventIdを複数回受信した場合でも、
同一のAudience Historyを複数生成しない。

ReservationCheckedIn
eventId = event-004

1回目
    ↓
Audience History生成

2回目
    ↓
処理済み
    ↓
Audience History生成しない

---

# Failure Handling

History生成に失敗した場合でも、
ReservationのCheck In自体を取り消さない。

ReservationCheckedInは発行済みEventとして保持し、
Event Handlerが再実行できる状態とする。

---

# Business Rules

- ReservationCheckedInはReservation Domainが発行する。
- ReservationCheckedInはReservationのCheck In完了後に発行する。
- Check In開始前にProductionおよびPerformanceを選択する。
- 選択されたPerformanceがCheck In対象となる。
- ReservationのPerformanceと受付中Performanceが一致しない場合はCheck Inできない。
- 手動Check InとQR Check Inは同じReservation Check Inとして扱う。
- Check In方法ごとに別のDomain Eventは発行しない。
- Check In前のReservationは必要に応じて修正できる。
- Check In前に人数や座席などのReservation内容を確定する。
- ReservationSeatはCheck Inの対象ではない。
- Check InはReservation単位で行う。
- CHECKED_INのReservationは変更できない。
- CHECKED_INのReservationはキャンセルできない。
- Check In完了時にReservationStatusをCHECKED_INへ変更する。
- Check In完了時にUpdatedByをCheck In実行者へ更新する。
- Check In完了時にUpdatedAtをCheck In日時へ更新する。
- GuestCountはCheck InされたReservationの来場人数を表す。
- GuestCountはチェックイン済み人数の集計に使用する。
- ReservationCheckedInではHistoryを直接操作しない。
- History DomainはReservationCheckedInを契機としてAudience Historyを生成する。
- Audience HistoryのSubjectはReservation.Bookerである。
- HandledParticipantはAudience HistoryのSubjectにならない。
- Audience HistoryのParticipantTypeはNULLとする。
- Event処理は冪等である。
- Event Handlerの失敗によってCheck Inを取り消さない。

---

# Design Principles

- Eventは発生したBusiness Eventを表現する。
- ReservationCheckedInはReservationの来場確定を表現する。
- Check In方法とDomain Eventを分離する。
- Manual Check InとQR Check Inは同じBusiness Eventとして扱う。
- Check In対象Performanceを明示的に選択する。
- ReservationとPerformanceの一致を検証する。
- Check In前にReservation内容を確定する。
- Check In後はReservationを変更しない。
- ReservationSeatは予約情報として管理し、個別にCheck Inしない。
- Check InはReservation単位で行う。
- Check In後のReservation変更によるHistory不整合を発生させない。
- UpdatedByとUpdatedAtによってCheck In実行者と日時を記録する。
- Reservation DomainはHistory Domainへ直接依存しない。
- History DomainはReservationCheckedInを契機としてAudience Historyを生成する。
- Audience HistoryのSubjectはBookerである。
- HandledParticipantとAudienceのSubjectを混同しない。
- Event処理は冪等性を確保する。