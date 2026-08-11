# StageArt Blueprint

# Domain Model : Budget

Version : 1.0

---

# Purpose

Budgetは、
Project / Productionにおける
将来の収支計画を管理するDomainである。

Budgetは、

「この公演をどのような前提で実施した場合、
どの程度の収入・支出になるか」

という計画を表す。

Budgetは実績ではない。

実績はJournal Entryを正本とし、
BudgetとJournal Entryを比較することで
予実管理を行う。

---

# Concept

基本構造：

Project
  ↓
Production
  ↓
Budget
  ↓
Budget Line
  ↓
Account

実績側：

Production
  ↓
Journal Entry
  ↓
Journal Entry Line
  ↓
Account

比較：

Budget
  ↓
Planned Amount

Journal Entry
  ↓
Actual Amount

Planned Amount
  ↓
Actual Amount
  ↓
Variance

---

# Ownership

Budgetは、
ProjectまたはProductionに関連付ける。

公演の予算を管理する場合は、
Productionに紐付ける。

基本構造：

Organization
  ↓
Project
  ↓
Production
  ↓
Budget

BudgetはOrganizationをまたいで
共有しない。

---

# Budget Scenario

Budgetには、
簡単な名称を設定できる。

例えば、

- A会場案
- B会場案
- 一日2公演案
- 一日1公演案
- 小規模公演案
- 標準案

など。

この名称によって、
異なる前提の予算案を識別する。

---

# Multiple Budgets

一つのProductionに対して、
複数のBudgetを保持できる。

例えば、

Production A

  Budget
    A会場案

  Budget
    B会場案

  Budget
    一日2公演案

という構造。

複数のBudgetを比較し、
最終的に採用するBudgetを決定できる。

---

# Budget Status

Budgetには、
状態を持たせる。

基本的な状態：

- DRAFT
- ACTIVE
- ARCHIVED

---

# Draft

DRAFTは、
作成・検討中のBudgetを表す。

金額やBudget Lineを
自由に変更できる。

---

# Active

ACTIVEは、
現在採用しているBudgetを表す。

Productionにおける
正式な計画値として利用する。

---

# Archived

ARCHIVEDは、
採用されなくなったBudgetを表す。

過去の検討案として保持する。

物理削除は基本的に行わない。

---

# Active Budget

一つのProductionについて、
Version 1.0では
ACTIVEなBudgetを一つに限定する。

例えば、

A会場案
  = ACTIVE

B会場案
  = DRAFT

とする。

A会場案を不採用として
B会場案を採用する場合、

A会場案
  ↓
ARCHIVED

B会場案
  ↓
ACTIVE

とする。

---

# Budget Line

Budgetは、
複数のBudget Lineから構成される。

基本構造：

Budget
  ↓
Budget Line
  ├── Account
  ├── Amount
  └── その他計画情報

Budget Lineは、
一つのAccountに対する
計画金額を表す。

---

# Account Relationship

Budget Lineは、
Accountを参照する。

例えば、

Budget

  チケット売上
    500,000円

  会場費
    200,000円

  出演料
    100,000円

  広告宣伝費
    50,000円

という構造。

AccountはBudgetの正本ではなく、
Budget LineがAccountを参照する。

---

# Revenue Budget

Revenue Accountを利用することで、
収入計画を管理できる。

例えば、

チケット売上
  500,000円

グッズ売上
  100,000円

など。

---

# Expense Budget

Expense Accountを利用することで、
支出計画を管理できる。

例えば、

会場費
  200,000円

出演料
  100,000円

広告宣伝費
  50,000円

交通費
  30,000円

など。

---

# Amount

Budget Lineには、
計画金額を設定する。

Amountは、
正の金額として管理する。

収入・支出の区別は、
Account Typeによって判断する。

例えば、

REVENUE
  500,000円

EXPENSE
  200,000円

など。

---

# Currency

Budgetには、
Currencyを設定できる。

Version 1.0では、
Organizationの会計通貨を
基本とする。

将来的に複数通貨へ対応できる構造を持つ。

---

# Quantity and Unit Price

将来的に、
Budget Lineで数量と単価を管理できる。

例えば、

会場費

Quantity
  3日

Unit Price
  100,000円

Total
  300,000円

など。

ただし、
Version 1.0では
Amountだけで管理できる構造を基本とする。

---

# Notes

Budget Lineには、
計画の根拠や補足を記載できる。

例えば、

「劇場見積書より」

「前回公演実績より」

「出演者5名想定」

