# StageArt Blueprint

# Domain Model : Journal Entry

Version : 1.0

---

# Purpose

Journal Entryは、
StageArtにおける会計上の取引を
仕訳として記録するDomainである。

Journal Entryは、
会計上の一つの取引を表す。

Journal Entryは、
複数のJournal Entry Lineから構成される。

---

# Concept

基本構造：

Accounting Event
  ↓
Journal Entry
  ↓
Journal Entry Line
  ├── Debit
  └── Credit

Journal Entryは、
会計上の取引を記録するFactである。

---

# Accounting Event

Journal Entryは、
Domain Eventを契機として生成できる。

例えば、

CheckInCompleted
  ↓
Ticket Revenue Recognition
  ↓
Journal Entry

という流れを取る。

Journal Entry自身が
Business Eventを発生させるのではなく、
Business Eventを会計上の取引へ変換する。

---

# Source Event

Journal Entryには、
どのBusiness Eventから生成されたかを
追跡できる情報を持つ。

例えば、

Source Event
  = CheckInCompleted

など。

これにより、

「この仕訳はどの業務処理から
発生したのか」

を追跡できる。

---

# Source Reference

Journal Entryには、
Source Eventに関連する
Domain Objectを参照できる。

例えば、

- Check In
- Reservation
- Production
- Project

など。

これにより、
会計から業務Factまで
追跡できる。

---

# Production Relationship

Journal Entryは、
可能な限りProductionとの関連を
保持できる。

例えば、

Production
  ↓
Check In
  ↓
Ticket Revenue
  ↓
Journal Entry

という関係を追跡できる。

Production単位で
収益・費用を集計するために利用する。

---

# Project Relationship

Journal Entryは、
Projectとの関連を保持できる。

基本構造：

Organization
  ↓
Project
  ↓
Production
  ↓
Journal Entry

Project単位で
複数Productionの会計実績を
集計できる。

---

# Organization Relationship

Journal Entryは、
必ず一つのOrganizationに所属する。

他Organizationの会計情報へ
アクセスしてはならない。

基本構造：

Organization
  ↓
Journal Entry

Multi Tenant原則に従う。

---

# Journal Entry Identity

Journal Entryは、
JournalEntryIdによって
一意に識別する。

JournalEntryIdは変更しない。

---

# Journal Date

Journal Entryには、
会計上の取引日を持つ。

Journal Dateは、
単純なCreated Atとは異なる。

例えば、

Check In
  = 2026-08-20

の場合、
Ticket RevenueのJournal Dateを
2026-08-20とする。

具体的な会計期間ルールは、
Accounting Domainで定義する。

---

# Description

Journal Entryには、
取引内容を説明するDescriptionを持つ。

例えば、

「2026/08/20 14:00公演 チケット売上」

など。

Descriptionは、
会計上の確認や検索に利用する。

---

# Journal Entry Lines

Journal Entryは、
複数のJournal Entry Lineから構成される。

基本構造：

Journal Entry
  ↓
Journal Entry Line
  ├── Line 1
  ├── Line 2
  └── ...

Journal Entry Lineは、
費目・勘定科目ごとの
一つの会計明細を表す。

---

# Line by Account

費目ごとに
Journal Entry Lineを分ける。

例えば、

チケット売上 10,000円

の場合、

Journal Entry
  ↓
Line 1
  現金等
  10,000円

Line 2
  チケット売上
  10,000円

という構造にする。

---

# Debit Credit Flag

貸借は、
Journal Entry Lineに
Debit / Credit Flagを持たせて管理する。

例えば、

Line 1
  Debit / 10,000円

Line 2
  Credit / 10,000円

とする。

DebitとCreditを
別々のEntityとして管理しない。

---

# Amount

Journal Entry Lineは、
金額を保持する。

Amountは正の金額として管理し、
貸借方向はDebit / Credit Flagで表現する。

例えば、

Debit
  Amount = 3,000

