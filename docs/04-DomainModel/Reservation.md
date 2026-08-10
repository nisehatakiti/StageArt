# StageArt Blueprint
# Domain Model : Reservation

Version : 4.0

---

# Purpose

ReservationはPerformanceに対する予約を表すDomainである。

Reservationは観客による来場予約を管理する。

Reservationは予約情報の整合性を管理するAggregate Rootであり、
CompanionおよびReservationSeatを管理する。

Reservationは、
「誰が」「どのPerformanceに」「何名で」「誰扱いで」
予約したかを管理する。

Business RuleはReservationが管理する。

HistoryはReservationの責務ではない。

---

# Concept

ReservationはPerformanceへの予約を表す。

Performance
    │
    ▼
Reservation
    ├── Booker
    ├── HandledParticipant
    ├── Companion
    ├── ReservationSeat
    ├── TicketType
    ├── QRCode
    ├── Status
    ├── CreatedBy
    ├── CreatedAt
    ├── UpdatedBy
    └── UpdatedAt

ReservationはAggregate Rootとして
Reservation内部の情報を一貫して管理する。

HistoryはReservationとは独立したDomainとして管理する。

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

また、Reservationは予約人数を管理する。

予約人数は、
予約者および同行者を含むReservation全体の人数を表す。

観劇履歴（History）は管理しない。

---

# Identity

ReservationはReservationIdによって識別する。

ReservationIdは変更できない。

ReservationNumberは利用者へ表示する識別子とする。

---

# Performance

Reservationは必ず一つのPerformanceへ所属する。

ReservationのPerformanceは、
予約対象となった公演回を表す。

Reservation作成後も、
Check In前であればReservationの変更ルールに従って
予約内容を変更できる。

Check In後はPerformanceを変更できない。

---

# Booker

Bookerは予約者を表す。

BookerはPersonを参照する。

BookerはReservationに必須である。

Bookerは、
「誰の予約なのか」を表す。

BookerとCreatedByは異なる概念である。

---

# Handled Participant

HandledParticipantは予約を担当するParticipantを表す。

HandledParticipantはParticipantを参照する。

HandledParticipantは任意である。

指定されない場合は一般予約として扱う。

HandledParticipantは、
予約における「○○扱い」を表す。

HandledParticipantは、
ReservationのBookerやCreatedByとは異なる概念である。

HandledParticipantは予約作成後でも変更できる。

ただし、Check In後は変更できない。

HandledParticipantの存在によって、
Participantの活動履歴を直接生成・更新しない。

---

# Companion

Companionは同行者を表す。

CompanionはReservationに属する。

Companion単独では存在できない。

CompanionはAggregate内部でのみ管理する。

Companionの追加・削除はReservationを経由して行う。

Companionの変更によって、
Reservationの予約人数が変更される。

Check In後はCompanionを変更できない。

---

# Reservation Count

Reservation Countは、
Reservationに含まれる来場人数を表す。

予約者本人と同行者を含めた人数を管理する。

例）

Booker
    = 1名

Companion
    = 2名

Reservation Count
    = 3名

Reservation Countは、
チェックインポータルにおける予約人数の集計に使用する。

チェックイン済み人数もReservation Countを基準として集計する。

---

# Reservation Seat

ReservationSeatは予約座席を表す。

ReservationSeatはReservationに属する。

ReservationSeat単独では存在できない。

ReservationSeatはAggregate内部でのみ管理する。

ReservationSeatの追加・変更・削除は
Reservationを経由して行う。

---

# Seat and Reservation Count

座席指定があるPerformanceでは、
Reservationの人数変更に応じてReservationSeatも調整する。

例えば、

2名
    ↓
3名

へ予約人数を変更した場合、
3名分の座席を確保する必要がある。

既存の座席を維持したまま追加席を確保する場合、
連続した座席を確保できない可能性がある。

そのため、座席指定がある予約では、
予約人数変更時に連席を確保できない場合があることを
予約者へ事前に告知する。

Reservationの人数変更とReservationSeatの変更は、
同一Reservationの更新として扱う。

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

QRCodeはReservationを特定するために使用する。

QRCodeは変更しない。

QRコードによるCheck Inは、
名前による手動Check Inと同じReservation Check Inとして扱う。

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

# Created By

