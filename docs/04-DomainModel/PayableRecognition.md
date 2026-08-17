# StageArt Blueprint

# Accounting Concept : Payable Recognition

Version : 1.0

---

# Purpose

Payable Recognitionは、Production運営において、予約・依頼等によって支払額が確定した時点で、証憑がまだ存在しない場合でも未払金を会計上認識できるようにするためのAccounting上の概念である。

これはBudgetではない。

Budgetは計画を表し、Payable Recognitionは予約・依頼によって発生した支払義務を会計上認識する。

---

# Basic Principle

外部スタッフへの依頼、会場予約、その他Production運営上の発注・予約等について、予約・依頼が成立し、支払額が会計上の金額として確定した時点で、証憑がなくても未払金を計上できる。

「証憑が存在しないこと」を未払計上の阻害条件としない。

一方、支払額が「概算」「見込み」等でまだ確定していない予約・依頼は、未払計上の対象としない。

---

# Accounting Flow

基本的なライフサイクルは以下とする。

予約・依頼
    ↓
支払額の合意・確定
    ↓
未払金計上
    ↓
Journal Entry
    ↓
実際の精算
    ↓
未払金の消込
    ↓
実際支払額との差額調整
    ↓
現金 / 預金等による支払

---

# Example : Venue Cost

会場を30,000円で予約した場合、予約確定時に以下を計上する。

借方　会場費　30,000
貸方　未払金　30,000

この時点で、

- 会場費 Actual = 30,000円
- 未払金残高 = 30,000円

となる。

---

# Settlement : Higher Actual Amount

予約時30,000円だった会場費が、実際の精算時に35,000円となった場合、予約時の仕訳を変更せず、精算時に差額を含めて処理する。

借方　未払金　30,000
借方　会場費　 5,000
貸方　現金等　35,000

結果として、

- 会場費 Actual = 35,000円
- 未払金残高 = 0円
- 現金等支払額 = 35,000円

となる。

---

# Settlement : Lower Actual Amount

予約時30,000円だった会場費が、実際の精算時に28,000円となった場合は、差額を減額する。

借方　未払金　30,000
貸方　会場費　 2,000
貸方　現金等　28,000

結果として、

- 会場費 Actual = 28,000円
- 未払金残高 = 0円
- 現金等支払額 = 28,000円

となる。

---

# Example : External Staff

外部スタッフへの出演・作業依頼でギャラ80,000円が確定した場合、依頼確定時に以下を計上する。

借方　外注費　80,000
貸方　未払金　80,000

実際の精算額が86,000円となった場合は、

借方　未払金　80,000
借方　外注費　 6,000
貸方　現金等　86,000

とする。

実際の精算額が75,000円となった場合は、

借方　未払金　80,000
貸方　外注費　 5,000
貸方　現金等　75,000

とする。

会場費・外部スタッフ費その他の発注・予約費用について、同じ考え方を適用できる構造とする。

---

# Relationship with Budget

Budgetは計画であり、未払計上とは異なる。

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

BudgetとActualを同一Factとして管理しない。

---

# Relationship with Journal Entry

未払計上はJournal Entryによって会計Factとして記録する。

Journal Entryは会計上の取引を記録する既存の正本であり、未払計上のために別の会計実績Factを二重管理しない。

基本構造：

予約・依頼確定
    ↓
Payable Recognition
    ↓
Journal Entry
    ↓
Production Actual

Production ActualはJournal EntryをProduction単位で集計したProjectionとして扱う既存方針を維持する。

---

# Relationship with Expense

既存のExpense / ExpenseLineは、確定済み支出を管理し、CONFIRMEDを契機としてJournal Entryを生成する既存設計を持つ。

予約・依頼時の未払計上とExpense CONFIRMEDの責務境界については、実装時に既存Expense設計との整合を確認する。

今回のBlueprintでは、予約・依頼時の未払計上という業務ルール自体を確定し、具体的なEntity分割や新Entity追加までは確定しない。

---

# Source Traceability

未払金の残高だけを管理し、発生元を失ってはならない。

未払計上について、少なくとも以下の業務経緯を追跡可能とする方針を採用する。

予約・依頼
    ↓
支払予定額
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

# Settlement and Difference Adjustment

精算時に実際支払額が予約・依頼時の未払計上額と異なる場合、予約・依頼時のJournal Entryを上書き・削除しない。

差額を別のJournal Entry Lineとして表現する。

上振れの場合：

予約額 < 実支払額

差額を費用として追加計上する。

下振れの場合：

予約額 > 実支払額

差額を費用から減額する。

POSTED済みJournal Entryの変更・削除は禁止し、既存のReversal原則を維持する。

---

# Payable Settlement

実際の支払時には、予約・依頼時に計上した未払金を消し込む。

最終的な支払額が未払計上額と一致しない場合でも、差額調整を含めて最終的に当該未払金の残高が正しく表現されることを基本とする。

支払がまだ行われていない場合、未払金残高は残る。

---

# Multiple Payables

同一Production内に、会場費、外部スタッフ費、印刷費等の複数の未払が存在することを許容する。

単純な未払金合計だけではなく、それぞれの未払計上の発生元を追跡できることを要求する。

---

# Cancellation

予約・依頼後にキャンセルされた場合の会計処理は今回確定しない。

例えば、会場費30,000円を予約した後にキャンセルし、キャンセル料5,000円が発生した場合の未払計上の反転およびキャンセル料の計上方法は、別途設計する。

これはOpen Questionとして保持する。

---

# Amount Confirmation

未払計上のトリガーは、予約・依頼が成立し、支払額が会計上の金額として確定した時点とする。

「概算」「見込み」等、金額がまだ確定していない段階では未払金を計上しない。

---

# Fact / Artifact

未払計上によって生成されたJournal EntryはAccounting Factである。

未払金残高やProduction Actual等の表示値は、Journal Entry等の正本から算出するRead Model / Projectionとして扱い、同じ会計事実を別Factとして二重保存しない。

---

# Business Rules

- 予約・依頼が成立し、支払額が確定した時点で未払金を計上できる。
- 未払計上時点で証憑が存在しなくてもよい。
- 金額が概算・見込みで確定していない場合は未払計上しない。
- 未払計上はBudgetではなくAccounting上の発生事実である。
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

- 予約・依頼時の未払計上を既存Expense Aggregateで表現するか、別のAccounting概念として扱うか。
- 未払計上の発生元とJournal Entryを具体的にどのEntity/Referenceで関連付けるか。
- 予約・依頼のBusiness FactをどのDomainからAccountingへ連携するか。
- キャンセル時に未払計上をどのように反転するか。
- 一部支払・分割支払をどのように扱うか。
- 支払額の一部だけが確定している場合の未払計上方法。

---

# Scope

この文書は会計ルールのBlueprint定義であり、今回の変更ではPHP、REST API、DB、Migration、UI、UseCase等の実装を行わない。

具体的なEntity構成・API・DB構造は、後続のAccounting実装Phaseで本Blueprintに基づいて設計・実装する。
