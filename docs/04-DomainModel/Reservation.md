# StageArt Blueprint
# Domain Model : Reservation

Version : 4.0

---

# Purpose

ReservationはPerformanceに対する予約を表すDomainである。

Reservationは観客による来場予約を管理する。

Reservationは予約情報の整合性を管理するAggregate Rootであり、
CompanionおよびReservationSeatを管理する。

Business RuleはReservationが管理する。

HistoryはReservationの責務ではない。

---

# Concept

Reservationは特定のPerformanceへの予約を表す。

Performance
    │
    ▼
Reservation
    ├── Booker
    ├── HandledParticipant
    ├── Companion
    └── ReservationSeat

ReservationはAggregate Rootとして
CompanionおよびReservationSeatを管理する。

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
- CreatedBy
- CreatedAt
- UpdatedBy
- UpdatedAt

Reservation人数は、
BookerおよびCompanionから算出・管理する。

観劇履歴(History)は管理しない。

---

# Identity

ReservationはReservationIdによって識別する。

ReservationIdは変更できない。

ReservationNumberは利用者へ表示する識別子とする。

ReservationNumberはReservation生成時に採番する。

---

# Performance

Reservationは必ず一つのPerformanceへ所属する。

ReservationはProductionではなく、
特定のPerformanceに対する予約を表す。

Performance
    ↓
Reservation

ReservationのPerformanceは、
Reservation作成後に変更できない。

---

# Booker

Bookerは予約者を表す。

BookerはPersonを参照する。

Bookerは、
「誰の予約か」を表す。

BookerとCreatedByは異なる概念である。

例えば、

Booker
    = 観客A

CreatedBy
    = Participant 山田

という状態を許可する。

---

# Handled Participant

HandledParticipantは、
予約における「○○扱い」のParticipantを表す。

HandledParticipantはParticipantを参照する。

HandledParticipantは任意である。

指定されない場合は一般予約として扱う。

HandledParticipantはCheck In前であれば変更できる。

HandledParticipantは、
BookerおよびCreatedByとは異なる概念である。

---

# Companion

Companionは同行者を表す。

CompanionはReservationに属する。

Companion単独では存在できない。

CompanionはAggregate内部でのみ管理する。

Companionの追加・削除は、
Reservationを経由して行う。

Companionの変更に伴って、
Reservation人数も整合するように更新する。

---

# Guest Count

GuestCountはReservationに含まれる来場人数を表す。

Booker本人とCompanionを含めた人数を表す。

例えば、

Booker
    = 1名

Companion
    = 1名

GuestCount
    = 2名

となる。

GuestCountはCheck In Portalにおける
予約人数およびチェックイン済み人数の集計に使用する。

---

# Reservation Seat

ReservationSeatは、
Reservationが予約しているSeatを表す。

ReservationSeatはReservationに属する。

ReservationSeat単独では存在できない。

ReservationSeatはAggregate内部でのみ管理する。

ReservationSeatはPerformanceに存在するSeatを参照する。

関係は以下のようになる。

Performance
    ↓
Seat

Reservation
    ↓
ReservationSeat
    ↓
Seat

---

# Seat State

Seatそのものは座席情報のみを管理する。

Seatは、

- 予約済み
- 空席
- チェックイン済み

などの状態を保持しない。

Seatの予約状況は、
ReservationおよびReservationSeatから判断する。

---

# Seat Reservation

ReservationがSeatを予約すると、
ReservationSeatによってSeatとの関係を保持する。

例えば、

Reservation
    ├── A-12
    └── A-13

の場合、

A-12
A-13

はそのReservationによって予約されていると判断する。

---

# Seat Addition

Reservation人数が増加した場合、
必要なSeatを追加する。

例えば、

2名
A-12 / A-13

から、

3名

へ変更する場合、
3名分のSeatを確保する。

追加されたSeatは、
ReservationSeatとしてReservationに追加する。

Seat自体の情報は変更しない。

---

# Seat Release

Reservation人数が減少した場合、
不要になったSeatを解放する。

例えば、

3名
A-12 / A-13 / B-12

から、

2名

へ変更する場合、
不要になったReservationSeatを解放する。

解放されたSeatは、
そのReservationによる予約がなくなるため、
再び予約可能なSeatとして扱う。

---

# Seat Change

Reservationの変更によって、
予約するSeatそのものを変更する場合がある。

その場合は、

既存ReservationSeatを解放
        ↓
新しいSeatをReservationSeatとして追加

という形でReservation側の状態を変更する。

Seat自体の情報は変更しない。

---

# Consecutive Seats

座席指定があるPerformanceでReservation人数を変更した場合、
連続したSeatを確保できない可能性がある。

例えば、