CreatedByは、
Reservationを作成した主体を表す。

CreatedByは、
BookerおよびHandledParticipantとは異なる概念である。

例えば、

観客本人が予約を作成した場合、

Booker
    = 観客

CreatedBy
    = 観客

となる。

Participant本人が観客の予約を代理入力した場合、

Booker
    = 観客

HandledParticipant
    = Participant

CreatedBy
    = Participant

となる。

CreatedByはReservation作成後に変更しない。

---

# Created At

CreatedAtはReservationが作成された日時を表す。

CreatedAtはReservation作成時に設定する。

CreatedAtは変更しない。

---

# Updated By

UpdatedByは、
Reservationを最後に変更した主体を表す。

Reservationの人数変更、
HandledParticipant変更、
Companion変更、
ReservationSeat変更、
TicketType変更、
その他Reservationの変更を行った主体を記録する。

CreatedByとは異なる主体が変更することができる。

---

# Updated At

UpdatedAtはReservationが最後に変更された日時を表す。

Reservationが変更されるたびに更新する。

Check InによるStatus変更もReservationの状態変更であるため、
UpdatedAtを更新する。

---

# Reservation Management

Reservationは、
作成後もCheck In前であれば管理画面から変更できる。

変更可能な内容には以下を含む。

- Booker
- HandledParticipant
- Companion
- Reservation Count
- ReservationSeat
- TicketType
- その他予約管理項目

変更を行った場合は、

UpdatedBy
UpdatedAt

を更新する。

Reservationの変更後はReservationUpdatedを発行する。

---

# Reservation Cancellation

ReservationはCheck In前であればキャンセルできる。

キャンセルされたReservationは削除しない。

Statusを、

RESERVED
    ↓
CANCELLED

へ変更する。

キャンセルを行った主体をUpdatedByとして記録し、
UpdatedAtを更新する。

キャンセル完了後にReservationCancelledを発行する。

---

# Check In

Check Inは、
Reservationに対して実際の来場を確定する操作である。

Check Inは以下の2つの方法で行うことができる。

- 予約一覧からの手動Check In
- QRコードによるCheck In

どちらも同じReservation Check Inとして扱う。

---

# Check In Context

Check Inを開始する前に、
受付担当者は以下を選択する。

Production
    ↓
Performance

選択されたPerformanceが、
その受付でCheck In対象となる公演回である。

---

# Check In Validation

ReservationをCheck Inする際は、
ReservationのPerformanceと、
受付で選択されているPerformanceが一致していることを確認する。

一致しない場合はCheck Inできない。

これにより、
別の公演回の予約者が誤ってCheck Inされることを防止する。

例えば、

Reservation
    = 夜公演

受付中Performance
    = 昼公演

の場合、

Check In
    = 不可

とする。

---

# Check In State Transition

Check In前のReservationは、

RESERVED

である。

Check Inによって、

RESERVED
    ↓
CHECKED_IN

へ変更する。

Check In完了後にReservationCheckedInを発行する。

---

# Checked In Reservation

CHECKED_INとなったReservationは、
予約内容を変更できない。

以下の変更を禁止する。

- Booker変更
- HandledParticipant変更
- Companion変更
- Reservation Count変更
- ReservationSeat変更
- TicketType変更
- Performance変更
- Cancel

Check In後のReservationは、
来場実績が確定した状態として扱う。

---

# Reservation Count Change Before Check In

Check In前であれば、
Reservation Countを変更できる。

例えば、

2名
    ↓
3名

への変更が可能である。

座席指定がある場合は、
ReservationSeatも同時に調整する。

連席を確保できない可能性がある場合は、
予約者へ事前に告知する。

Reservation CountとReservationSeatの整合性が
確保された状態でCheck Inを行う。

---

# Check In Portal

Check In Portalでは、
受付担当者が事前に以下を選択する。

Production
    ↓
Performance

その後、
選択したPerformanceに対する受付画面を表示する。

受付画面では以下を確認できる。

- 予約人数
- 未チェックイン人数
- チェックイン済み人数

通常画面には、
未チェックインのReservation一覧を表示する。

---

# Unchecked In List

未チェックイン一覧には、
Check In前のReservationを表示する。

一覧には、
予約者およびHandledParticipantなど、
受付に必要な情報を表示する。

例えば、

