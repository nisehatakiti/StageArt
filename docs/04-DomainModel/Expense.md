# StageArt Blueprint

# Domain Model : Expense

Version : 1.1

---

# Purpose

Expenseは、Production / Projectにおいて発生した支出を管理するDomainである。

Expenseは、会場費、外部スタッフ費、印刷費、交通費、広告宣伝費など、公演運営に必要な支出を表す。

Expenseは、Budgetとは異なり、会計上の実績を構成する支出Factである。

---

# Evidence

Expenseには証憑を関連付けることができる。

ただし、予約・依頼時点で支払額が確定した未払計上については、証憑がまだ存在しなくてもよい。

証憑が存在しないことを、未払計上の阻害条件としない。

証憑は後から追加できる構造とする。

---

# Payable Recognition

外部スタッフへの依頼、会場予約、その他Production運営上の発注・予約等について、予約・依頼が成立し、支払額が会計上の金額として確定した時点でExpenseを登録し、未払金を計上できる。

この時点では請求書・領収書等の証憑が存在しなくてもよい。

一方、「概算」「見込み」等で支払額がまだ確定していない場合は、未払金を計上しない。

基本Flow：

予約・依頼
    ↓
支払額の合意・確定
    ↓
Expense
    ↓
未払計上Journal Entry
    ↓
精算・支払

---

# Example : Venue Cost

会場を30,000円で予約し、支払額が確定した場合、予約確定時に以下を計上する。

借方　会場費　30,000
貸方　未払金　30,000

この時点で会場費30,000円はJournal Entryを通じてActualに反映される。

---

# Example : External Staff

外部スタッフへの出演・作業依頼でギャラ80,000円が確定した場合、依頼確定時に以下を計上する。

借方　外注費　80,000
貸方　未払金　80,000

会場費・外部スタッフ費その他の発注・予約費用について、同じ考え方を適用できる構造とする。

---

# Settlement

実際の精算時には、予約・依頼時の未払計上額と実際支払額を比較する。

実際支払額が上振れ・下振れした場合、予約・依頼時のJournal Entryを上書きせず、差額を別のJournal Entry Lineとして調整する。

---

# Settlement : Higher Actual Amount

予約時30,000円だった会場費が、実際の精算時に35,000円となった場合、

借方　未払金　30,000
借方　会場費　 5,000
貸方　現金等　35,000

とする。

結果として、会場費Actualは35,000円、未払金残高は0円、現金等支払額は35,000円となる。

---

# Settlement : Lower Actual Amount

予約時30,000円だった会場費が、実際の精算時に28,000円となった場合、

借方　未払金　30,000
貸方　会場費　 2,000
貸方　現金等　28,000

とする。

結果として、会場費Actualは28,000円、未払金残高は0円、現金等支払額は28,000円となる。

---

# Source Traceability

未払金の残高だけを管理し、発生元を失ってはならない。

未払計上について、少なくとも以下の業務経緯を追跡可能とする。

予約・依頼
    ↓
支払予定額
    ↓
Expense
    ↓
未払計上Journal Entry
    ↓
未払金残高
    ↓
精算
    ↓
差額調整
    ↓
支払

例えば、

「会場予約30,000円 → 未払計上30,000円 → 実際35,000円 → 差額5,000円 → 最終支払35,000円」

という経緯を後から確認できること。

未払金Accountそのものに相手勘定を直接持たせることは今回の仕様として確定しない。

既存のExpense / ExpenseLine、Journal Entry / Journal Entry Line、Account等の関係を正本として利用し、発生元を追跡できる方法を実装時に決定する。

---

# Budget Relationship

Budgetは計画であり、Expense / Journal Entryとは異なる。

例えば、

Budget
  会場費 50,000円

予約確定
  会場費 30,000円

精算
  会場費 35,000円

の場合、

- Budget = 50,000円
- Actual = 35,000円
- Variance = ActualとBudgetの差額

となる。

予約時の30,000円はBudgetではなく、Journal Entryを通じたActualの構成要素となる。

---

# Journal Entry Relationship

未払計上はJournal Entryによって会計Factとして記録する。

Journal Entryは会計上の取引を記録する正本であり、未払計上のために別の会計実績Factを二重管理しない。

Expenseから生成されたJournal EntryをProduction Actualの集計対象とする既存方針を維持する。

---

# Immutability

POSTED済みJournal Entryは変更・削除しない。

精算時の上振れ・下振れは、既存のJournal Entryを上書きせず、追加のJournal Entry Line / Journal Entryで調整する。

既存JournalEntry.mdのReversal原則を維持する。

---

# Payable Settlement

実際の支払時には、予約・依頼時に計上した未払金を消し込む。

支払額が未払計上額と異なる場合でも、差額調整を含めて会計上正しい未払金残高を表現する。

支払がまだ行われていない場合、未払金残高は残る。

---

# Multiple Payables

同一Production内に、会場費、外部スタッフ費、印刷費等の複数の未払が存在することを許容する。

単純な未払金合計だけではなく、それぞれの未払計上の発生元を追跡できることを要求する。

---

# Cancellation

予約・依頼後にキャンセルされた場合の会計処理は今回確定しない。

例えば、会場費30,000円を予約した後にキャンセルし、キャンセル料5,000円が発生した場合の未払計上の反転およびキャンセル料の計上方法は、別途設計する。

---

# Business Rules

- ExpenseはProduction / Projectにおける支出を表す。
- Budgetは計画であり、Expense / Journal EntryによるActualとは別の概念である。
- 予約・依頼が成立し、支払額が確定した時点でExpenseを登録し、未払金を計上できる。
- 未払計上時点で証憑が存在しなくてもよい。
- 金額が概算・見込みで確定していない場合は未払計上しない。
- 未払計上はJournal Entryを通じて記録する。
- 未払計上によるJournal EntryはProduction Actualの集計対象となる。
- 予約・依頼時の未払計上額を後から上書きしない。
- 実際の精算額が上振れした場合は差額を追加計上する。
- 実際の精算額が下振れした場合は差額を減額する。
- POSTED済みJournal Entryを直接変更・削除しない。
- 実際の支払時に未払金を消し込む。
- 支払前の未払金残高は保持する。
- 未払金の発生元を追跡可能にする。
- 未払金Accountそのものに相手勘定を直接保持することは今回確定しない。
- 同一Production内に複数の未払を保持できる。
- 会場費・外部スタッフ費・その他発注・予約費用に同じ考え方を適用できる。

---

# Open Questions

- 予約・依頼時のExpenseを既存Expense AggregateのどのStatusで表現するか。
- 予約・依頼のBusiness FactをどのDomainからExpense / Accountingへ連携するか。
- 未払計上の発生元とJournal Entryを具体的にどのReferenceで関連付けるか。
- キャンセル時に未払計上をどのように反転するか。
- 一部支払・分割支払をどのように扱うか。
- 支払額の一部だけが確定している場合の未払計上方法。

---

# Scope

Expense Domainにおける未払計上・精算差額のBlueprint定義である。

具体的なEntity構成、REST API、DB構造、Migration、UI、UseCase等は後続のAccounting実装Phaseで設計・実装する。
