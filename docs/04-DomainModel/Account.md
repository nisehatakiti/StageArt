# StageArt Blueprint

# Domain Model : Account

Version : 1.0

---

# Purpose

Accountは、
StageArtにおける会計上の勘定科目を管理するDomainである。

Accountは、
Journal Entry LineやBudgetなどが
金額を分類するための会計上の分類軸を表す。

---

# Concept

Accountは、
会計上の取引を分類するために利用する。

基本構造：

Account
  ↓
Journal Entry Line

Budget
  ↓
Account

Journal Entry LineとBudgetは、
同じAccountを利用することで、

「計画した費目」

と

「実際に発生した費目」

を同じ分類軸で比較できる。

---

# Relationship

AccountはOrganizationに所属する。

Organization
  ↓
Account

他OrganizationのAccountを
利用してはならない。

---

# Account Identity

AccountはAccountIdによって
一意に識別する。

AccountIdは変更しない。

Account Nameを
識別子として利用しない。

---

# Account Name

Accountには、
会計上の名称を設定する。

例：

- 現金
- 普通預金
- 売掛金
- チケット売上
- 会場費
- 出演料
- 人件費
- 交通費
- 広告宣伝費
- 印刷費
- 消耗品費

---

# Account Type

Accountには、
会計上の分類を設定する。

基本的な分類：

- ASSET
- LIABILITY
- EQUITY
- REVENUE
- EXPENSE

---

# Asset

ASSETは、
資産を表す。

例：

- 現金
- 普通預金
- 売掛金

---

# Liability

LIABILITYは、
負債を表す。

例：

- 未払金
- 借入金

---

# Equity

EQUITYは、
純資産・資本を表す。

---

# Revenue

REVENUEは、
収益を表す。

例：

- チケット売上
- グッズ売上
- その他売上

---

# Expense

EXPENSEは、
費用を表す。

例：

- 会場費
- 出演料
- 人件費
- 交通費
- 広告宣伝費

---

# Parent Account

Accountは、
必要に応じて階層構造を持つ。

例えば、

売上
  ├── チケット売上
  ├── グッズ売上
  └── その他売上

費用
  ├── 会場費
  ├── 出演料
  ├── 宣伝広告費
  └── 交通費

という構造。

Parent Accountを利用して、
Accountをグループ化できる。

---

# Account Hierarchy

Account Hierarchyは、
集計・表示のために利用する。

例えば、

費用
  ↓
制作費
  ↓
会場費

のような階層を持つことで、

- 会場費
- 出演料
- 宣伝広告費

などの詳細と、

「制作費」

としての合計を
両方確認できる。

---

# Posting Account

すべてのAccountを
Journal Entry Lineへ直接指定できるとは限らない。

必要に応じて、
取引を直接計上できるAccountを
Posting Accountとして扱う。

親Accountが単なる集計用の場合、

Parent Account
  = Posting不可

Child Account
  = Posting可

とすることができる。

---

# Budget Relationship

Budgetは、
計画金額をAccount単位で管理できる。

基本構造：

Budget
  ↓
Budget Line
  ↓
Account

例えば、

Budget

チケット売上
  500,000円

会場費
  200,000円

広告宣伝費
  100,000円

という形で計画する。

---

# Actual Relationship

Actualは、
Journal EntryからAccount単位で
集計できる。

基本構造：

Journal Entry
  ↓
Journal Entry Line
  ↓
Account
  ↓
Actual

Actualを別の正本データとして
保存しない。

Journal Entryを正本とする。

---

# Budget / Actual

同一Accountを利用することで、

Budget
  ↓
Planned Amount

Journal Entry
  ↓
Actual Amount

を比較できる。

例えば、

Account
  = チケット売上

Budget
  = 500,000円

Actual
  = 430,000円

Variance
  = -70,000円

という分析が可能になる。

---

# Production Relationship

Account自体はProductionに所属しない。

AccountはOrganizationの
会計分類マスタである。

Production単位の実績は、
Journal EntryのProduction関連情報を通じて
Account別に集計する。

---

# Project Relationship

Account自体はProjectに所属しない。

Project単位の予実管理も、
BudgetおよびJournal Entryが
Accountを参照することで実現する。

---

# Organization Scope

AccountはOrganization単位で管理する。

例えば、

Organization A

  チケット売上
  会場費
  出演料

Organization B

  チケット売上
  会場費
  出演料

というように、
同名Accountが別Organizationに存在できる。

AccountIdはOrganizationをまたいで
共有しない。

---

# System Account

StageArtが標準的な会計処理を行うために、
System Accountを提供できる。

例えば、

- 現金
- 普通預金
- 売掛金
- チケット売上
- 会場費

など。

ただし、
Organizationが独自のAccountを
追加・変更できる構造を維持する。

---

# Account Template

将来的に、
Organization作成時に
標準Accountセットを生成できる。

例えば、

Organization作成
  ↓
Default Account Template
  ↓
Organization Account生成

というFlow。

AccountはOrganizationごとに生成され、
別OrganizationのAccountと共有しない。

---

# Account Code

Accountには、
管理用のCodeを設定できる。

例えば、

1000
  現金

1100
  普通預金

4000
  チケット売上

5000
  会場費

など。

Codeは表示・検索・並び順などに利用する。

Account Codeを
AccountIdとして扱わない。

---

# Account Code Uniqueness

Account Codeは、
Organization内で一意とする。

同一Organization内で
同じCodeを複数Accountへ設定しない。

異なるOrganizationでは、
同じCodeを利用できる。

---

# Account Status

Accountには、
状態を持たせる。

基本的な状態：

- ACTIVE
- INACTIVE

---

# Active

ACTIVEは、
現在利用可能なAccountを表す。

BudgetやJournal Entry Lineで
利用できる。

