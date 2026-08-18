# StageArt Blueprint

# Business Flow Consistency Policy

Version : 1.0

---

# Purpose

`03-BusinessFlow.md` に残る旧仕様と、現在までに確定したCanonical仕様の差異を整理する。

本書はBusiness Flowの整合性を補完する上位ルールとして扱い、既存Business Flowと衝突する場合は本書を優先する。

---

# 1. Public Page Generation

Production作成時点では、Public Web Pageを一般公開しない。

Production作成によって内部的なProduction情報や必要な管理領域を準備することと、Public Pageを一般公開することを分離する。

Canonical Flow：

```text
Production作成
    ↓
内部情報・管理領域を準備
    ↓
Information Public = 未公開
    ↓
公開日時を予約
    ↓
指定日時にBackground Job / CRON等が実行
    ↓
Public Visibility = ON
    ↓
Public Pageを生成・公開
```

情報公開前のProductionについて、一般向けPublic Pageを生成・公開してはならない。

---

# 2. Public Page Content Readiness

Information Publicが有効になった後でも、Venue、Performance、Ticket等の主要情報が未確定の場合がある。

その場合、Public Page自体は公開可能だが、未確定情報を推測して表示してはならない。

該当箇所は`Coming Soon`または`準備中`として表示する。

したがって、以下を分離する。

```text
Information Public
    = 一般公開してよいか

Content Readiness
    = 公開ページに必要な情報が揃っているか
```

---

# 3. Public Release Scheduling

情報公開は、利用者が指定時刻に手動でボタンを押すことを前提としない。

公演基本情報に表示される`情報公開：未公開`等の状態から公開日時を設定し、指定時刻にBackground Job / CRON等で公開処理を行う。

例えば`2026-10-01 00:00`を指定した場合、利用者が午前0時に操作する必要はない。

公開前であれば、公開日時の変更または公開予約の取消を可能とする。

---

# 4. Production Capacity

Productionの基本情報で標準予約定員を設定できる。

Performance作成時にはProductionの標準予約定員を初期値として継承する。

Performance側では、必要に応じて公演回固有の定員へ変更できる。

したがって、Production Wizardで定員を二重入力するのではなく、基本情報で標準定員を入力し、Performance Stepでは継承値を確認・変更する。

Canonical Flow：

```text
Production Basic Information
    ↓
標準予約定員
    ↓
Performance
    ↓
継承された定員を確認・必要なら変更
    ↓
Reservation受付条件
```

Productionの標準定員を後から変更しても、既存Performanceの定員を自動上書きしない。

---

# 5. Budget Input UX

Budgetは毎回ゼロから費目を作成することを基本としない。

Budget作成画面では、StageArt標準テンプレートに基づく必要なカテゴリをあらかじめ表示し、利用者は必要なカテゴリへ金額を入力する。

さらに、過去または既存Budgetをコピーして新しいBudgetを作成できる。

Canonical Flow：

```text
新規Budget
   ↓
標準カテゴリを表示
   ↓
金額を入力

または

過去Budgetをコピー
   ↓
前回のカテゴリ・金額を初期値として表示
   ↓
今回必要な金額だけ修正
```

Budgetには識別しやすい名称を付ける。

A4一枚帳票ではBudget Nameをタイトルとして利用できる。

---

# 6. Merged Budget Categories

Budgetの標準候補は、今回確定したカテゴリと旧Business Flowで使用していたカテゴリを統合する。

### 支出候補

- 会場費用
- 機器レンタル費用
- 外注費（スタッフ＋キャスト）
- 人件費
- チケットバック
- 広告宣伝費用（フライヤー印刷等）
- 通信費
- 車両交通費（レンタカー・駐車場代等）
- 消耗品費
- その他雑費（大道具・小道具・消耗品等）
- その他支出

### 収入候補

- 集客予測 × チケット代
- チケット収入
- 物販
- 協賛金
- 拠出金
- その他収入
- その他

これらは標準候補であり、すべてを必須入力とするものではない。

利用者は不要な項目を空欄または0円として扱い、必要に応じてAccount Masterに基づく追加・変更を行える。

---

# 7. Budget / Actual Separation

Budgetは計画、Journal Entryは実績Factとする。

ActualはJournal Entryから集計する。

```text
Budget
   = Plan

Journal Entry
   = Actual Fact

Actual / Variance
   = Calculated View
```

Project BudgetはProject全体の予実管理、Production Budgetは個別公演の計画に利用する。

Productionの公演収支・決算はProduction ScopeのJournal Entryから集計する。

---

# 8. Superseded Business Flow Rules

既存`03-BusinessFlow.md`に以下の旧表現が残っている場合、本Policyを優先する。

- 「活動を作成すると一般向け公開ページが生成される」
- 「公演作成時点でPublic Web Pageを生成する」
- 「定員をProductionとPerformanceで別々に新規入力する」
- Budget標準費目を旧一覧だけに限定する

これらは現在のCanonical仕様に合わせて解釈・実装する。

---

# Design Principle

利用者が公演を作る操作はシンプルに保ちつつ、公開タイミング、定員継承、予算再利用、予算カテゴリなどの複雑な処理はStageArtが内部で責任を持って管理する。

特に公開処理は利用者の手動操作に依存せず、指定された公開日時にシステムが確実に処理することを基本とする。
