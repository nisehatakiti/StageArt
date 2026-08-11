# StageArt Blueprint

# Domain Model : Reservation

Version : 5.0

---

# Purpose

Reservationは、
観客が特定のPerformanceへ予約するというFactを表すDomainである。

Reservationは、
「誰が」「どの公演回を」「どのTicket Typeで」予約したかを管理する。

ReservationはAggregate Rootとして、
予約に関するBusiness Ruleを管理する。

---

# Concept

Reservationは、
特定のPerformanceに対する予約を表す。

基本構造：

Performance
  ↓
Reservation
  ↓
Booker

Reservationは、
観客による予約というFactを管理する。

---

# Responsibility

Reservationは以下を管理する。

- Performance
- Booker
- Ticket Type
- Guest Count
- QR Code
- Status
- Created By
- Created At
- Updated By
- Updated At

ReservationはHistoryを直接管理しない。

---

# Identity

ReservationはReservationIdによって一意に識別する。

ReservationIdは変更しない。

ReservationNumberは、
利用者へ表示する予約番号として利用する。

ReservationNumberはReservation生成時に採番する。

ReservationIdとReservationNumberは別の概念として扱う。

---

# Performance

Reservationは必ず一つのPerformanceに所属する。

ReservationはProductionではなく、
特定のPerformanceに対する予約を表す。

基本構造：

Production
  ↓
Performance
  ↓
Reservation

Reservation作成後、
対象Performanceを変更することはできない。

別のPerformanceへ変更する場合は、
既存Reservationをキャンセルし、
新しいReservationを作成する。

---

# Booker

Bookerは予約者を表す。

BookerはPersonを参照する。

Bookerは、

「誰の予約か」

を表す。

BookerとCreatedByは異なる概念である。

例えば、

Booker
  = 観客A

CreatedBy
  = 劇団スタッフB

という状態を許可する。

---

# Guest Count

GuestCountは、
Reservationによって予約される人数を表す。

Version 1.0では、
同行者を個別のDomainとして管理しない。

例えば、

GuestCount
  = 2

の場合、
2名分の予約として扱う。

GuestCountは、
予約人数および受付時の来場人数集計に利用する。

---

# Companion

StageArtでは、
Companionを独立したDomainとして管理しない。

同行者の氏名やPerson情報を
Reservation内で管理することもしない。

同行者を含む予約人数は、
GuestCountによって表現する。

将来的に同行者管理が必要となった場合は、
別Domainとして設計する。

---

# Ticket Type

Reservationには、
利用するTicket Typeを設定する。

Ticket TypeおよびPriceは、
Productionごとに管理される。

基本構造：

Production
  ↓
Ticket Type / Price
  ↓
Performance
  ↓
Reservation

Reservationは、
Productionに設定されたTicket Type / Priceの組み合わせを参照する。

例えば、

- 一般 / 3,000円
- 学生 / 2,000円
- 当日券 / 3,500円

など。

Ticket TypeとPriceの正本はProduction側のTicket Masterとする。

ReservationへPriceを単独のマスタとして複製しない。

ただし、
予約確定時点の販売価格をFactとして保持する必要がある場合は、
Reservationの取引情報として記録する。

---

# QR Code

QRCodeは、
受付時にReservationを識別するための情報である。

QR TicketはReservationから生成されるArtifactとして扱う。

基本構造：

Reservation
  ↓
QR Ticket

QRコードからReservationを特定できる。

QRコードそのものを、
予約情報の正本として扱わない。

---

# Status

ReservationStatusは予約状態を表す。

基本的な状態：

- RESERVED
- CHECKED_IN
- CANCELLED
- NO_SHOW

---

# Reserved

RESERVEDは、
予約が有効であり、
まだCheck Inされていない状態を表す。

RESERVEDのReservationは、
Check In前であれば変更・キャンセルできる。

---

# Checked In

CHECKED_INは、
予約者が受付を完了した状態を表す。

CHECKED_INとなったReservationは、
原則として変更できない。

Check InはReservation単位で行う。

---

# Cancelled

CANCELLEDは、
予約がキャンセルされた状態を表す。

Reservationそのものは削除しない。

キャンセルされたReservationは、
Check Inできない。

---

# No Show

NO_SHOWは、
予約が存在したものの、
公演終了時点で来場が確認できなかった状態を表す。

NO_SHOWの判定タイミングや自動更新については、
別Business Processで定義する。

NO_SHOWは、
予約そのものが存在しなかったことを意味しない。

---

# Status Transition

基本的な状態遷移：

RESERVED
  ↓
CHECKED_IN

または、

RESERVED
  ↓
CANCELLED

公演終了後に、

RESERVED
  ↓
NO_SHOW

へ変更できる。

CHECKED_INとなったReservationは、
通常の予約変更を行わない。