---

# Inactive

INACTIVEは、
新しい取引への利用を停止したAccountを表す。

過去のJournal Entryから
削除・変更しない。

過去の仕訳を参照するために、
Account自体は保持する。

---

# Account Change

Account NameやCodeなどの
表示情報を変更できる。

ただし、
過去のPOSTED Journal Entryの
会計Factを変更してはならない。

過去仕訳の表示に必要な情報については、
Journal Entry Line側でSnapshotを保持できる。

---

# Account Deletion

過去のJournal EntryやBudgetから
参照されるAccountを
物理削除しない。

利用を停止する場合は、

ACTIVE
  ↓
INACTIVE

とする。

---

# Revenue Account

Ticket Revenueなどの収益は、
REVENUE Accountへ計上する。

例えば、

Account
  = チケット売上

として管理する。

CheckInCompletedから
Ticket Revenue Recognitionを行う際、
Accounting Domainが適切なRevenue Accountを
選択する。

---

# Expense Account

費用は、
EXPENSE Accountへ計上する。

例えば、

- 会場費
- 出演料
- 人件費
- 交通費
- 広告宣伝費
- 印刷費
- 消耗品費

など。

---

# Account Mapping

Business Eventから
Accountを決定するためのMappingを
将来的に管理できる。

例えば、

CheckInCompleted
  ↓
Ticket Revenue
  ↓
チケット売上 Account

など。

Account Mapping自体は、
Account DomainのAccount情報とは
分離して管理する。

---

# Tax

Account自体は、
税額を直接管理しない。

税区分が必要となった場合は、
Accounting Tax Domainなどで管理する。

Journal Entry Lineが
必要な税情報を参照できる構造とする。

---

# Currency

Accountは、
特定Currencyに固定しない。

Journal Entry側で
取引Currencyを管理する。

Version 1.0では、
Organizationの会計通貨を
基本とする。

---

# Audit

Accountには、
変更を追跡できるよう監査情報を保持する。

基本情報：

- Created By
- Created At
- Updated By
- Updated At

---

# Authorization

Accountの作成・変更・無効化は、
会計管理権限を持つPersonが行う。

Organization Administratorは、
自身のOrganizationについて
全権限を持つ。

稽古管理権限だけでは、
Accountを変更できない。

---

# Business Rules

- Accountは会計上の分類軸を表す。
- AccountはOrganizationに所属する。
- 他OrganizationのAccountを利用できない。
- AccountIdによって一意に識別する。
- Account Nameを識別子として利用しない。
- Account Typeを持つ。
- Account TypeはASSET / LIABILITY / EQUITY / REVENUE / EXPENSEを基本とする。
- Accountは階層構造を持つことができる。
- Parent Accountを設定できる。
- 必要に応じてPosting Accountを定義できる。
- Budget LineはAccountを参照できる。
- Journal Entry LineはAccountを参照する。
- ActualはJournal EntryからAccount単位で集計する。
- Actualを別の正本として二重管理しない。
- ProductionはAccountを所有しない。
- ProjectはAccountを所有しない。
- AccountはOrganization単位の会計マスタとして管理する。
- Account CodeはOrganization内で一意とする。
- AccountはACTIVE / INACTIVEの状態を持つ。
- 過去の会計Factから参照されるAccountを物理削除しない。
- AccountをINACTIVEにしても過去の仕訳は変更しない。
- POSTED Journal Entryの会計FactをAccount変更によって書き換えない。
- Account自体は税額を管理しない。
- Account自体はCurrencyを正本として管理しない。
- Account MappingはAccount本体と分離する。
- Accountには監査情報を保持する。
- Account管理権限は会計管理権限に従う。
- Organization Administratorは自身のOrganizationについて全権限を持つ。

---

# Domain Events

Accountに関する主なDomain Event：

- AccountCreated
- AccountUpdated
- AccountActivated
- AccountDeactivated

Accountの変更によって、
過去のPOSTED Journal Entryを
変更してはならない。

---

# Design Decisions

Accountは、
StageArtの会計における分類軸である。

Accountそのものを
ProductionやProjectに所属させない。

ProductionやProjectの予実管理は、

Budget
  ↓
Account

および、

Journal Entry
  ↓
Account

という共通の分類軸を利用する。

これにより、

「公演開始前に予定していた費用」

と

「実際に発生した費用」

を同じAccount単位で比較できる。

AccountはOrganization単位で管理し、
他Organizationとの会計データ混在を防止する。

また、
過去の会計Factを保護するため、
利用停止したAccountも物理削除しない。

---

# Future

将来的に以下へ対応できる。

- 標準Account Template
- Account Import
- Account Export
- Account Mapping
- 補助科目
- 部門
- 税区分
- 複数通貨
- 会計ソフト連携
- Account Hierarchyによる詳細分析
- Production別Account集計
- Project別Account集計
- Organization別Account集計

ただし、
将来機能を追加する場合も、
Accountは会計分類軸としての責務に限定する。

---

# Design Principles

- Accountは会計上の分類軸である。
- AccountはOrganizationに所属する。
- AccountはProductionやProjectに所属しない。
- BudgetとJournal Entryで同じAccount分類を利用できる。
- ActualはJournal Entryから集計する。
- AccountをActualの正本として利用しない。
- Account Typeを明確に管理する。
- Debit / CreditはJournal Entry Line側で管理する。
- Accountは貸借方向そのものを保持しない。
- Accountは階層構造を持つことができる。
- Account CodeはOrganization内で一意とする。
- 過去の会計Factから参照されるAccountを物理削除しない。
- Account変更によってPOSTED Journal Entryを変更しない。
- AccountとAccount Mappingを分離する。
- 会計管理権限を適切に分離する。
- Blueprintを唯一の設計基準とする。
