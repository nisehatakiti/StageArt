# StageArt Blueprint

# Domain Consistency Policy : Account

Version : 1.0

---

# Purpose

Account Domainについて、現在のCanonical Accounting Model、Organization / Project / ProductionのScope、およびBudget / Actualとの整合性を定義する。

---

# Canonical Role

Accountは会計上の分類軸であり、会計Factそのものではない。

AccountはOrganizationに所属する会計マスタとして管理し、ProjectやProductionには所属させない。

```text
Organization
    └─ Account Master
          ├─ Asset
          ├─ Liability
          ├─ Equity
          ├─ Revenue
          └─ Expense
```

---

# Accounting Fact Boundary

会計Factの正本はJournal Entryである。

Accountは分類マスタであり、Actualを保持しない。

```text
Business Event
    ↓
Journal Entry
    ↓
Journal Entry Line
    ↓
Account
```

Production ActualおよびProject ActualはJournal EntryをAccount等で集計して算出する。

---

# Organization Scope

Accountは必ず一つのOrganizationに所属する。

他OrganizationのAccountをJournal Entry LineやBudget Lineから利用してはならない。

同名Accountや同一CodeのAccountが異なるOrganizationに存在することは許容する。

---

# Account Classification

基本Account Typeは以下とする。

- ASSET
- LIABILITY
- EQUITY
- REVENUE
- EXPENSE

Account Typeは、一般的な複式簿記における勘定分類として扱う。

---

# PL / BS Relationship

Account Typeは財務諸表上の分類にも利用する。

- ASSET / LIABILITY / EQUITY：Balance Sheet系
- REVENUE / EXPENSE：Profit and Loss系

StageArtでは、Accountを単純なPL/BSフラグだけで管理せず、Account Typeを正本の分類として扱う。

---

# Parent Account

Accountは階層構造を持つことができる。

親Accountは集計用として利用でき、必要に応じてPosting不可とする。

例：

```text
売上
 ├─ チケット売上
 ├─ 物販売上
 └─ その他売上

費用
 ├─ 会場費
 ├─ 外注費
 ├─ 広告宣伝費
 └─ 車両交通費
```

---

# Posting Account

Journal Entry LineおよびBudget Lineに直接指定できるAccountはPosting Accountとして扱う。

単なる集計用Parent Accountへ直接仕訳を計上しない。

---

# Budget Relationship

Budget LineはAccountを参照する。

```text
Budget
  ↓
Budget Line
  ↓
Account
```

これによりBudgetとActualを同一Account分類で比較できる。

Project BudgetではProject全体の計画を、Production Budgetでは個別公演の計画を同じAccount体系で表現する。

---

# Actual Relationship

ActualはJournal Entryから算出する。

```text
Journal Entry
  ↓
Journal Entry Line
  ↓
Account
  ↓
Actual
```

Actualを独立した正本として二重保存しない。

---

# Ticket Revenue

Ticket RevenueはCheckInCompletedを契機としてJournal Entryへ変換される。

Revenue AccountにはREVENUE TypeのTicket Revenue用Accountを使用する。

Reservation成立時点ではRevenueを認識せず、未収金等の状態をAccounting Domainで扱う。

Check In時点でRevenue Recognitionを行う。

---

# Initial Account Set

会計管理を有効にしたOrganizationでは、初期Accountセットを提示できるものとする。

少なくとも、現在のセットアップ仕様で必要となる以下を標準候補とする。

- 現金
- 普通預金
- 未収金
- 未払金
- チケット売上
- 物販売上
- その他収入
- 会場費
- 機器レンタル費
- 外注費
- 広告宣伝費
- 通信費
- 車両交通費
- その他雑費

Organizationは標準Accountを追加・変更・無効化できる。

---

# Organization Setup Relationship

Organization Setupで会計管理を有効にした場合、初期流動資産として少なくとも以下を分けて入力する。

- 現金
- 預金

これらは対応するAsset Accountの初期残高として会計処理する。

具体的な初期残高のJournal Entry生成方法はAccounting Setup Domainで定義する。

---

# Account Code

Account Codeを設定できる。

Account CodeはOrganization内で一意とする。

AccountIdとAccount Codeは別物として扱う。

---

# Account Status

AccountはACTIVE / INACTIVEを持つ。

INACTIVE Accountは新規取引への利用を停止するが、過去のJournal EntryやBudgetとの関連を保持する。

過去Factを守るため、参照済みAccountを物理削除しない。

---

# Account Change

Account Name、Code、階層等のマスタ情報は変更できる。

ただし、POSTED Journal Entryの会計Factを変更してはならない。

過去仕訳の監査・表示に必要な情報はJournal Entry Line側のSnapshot等で保持する。

---

# Account Mapping

Business Eventから使用Accountを決定するMappingはAccount本体と分離する。

例えば、

```text
CheckInCompleted
    ↓
Ticket Revenue Recognition
    ↓
Ticket Revenue Account
```

のようなMappingを将来管理できる。

---

# Authorization

Accountの作成、変更、無効化は会計管理権限に従う。

Organization Administratorは自身のOrganizationについて管理できる。

Production管理権限だけではAccount Masterを変更できない。

---

# Canonical Relationship Summary

```text
Organization
    ├─ Account Master
    │    ├─ Account
    │    └─ Account
    │
    ├─ Project
    │    └─ Production
    │
    └─ Journal Entry
         └─ Journal Entry Line
              └─ Account
```

Project / ProductionはAccountを所有しない。

Project Budget / Production BudgetはAccountを参照し、ActualはJournal EntryからAccount単位で集計する。

---

# Design Principle

Accountは「何に対する金額か」を分類するためのOrganization単位の会計マスタである。

BudgetとActualで同じAccount分類を利用し、計画と実績を比較可能にする。

会計FactはJournal Entryを正本とし、Account自身に金額を蓄積しない。
