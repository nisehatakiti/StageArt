# StageArt Blueprint

# Domain Model : Budget Management Policy

Version : 1.1

---

# Purpose

Production Budgetは、劇団が公演ごとの収支計画を無理なく継続利用できることを重視する。

Budgetを毎回ゼロから作成することを避け、過去に作成したBudgetや既存のBudgetを再利用できることを基本方針とする。

既存のBudget Domain Modelにおける複数Budget、DRAFT / ACTIVE / ARCHIVED、Budget Line / Accountの構造を前提とする。

---

# Budget Name

Budgetには、利用者が自由に設定できる「予算名称」を持たせる。

予算名称は、Budgetを人間が識別するための表示名であり、BudgetのシステムIDとは別の情報である。

例えば以下を許可する。

- 河童ホームラン2026予算 Version1
- 河童ホームラン2026予算 Version2
- 河童ホームラン2026最終予算
- 小劇場案
- 一日2公演案

Version番号や「初稿」「修正版」「最終予算」等の命名方法は利用者に委ね、StageArtが名称を強制生成しない。

---

# Budget Creation Methods

Budget作成時は、毎回ゼロから入力することを必須としない。

利用者は以下の方法から選択できる。

- 過去または既存のBudgetをコピーして作成
- StageArt標準テンプレートから作成
- 空のBudgetから作成

新規Productionでは、特に「前回公演のBudgetをコピーして今回用に修正する」利用方法を重視する。

---

# Multiple Budgets

一つのProductionに複数のBudgetを保持できる既存仕様を維持する。

複数Budgetは、異なる予算案、前提条件、修正版、比較案等として利用できる。

Version 1.0では、一つのProductionについてACTIVEなBudgetを一つとする既存仕様を維持する。

---

# Budget Reuse

新しいProductionのBudgetを作成する際、利用者は既存Budgetをコピーして新しいBudgetを作成できる。

コピー元には、同一Productionの既存Budgetだけでなく、過去のProductionに属するBudgetも指定できる。

コピー対象はBudget Lineの構造および計画金額を基本とする。

コピー後は、新しいProductionのBudgetとして独立して編集できる。

コピー元のBudgetを変更しても、コピー済みのBudgetへ変更が波及してはならない。

---

# Standard Template

StageArtは、劇団の一般的な公演を想定した標準Budgetテンプレートを提供する。

利用者は標準テンプレートを起点にBudgetを作成できる。

不要な項目は使用しなくてよく、必要に応じて独自のBudget Lineを追加できる構造とする。

---

# Standard Expense Categories

標準テンプレートの支出項目は、劇団会計として必要十分な粒度を基本とする。

- 会場費
- 機器レンタル費
- 外注費（スタッフ・キャスト）
- 広告宣伝費
- 通信費
- 車両・交通費（レンタカー・駐車場等を含む）
- 宿泊・飲食費
- 著作権・使用料
- その他雑費（大道具・小道具・消耗品等を含む）

---

# Standard Revenue Categories

標準テンプレートの収入項目は以下を基本とする。

- チケット収入
- 物販売上
- 助成金・補助金
- 協賛・寄付
- その他収入

チケット収入の予算は、必要に応じて「想定集客数 × チケット単価」等を前提として入力できる。

ただし、BudgetがTicketそのものを所有する設計にはせず、既存Budget Domain ModelのBudget Line / Account構造を利用する。

---

# Budget Input Screen

Budget入力画面は、会計専門知識を前提とせず、劇団の公演担当者が直感的に利用できる構成とする。

会計ソフトの仕訳入力画面のような複雑なUIを避け、収入・支出・収支を一画面で把握できることを基本とする。

画面上部に以下を表示する。

- Production Name
- 予算名称（Budget Name）
- Budget作成方法

Budget作成方法では、以下を選択できる。

- 過去公演・既存Budgetをコピー
- 標準テンプレートを利用
- 空のBudgetを作成

---

# Revenue Input

収入欄では、標準収入項目を一覧表示し、金額を入力できる。

基本項目：

- チケット収入
- 物販売上
- 助成金・補助金
- 協賛・寄付
- その他収入

チケット収入については、必要に応じて以下の簡易計算入力を利用できる。

- 想定集客数
- チケット単価
- 計画チケット収入

基本的な計算は、

想定集客数 × チケット単価 = 計画チケット収入

とする。

将来、料金種別ごとの詳細入力へ拡張できる構造を妨げないが、Version 1.0では平均的なチケット単価による簡易入力を基本とする。

