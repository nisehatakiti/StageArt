# StageArt Blueprint

# Domain Model : QR Ticket

Version : 2.0

---

# Purpose

QR Ticketは、
Issued Ticketから生成される
電子チケットArtifactである。

QR Ticketは予約そのものを表すFactではない。

Reservationが予約に関する正本であり、
Issued Ticketが実際に発行されたチケットを表す。

QR Ticketは、
Issued Ticketを識別し、
公演当日の受付を簡単に行うために生成される。

---

# Concept

基本構造：

Production
  ↓
Performance
  ↓
Reservation
  ↓
Issued Ticket
  ↓
QR Ticket
  ↓
Check In
  ↓
CheckInCompleted

QR Ticketは、
Issued Ticketから生成されるArtifactとして扱う。

---

# Relationship

QR Ticketは一つのIssued Ticketに関連付けられる。

基本構造：

Reservation
  ↓
Issued Ticket
  ↓
QR Ticket

一つのIssued Ticketに対して、
基本的に一つの有効なQR Ticketを発行する。

QR Ticketを再発行した場合でも、
ReservationやIssued Ticketそのものを新しく作成しない。

---

# Reservation as Source of Truth

Reservationは、
予約に関する唯一の正本である。

Issued Ticketは、
Reservationに基づいて発行された
実際のチケットを表す。

QR Ticketは、
予約情報やチケット情報そのものを
正本として管理しない。

例えば、

- Booker
- Performance
- Ticket
- Guest Count
- Reservation Status

などの正本は、
ReservationまたはTicket側に存在する。

QR Ticketは、
Issued Ticketを経由して
対象Reservationを特定するための
識別情報を保持する。

---

# Issued Ticket

Issued Ticketは、
Reservationに基づいて発行された
実際のチケットを表す。

基本構造：

Reservation
  ↓
Issued Ticket

Issued Ticketは、
受付に利用できるチケットとして扱う。

QR Ticketは、
Issued Ticketをデジタルに提示するための
Artifactである。

---

# QR Code

QR Codeは、
受付時にIssued Ticketおよび
関連するReservationを識別するために利用する。

QR Codeそのものを
ReservationのPrimary Keyとして扱わない。

QR Codeから、
StageArt上のIssued Ticketおよび
関連Reservationを特定できる。

---

# QR Payload

QR Codeには、
必要最小限の識別情報を格納する。

QR Codeへ、
不要な個人情報を直接格納しない。

例えば、

- Issued Ticket ID
- Ticket Token
- Verification Token

などを利用できる。

具体的なPayload形式は、
InfrastructureおよびSecurity Designで定義する。

---

# Token

QR Ticketには、
必要に応じて一意のTicket Tokenを持たせる。

Tokenは、
QR CodeからIssued Ticketを安全に識別するために利用できる。

Tokenへ
個人情報を直接埋め込まない。

---

# Security

QR Codeを第三者に撮影・転送される可能性を考慮する。

そのため、
QR Payloadに個人情報を直接含めない。

受付時には、
QR Codeから取得した識別情報を利用して
StageArt側のIssued TicketおよびReservationを検証する。

---

# Verification

QR Ticketを読み取った場合、
StageArtは以下を検証する。

- QR Ticketが有効であること
- Issued Ticketが存在すること
- Reservationが存在すること
- ReservationがCANCELLEDではないこと
- 対象Performanceが一致すること
- ReservationがまだCheck Inされていないこと

すべての条件を満たした場合、
Check Inを実行できる。

---

# Performance Validation

QR TicketによるCheck Inでは、
受付中のPerformanceと
ReservationのPerformanceが一致することを確認する。

例えば、

受付中：

10月12日 14:00

QR Ticket：

10月12日 18:00

の場合、
Check Inを許可しない。

QR Ticketが有効であっても、
異なるPerformanceでは利用できない。

---

# Check In

QR Ticket自体が
Check In済み状態を正本として管理するわけではない。

Check Inの正本はReservationである。

基本Flow：

QR Ticket Scan
  ↓
Issued Ticket取得
  ↓
