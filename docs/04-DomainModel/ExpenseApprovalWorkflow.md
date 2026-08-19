# StageArt Blueprint

# Domain Model : Expense Approval Workflow

Version : 1.1

---

# Purpose

本書は、Production / Projectにおける小口立替経費の登録・承認・会計転記に関する確定方針を定義する。

本Workflowは、一般Memberが日常的な小口支出を登録できるようにしつつ、承認前の申請を正式なAccounting Actualへ反映させないための最小限の業務フローを定義する。

StageArtは会計業務を複雑化させないことを基本とし、承認済みExpenseは承認時点で直ちに会計へ転記する。

---

# Scope

Memberが登録できるExpenseは、Production運営上発生した小口の立替精算を主対象とする。

対象例：

- 消耗品
- 小道具
- 文具等の少額物品
- その他、Memberが個人で立替購入し精算することを想定した小口支出

以下のような大口・計画的な支出は、Member向けの小口立替Expense登録の対象外とする。

- 会場費
- 外注費
- 出演料・人件費
- 大型設備・高額物品
- その他、予算管理者が直接管理すべき大口支出

大口支出については、Primary Manager / Production Delegateが適切な業務フローから登録・管理する。

具体的な金額上限は本書では固定せず、必要になった時点で別途定義する。

---

# Accounting Visibility

Accountingの予算・実績は、一般Memberには公開しない。

| 情報 | Primary Manager | Production Delegate | Member |
|---|---:|---:|---:|
| Budgetを見る | ○ | ○ | × |
| Budgetを入力・変更 | ○ | ○ | × |
| Actualを見る | ○ | ○ | × |
| 小口立替Expenseを登録 | ○ | ○ | ○ |
| Expense申請を承認 | ○ | ○ | × |
| Journal Entryを確定 | ○ | × | × |
| Production決算を確定 | ○ | × | × |

Memberは、自分が登録した小口立替Expenseの申請状態など、当該申請を処理するために必要な情報のみ確認できればよい。

MemberにProduction全体のBudget / Actualを表示する画面を提供しない。

---

# Expense Approval Lifecycle

小口立替Expenseの基本状態は以下とする。

```text
Expense登録
    ↓
SUBMITTED
    ↓
承認
    ↓
POSTED
    ↓
Journal Entryへ転記
    ↓
Actualへ反映
```

承認者が却下した場合：

```text
SUBMITTED
    ↓
REJECTED
    ↓
修正
    ↓
SUBMITTED
```

承認されたExpenseは、承認時点で会計へ転記する。

承認後に別の「会計転記承認」「仕訳確認」「Posting確認」等の中間Workflowを設けない。

---

# Submission

Member / Production Delegate / Primary Managerは、対象範囲内のExpenseを登録できる。

Memberが登録する場合は、小口立替精算を目的としたExpenseとして扱う。

Expense登録時点では、まだ正式なActualへ反映しない。

Expenseの状態はSUBMITTEDとし、Primary ManagerまたはProduction Delegateによる承認を待つ。

---

# Approval Notification

SUBMITTEDになったExpense申請について、承認可能なPrimary Manager / Production Delegateへ、出欠登録等の既存業務通知と同様の通知を行えるものとする。

通知から対象Expense申請の確認・承認・却下へ直接進められることを基本とする。

通知は承認処理への入口であり、通知を受け取らなかった場合でも別の承認入口から処理できる構造を妨げない。

同一Expenseに対して承認可能な複数Roleへ通知する場合でも、最初に有効な承認処理が行われた時点で処理済みとする。

---

# Approval Entry / Manager Review

承認処理には、少なくとも以下の2つの入口を許容する。

1. 業務通知から対象Expenseを開いて承認・却下する。
2. Primary Manager / Production Delegate向けの管理者メニューから、承認待ちExpenseを一覧・確認して処理する。

管理者メニューへの「会計承認」または同等の承認待ち一覧画面は、通知だけに依存せず未処理申請を確認できるようにするためのUIとして実装してよい。

ただし、この一覧画面を別個の会計Workflowとして扱わない。通知と管理者メニューは同じExpense承認処理への入口とする。

Role別の表示制御だけに依存せず、承認可否はBackendのAuthorization / Domain Ruleで担保する。

---

# Approval

Expense申請はPrimary ManagerまたはProduction Delegateが承認できる。