---

# Expense Input

支出欄では、標準支出項目を一覧表示し、金額を入力できる。

基本項目：

- 会場費
- 機器レンタル費
- 外注費
- 広告宣伝費
- 通信費
- 車両・交通費
- 宿泊・飲食費
- 著作権・使用料
- その他雑費

外注費にはスタッフ・キャストを含む。

その他雑費には大道具・小道具・消耗品等を含む。

不要な項目は0円のまま利用しないことができる。

---

# Custom Budget Line

標準項目だけでは不足する場合、利用者は「予算項目を追加」から独自のBudget Lineを追加できる。

追加したBudget Lineも既存のAccountとの関係を維持し、Budgetの収入・支出集計に含める。

標準項目を細分化する必要がある場合にも、独自項目追加で対応できる。

---

# Budget Summary

Budget入力画面では、収入・支出の入力と同時に以下を表示する。

- 収入合計
- 支出合計
- 計画収支

基本式：

Planned Revenue
  -
Planned Expense
  =
Planned Profit

例えば、

収入 ¥550,000
支出 ¥800,000
収支 -¥250,000

のように、一目で公演の計画収支を確認できる構成とする。

Planned ProfitはBudget Lineから算出し、独立した正本データとして保存しない既存Budget仕様を維持する。

---

# Budget Save and PDF Actions

Budget入力画面では、入力内容を保存できる。

保存操作とは別に、A4予算帳票をPDF出力できる。

基本的な画面操作として、Budgetの保存とPDF出力を明確に分離する。

---

# A4 Budget Report

Budgetは、紙で比較確認できるよう**A4・1ページの予算帳票**として出力できるものとする。

基本出力形式はPDFとする。

一つのBudgetを一つのA4帳票として出力でき、複数Budgetをそれぞれ出力して比較できる。

帳票は予算比較確認を目的とし、A4 1ページに収まることを仕様上の制約とする。

---

# A4 Budget Report Title

A4予算帳票のタイトル行には、Budget Nameを表示する。

例えばBudget Nameが、

「河童ホームラン2026予算 Version1」

の場合、帳票タイトルも、

「河童ホームラン2026予算 Version1」

とする。

システム上のBudget IDを利用者向け帳票タイトルとして表示することを基本としない。

---

# A4 Budget Report Content

A4予算帳票には、予算の比較確認に必要な情報を優先して表示する。

基本的に以下を含む。

- Budget Name
- Production Name
- Budgetの状態（必要に応じてACTIVE等）
- 作成日等の基本情報
- 収入合計
- 支出合計
- 計画差引（Planned Profit）
- Budget Lineの収入項目
- Budget Lineの支出項目

Planned ProfitはBudget Lineから算出し、独立した正本データとして保存しない既存Budget仕様を維持する。

---

# Comparison Use Case

主要な利用シーンは、例えば以下とする。

```text
河童ホームラン2026予算 Version1
        ↓
A4 PDF

河童ホームラン2026予算 Version2
        ↓
A4 PDF

        ↓

紙で並べて比較確認
```

StageArtは、予算を画面上で管理するだけでなく、稽古場・打合せ等で紙に出して比較検討できることを重視する。

---

# Business Rules

1. Budgetには利用者が設定できる予算名称を持たせる。
2. 予算名称はBudgetのシステムIDとは別に管理する。
3. 一つのProductionに複数Budgetを保持できる。
4. 新しいBudgetは標準テンプレート、既存Budgetのコピー、または空のBudgetから作成できる。
5. 過去ProductionのBudgetもコピー元として利用できる。
6. Budgetをコピーした後は、コピー先を独立して変更できる。
7. 標準Budget項目は劇団会計として必要十分な粒度とする。
8. 不要な標準項目は使用しなくてよい。
9. 必要なBudget Lineを追加できる構造とする。
10. Budget入力画面は会計専門知識を前提とせず、収入・支出・収支を一画面で確認できる構成とする。
11. チケット収入は必要に応じて想定集客数×チケット単価で計画できる。
12. Budget入力画面では収入合計・支出合計・計画収支を即時確認できる。
13. A4・1ページの予算帳票をPDFで出力できる。
14. A4帳票のタイトルにはBudget Nameを表示する。
15. 複数Budgetを個別に出力し、紙で比較確認できる。
16. A4帳票は比較確認に必要な情報を優先し、1ページに収める。
17. Budgetの再利用を容易にし、毎回ゼロから予算を作成する負担を避ける。
