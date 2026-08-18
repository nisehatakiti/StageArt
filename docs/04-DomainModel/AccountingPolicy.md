# StageArt Blueprint

# Domain Model : Accounting Policy

Version : 1.1

---

# Purpose

本書は、Production AccountingとOrganization Accountingの関係、および未収金・未払金・精算・決算後修正に関する確定方針を定義する。

Journal Entry自体の基本構造・貸借・POSTED / REVERSED等はJournal Entry Domainで定義する。

---

# Organization Accounting as Source of Truth

Productionの会計は、Organizationの会計から独立した別会計帳簿として管理しない。

Productionに関連するJournal Entryは、必ず所属OrganizationのAccountingに含まれる。

基本構造：

Organization
    ↓
Journal Entry
    ↓
Production（該当する場合）

同一のJournal Entryを、Organization / Project / Productionの各Scopeから集計して参照できる。

Production AccountingとOrganization Accountingを別々の仕訳として二重計上しない。

---

# Production Accounting

Production単位の収支は、Productionに紐付くJournal Entryを集計して算出する。

Production Accountingは、Production固有の別Journal帳簿ではない。

例えば、Production Aに関する以下の取引は、すべてOrganization Accountingにも同時に反映される。

- Ticket Revenue
- Venue Cost
- External Staff Cost
- Goods / Purchase Cost
- その他Production関連収支

---

# Journal Entry Timing

会計上の取引は、現金の入出金時だけでなく、取引が確定した時点でJournal Entryとして計上できる。

特にProductionでは、予約・依頼等によって金額が確定した時点で未収金・未払金を計上することを基本とする。

実際の入出金は、その後の精算時に未収金・未払金を消し込む。

---

# Accounts Receivable / 未収金

Ticket等の受取権利が予約・取引確定時点で発生した場合、未収金として計上する。

例：30,000円のTicket予約が成立した場合

未収金 30,000 / Ticket Revenue 30,000

実際に受付・入金した時点で、未収金を現金・預金等へ振り替えて消し込む。

キャンセル、免除、金額修正等によって未収金を消す場合は、入金とは区別された調整として記録し、理由を追跡可能にする。

---

# Accounts Payable / 未払金

会場費、外部スタッフ費、物品購入費等について、予約・依頼時点で支払額が確定している場合、未払金として計上できる。

例：会場費30,000円の予約が確定した場合

会場費 30,000 / 未払金 30,000

実際の精算・支払時に未払金を消し込む。

実際の支払額が当初計上額と異なる場合は、当初の未払金を相手勘定として差額を調整する。

---

# Settlement Difference

未収金・未払金の精算時に、当初計上額と実際の金額が異なる場合、差額を精算時のJournal Entryとして調整する。

これにより、予約・依頼時点の確定額と最終的な実額との差異を会計履歴上追跡できる。

当初のJournal Entryを直接書き換えない。

---

# Counterparty

未収金・未払金等の債権・債務については、可能な限り取引相手を特定できる情報を保持する。

例えば：

- Ticket購入者・関係者等のPerson
- 会場運営者等のOrganization
- 外部スタッフ等のPerson / Organization
- その他の取引先

決算時には、未収金・未払金を残高だけでなく「誰／どの取引について未精算なのか」という単位で確認できることを基本とする。

---

# Production Settlement

Productionの精算では、Productionに関連する未収金・未払金を確認し、必要な入金・支払・キャンセル・免除・差額調整等を行う。

最終的に未処理の未収金・未払金が残っていないことを確認できる状態を、Productionの決算完了条件の基本とする。

未収金を免除等によって消す場合も、単純な残高削除ではなく、調整として履歴を残す。

---

# Production Completion

Productionの「決算完了」は、Productionに属する会計上の必要な精算・調整が完了したことを意味する。

Productionの決算完了は、Organizationの会計期間Closeとは別概念である。

Productionが会計期間をまたいだ場合でも、Journal Dateに従って各会計期間のOrganization Accountingへ反映する。