Reservation取得
  ↓
Reservation Validation
  ↓
Performance Validation
  ↓
Reservation Check In
  ↓
Reservation Status
  = CHECKED_IN
  ↓
CheckInCompleted

QR Ticketは、
Check Inを実行するための識別・認証手段である。

---

# Duplicate Scan

同じQR Ticketを
複数回読み取った場合でも、
同一Reservationを二重にCheck Inしない。

すでにCHECKED_INの場合は、

「受付済み」

として扱う。

QR Ticketを再発行することで、
Check In状態をリセットしてはならない。

また、
同一CheckInCompletedから
チケット売上の会計仕訳を
二重生成してはならない。

---

# Cancelled Reservation

CANCELLED状態のReservationに
紐付いたIssued TicketおよびQR Ticketは
Check Inに利用できない。

QR Ticketを読み取った場合、

「この予約はキャンセルされています」

などの状態を表示できる。

QR Ticketを無効化しても、
Reservationそのものを削除しない。

---

# Expired Ticket

公演終了後のQR Ticketは、
新規Check Inには利用できない。

ただし、
QR Ticket自体を削除する必要はない。

過去のReservation、
Issued Ticket、
受付履歴を確認するために保持する。

---

# Reissue

QR Ticketを再発行できる構造を持つ。

例えば、

- メールを紛失した
- QR Ticketを表示できなくなった
- Reservation情報を再送する

など。

再発行しても、
Reservationそのものは新しく作成しない。

Issued Ticketについても、
再発行だけを理由として
新しいIssued Ticketを作成しない。

---

# Reissue and Check In

QR Ticketを再発行した場合でも、
ReservationのCheck In状態を変更しない。

例えば、

旧QR Ticket
  ↓
無効

新QR Ticket
  ↓
発行

という場合でも、
Reservationは同一のままである。

また、

Reservation
  = CHECKED_IN

の場合に再発行しても、
新しいQR Ticketによって
再度Check Inできるようにはしない。

---

# Revocation

QR Ticketを無効化できる構造を持つ。

例えば、

- 再発行
- 不正利用の疑い
- Reservationキャンセル

など。

QR Ticketを無効化しても、
Issued TicketやReservationそのものを
削除しない。

---

# Ticket Version

QR Ticketを再発行した場合、
発行履歴を管理できる構造を持つ。

例えば、

QR Ticket Version 1
  ↓
QR Ticket Version 2

など。

どのQR Ticketが現在有効かを
管理できる。

Version管理を行う場合でも、
ReservationおよびIssued Ticketの
Identityは変更しない。

---

# Guest Count

QR Ticketは、
Issued Ticketおよび関連するReservation全体を識別する。

ReservationのGuest Countが複数であっても、
Version 1.0では
同行者ごとに別QR Ticketを発行しない。

例えば、

Guest Count
  = 3

の場合でも、

Reservation
  = 1件

Issued Ticket
  = 1件

QR Ticket
  = 1枚

として扱う。

---

# Companion

Companionは
QR Ticket Domainでは管理しない。

同行者を個別Personとして
QR Ticketへ紐付けない。

複数名の来場については、
ReservationのGuest Countを利用する。

---

# Display

QR Ticketには、
観客が受付時に確認できる情報を表示できる。

例えば、

- Production Name
- Performance Date
- Performance Start Time
- Ticket Type
- Guest Count
- Reservation Number

など。

これらはReservation、
Ticket、
Performanceなどの
正本情報から表示する。

QR Ticket内に、
これらの情報を正本として複製しない。

---

# Reservation Number

QR Ticketには、
Reservation Numberを表示できる。

QR Codeが利用できない場合でも、
Reservation Numberを利用して
受付を行える。

Reservation Numberは、
Reservation Domainが正本として管理する。

---

# Manual Check In

QR Ticketを利用できない場合、
受付担当者は以下の方法で
Reservationを検索できる。

- Reservation Number
- Booker Name
- その他予約検索条件

Manual Check Inの場合も、
最終的にCheck InされるFactは
Reservationである。

---

