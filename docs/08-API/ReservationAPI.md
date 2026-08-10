# StageArt Blueprint
# API : Reservation

Version : 5.0

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

# General Reservation

Reservationの作成は一般利用者が利用する公開Business Flowである。

一般利用者は、
対象Performanceに対してReservationを作成できる。

Reservationの作成自体は、
ProductionのPrimaryManagerまたはProductionDelegateによる
管理権限の対象としない。

ただし、販売期限を過ぎたPerformanceについては、
新規予約を作成できない。

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

# Update Authorization

Reservationの更新は、
チケット販売期限およびReservationStatusによって制御する。

## Before Ticket Sales Deadline

チケット販売期限前で、
ReservationStatusがCHECKED_INではない場合、
一般予約者は自身のReservationを更新できる。

一般予約者による更新は、
Productionの管理権限を必要としない。

一般予約者が更新できるのは、
自身がBookerであるReservationとする。

---

## After Ticket Sales Deadline

チケット販売期限を過ぎた場合、
一般予約者によるReservationの更新はできない。

チケット販売期限後のReservation更新は、
Production単位の管理権限を持つ利用者のみ可能とする。

管理権限は以下のいずれかによって付与される。

- PrimaryManager
- ProductionDelegate

PrimaryManagerは対象Productionに対して
全権限を持つ。

ProductionDelegateは、
対象Productionに設定されたDelegateRoleによって
Reservation.Update Permissionが付与されている場合のみ、
Reservationを更新できる。

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

# Update Authorization Decision

Reservation Updateの実行可否は、
以下の順序で判定する。

1. Reservationが存在すること
2. ReservationStatusがCHECKED_INではないこと
3. 対象Performanceのチケット販売期限を確認する
4. 販売期限前の場合は一般予約者による更新を許可する
5. 販売期限後の場合はProduction Authorizationを確認する
6. PrimaryManagerの場合は許可する
7. ProductionDelegateの場合はDelegateRoleを確認する
8. DelegateRoleにReservation.Update Permissionがある場合は許可する
9. それ以外の場合は403 Forbiddenを返す

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

# Check In Authorization

Check Inは管理操作として扱う。

Check Inを実行できる利用者は、
対象Productionに対する管理権限を持つ必要がある。

PrimaryManagerはCheck Inを実行できる。

ProductionDelegateは、
DelegateRoleにReservation.CheckIn Permissionが付与されている場合のみ
Check Inを実行できる。

一般予約者はCheck Inを実行できない。

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

ただし、チケット販売期限を過ぎたReservationを
一般予約者がキャンセルすることはできない。

キャンセルされたReservationは削除しない。

ReservationStatusをCANCELLEDへ変更する。

UpdatedByはキャンセルを実行した認証済み利用者から設定する。

UpdatedAtはキャンセル日時に更新する。

ReservationCancelledを発行する。

---

# Cancel Authorization

## Before Ticket Sales Deadline

チケット販売期限前で、
ReservationStatusがCHECKED_INではない場合、
一般予約者は自身のReservationをキャンセルできる。

一般予約者によるキャンセルは、
Productionの管理権限を必要としない。

一般予約者がキャンセルできるのは、
自身がBookerであるReservationとする。

---

## After Ticket Sales Deadline

チケット販売期限を過ぎた場合、
一般予約者によるReservationのキャンセルはできない。

チケット販売期限後のReservationキャンセルは、
Production単位の管理権限を持つ利用者のみ可能とする。

管理権限は以下のいずれかによって付与される。

- PrimaryManager
- ProductionDelegate

PrimaryManagerは対象Productionに対して
全権限を持つ。

ProductionDelegateは、
対象Productionに設定されたDelegateRoleによって
Reservation.Cancel Permissionが付与されている場合のみ、
Reservationをキャンセルできる。

---

# Cancel Restrictions

ReservationStatusがCHECKED_INの場合、
Reservationをキャンセルすることはできない。

CHECKED_INのReservationに対してCancel APIを実行した場合、
409 Conflictを返す。

---

# Cancel Authorization Decision

Reservation Cancelの実行可否は、
以下の順序で判定する。

1. Reservationが存在すること
2. ReservationStatusがCHECKED_INではないこと
3. 対象Performanceのチケット販売期限を確認する
4. 販売期限前の場合は一般予約者によるキャンセルを許可する
5. 販売期限後の場合はProduction Authorizationを確認する
6. PrimaryManagerの場合は許可する
7. ProductionDelegateの場合はDelegateRoleを確認する
8. DelegateRoleにReservation.Cancel Permissionがある場合は許可する
9. それ以外の場合は403 Forbiddenを返す

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

Reservationの作成は、
一般利用者も利用可能なBusiness Flowとする。

Reservationの作成は、
ProductionのPrimaryManagerまたはProductionDelegateによる
管理権限を必須としない。

Reservationの取得・更新・キャンセル・Check Inについては、
操作内容に応じてAuthorizationを行う。

## General User

一般予約者は、
自身のReservationについて以下を実行できる。

チケット販売期限前

- Reservation.Create
- Reservation.Read
- Reservation.Update
- Reservation.Cancel

チケット販売期限後

- Reservation.Read

