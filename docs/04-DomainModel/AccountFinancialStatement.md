# StageArt Blueprint

# Account Financial Statement Classification

Version : 1.0

---

# Purpose

本書は、Account Masterにおける財務諸表上の分類を定義する。

StageArtは劇団・団体向けの会計システムであるため、一般的な会計システムのように細かな財務諸表分類を持たせず、PL（損益計算書）とBS（貸借対照表）を最低限の分類軸として管理する。

本書はAccount Domainを補完するPolicyであり、新しい会計Entityを追加するものではない。

---

# Financial Statement Classification

Accountには、Account Typeとは別に、財務諸表上の分類を設定する。

基本的な考え方：

Account Type
  ↓
会計上の基本分類

Financial Statement Classification
  ↓
PL / BS上の表示分類

Account Typeと財務諸表分類は同じものとして扱わない。

---

# PL / BS Classification

Financial Statement Classificationは、以下を基本とする。

- PL
- BS

PLは収益・費用を表すAccount、BSは資産・負債・純資産を表すAccountに使用する。

---

# PL Classification

PL Accountは、損益計算書上の区分を表す。

基本分類：

- REVENUE
- EXPENSE

例：

チケット売上
  Account Type = REVENUE
  Financial Statement = PL
  PL Category = REVENUE

会場費
  Account Type = EXPENSE
  Financial Statement = PL
  PL Category = EXPENSE

劇団会計では、PL内をさらに細かく階層化することを必須としない。

---

# BS Classification

BS Accountは、貸借対照表上の区分を表す。

劇団会計で最低限必要な分類：

- CURRENT_ASSET
- FIXED_ASSET
- LIABILITY
- EQUITY

例：

現金
  Account Type = ASSET
  Financial Statement = BS
  BS Category = CURRENT_ASSET

普通預金
  Account Type = ASSET
  Financial Statement = BS
  BS Category = CURRENT_ASSET

未払金
  Account Type = LIABILITY
  Financial Statement = BS
  BS Category = LIABILITY

劇団の基本的な資産については、原則としてCURRENT_ASSETで扱い、固定資産が必要になった場合のみFIXED_ASSETを使用する。

---

# Account Typeとの整合性

Financial Statement Classificationは、Account Typeと矛盾してはならない。

基本ルール：

- REVENUE → PL / REVENUE
- EXPENSE → PL / EXPENSE
- ASSET → BS / CURRENT_ASSET または FIXED_ASSET
- LIABILITY → BS / LIABILITY
- EQUITY → BS / EQUITY

Account Typeだけでは財務諸表上の表示位置を一意に決められないため、Financial Statement Classificationを別途保持する。

---

# No Boolean Flags

PL/BSの判定を複数のBoolean Flagで管理しない。

例えば、以下のような設計は採用しない。

- is_pl
- is_bs
- is_current_asset
- is_fixed_asset

代わりに、Financial StatementおよびCategoryをEnum/分類値として管理する。

これにより、PLとBSの両方に同時に属するなどの矛盾状態を防止する。

---

# Minimum Account Master Fields

Account Masterでは、最低限以下を保持できる構造とする。

- AccountId
- OrganizationId
- Account Code
- Account Name
- Account Type
- Financial Statement
- Financial Statement Category
- Parent Account（必要な場合）
- Posting可否
- Status

Financial Statement Categoryは、Financial Statementに応じて以下を使用する。

PL：
- REVENUE
- EXPENSE

BS：
- CURRENT_ASSET
- FIXED_ASSET
- LIABILITY
- EQUITY

---

# Parent Account Relationship

Parent Accountによる階層構造は、Financial Statement Classificationと整合させる。

原則として、異なる財務諸表区分をまたいで集計用Parent Accountを作成しない。

例：

PL
  └── EXPENSE
       ├── 会場費
       ├── 出演料
       └── 交通費

BS
  └── CURRENT_ASSET
       ├── 現金
       └── 普通預金

---

# Accounting Presentation

この分類は、以下の集計に利用する。

- PL表示
- BS表示
- Budget / Actualの分類
- Organization会計概要
- 将来の会計帳票

Journal EntryのDebit / CreditそのものをAccountに持たせるものではない。
Debit / CreditはJournal Entry Line側で管理する。

---

# Business Rules

- Accountには財務諸表上の分類を設定する。
- 財務諸表分類はPLまたはBSを基本とする。
- PLはREVENUE / EXPENSEを基本とする。
- BSはCURRENT_ASSET / FIXED_ASSET / LIABILITY / EQUITYを基本とする。
- Account TypeとFinancial Statement Classificationを混同しない。
- Account TypeとFinancial Statement Classificationは整合していなければならない。
- PL/BS判定を複数のBoolean Flagで管理しない。
- 劇団会計として必要以上に細かな財務諸表分類を導入しない。
- Parent Accountは財務諸表区分をまたいで集計しない。
- Debit / CreditはAccountではなくJournal Entry Lineで管理する。

---

# Design Decision

StageArtの会計は、一般企業向け会計システムの完全な勘定体系を再現することを目的としない。

劇団の予実管理・収支管理・資産負債管理に必要な最低限の分類をAccount Masterに持たせ、PL/BSを明確に区別できることを優先する。

将来、より詳細な財務諸表区分が必要になった場合は、既存Accountの基本分類を壊さず拡張できる構造とする。