CANCELLEDとなったReservationは、
Check Inできない。

---

# Create

Reservation作成時に以下を確定する。

- Performance
- Booker
- Ticket Type
- Guest Count
- QR Code
- Status
- Created By
- Created At
- Updated By
- Updated At

初期状態は、

Status
  = RESERVED

とする。

初期値として、

Updated By
  = Created By

Updated At
  = Created At

とする。

---

# Update

Reservationは、
Check In前であれば変更できる。

変更可能な情報には、

- Booker
- Ticket Type
- Guest Count

などを含む。

ReservationのPerformanceは変更できない。

変更はAggregate RootであるReservationを経由して行う。

変更完了時に、

Updated By
Updated At

を更新する。

変更完了後、
ReservationUpdatedを発行する。

---

# Update Restriction

CHECKED_INのReservationは変更できない。

以下の変更を禁止する。

- Performance
- Booker
- Ticket Type
- Guest Count

CANCELLEDのReservationについても、
通常のReservation Updateを行わない。

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

Updated By
Updated At

を更新する。

その後、

ReservationCancelled

を発行する。

---

# Cancellation Restriction

CHECKED_INのReservationはキャンセルできない。

CANCELLEDのReservationは、
再度キャンセルできない。

CANCELLEDのReservationは、
Check Inできない。

---

# Check In

Check InはReservation単位で行う。

受付開始時に、
受付担当者は対象となるProductionおよびPerformanceを選択する。

基本Flow：

Production
  ↓
Performance
  ↓
Check In受付開始
  ↓
Reservation特定
  ↓
Check In

ReservationのPerformanceと
受付中Performanceが一致する場合のみ、
Check Inを実行できる。

---

# Check In Methods

Check Inは以下の方法で実行できる。

- Reservation一覧からの手動Check In
- QRコードによるCheck In

どちらの場合も、
同じReservation Check Inとして扱う。

Check In方法によって、
異なる予約Factを作成しない。

---

# Check In Target

Check Inの対象はReservationである。

Guest Countが複数名であっても、
Reservation全体をCheck Inする。

例えば、

Reservation
  Guest Count = 3

の場合、

3名分の予約を一つのReservationとしてCheck Inする。

Version 1.0では、
同行者を個別にCheck Inする機能は提供しない。

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

これにより、
別の公演回に予約されたReservationを
誤ってCheck Inすることを防止する。

---

# Check In Completion

Check In完了時に、

ReservationStatus
  = CHECKED_IN

へ変更する。

同時に、

Updated By
  = Check In実行者

Updated At
  = Check In完了日時

へ更新する。

Check In完了後、
Reservationは通常の変更を受け付けない。

Check In完了後、

ReservationCheckedIn

を発行する。

---

# Check In and History

Reservationは、
Historyを直接生成・管理しない。

ReservationCheckedInを契機として、
History Domainが観劇履歴を生成する。

基本Flow：

Reservation
  ↓
ReservationCheckedIn
  ↓
History
  ↓
Audience History

予約しただけでは、
観劇履歴を生成しない。

実際にCheck Inされた場合に、
観劇したというFactとしてHistoryを生成する。

---

# Audience History

Audience HistoryのSubjectは、
ReservationのBookerである。

BookerがStageArtユーザーとして登録されている場合、
そのPersonの観劇履歴として表示できる。

一般観客は、
StageArtのポータルを利用する必要はない。

ただし、
StageArtへユーザー登録してチケットを予約・購入したPersonは、
自身の観劇履歴を確認できる。

---

# General Audience

一般観客にStageArtのInternal Portalへの参加を要求しない。

観客は、
公開されたProductionページから
公演情報を確認し、
チケットを予約できる。

Reservationを行った観客が
StageArtユーザーとして登録されている場合は、
PersonとReservationを関連付けることで、
観劇履歴などを利用できる。

---

# Created By

CreatedByは、
Reservationを作成した主体を表す。

CreatedByには、
実際にReservation作成処理を実行した
認証済み利用者を記録する。

CreatedByはBookerとは異なる場合がある。

例えば、

Booker
  = 観客A

CreatedBy
  = 劇団スタッフB

という状態を許可する。

CreatedByは、
Reservation作成後に変更しない。

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
  = 劇団スタッフB

UpdatedBy
  = 劇団スタッフC

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

# Reservation Number

ReservationNumberは、
観客および受付担当者が予約を識別するための番号である。

Reservation生成時に採番する。

ReservationNumberは、
ReservationIdとは別の識別子として扱う。

---

# Memo

Reservationには、
必要に応じて内部Memoを保持できる。

Memoは一般観客へ公開しない。

Memoの利用範囲や権限については、
Reservationを扱うOrganizationのRoleおよび
Production権限に従う。

---

# Privacy