承認権限はRole別UIの表示切替ではなく、BackendのAuthorization / Domain Ruleとして担保する。

承認された時点でExpenseをPOSTEDとして扱い、必要なJournal Entryを生成・確定し、Production Actualへ反映する。

承認者は、Expenseの内容・金額・対象Production等、承認に必要な業務情報を確認できるものとする。

---

# Rejection

Primary ManagerまたはProduction Delegateは、Expense申請を却下できる。

却下されたExpenseはREJECTEDとして履歴を保持する。

申請者は必要な修正を行い、再申請できるものとする。

再申請時は新しい会計Factを重複生成せず、承認前のExpense申請として再びSUBMITTEDに戻す。

---

# Posting Rule

Expenseの承認と会計転記を分離しない。

以下を確定原則とする。

> Expenseを承認した時点で、必要なJournal Entryを会計へ転記し、Production Actualへ反映する。

承認済みExpenseについて、さらに別のPosting操作を要求しない。

これにより、利用者が複数の会計Workflowを順番に処理する必要をなくす。

---

# Journal Entry Relationship

Expenseは業務上の支出情報を表し、Journal Entryは会計上の正本を表す。

SUBMITTED中のExpenseは、正式なActualの構成要素として扱わない。

承認されたExpenseは、既存のExpense / Journal Entry方針に従ってJournal Entryを生成し、そのJournal EntryをActualの集計対象とする。

POSTED済みJournal Entryを直接変更・削除しないという既存のJournal Entry原則を維持する。

承認後の訂正は、既存Journal Entryを上書きせず、既存のReversal / Adjustment原則に従う。

---

# Large Expense Boundary

Member向けExpense登録機能は、会場費・外注費等の大口支出を登録するための汎用入力画面として設計しない。

大口支出はBudgetとの関係、未払金、支払予定、取引先等を含む管理対象となるため、Primary Manager / Production Delegateが管理する業務フローから登録する。

小口立替Expenseと大口支出の具体的な金額境界は、UI実装時に恣意的な固定値を導入せず、別途必要性を確認して決定する。

---

# Business Rules

- MemberはProductionのBudgetを閲覧できない。
- MemberはProductionのActualを閲覧できない。
- Primary ManagerとProduction DelegateはProductionのBudgetを閲覧できる。
- Primary ManagerとProduction DelegateはProductionのBudgetを入力・変更できる。
- Primary ManagerとProduction DelegateはProductionのActualを閲覧できる。
- Memberは小口立替精算を目的としたExpenseを登録できる。
- Production DelegateとPrimary Managerも対象範囲の小口立替Expenseを登録できる。
- Member向けExpense登録は消耗品・小道具等の小口支出を主対象とする。
- 会場費・外注費等の大口支出をMember向け小口Expense登録の対象としない。
- Expense登録直後はSUBMITTEDとし、正式なActualへ反映しない。
- SUBMITTEDのExpenseについて承認可能者へ業務通知を行える。
- 通知からExpenseの確認・承認・却下へ進められる。
- Primary ManagerまたはProduction DelegateがExpense申請を承認できる。
- 承認待ちExpenseを管理者メニューから一覧・確認・処理できる入口を設けてよい。
- 通知と管理者メニューは同一のExpense承認処理への入口とする。
- 承認時点でExpenseをPOSTEDとし、Journal Entryへ転記する。
- 承認時点でProduction Actualへ反映する。
- 承認後に別途Posting操作を要求しない。
- Primary ManagerまたはProduction DelegateはExpense申請をREJECTEDにできる。
- REJECTEDのExpenseは履歴を保持し、修正後に再申請できる。
- SUBMITTEDのExpenseが承認されるまで、Actualへ二重計上しない。
- POSTED済みJournal Entryを直接変更・削除しない。
- 承認後の訂正はReversal / Adjustment原則に従う。
- Expense申請の承認可否はBackendのAuthorization / Domain Ruleで担保する。
- 会計Workflowは必要最小限とし、承認と会計転記を分離しない。

---

# Design Principle

StageArtのExpense Workflowは、会計上の厳密性を保ちながら、現場利用者に不要な会計Workflowを要求しないことを原則とする。

基本操作は、

> 登録 → 通知 → 承認 / 却下 → 会計転記

とし、通知は承認処理を促す入口、管理者メニューは未処理申請を確認する補助入口として扱う。

承認後に追加のPosting操作を要求しない。