Credit
  Amount = 3,000

とする。

マイナス金額によって
貸借方向を表現しない。

---

# Currency

Journal Entryには、
Currencyを設定できる。

Version 1.0では、
Organizationの会計通貨を
基本とする。

将来的に複数通貨へ対応できる構造を持つ。

---

# Account

Journal Entry Lineは、
Accountを参照する。

Accountは、
会計上の勘定科目を表す。

例えば、

- 現金
- 普通預金
- 売掛金
- チケット売上
- 会場費
- 人件費
- 交通費
- 広告宣伝費

など。

Account自体は、
Accounting Account Domainで管理する。

Journal Entryが
勘定科目名称を直接管理するものではない。

---

# Account Snapshot

過去の仕訳を正しく表示するため、
必要に応じてJournal Entry Lineに
Account情報のSnapshotを保持できる。

例えば、

Account
  ID = 1001
  Name = チケット売上

という状態で仕訳を作成した後、

Account Name
  = 公演売上

へ変更された場合でも、
過去仕訳の表示・監査に必要な情報を
保持できる構造とする。

具体的なSnapshot方式は
Data Modelで定義する。

---

# Balance

Journal Entryは、
借方合計と貸方合計が
一致しなければならない。

基本ルール：

Total Debit
  =
Total Credit

この条件を満たさないJournal Entryを
確定状態にしてはならない。

---

# Status

Journal Entryは、
以下の状態を持つ。

- DRAFT
- POSTED
- REVERSED

---

# Draft

DRAFTは、
仕訳が作成されたが
まだ確定していない状態。

DRAFT状態では、
内容を確認・修正できる。

---

# Posted

POSTEDは、
会計上の仕訳として確定した状態。

POSTEDとなったJournal Entryは、
通常の編集を許可しない。

---

# Reversed

REVERSEDは、
既存の仕訳を取消した状態。

過去のJournal Entryを
物理削除するのではなく、
取消仕訳によって修正する。

---

# Posting

Journal EntryをPOSTEDにする際には、
以下を検証する。

- Organizationが存在する
- Journal Dateが有効
- Journal Entry Lineが存在する
- Debitが存在する
- Creditが存在する
- Total Debit = Total Credit
- Accountが有効
- Accounting Periodが有効

すべての条件を満たした場合、
Journal EntryをPOSTEDにできる。

---

# Immutability

POSTEDとなったJournal Entryは、
原則として変更しない。

誤りがある場合は、

Journal Entry
  ↓
Reversal Journal Entry
  ↓
Correct Journal Entry

という形で修正する。

これにより、
会計履歴を破壊しない。

---

# Reversal

Journal Entryを取り消す場合、
元のJournal Entryを削除・変更しない。

元の仕訳と逆方向の
Reversal Journal Entryを生成する。

例えば、

Original：

Debit
  現金 3,000

Credit
  チケット売上 3,000

Reversal：

Debit
  チケット売上 3,000

Credit
  現金 3,000

とする。

---

# Reversal Relationship

Reversal Journal Entryは、
元Journal Entryを参照する。

基本構造：

Original Journal Entry
  ↓
Reversal Journal Entry

どの仕訳を取り消したのか
追跡できる。

---

# Source Idempotency

同一Business Eventから
同一の会計取引を
二重生成してはならない。

例えば、

CheckInCompleted
  ↓
Journal Entry

という処理を複数回実行しても、
同一売上を二重計上しない。

Source Event IDなどを利用して、
Idempotencyを確保する。

---

# Ticket Revenue

Ticket Revenueは、
CheckInCompletedを契機として
Journal Entryへ変換される。

基本Flow：

Reservation
  ↓
Check In
  ↓
CheckInCompleted
  ↓
Ticket Revenue Recognition
  ↓
Journal Entry
  ↓
Journal Entry Line

---

# Ticket Revenue Amount

Ticket Revenueの金額は、
Reservationの取引価格を利用する。