公演終了後に未収金・未払金が解消される場合、その消込・調整は解消された時点の会計期間に反映する。

---

# Cross-Period Production

Productionが複数の会計年度にまたがる場合、Production全体を公演終了時点の年度へまとめて転記しない。

各Journal Entryを発生日・Journal Dateに基づいて該当するAccounting Periodへ計上する。

例えば、Productionが2026年度から2027年度にまたがる場合、2026年度末に未収金・未払金が残っていても、その残高をOrganization Accounting上に保持する。

翌年度に入金・支払・精算が行われた場合、その消込・差額調整を2027年度のJournal Entryとして計上する。

これにより、Production AccountingとOrganization Accountingの会計事実を完全に一致させる。

---

# Production and Organization Consistency

以下を確定原則とする。

> Production AccountingはOrganization Accountingの一部であり、Productionに紐付くJournal EntryはOrganization Accountingへ必ず反映される。

Production単位で見た収支とOrganization単位で見た該当Productionの収支は、同じJournal Entryを集計する限り一致する。

Production終了時に費目ごとの合計額をOrganization Accountingへ新たに転記する方式は採用しない。

---

# Post-Closing Adjustment

Productionが決算完了しCOMPLETEDとなった後に、計上漏れ・金額誤り等が発覚した場合でも、Productionの過去Journal Entryを直接書き換えない。

決算後修正は、新たなAdjustment Journal Entryとして記録する。

基本Flow：

Production決算完了
    ↓
COMPLETED
    ↓
計上漏れ・誤り発覚
    ↓
Adjustment Journal Entry
    ↓
Production Actualへ反映
    ↓
Organization Accountingへ反映

決算後修正は、Organization Accountingにも通常の会計取引として反映される。

決算完了時点の状態と、決算後修正後の現在状態を追跡できることを基本とする。

---

# Budget / Actual

Budgetは計画値、Journal Entryは実際に発生した会計Factとする。

ActualはJournal Entryから集計して算出し、Production単位およびOrganization単位で参照できるようにする。

Productionの決算後修正が発生した場合、修正Journal Entryを含めたActualを再集計する。

---

# Accounting Adoption / Activation

AccountingはOrganization単位のオプション機能とする。

StageArtの基本機能を利用するために、Accountingを有効化することを必須条件としない。

Organizationは、Accountingを使用せずに以下の基本的なProduction管理機能を利用できる。

- Production
- Rehearsal
- Timetable
- Participant
- Reservation等のProduction関連機能

会計を利用しないOrganizationに対して、Account Master、Budget、Journal Entry等の会計初期設定を要求してはならない。

## Initial Choice

Organizationの利用開始時に、Accountingを使用するかどうかを選択できる。

Accountingを使用する場合：

1. Accountingを有効化する。
2. 会計開始時点の現在資金を確認・入力する。
3. その開始残高をAccountingの初期状態として登録する。
4. その後、Account / Budget / Journal Entry等の会計機能を利用できる。

Accountingを使用しない場合：

1. Accountingは未開始状態とする。
2. 会計用の初期設定を要求しない。
3. そのままProduction管理へ進める。

## Current Funds at Activation

Accountingを有効化する時点で、Organizationは現在の資金を入力する。

UI上では「現在の資金」を分かりやすく入力できることを優先し、会計内部のDebit / Credit構造を初期設定画面へ直接要求しない。

現在資金は、会計開始時点のOpening BalanceとしてAccountingへ反映する。

現時点で必要な最低限の対象は流動資産とし、具体的な内訳はAccount Masterの設定に従う。

会計開始時点より前の過去取引を遡って入力することを、Accounting有効化の必須条件とはしない。

## Activation After Organization Start

Accountingを使用せずにOrganizationを開始した場合でも、後からAccountingを有効化できる。

後から有効化する場合も、基本Flowは同じとする。

Accounting未開始
    ↓
Accountingを有効化
    ↓
現在の資金を入力
    ↓
Opening Balanceを登録
    ↓
Accounting利用開始