2名
A-12 / A-13

から、

3名

へ変更した場合、

A-12 / A-13 / B-12

のように連席にならない場合がある。

そのため、
座席指定があるPerformanceでは、
人数変更によって連席を確保できない可能性があることを
予約者へ事前に告知する。

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

QRCodeによるCheck Inでは、
QRCodeからReservationを特定する。

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

# Status Transition

基本的な状態遷移は以下の通り。

RESERVED
    │
    ├── Update
    │      ↓
    │   RESERVED
    │
    ├── Check In
    │      ↓
    │   CHECKED_IN
    │
    └── Cancel
           ↓
       CANCELLED

CHECKED_IN
    │
    └── 変更不可

CANCELLED
    │
    └── Check In不可

---

# Created By

CreatedByは、
Reservationを作成した主体を表す。

CreatedByには、
実際にReservation作成処理を実行した
認証済み利用者を設定する。

CreatedByはBookerとは異なる場合がある。

例えば、

Booker
    = 観客A

CreatedBy
    = Participant 山田

という状態を許可する。

CreatedByはReservation作成後に変更しない。

---

# Created At

CreatedAtは、
Reservationが作成された日時を表す。

CreatedAtはReservation作成時に設定する。

CreatedAtは変更しない。

---

# Updated By

UpdatedByは、
Reservationを最後に変更した主体を表す。

Reservationを変更した認証済み利用者を設定する。

例えば、

CreatedBy
    = Participant 山田

UpdatedBy
    = 制作スタッフ 佐藤

という状態を許可する。

Check Inを実行した利用者も、
Check In完了時にUpdatedByとして記録する。

---

# Updated At

UpdatedAtは、
Reservationが最後に変更された日時を表す。

Reservationの変更時に更新する。

Check In完了時にも更新する。

---

# Create

Reservation作成時に以下を確定する。

- Performance
- Booker
- HandledParticipant
- Companion
- GuestCount
- ReservationSeat
- TicketType
- QRCode
- Status
- CreatedBy
- CreatedAt
- UpdatedBy
- UpdatedAt

初期状態は、

Status
    = RESERVED

とする。

初期値として、

UpdatedBy
    = CreatedBy

UpdatedAt
    = CreatedAt

とする。

---

# Update

ReservationはCheck In前であれば変更できる。

変更可能な情報には以下を含む。

- Booker
- HandledParticipant
- Companion
- GuestCount
- ReservationSeat
- TicketType
- Memo

Reservationの変更はAggregate Rootである
Reservationを経由して行う。

変更完了時に、

UpdatedBy
UpdatedAt

を更新する。

変更完了後、
ReservationUpdatedを発行する。

---

# Update Restriction

CHECKED_INのReservationは変更できない。

以下の変更を禁止する。

- Booker
- HandledParticipant
- Companion
- GuestCount
- ReservationSeat
- TicketType
- Performance
- Memo

Reservation内容に誤りがある場合は、
Check In前に修正する。

---

# Cancellation

ReservationはCheck In前であればキャンセルできる。

キャンセルによって、

RESERVED
    ↓
CANCELLED

へ変更する。

Reservation自体は削除しない。

キャンセル時に、

UpdatedBy
UpdatedAt

を更新する。

キャンセルされたReservationの
ReservationSeatはすべて解放する。

その後、

ReservationCancelled

を発行する。

---

# Cancellation Restriction

CHECKED_INのReservationはキャンセルできない。

CANCELLEDのReservationは
再度キャンセルできない。

CANCELLEDのReservationは
通常のReservation Updateを行わない。

CANCELLEDのReservationは
Check Inできない。

---

# Check In

Check InはReservation単位で行う。

Check Inを開始する前に、
受付担当者はProductionおよびPerformanceを選択する。

Production
    ↓
Performance
    ↓
Check In受付開始

選択されたPerformanceが、
Check In対象となる公演回である。

ReservationのPerformanceと
受付中Performanceが一致する場合のみ、
Check Inを実行できる。

---

# Check In Methods

Check Inは以下の方法で実行できる。

- Reservation一覧からの手動Check In
- QRコードによるCheck In

どちらの方法でも、
同じReservation Check Inとして扱う。

Check In方法ごとに別のDomain Eventは発行しない。

---

# Check In Target

Check Inの対象はReservationである。

ReservationSeatはCheck Inの対象ではない。

例えば、

Reservation
    ├── A-10
    ├── A-11
    └── A-12

という3席のReservationであっても、
A-10、A-11、A-12を個別にCheck Inすることはない。

Reservation全体をCheck Inする。

---

# Check In Completion

Check In完了時に、

ReservationStatus
    = CHECKED_IN

へ変更する。

同時に、