# QR Ticket and Manual Check In

QR Check InとManual Check Inは、
異なる予約Factを生成しない。

QR Check In：

QR Ticket
  ↓
Issued Ticket
  ↓
Reservation
  ↓
Check In

Manual Check In：

Reservation
  ↓
Check In

どちらも、
同じReservation Statusを更新する。

どちらの場合も、
Check In完了時に
CheckInCompletedを発行する。

---

# Audience History

QR Ticketを発行しただけでは、
観劇履歴を生成しない。

実際にCheck Inされた場合に、
CheckInCompletedを契機として
History Domainが観劇履歴を生成する。

基本Flow：

QR Ticket
  ↓
Issued Ticket
  ↓
Reservation
  ↓
Check In
  ↓
CheckInCompleted
  ↓
History
  ↓
Audience History

---

# Accounting

QR Ticketを発行しただけでは、
チケット売上を会計へ計上しない。

実際にCheck Inされた場合に、
CheckInCompletedを契機として
Accounting Domainへ連携する。

基本Flow：

QR Ticket
  ↓
Issued Ticket
  ↓
Reservation
  ↓
Check In
  ↓
CheckInCompleted
  ↓
Ticket Revenue
  ↓
Journal Entry

QR Ticket Domain自身は、
Journal Entryを管理しない。

Ticket Revenueおよび
Journal Entryの詳細は、
Accounting Domainで管理する。

---

# Revenue Amount

Ticket Revenueに利用する金額は、
Reservationに保持された
予約時点の取引価格を利用する。

Ticketの現在価格を参照して、
過去のReservationの売上金額を
再計算してはならない。

例えば、

Ticket
  一般 3,000円

予約後に、

Ticket
  一般 3,500円

へ変更された場合でも、

Check Inされた既存Reservationの売上
  = 3,000円

とする。

QR Ticketは、
この金額を独自に保持するのではなく、
Reservationの取引Factを参照する。

---

# Public Access

QR Ticketは、
一般公開ページへ公開しない。

QR Ticketは、
Reservationを行った観客本人および
必要な受付担当者が利用する。

---

# Mobile

QR Ticketは、
スマートフォンで表示することを前提とする。

観客は、
スマートフォンの画面から
QR Codeを提示できる。

受付担当者は、
スマートフォンなどのカメラから
QR Codeを読み取れる。

---

# Artifact

QR TicketはArtifactである。

Fact：

- Reservation
- Issued Ticket
- Check In

Artifact：

- QR Ticket
- QR Code

ArtifactはFactから生成される。

QR Ticketを削除・再生成しても、
ReservationおよびIssued TicketのFactは変更しない。

---

# Audit

QR Ticketには、
発行・再発行・無効化を追跡できるよう
監査情報を保持する。

基本情報：

- Created By
- Created At
- Updated By
- Updated At

---

# Business Rules

- QR TicketはIssued Ticketから生成されるArtifactである。
- Reservationが予約Factの正本である。
- Issued TicketはReservationに基づいて発行される。
- QR Ticketは予約Factそのものではない。
- QR Ticketは一つのIssued Ticketに関連付ける。
- QR CodeからIssued TicketおよびReservationを識別できる。
- QR Codeへ個人情報を直接格納しない。
- QR Payloadは必要最小限とする。
- QR TicketによるCheck InではIssued TicketとReservationを検証する。
- 対象Performanceが一致しない場合はCheck Inできない。
- CANCELLEDのReservationはCheck Inできない。
- CHECKED_INのReservationを二重Check Inしない。
- QR Ticketの再発行によってCheck In状態をリセットしない。
- Guest Countが複数でもVersion 1.0ではQR TicketをReservation単位で発行する。
- CompanionをQR Ticketへ紐付けない。
- QR Ticketを利用できない場合はReservation Number等によるManual Check Inを許可する。
- QR Check InとManual Check Inは同じReservation Check Inとして扱う。
- どちらのCheck In方法でもCheckInCompletedを発行する。
- QR Ticket発行だけではHistoryを生成しない。
- CheckInCompletedを契機としてHistory DomainがAudience Historyを生成する。
- QR Ticket発行だけではTicket Revenueを会計計上しない。
- CheckInCompletedを契機としてAccounting DomainへTicket Revenueを連携する。
- Ticket Revenueの金額はReservationのPrice Snapshotを基礎とする。
- QR Ticket DomainはJournal Entryを管理しない。
- QR Ticketはスマートフォン表示を前提とする。
- QR Ticketは一般公開しない。
- QR Ticketは原則として物理削除しない。
- QR Ticketには監査情報を保持する。
- QR Ticketの無効化はReservationやIssued Ticketの削除を意味しない。

