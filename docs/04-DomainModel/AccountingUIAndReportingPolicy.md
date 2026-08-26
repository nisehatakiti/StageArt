# StageArt Blueprint

# Accounting UI and Reporting Policy

Version : 1.1

---

# Purpose

StageArt V1の会計管理における利用者向け明細画面、Journal Entry転記、訂正、総勘定元帳、BS / PL出力、およびProduction決算確定の基本方針を定義する。

Journal Entryを会計上の正本とし、利用者向け画面はJournal Entryを直接編集するための画面ではなく、業務上理解しやすい会計明細ビューとして提供する。

---

# Accounting Entry Confirmation

会計処理からJournal Entryへ転記する際は、利用者に転記内容を確認させる。

基本Flow：

```text
会計入力 / 承認
    ↓
「○○円で転記します」
    ↓
YES
    ↓
Journal Entry生成・POSTED
```

立替経費の場合：

```text
立替金申請
    ↓
会計担当が確認
    ↓
必要に応じて金額を修正
    ↓
承認
    ↓
「○○円で転記します」
    ↓
YES
    ↓
Journal Entry生成
```

通常の会計入力も同じ確認フローを使用する。

---

# Accounting Actuals List

会計管理の「実績」画面は、総勘定元帳ではなく、劇団員・会計担当者が日常的に利用しやすい家計簿型の会計明細一覧として提供する。

基本表示：

- 日付
- 内容 / 摘要
- 費目 / 勘定科目
- 収入
- 支出
- 公演
- 入力者
- 転記状態
- 訂正状態

期間、公演、収入 / 支出等で絞り込みできる構造を基本とする。

明細を選択すると詳細を確認でき、権限を持つ利用者は修正処理へ進める。

この画面は利用者向けUIであり、Journal Entryそのものを直接編集させない。

---

# Journal Entry Correction

POSTED済みJournal Entryは直接変更・削除しない。

利用者が会計明細一覧から対象明細を選択し、変更後金額を入力して修正する。

基本Flow：

```text
会計明細を選択
    ↓
変更金額を入力
    ↓
「○○円 → △△円に修正します」
    ↓
YES
    ↓
元仕訳を反転する訂正仕訳を自動生成
    ↓
変更後金額による新規Journal Entryを自動生成
```

内部的には、元仕訳を赤仕訳で一旦打ち消し、新しい金額を黒仕訳として登録する。

利用者は赤黒仕訳を直接操作せず、修正後の金額を入力するだけとする。

元のJournal Entryは保持し、訂正後も監査可能な履歴として追跡できる。

Journal Entryの物理削除はV1では行わない。

---

# General Ledger

総勘定元帳は、日常操作用の会計明細一覧とは別に提供する。

総勘定元帳はJournal Entry / Journal Entry Lineを正本として生成し、監査・会計確認用の正式な帳簿ビューとする。

利用者が総勘定元帳上の仕訳を直接編集することはできない。

総勘定元帳では、勘定科目ごとの借方・貸方・残高を確認でき、訂正仕訳を含めた会計履歴を追跡できる。

---

# Production Closing

Productionの決算確定は、Productionに関する未収金・未払金がすべて解消された時点で可能とする。

基本Flow：

```text
公演終了
    ↓
未収金・未払金を確認
    ↓
入金・支払・キャンセル・免除・差額調整等を完了
    ↓
未収金・未払金 = 0
    ↓
BS / PL確認
    ↓
「この内容で決算を確定します」
    ↓
YES
    ↓
Production決算確定
```

公演終了そのものを決算確定条件とはしない。未収金・未払金が残っている場合は決算確定できない。

Productionの決算確定はOrganization全体の会計期間Closeとは別概念とする。

---

# Production Closing Authority

Productionの決算確定を実行できる権限は以下とする。

- Primary Manager
- 会計管理者

公演管理者単独ではProduction決算を確定できない。

---

# Production Closing Lock

Productionの決算確定後、そのProductionに属する公演会計は完全ロックする。