チケット販売期限後は、
一般予約者によるUpdateおよびCancelを禁止する。

---

## PrimaryManager

PrimaryManagerは、
対象Productionに対して全権限を持つ。

したがって、対象Productionに属するReservationについて、

- Reservation.Read
- Reservation.Update
- Reservation.Cancel
- Reservation.CheckIn

を実行できる。

PrimaryManagerはDelegateRoleによる制限を受けない。

---

## ProductionDelegate

ProductionDelegateは、
対象Productionに設定されたDelegateRoleによって
Reservation関連Permissionを付与される。

例

- Reservation.Read
- Reservation.Update
- Reservation.Cancel
- Reservation.CheckIn

DelegateRoleに定義されていない操作は実行できない。

---

# Production Authorization Scope

Reservationの管理Authorizationは、
Reservationが所属するPerformanceを経由して
Productionを特定する。

Reservation
    ↓
Performance
    ↓
Production
    ↓
PrimaryManager / ProductionDelegate
    ↓
DelegateRole

Organization Membershipと
Production単位の管理権限は分離する。

Organization Membershipを持っていることだけを理由として、
Production上のReservation管理権限を自動的に付与しない。

---

# Reservation Management Permissions

ProductionDelegateに付与可能なReservation関連Permissionは以下とする。

- Reservation.Read
- Reservation.Update
- Reservation.Cancel
- Reservation.CheckIn

Reservation.Createは、
一般予約にも利用されるため、
ProductionDelegateの基本Permissionには含めない。

管理者による代理予約作成が将来必要になった場合は、
別途Permissionとして追加する。

---

# Ticket Sales Deadline

チケット販売期限は、
Performanceに設定されたBusiness Ruleとして扱う。

Reservation UpdateおよびCancelのAuthorizationでは、
対象ReservationのPerformanceに設定された
チケット販売期限を参照する。

販売期限前

- 一般予約者によるUpdateを許可する。
- 一般予約者によるCancelを許可する。

販売期限後

- 一般予約者によるUpdateを禁止する。
- 一般予約者によるCancelを禁止する。
- Production管理者によるUpdateを許可する。
- Production管理者によるCancelを許可する。

PrimaryManagerは全権限を持つ。

ProductionDelegateはDelegateRoleに定義された
Permissionの範囲で操作できる。

---

# CreatedBy / UpdatedBy

CreatedByには、
実際にReservationを作成した認証済み利用者を設定する。

UpdatedByには、
実際にReservationを最後に変更した認証済み利用者を設定する。

一般予約者による操作であっても、
CreatedByおよびUpdatedByには実際の操作主体を記録する。

管理者が代理操作した場合も、
Bookerとは別にCreatedByまたはUpdatedByへ
実際の操作主体を記録する。

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

販売期限後に一般予約者がUpdateまたはCancelを実行した場合は、
Business RuleによるAuthorization違反として403 Forbiddenを返す。

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
│   ↓
│  RESERVED
│
├── Cancel
│   ↓
│  CANCELLED
│
└── Check In
    ↓
   CHECKED_IN

CHECKED_IN
└── 変更不可

CANCELLED
└── Check In不可

販売期限はReservationStatusとは別の
Business Ruleとして扱う。

販売期限前

RESERVED
├── 一般予約者 Update
└── 一般予約者 Cancel

販売期限後

RESERVED
├── 一般予約者 Update不可
├── 一般予約者 Cancel不可
├── PrimaryManager Update可
├── PrimaryManager Cancel可
├── ProductionDelegate Update可
└── ProductionDelegate Cancel可
    └── DelegateRole Permissionによる

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

管理者による代理予約作成が必要になった場合、
Reservation.CreateをProductionDelegateのPermissionとして
追加できる構造とする。

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
- Reservationの作成は一般利用者も利用できる。
- Reservationの作成にProduction管理権限を要求しない。
- チケット販売期限前は一般予約者が自身のReservationを変更できる。
- チケット販売期限前は一般予約者が自身のReservationをキャンセルできる。
- チケット販売期限後は一般予約者によるReservation変更を禁止する。
- チケット販売期限後は一般予約者によるReservationキャンセルを禁止する。
- チケット販売期限後のReservation変更はProduction管理権限を要求する。
- チケット販売期限後のReservationキャンセルはProduction管理権限を要求する。
- PrimaryManagerは対象Productionに対して全権限を持つ。
- ProductionDelegateはDelegateRoleによって権限を制限する。
- Reservation.ReadはProductionDelegateに付与可能なPermissionである。
- Reservation.UpdateはProductionDelegateに付与可能なPermissionである。
- Reservation.CancelはProductionDelegateに付与可能なPermissionである。
- Reservation.CheckInはProductionDelegateに付与可能なPermissionである。
- Reservation.Createは一般予約にも利用されるためProductionDelegateの基本Permissionには含めない。
- Reservationの管理AuthorizationはPerformanceからProductionを特定して判定する。
- Organization MembershipとProduction単位の管理権限を分離する。
- CreatedByおよびUpdatedByには実際にAPI操作を実行した認証済み利用者を設定する。
- APIはDomain Eventを契機とするBusiness Processから分離される。
- Business RuleはDomain Layerが管理する。
- APIはRESTを採用する。