会計を有効化する前のProduction管理や予約管理等を、会計利用開始のためにやり直すことを要求しない。

過去の取引について必要な場合のみ、Accounting開始後に追加のJournal Entryとして記録できる。

## Irreversible Activation

OrganizationでAccountingを一度有効化した後、Accountingを単純にOFFへ戻すことはできない。

これは、会計開始後に生成されたJournal Entry、Budget、Actual、未収金・未払金、Opening Balance等の会計履歴を非表示・無効化して会計事実を失わせることを防ぐためである。

Accountingを有効化した後は、AccountingをOrganizationの継続的な機能として扱う。

「会計を使わない状態へ戻す」ために、既存の会計データを削除・無効化する設計は採用しない。

## Organization Scope

Accountingの使用状態はOrganizationごとに管理する。

同一Personが複数Organizationに所属する場合でも、Accountingの使用状態はOrganizationごとに独立する。

例：

Organization A
    Accounting = 未開始

Organization B
    Accounting = ACTIVE

Organization C
    Accounting = ACTIVE

Organization AのAccounting未開始状態が、Organization BまたはCのAccounting利用を妨げてはならない。

## Accounting Availability and UI

Accountingが未開始のOrganizationでは、Accountingの初期設定を要求したり、会計データが「0円」であるかのように表示してはならない。

Accountingが未開始であることと、Accountingの残高が0円であることは別の状態として扱う。

Accounting未開始のOrganizationでは、Accounting機能を利用しない利用者に不要な会計設定を強制しない。

Mobile / Web UIでAccountingを表示する場合も、OrganizationのAccounting状態を基準として扱い、PersonのRoleによってAccounting機能そのものを別機能として分岐させない。

---

# Business Rules

- Production AccountingはOrganization Accountingから独立した別帳簿として管理しない。
- Productionに紐付くJournal Entryは所属OrganizationのAccountingへ必ず反映する。
- Production終了時に会計データを費目別にまとめてOrganization Accountingへ転記する方式は採用しない。
- Journal Entryは取引確定時点で計上できる。
- 予約・依頼時点で金額が確定した会場費・外部スタッフ費・物品購入費等は未払金として計上できる。
- Ticket予約等で受取権利が確定した場合は未収金として計上できる。
- 実際の入出金時に未収金・未払金を消し込む。
- 当初計上額と実額が異なる場合は精算時に差額調整を行う。
- 当初のJournal Entryを直接書き換えず、調整Journal Entryとして履歴を残す。
- 未収金・未払金は可能な限り取引相手を特定できる状態で管理する。
- 決算時には未収金・未払金を誰／どの取引に対する残高なのか確認できる。
- Productionの決算完了とOrganizationの会計期間Closeは別概念である。
- 会計年度をまたぐProductionについてもJournal Dateに従って各年度へ計上する。
- 年度末に未収金・未払金が残っている場合、その残高をOrganization Accounting上に保持する。
- 翌年度の入金・支払・精算は翌年度のJournal Entryとして反映する。
- Productionの決算後修正はAdjustment Journal Entryとして記録する。
- 決算後修正はProduction ActualとOrganization Accountingの双方に反映する。
- Budgetは計画値、ActualはJournal Entryから集計される実績値とする。
- AccountingはOrganization単位のオプション機能であり、StageArtの基本的なProduction管理機能を利用するために必須ではない。
- Accounting未開始のOrganizationにAccount / Budget / Journal Entry等の初期設定を要求しない。
- Organization作成時にAccountingを使用するかどうかを選択できる。
- Accountingを有効化する時点で現在資金を入力し、Opening Balanceとして登録する。
- Accounting未開始のOrganizationは、後からAccountingを有効化できる。
- Accounting有効化前のProduction管理や予約管理等をやり直すことを要求しない。
- Accountingを一度有効化したOrganizationは、Accountingを単純にOFFへ戻せない。
- Accountingの使用状態はOrganizationごとに独立する。
- Accounting未開始と会計残高0円を同一状態として扱わない。