など。

---

# Assumption

Budgetには、
計画の前提条件を記載できる。

例えば、

- 会場3日間
- 本番2日
- 1日2公演
- 客席100席
- チケット平均単価3,000円

など。

Assumptionは、
Budget Scenarioを比較する際に利用する。

---

# Scenario Comparison

複数Budgetを比較できる。

例えば、

A会場案

売上
  800,000円

費用
  600,000円

想定利益
  200,000円

B会場案

売上
  1,000,000円

費用
  850,000円

想定利益
  150,000円

など。

この比較は、
Budgetから集計して表示する。

比較結果を
別の正本データとして保存しない。

---

# Planned Profit

Budgetから、
計画上の収支を算出できる。

基本式：

Planned Revenue
  -
Planned Expense
  =
Planned Profit

この値を
Budgetの独立した正本として
保存しない。

Budget Lineから算出する。

---

# Actual

Actualは、
Journal Entryから算出する。

基本構造：

Journal Entry
  ↓
Journal Entry Line
  ↓
Account
  ↓
Actual

BudgetがActualを直接保持しない。

---

# Budget and Actual

BudgetとActualは、
同じAccountを利用して比較する。

例えば、

Account
  = チケット売上

Budget
  = 500,000円

Actual
  = 430,000円

Variance
  = -70,000円

という比較ができる。

---

# Variance

Varianceは、
BudgetとActualの差を表す。

基本的な考え方：

Actual
  -
Budget
  =
Variance

ただし、
RevenueとExpenseでは
「良い差異」の意味が異なる。

UIでは、
Account Typeに応じて
適切に表示する。

Varianceそのものを
独立した正本データとして
保存しない。

---

# Budget Snapshot

ACTIVE Budgetを採用した後、
計画値を変更する必要が生じる場合がある。

Budgetを変更する場合、
既存の計画履歴を壊さない構造を持つ。

将来的には、

Budget Version
  1
  ↓
Budget Version
  2

のようなVersion管理へ拡張できる。

Version 1.0では、
Budget自体の変更履歴を
Audit情報で追跡する。

---

# Budget and Accounting

Budgetは、
会計上の取引を生成しない。

Budget：

計画

Journal Entry：

実績

という責務分離を行う。

---

# Ticket Revenue

チケット売上のBudgetは、
Ticketの販売条件を参考にして
計画できる。

例えば、

想定販売枚数
  150枚

想定平均単価
  3,000円

計画売上
  450,000円

など。

ただし、
Version 1.0では
BudgetがTicketを直接所有しない。

Budget LineのAccountを
チケット売上に設定して管理する。

---

# Ticket Actual

Ticket売上のActualは、
Check InCompletedから
生成されたJournal Entryを
Account単位で集計する。

基本Flow：

Ticket
  ↓
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
Actual

---

# Production Accounting

Production単位で、
BudgetとActualを比較できる。

例えば、

Production A

Budget

Revenue
  800,000円

Expense
  600,000円

Actual

Revenue
  760,000円

Expense
  620,000円

というように、
公演ごとの予実を確認できる。

---

# Project Accounting

Project単位で、
複数ProductionのBudget / Actualを
集計できる。

例えば、

Project
  ↓
Production A
  ↓
Production B
  ↓
Production C

それぞれのBudget / Actualを
Project単位で確認できる。

---

# Organization Accounting

Organization単位でも、
複数Project / Productionの
Budget / Actualを集計できる。

ただし、
Budgetは必ず所属する
Project / ProductionのScopeに従う。

---

# Budget Scope

BudgetのScopeを明確にする。

基本的には、

Production Budget

として公演単位で管理する。

将来的には、

- Project Budget
- Organization Budget

へ拡張できる構造を持つ。

---

# Authorization

Budgetの作成・変更・ACTIVE化は、
会計管理権限を持つPersonが行う。

Organization Administratorは、
自身のOrganizationについて
全権限を持つ。

稽古管理権限だけでは、
Budgetを変更できない。

---

# Audit

Budgetには、
変更を追跡できるよう監査情報を保持する。

基本情報：

- Created By
- Created At
- Updated By
- Updated At

ACTIVE化したPersonや
ACTIVE化した日時も
必要に応じて記録する。

---

# Deletion

Budgetは、
過去の計画情報として価値を持つ。

そのため、
採用されなかったBudgetであっても
物理削除を基本としない。

ARCHIVEDとして保持する。

---

# Business Rules