UpdatedBy
    = Check In実行者

UpdatedAt
    = Check In完了日時

へ更新する。

Check In完了後、
Reservationは変更不可となる。

Check In完了後、

ReservationCheckedIn

を発行する。

---

# Check In Validation

受付中Performanceと
ReservationのPerformanceが一致しない場合、
Check Inできない。

例えば、

受付中Performance
    = 10月12日 14:00

Reservation
    = 10月12日 18:00

の場合、

Check In
    = 不可

とする。

---

# Check In and Seat

SeatはCheck In状態を保持しない。

ReservationSeatも個別にCheck In状態を保持しない。

Check In済みかどうかは、
ReservationStatusによって判断する。

---

# History

ReservationはHistoryを生成・更新・削除しない。

ReservationCheckedInを契機として、
History DomainがAudience Historyを生成する。

ReservationCreated、
ReservationUpdated、
ReservationCancelled

ではAudience Historyを生成しない。

Audience HistoryのSubjectは、
ReservationのBookerである。

HandledParticipantは、
Audience HistoryのSubjectにならない。

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

# Business Rules

- Reservationは必ず一つのPerformanceへ所属する。
- ReservationはAggregate Rootである。
- Bookerは必須である。
- HandledParticipantは任意である。
- BookerとCreatedByは別の概念である。
- HandledParticipantとCreatedByは別の概念である。
- CompanionはReservationを経由してのみ変更できる。
- ReservationSeatはReservationを経由してのみ変更できる。
- GuestCountはReservationの来場人数を表す。
- GuestCountとCompanionの整合性を維持する。
- GuestCountとReservationSeatの整合性を維持する。
- 人数増加時は必要なReservationSeatを追加する。
- 人数減少時は不要なReservationSeatを解放する。
- 座席変更時はReservationSeatを変更する。
- 座席指定公演では人数変更によって連席を確保できない場合がある。
- 連席にならない可能性があることを予約者へ事前に告知する。
- Seatは予約状態を保持しない。
- ReservationSeatはSeatを参照する。
- SeatはCheck Inの対象ではない。
- ReservationSeatはCheck Inの対象ではない。
- Check InはReservation単位で行う。
- Check In開始前にProductionおよびPerformanceを選択する。
- ReservationのPerformanceと受付中Performanceが一致しない場合はCheck Inできない。
- Check In前のReservationは変更できる。
- CHECKED_INのReservationは変更できない。
- CHECKED_INのReservationはキャンセルできない。
- CANCELLEDのReservationはCheck Inできない。
- CANCELLEDのReservationは通常更新できない。
- CreatedByはReservation作成後に変更しない。
- CreatedAtは変更しない。
- UpdatedByは変更実行者へ更新する。
- UpdatedAtは変更日時へ更新する。
- Check In完了時もUpdatedByおよびUpdatedAtを更新する。
- ReservationはHistoryを管理しない。
- Audience HistoryはReservationCheckedInを契機としてHistory Domainが生成する。

---

# Design Decisions

ReservationはPerformanceへの予約を表す。

ReservationはAggregate Rootである。

HandledParticipantは予約における
「○○扱い」を表す。

Bookerは予約者を表す。

CreatedByはReservationを作成した主体を表す。

UpdatedByはReservationを最後に変更した主体を表す。

CompanionおよびReservationSeatは
Aggregate内部で管理する。

SeatはPerformanceに所属する。

ReservationSeatはReservationに所属する。

Seat自体は予約状態を保持しない。

Check InはReservation単位で行う。

SeatおよびReservationSeatは個別にCheck Inしない。

ReservationはHistoryへ依存しない。

Historyは独立したDomainとして管理する。

---

# Design Principles

- ReservationはPerformanceへの予約を表すBusiness Domainである。
- ReservationはAggregate Rootである。
- Bookerは予約者を表す。
- HandledParticipantは予約担当Participantを表す。
- HandledParticipantは任意である。
- CreatedByはReservationを作成した主体を表す。
- UpdatedByはReservationを最後に変更した主体を表す。
- CompanionはReservation経由でのみ操作する。
- ReservationSeatはReservation経由でのみ操作する。
- GuestCountとReservationSeatの整合性を維持する。
- 人数変更に伴うSeatの追加・解放をReservation Aggregateで管理する。
- Seatは予約状態を保持しない。
- SeatはCheck Inしない。
- Check InはReservation単位で行う。
- Check In前にReservation内容を確定する。
- Check In後はReservationを変更しない。
- ReservationはHistoryを管理しない。
- ReservationCheckedInをAudience History生成の契機とする。
- Business RuleはReservation Domainが管理する。
- Business ProcessはDomain Eventを契機として別Domainで実行する。