山田太郎
2名
山田扱い

のように表示する。

ReservationがCheck Inされると、
未チェックイン一覧から消える。

---

# Checked In List

Check In済みのReservationは、
チェックイン済み一覧から確認できる。

チェックイン済み一覧では、
少なくとも以下を確認できる。

- Booker
- Reservation Count
- HandledParticipant
- Check In日時

---

# Check In by Manual Search

受付担当者が予約者の名前などから
Reservationを一覧から検索する。

Reservationを確認した後、
Check Inを実行する。

---

# Check In by QR

観客が提示するQRCodeを読み取ることで、
Reservationを特定する。

QRCodeからReservationを特定した後、
受付中Performanceとの一致を確認する。

一致した場合のみCheck Inを実行する。

---

# History

ReservationはHistoryを生成・更新・削除しない。

ReservationCheckedInを契機として、
History DomainがAudience Historyを生成する。

Reservation
    ↓
ReservationCheckedIn
    ↓
History Domain
    ↓
Audience History

---

# Business Rules

- Reservationは必ず一つのPerformanceへ所属する。
- ReservationはAggregate Rootである。
- Bookerは必須である。
- HandledParticipantは任意である。
- HandledParticipantは予約の「○○扱い」を表す。
- CreatedByはReservationを作成した主体を表す。
- CreatedByはReservation作成後に変更しない。
- CreatedAtはReservation作成日時を表す。
- UpdatedByはReservationを最後に変更した主体を表す。
- UpdatedAtはReservationの最終更新日時を表す。
- CompanionはReservationを経由してのみ変更できる。
- ReservationSeatはReservationを経由してのみ変更できる。
- Reservation CountはReservationの来場人数を表す。
- Reservation Count変更時はReservationSeatとの整合性を確保する。
- 座席指定公演では人数変更によって連席を確保できない場合がある。
- 連席を確保できない可能性があることを予約者へ事前に告知する。
- Check In前のReservationは変更できる。
- Check In前のReservationはキャンセルできる。
- Check In後のReservationは変更できない。
- Check In後のReservationはキャンセルできない。
- Check In前に予約内容を正しい状態へ修正する。
- Check InはProductionおよびPerformanceを選択した受付コンテキストで行う。
- ReservationのPerformanceと受付中Performanceが一致しない場合はCheck Inできない。
- 手動Check InとQR Check Inは同じReservation Check Inとして扱う。
- Check InによってReservationStatusをCHECKED_INへ変更する。
- ReservationCheckedInはCheck In完了後に発行する。
- ReservationCreatedは予約作成後に発行する。
- ReservationUpdatedはReservation変更後に発行する。
- ReservationCancelledはReservationキャンセル後に発行する。
- ReservationはHistoryを直接管理しない。
- HistoryはReservationが発行するDomain Eventを契機として別Domainが管理する。

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

Bookerは予約者を表す。

HandledParticipantは予約担当Participantを表す。

HandledParticipantは任意である。

CreatedByはReservationを作成した主体を表す。

UpdatedByはReservationを最後に変更した主体を表す。

CompanionおよびReservationSeatは
Aggregate内部で管理する。

Reservation CountはReservationの来場人数を表す。

Reservation CountとReservationSeatは
Check In前に整合性を確保する。

Check In後はReservationを変更しない。

Check In前に予約内容の修正を完了させる。

Historyは独立したDomainとして管理する。

ReservationはHistoryへ依存しない。

---

# Design Principles

- ReservationはPerformanceへの予約を表すBusiness Domainである。
- ReservationはAggregate Rootである。
- Bookerは予約者を表す。
- HandledParticipantは予約担当Participantを表す。
- HandledParticipantは任意である。
- CreatedByは予約作成者を表す。
- UpdatedByは最終更新者を表す。
- CompanionはReservation経由でのみ操作する。
- ReservationSeatはReservation経由でのみ操作する。
- Reservation CountとReservationSeatの整合性を維持する。
- Check In前に予約内容を確定する。
- Check In後はReservationを変更しない。
- 手動Check InとQR Check Inは同じBusiness Eventとして扱う。
- ReservationはHistoryを管理しない。
- HistoryはDomain Eventによって管理する。
- Check InはReservationStatusで管理する。
- ReservationはBusiness Ruleを管理する。