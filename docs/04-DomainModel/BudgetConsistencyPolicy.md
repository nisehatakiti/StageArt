# StageArt Blueprint

# Domain Consistency Policy : Budget

Version : 1.0

---

# Purpose

Budget Domainについて、現在のCanonical Domain Modelおよび会計設計との整合性を定義する。

特に、Project BudgetとProduction Budget、複数Budget、Budget再利用、Account、Actual、帳票出力の関係を明確化する。

---

# Canonical Scope

BudgetはProjectまたはProductionの計画を表す。

現在のCanonical Scopeは以下の2種類とする。

- Project Budget
- Production Budget

Organization BudgetはVersion 1では実装対象としない。将来拡張余地のみ残す。

```text
Organization
  └─ Project
       ├─ Project Budget
       └─ Production
            └─ Production Budget
```

---

# Project Budget

Project Budgetは、Project全体の企画・活動計画を表す。

一つのProjectに複数Productionが存在する場合でも、Project全体を一つの予算Scopeとして管理できる。

例：

```text
Project「河童ホームラン2027」
  ├─ Production「東京公演」
  ├─ Production「大阪公演」
  └─ Project Budget
```

Project BudgetはProject Accountingにおける予実管理の計画側の正本となる。

---

# Production Budget

Production Budgetは個別Productionの計画を表す。

Production Budgetでは、個々の公演に必要な収入・支出を計画する。

Project BudgetとProduction Budgetは別のScopeとして扱う。

同一BudgetをProjectとProductionの双方へ所属させて二重利用することはしない。

---

# Multiple Budgets

Project、Productionともに複数Budgetを保持できる。

用途例：

- 初期予算
- 会場変更案
- 一日2公演案
- 保守案
- 修正版予算

Budgetには利用者が分かりやすい名称を設定する。

例：

`河童ホームラン2026予算Version1`

---

# Budget Reuse

Budgetは過去に作成したBudgetをコピーして再利用できる。

コピー対象には、少なくとも以下を含める。

- Budget Name
- Budget Line
- Account
- Amount
- Notes
- Assumption

コピー後は独立したBudgetとして扱い、コピー元の変更をコピー先へ反映しない。

Production間でBudgetをコピーする場合、コピー先ProductionのScopeへ所属させる。

Project Budgetも既存Project Budgetからコピーして再利用できる。

---

# Budget Status

Budgetは以下の状態を基本とする。

- DRAFT
- ACTIVE
- ARCHIVED

DRAFTは編集中、ACTIVEは現在採用している計画、ARCHIVEDは過去の案を表す。

ProjectおよびProductionの各ScopeでACTIVE Budgetを一つにすることを基本とする。

過去Budgetを削除して履歴を失わせず、ARCHIVEDとして保持する。

---

# Budget Name

Budgetには必ず利用者が識別しやすい名称を設定できる。

Budget NameはBudgetの識別・比較・帳票出力に利用する。

A4一枚の予算帳票を出力した場合、Budget Nameをタイトルとして表示できる。

例：

`河童ホームラン2026予算Version1`

---

# Budget Line

Budgetは複数のBudget Lineから構成される。

各Budget LineはAccountを参照し、計画金額を保持する。

```text
Budget
  └─ Budget Line
       ├─ Account
       ├─ Amount
       └─ Notes / Assumption
```

収入・支出の分類はAccount Typeによって判断する。

---

# Initial Budget Template

StageArtはBudget作成時の初期候補として、以下の費目を提示できる。

### 支出

- 会場費用
- 機器レンタル費用
- 外注費（スタッフ＋キャスト）
- 広告宣伝費用
- 通信費
- 車両交通費
- その他雑費

### 収入

- 集客予測 × チケット代
- 物販
- その他

これらは初期テンプレートであり、OrganizationのAccount Masterに応じて追加・変更できる。

---

# Account Relationship

Budget LineはOrganizationのAccount MasterにあるAccountを参照する。

ProjectやProductionが独自のAccount Masterを持つことはない。

BudgetとActualが同じAccount体系を利用することで、予実比較を可能にする。

---

# Planned Values

Planned Revenue、Planned Expense、Planned ProfitはBudget Lineから算出する。

これらを独立した会計Factとして保存しない。

```text
Budget Line
  ↓
Planned Revenue / Expense
  ↓
Planned Profit
```

---

# Actual Values

ActualはJournal Entryを正本として算出する。

BudgetはActualを保持しない。

```text
Journal Entry
  ↓
Journal Entry Line
  ↓
Account
  ↓
Actual
```

---

# Project Budget and Production Actual

Project BudgetはProject全体の計画を表し、Project ActualはProject Scopeに属するJournal Entryから算出する。

Production BudgetはProduction単位の計画を表し、Production ActualはProduction Scopeに属するJournal Entryから算出する。

Project BudgetとProduction Budgetの金額を自動合算してProject Budgetを生成する仕様にはしない。

必要に応じて、Project全体の計画と各Productionの計画を別々に確認できるようにする。

---

# Variance

VarianceはBudgetとActualの差として表示する。

```text
Variance = Actual - Budget
```

Varianceは計算値であり、独立した正本として保存しない。

RevenueとExpenseでは「有利／不利」の意味が異なるため、UIではAccount Typeを考慮して表示する。

---

# Ticket Revenue Budget

Ticket Revenueの予算はBudget Lineで計画する。

集客予測 × チケット代を基本的な収入計画の入力支援として利用できる。

TicketそのものをBudgetが所有することはない。

実際のTicket RevenueはCheckInCompletedを契機としてJournal Entryへ計上され、Actualとして集計される。

---

# Budget Assumption

Budgetには計画の前提条件を記録できる。

例：

- 会場3日間
- 本番2日
- 1日2公演
- 客席100席
- 集客予測150人
- チケット平均単価3,000円

AssumptionはBudget比較時に参照できる。

---

# Budget Comparison

同一Scopeに複数Budgetが存在する場合、それぞれを比較できる。

比較結果はBudgetから計算し、別の正本データとして保存しない。

---

# A4 Output

BudgetはA4一枚の紙で比較確認できる帳票として出力可能とする。

帳票には少なくとも以下を含められる。

- Budget Name
- Project / Production名
- 作成日
- 収入計
- 支出計
- 計画収支
- Account別のBudget Line
- 必要に応じてNotes / Assumption

目的は、複数の予算案を紙で並べて比較確認できることとする。

---

# Authorization

Budgetの作成・変更・ACTIVE化は会計管理権限に従う。

Production管理権限だけでは、会計管理権限がない限りBudgetを変更できない。

Organization Administratorは自身のOrganizationについて管理できる。

---

# Audit and History

Budgetの作成・更新・ACTIVE化・ARCHIVE化は監査情報で追跡できるようにする。

採用されなかったBudgetもARCHIVEDとして保持し、後から参照・比較できるようにする。

---

# Canonical Relationship Summary

```text
Organization
  └─ Project
       ├─ Project Budget
       │    └─ Budget Line → Account
       │
       └─ Production
            └─ Production Budget
                 └─ Budget Line → Account

Actual
  Journal Entry → Journal Entry Line → Account
```

Project Budget / Production Budgetは計画、Journal Entryは実績の正本である。

---

# Design Principle

Budgetは「毎回ゼロから作るもの」ではなく、過去の予算をコピーして短時間で新しい計画を作れることを重要なUX要件とする。

また、Project全体の予実と個別Productionの決算を同じAccount体系で比較できるようにする。