決算確定後は、通常の会計入力・明細修正・Journal Entryの変更・削除・公演決算内容の再編集を行わない。

決算確定後に計上漏れ・金額誤り等が発覚した場合は、Productionの決算を開き直して修正するのではなく、Organization Accounting側で調整仕訳を行う。

調整仕訳は通常のJournal Entryとして記録し、元のProduction決算の会計履歴を変更しない。

---

# Production Closing Result

決算確定時点のBS / PLは、Journal Entryを正本として算出する。

決算確定時点のBS / PLを別の会計正本データとして二重管理しない。

決算確定後も、確定時点の会計状態をJournal EntryおよびProductionとの関連から再現できる構造とする。

---

# Balance Sheet

BSはA4用紙1枚程度に収まることを基本とする。

表示は左右2列とする。

```text
┌──────────────────────┬──────────────────────┐
│ 資産                 │ 負債 / 純資産         │
│                      │                      │
│ 現金                 │ 未払金               │
│ 普通預金             │ その他負債           │
│ その他資産           │ 初期純資産           │
│                      │ 当期純損益           │
└──────────────────────┴──────────────────────┘
```

具体的な科目はAccountから集計する。

---

# Profit and Loss

PLもA4用紙1枚程度に収まることを基本とする。

収益と費用を左右2列に分けて表示する。

```text
┌──────────────────────┬──────────────────────┐
│ 収益                 │ 費用                 │
│                      │                      │
│ チケット売上         │ 会場費               │
│ その他収益           │ 宣伝費               │
│                      │ 交通費               │
│                      │ 人件費               │
│                      │ その他費用           │
└──────────────────────┴──────────────────────┘
```

当期純損益は収益と費用の差額から算出し、BSの純資産側へ反映する。

---

# Initial Net Assets

Organization登録時に初期純資産を入力する。

初期純資産はAccountingの開始時点におけるOrganizationの純資産として扱う。

その後の資産・負債・収益・費用および純資産の変動は、原則としてJournal Entryから集計する。

---

# Source of Financial Statements

BS / PLおよび実績集計はJournal Entryを正本として算出する。

Actualを別の正本データとして二重管理しない。

BS / PLはAccounting AccountとJournal Entryから集計・生成する。

---

# Audit Principle

会計明細一覧は日常運用のためのUI、Journal Entryは会計Factの正本、総勘定元帳は監査・会計確認用の帳簿として役割を分離する。

POSTED済みJournal Entryを直接編集・削除せず、訂正時には赤黒による訂正仕訳を生成することで、元の会計履歴を保持する。

---

# Business Rules

- 会計入力・経費承認からJournal Entryへ転記する前に確認ダイアログを表示する。
- 確認文言は「○○円で転記します」とし、YESで転記する。
- 立替経費は会計担当者が必要に応じて金額を修正して承認できる。
- 会計明細一覧は家計簿型の時系列UIとする。
- Journal Entryを利用者向け明細一覧から直接編集しない。
- POSTED済みJournal Entryは直接変更・削除しない。
- 修正は対象明細選択→変更金額入力→確認→赤黒訂正仕訳＋新規Journal Entryの生成で行う。
- 総勘定元帳をV1から提供する。
- 総勘定元帳はJournal Entryから生成し、直接編集できない。
- Production決算は未収金・未払金がすべて解消された時点で確定可能とする。
- Production決算の確定権限はPrimary Managerと会計管理者とする。
- Production決算確定後は公演会計を完全ロックする。
- 決算後の修正はProduction決算を開き直さず、Organization Accounting側の調整仕訳で行う。
- BSをA4一枚程度、資産と負債/純資産の左右2列で出力する。
- PLをA4一枚程度、収益と費用の左右2列で出力する。
- 初期純資産はOrganization登録時に入力する。
- それ以外の会計実績は原則としてJournal Entryから集計する。
- BS / PLおよびActualの正本はJournal Entryである。