Ticket Masterに登録されている
現在のPriceを再参照して
過去のReservationの金額を
再計算しない。

---

# Payment Account

Ticket Revenueの借方側は、
実際の決済方法に応じて
適切なAccountを使用する。

例えば、

現金
  → 現金Account

銀行振込
  → 普通預金Account

カード決済
  → 売掛金等のAccount

など。

具体的な決済手段とAccountの対応は、
Payment / Accounting Domainで定義する。

---

# Revenue Account

Ticket Revenueの貸方側は、
Ticket売上に対応するRevenue Accountを
使用する。

例えば、

チケット売上

など。

Revenue Accountの具体的な構成は、
Accounting Account Domainで定義する。

---

# Budget Relationship

Journal Entryは、
Productionの実績として
Budget / Actualへ連携できる。

基本構造：

Budget
  ↓
Production
  ↓
Journal Entry
  ↓
Actual

Budget自身は
将来の計画値を表す。

Journal Entryは
実際に発生した会計Factを表す。

---

# Actual

Actualは、
Journal Entryから集計する。

例えば、

Budget

チケット売上
  500,000円

Actual

チケット売上
  430,000円

Variance

  -70,000円

という比較ができる。

Actual金額を
別の正本データとして
二重管理しない。

Journal Entryを正本とし、
Actualは集計によって算出する。

---

# Variance

予算と実績の差異は、
BudgetとJournal Entryから算出する。

基本構造：

Budget
  ↓
Planned Amount

Journal Entry
  ↓
Actual Amount

Planned Amount
  -
Actual Amount
  ↓
Variance

Varianceを
独立した会計Factとして
重複保存しない。

---

# Production Accounting

Production単位で
Journal Entryを集計できる。

例えば、

Production A

Ticket Revenue
  430,000円

Venue Cost
  180,000円

Staff Cost
  100,000円

など。

これにより、
公演単位の収支を確認できる。

---

# Project Accounting

Project単位で、
複数ProductionのJournal Entryを
集計できる。

例えば、

Project
  ↓
Production A
  ↓
Production B
  ↓
Production C

それぞれのJournal Entryを集計して、
Project全体の実績を確認できる。

---

# Organization Accounting

Organization単位で、
すべてのJournal Entryを
集計できる。

ただし、
Project / Productionに紐付く
Journal Entryについては、
それぞれの単位でも集計可能とする。

---

# Accounting Period

Journal Entryは、
Accounting Periodに関連付けることができる。

Accounting Periodが締められた場合、
その期間のPOSTED Journal Entryを
変更できない。

具体的な会計期間の管理方法は、
Accounting Period Domainで定義する。

---

# Audit

Journal Entryには、
作成・確定・取消を追跡できるよう
監査情報を保持する。

基本情報：

- Created By
- Created At
- Updated By
- Updated At
- Posted By
- Posted At

---

# Authorization

Journal Entryの作成・修正・Posting・Reversalは、
会計管理権限を持つPersonが行う。

Organization Administratorは、
自身のOrganizationについて
全権限を持つ。

稽古管理権限だけでは、
Journal Entryを管理できない。

---

# Privacy

会計情報は、
Organization内部の権限を持つPersonのみが
参照できる。

他OrganizationのJournal Entryへ
アクセスしてはならない。

---

# Business Rules