Reservationには、
観客の個人情報が含まれる可能性がある。

そのため、

- Booker
- 予約情報
- 連絡先
- Memo
- その他予約に関連する個人情報

は、
権限のないPersonへ公開してはならない。

Productionの公開ページには、
Reservation情報を表示しない。

---

# Seat

Seatは将来実装する。

Version 1.0では、
ReservationはSeatを管理しない。

したがって、

- ReservationSeat
- 座席指定
- 座席レイアウト
- 連席管理
- 座席単位の予約

はVersion 1.0では実装しない。

将来的にSeatを実装する場合も、
ReservationをAggregate Rootとして
Reservationとの関係を管理する。

---

# Business Rules

- ReservationはPerformanceへの予約Factである。
- Reservationは一つのPerformanceに所属する。
- Reservation作成後にPerformanceを変更できない。
- ReservationはPersonをBookerとして持つ。
- CreatedByとBookerは異なる場合がある。
- Guest Countによって予約人数を管理する。
- Companionを独立したDomainとして管理しない。
- Ticket TypeはProductionごとのTicket Masterを参照する。
- Ticket TypeとPriceの正本はProduction側で管理する。
- ReservationはQR Ticket生成の基礎となる。
- Reservationは予約状態をStatusで管理する。
- RESERVEDのReservationはCheck In前であれば変更できる。
- RESERVEDのReservationはCheck In前であればキャンセルできる。
- CHECKED_INのReservationは通常変更できない。
- CANCELLEDのReservationはCheck Inできない。
- Check InはReservation単位で行う。
- Check Inには対象Performanceの一致を検証する。
- QR Check InとManual Check Inは同じReservation Check Inとして扱う。
- ReservationCheckedInを契機としてHistory Domainが観劇履歴を生成する。
- Reservation自身はHistoryを管理しない。
- StageArtユーザーとして登録されたBookerは自身の観劇履歴を確認できる。
- 一般観客にInternal Portalへの参加を要求しない。
- Reservationは原則として物理削除しない。
- Reservationには監査情報を保持する。
- Reservationの個人情報は適切な権限のもとでのみ閲覧できる。
- SeatおよびReservationSeatはVersion 1.0では実装しない。

---

# Domain Events

Reservationに関する主なDomain Event：

- ReservationCreated
- ReservationUpdated
- ReservationCancelled
- ReservationCheckedIn
- ReservationMarkedNoShow

ReservationCheckedInを契機として、
History DomainがAudience Historyを生成する。

---

# Design Decisions

Reservationは、
Performanceへの予約というFactを表す。

ReservationのAggregate Rootとして、
予約内容と予約状態の整合性を管理する。

Companionは不要なため、
同行者を個別Entityとして管理しない。

複数名の予約はGuestCountで表現する。

SeatおよびReservationSeatは、
Seats機能の将来実装までReservation Domainへ導入しない。

Ticket Type / PriceはProductionごとのMasterを参照する。

Reservationは、
BookerとCreatedByを分離する。

これにより、
観客本人による予約だけでなく、
劇団スタッフなどによる代理予約も表現できる。

Check InはReservation単位で行う。

予約しただけでは観劇履歴を生成せず、
ReservationCheckedInを契機としてHistoryを生成する。

---

# Future

将来的に以下へ対応できる構造とする。

- Seat
- Reservation Seat
- 座席指定
- 連席管理
- 同行者情報
- 複数Ticket Type
- 決済連携
- 払い戻し
- 振替公演
- 自動No Show処理
- 予約変更履歴
- 観客向けマイページ拡張

ただし、
将来機能を追加する場合も、
Reservationの責務を不必要に拡張しない。

---

# Design Principles

- ReservationはPerformanceへの予約Factである。
- ReservationはPerformanceに所属する。
- ReservationはAggregate Rootとして予約状態を管理する。
- Bookerは予約者を表す。
- CreatedByは予約作成者を表す。
- BookerとCreatedByを分離する。
- GuestCountで予約人数を管理する。
- CompanionをVersion 1.0では管理しない。
- Ticket Type / PriceはProduction側を正本とする。
- ReservationはQR Ticket生成の基礎となる。
- Check InはReservation単位で行う。
- QR Check InとManual Check Inを同一のReservation Check Inとして扱う。
- Check In時には対象Performanceを検証する。
- ReservationCheckedInを契機としてHistoryを生成する。
- Reservation自身はHistoryを管理しない。
- StageArtユーザーとして登録された観客は観劇履歴を確認できる。
- 一般観客にInternal Portalへの参加を要求しない。
- SeatはVersion 1.0では実装しない。
- ReservationSeatはVersion 1.0では実装しない。
- Reservationは過去の予約Factを保持する。
- 個人情報を適切な権限で保護する。
- Blueprintを唯一の設計基準とする。