- Budgetは計画を表す。
- Budgetは実績ではない。
- BudgetはProjectまたはProductionに関連付ける。
- Productionの予算はProductionに関連付ける。
- 一つのProductionに複数Budgetを保持できる。
- Budgetには簡単な名称を設定できる。
- 「A会場案」「一日2公演案」などの名称を許可する。
- BudgetはDRAFT / ACTIVE / ARCHIVEDの状態を持つ。
- Version 1.0ではProductionごとにACTIVE Budgetを一つとする。
- Budgetは複数Budgetを比較できる。
- Budgetは複数のBudget Lineから構成される。
- Budget LineはAccountを参照する。
- AccountはBudgetの所有者ではない。
- Revenue Accountを収入計画に利用できる。
- Expense Accountを支出計画に利用できる。
- Budget LineのAmountは正の金額として管理する。
- 収入・支出の分類はAccount Typeによって判断する。
- Planned RevenueはBudget Lineから算出する。
- Planned ExpenseはBudget Lineから算出する。
- Planned ProfitはBudget Lineから算出する。
- Planned Profitを独立した正本として保存しない。
- ActualはJournal Entryから算出する。
- BudgetとActualは同じAccount分類で比較する。
- VarianceはBudgetとActualから算出する。
- Varianceを独立した正本として保存しない。
- Budgetは会計仕訳を生成しない。
- Ticket RevenueのActualはJournal Entryから取得する。
- CheckInCompletedをTicket Revenueの実績認識に利用する。
- BudgetはTicketを直接所有しない。
- BudgetはProduction単位で予実管理できる。
- Project単位でBudget / Actualを集計できる。
- Organization単位でBudget / Actualを集計できる。
- Budgetは適切な会計管理権限を持つPersonのみが管理できる。
- Organization Administratorは自身のOrganizationについて全権限を持つ。
- Budgetには監査情報を保持する。
- Budgetを物理削除しないことを基本とする。
- 採用されなくなったBudgetはARCHIVEDとして保持する。

---

# Domain Events

Budgetに関する主なDomain Event：

- BudgetCreated
- BudgetUpdated
- BudgetActivated
- BudgetArchived

BudgetActivatedを契機として、
Productionの正式な計画値として
利用できる。

---

# Design Decisions

Budgetは、
公演の「計画」を管理する。

会計の「実績」はJournal Entryを正本とする。

そのため、

Budget
  = Plan

Journal Entry
  = Actual

と明確に分離する。

Budgetには、
複数のScenarioを持たせる。

これにより、

「A会場案」

「B会場案」

「一日2公演案」

など、
公演計画を比較してから
採用案を決定できる。

採用されたBudgetだけを
ACTIVEとして扱う。

また、
Budget LineとJournal Entry Lineで
同じAccountを利用することで、

Plan
  ↓
Account
  ↓
Actual

という共通分類軸を構築する。

これにより、
Production単位で、

Budget
Actual
Variance

を比較できる。

---

# Future

将来的に以下へ対応できる。

- Budget Version
- Budget Template
- Budget Copy
- Budget Scenario比較
- 数量 × 単価
- Ticket販売数による自動計画
- Performance数による自動計画
- Seat数による自動計画
- 前回公演実績からのBudget作成
- Budget / Actual / Variance Dashboard
- Project Budget
- Organization Budget
- キャッシュフロー予測
- 損益予測
- 損益分岐点分析

ただし、
将来機能を追加する場合も、

Budget
  = Plan

Journal Entry
  = Actual

という責務分離を維持する。

---

# Design Principles

- Budgetは計画である。
- Journal Entryは実績である。
- BudgetとActualを分離する。
- BudgetはProduction単位で管理できる。
- 一つのProductionに複数のBudget Scenarioを持てる。
- Budgetには自由な識別名称を設定できる。
- ACTIVE BudgetはProductionごとに一つとする。
- Budget LineはAccountを参照する。
- BudgetとJournal Entryで同じAccount分類を利用する。
- ActualはJournal Entryから集計する。
- VarianceはBudgetとActualから算出する。
- Planned Profitを二重管理しない。
- Actualを二重管理しない。
- Varianceを二重管理しない。
- Budgetは会計Factを生成しない。
- CheckInCompletedによるTicket RevenueをActualとして集計できる。
- BudgetはTicketを所有しない。
- BudgetはProduction / Project / OrganizationのScopeに従う。
- 採用されないBudgetも履歴として保持する。
- 会計管理権限を適切に分離する。
- Blueprintを唯一の設計基準とする。