- Journal Entryは会計上の取引を表すFactである。
- Journal EntryはOrganizationに所属する。
- Journal Entryは必要に応じてProject / Productionに関連付ける。
- Journal Entryは複数のJournal Entry Lineから構成される。
- Journal Entry Lineは費目・勘定科目ごとに分ける。
- 貸借はDebit / Credit Flagで管理する。
- Amountは正の金額として管理する。
- 貸借方向をマイナス金額で表現しない。
- Total Debit = Total CreditでなければPOSTEDにできない。
- Journal EntryはDRAFT / POSTED / REVERSEDの状態を持つ。
- POSTEDのJournal Entryは原則として変更しない。
- POSTEDのJournal Entryを物理削除しない。
- 取消はReversal Journal Entryで行う。
- Reversal Journal Entryは元Journal Entryを参照する。
- 同一Business Eventから会計取引を二重生成しない。
- Source Event IDなどによってIdempotencyを確保する。
- CheckInCompletedをTicket Revenue Recognitionの契機として利用できる。
- Ticket Revenueの金額はReservationの取引価格を利用する。
- Ticketの現在価格から過去の売上金額を再計算しない。
- Payment Accountは決済手段に応じてAccounting Domainで決定する。
- Revenue AccountはAccounting Domainで管理する。
- Journal Entry自身が勘定科目マスタを管理しない。
- Budgetは計画値を表す。
- Journal Entryは実績の正本である。
- ActualはJournal Entryから集計する。
- Actualを独立した正本として二重管理しない。
- VarianceはBudgetとActualから算出する。
- Production単位でJournal Entryを集計できる。
- Project単位でJournal Entryを集計できる。
- Organization単位でJournal Entryを集計できる。
- Accounting Periodの締め後は該当期間の仕訳を変更しない。
- 会計管理権限を持つPersonのみがJournal Entryを管理できる。
- 他OrganizationのJournal Entryへアクセスできない。

---

# Domain Events

Journal Entryに関する主なDomain Event：

- JournalEntryCreated
- JournalEntryPosted
- JournalEntryReversed

JournalEntryPostedを契機として、
Actual集計や予実分析へ
反映できる。

JournalEntryReversedを契機として、
Actual集計を再計算できる。

---

# Design Decisions

Journal Entryは、
StageArtにおける会計実績の正本とする。

予算と実績を別々に手入力するのではなく、

Budget
  ↓
Plan

Journal Entry
  ↓
Actual

という責務分離を行う。

ActualはJournal Entryから
集計によって算出する。

これにより、

「公演開始前に計画した予算」

と

「実際に発生した会計取引」

を同じProduction単位で比較できる。

また、
CheckInCompletedをチケット売上認識の起点とすることで、

Reservation
  ↓
Check In
  ↓
Revenue
  ↓
Journal Entry

という実際の公演活動に沿った
会計実績を構築できる。

---

# Future

将来的に以下へ対応できる。

- 自動仕訳
- 手動仕訳
- 決済手段別会計
- 税区分
- 消費税
- 会計期間
- 月次締め
- 年次締め
- 補助科目
- 部門
- プロジェクト別会計
- Production別損益
- Project別損益
- Organization別損益
- Budget / Actual / Variance分析
- 会計帳票
- CSV出力
- 外部会計ソフト連携

ただし、
将来機能を追加する場合も、
Journal Entryを会計実績の正本として扱う。

---

# Design Principles

- Journal Entryは会計取引Factである。
- Journal Entryは会計実績の正本である。
- Journal EntryはOrganizationに所属する。
- Journal Entryは必要に応じてProject / Productionに関連付ける。
- Journal Entry Lineは費目ごとに分ける。
- 貸借はDebit / Credit Flagで管理する。
- Amountは正の金額で管理する。
- Total DebitとTotal Creditを一致させる。
- POSTED仕訳を直接変更しない。
- 取消はReversal Journal Entryで行う。
- 会計Factを物理削除しない。
- Source Eventとの関連を保持する。
- 同一Eventから二重仕訳を生成しない。
- CheckInCompletedをTicket Revenue Recognitionの起点とする。
- Ticket RevenueはReservationの取引価格を利用する。
- Ticketの現在価格を過去取引へ遡及させない。
- Budgetは計画値である。
- Journal Entryは実績値の正本である。
- Actualを二重管理しない。
- Varianceを二重管理しない。
- Production / Project / Organization単位で実績を集計できる。
- 会計DomainとCheck In Domainの責務を分離する。
- 会計管理権限を適切に分離する。
- Blueprintを唯一の設計基準とする。