---

# Domain Events

QR Ticketに関する主なDomain Event：

- QRTicketIssued
- QRTicketReissued
- QRTicketRevoked

Check Inに関するEventは、
CheckIn / Reservation Domainで管理する。

Check In完了時に発生するEvent：

- CheckInCompleted

CheckInCompletedは、
History Domainおよび
Accounting Domainが利用できる。

QR Ticketの発行・再発行・無効化は、
Reservationの状態を直接変更しない。

---

# Design Decisions

QR Ticketは、
Issued Ticketから生成されるArtifactとして扱う。

Reservationは、
予約に関する正本である。

Issued Ticketは、
Reservationに基づいて発行された
実際のチケットを表す。

QR Ticketは、
Issued Ticketをデジタルに提示するための
Artifactとして扱う。

この構造により、

- QR Ticketの再発行
- QR Ticketの無効化
- QR Ticketの紛失
- QR Ticketの再送

などが発生しても、
ReservationおよびIssued TicketのFactを
変更せずに対応できる。

Check Inの正本はReservationであり、
QR TicketはCheck Inを行うための
識別・認証手段として扱う。

QR Check InとManual Check Inを
同じReservation Check Inとして扱うことで、
受付方法によってDomain Factが分裂することを防ぐ。

Check Inが完了すると、
CheckInCompletedが発生する。

CheckInCompletedを契機として、

- Audience History
- Ticket Revenue

をそれぞれのDomainへ連携する。

QR Ticket Domainは、
HistoryやJournal Entryを直接生成・更新しない。

---

# Future

将来的に以下へ対応できる。

- Apple Wallet
- Google Wallet
- PDF Ticket
- メールチケット
- チケット再送
- QR Token Rotation
- 不正利用検知
- 複数端末対応
- 入場時刻記録
- 再入場管理
- 同行者単位のQR Ticket
- 座席指定Ticket

ただし、
将来機能を追加する場合も、
Reservationを予約Factの正本とする。

Issued Ticketを、
実際に発行されたチケットの正本として扱う。

QR Ticketは、
引き続き受付用Artifactとして扱う。

---

# Design Principles

- QR TicketはArtifactである。
- Reservationが予約Factの正本である。
- Issued Ticketは発行済みチケットを表す。
- QR TicketはIssued Ticketに関連付ける。
- QR TicketからIssued TicketおよびReservationを識別する。
- QR Codeへ個人情報を直接格納しない。
- QR TicketとReservationを分離する。
- QR Check InとManual Check Inを同じReservation Check Inとして扱う。
- Check Inの正本はReservationである。
- CheckInCompletedはCheck In完了を表すBusiness Eventである。
- QR Ticketの再発行でReservationの状態を変更しない。
- QR Ticketの再発行でIssued Ticketを新規作成しない。
- QR Ticket発行だけでは観劇履歴を生成しない。
- CheckInCompletedからAudience Historyを生成する。
- QR Ticket発行だけではTicket Revenueを会計計上しない。
- CheckInCompletedからTicket RevenueをAccountingへ連携する。
- Ticket RevenueはReservationのPrice Snapshotを基礎とする。
- Guest CountとQR Ticketを1対1で対応させない。
- CompanionをQR Ticket Domainへ導入しない。
- SeatはVersion 1.0では実装しない。
- QR Ticketはスマートフォン表示を前提とする。
- QR Ticketは一般公開しない。
- Blueprintを唯一の設計基準とする。