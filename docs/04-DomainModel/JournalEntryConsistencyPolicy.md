# StageArt Blueprint

# Domain Consistency Policy : Journal Entry

Version : 1.0

---

# Purpose

Journal EntryをStageArt会計の正本Factとして位置付け、Organization / Project / Production、Account、Budget、Reservation、CheckInとの整合性を定義する。

---

# Canonical Role

Journal Entryは、確定した会計上の取引Factである。

Accountは分類マスタ、Budgetは計画、ActualはJournal Entryから算出される集計値であり、Journal Entryとは異なる。

---

# Canonical Accounting Flow

チケット売上については以下をCanonical Flowとする。

Reservation
    ↓
未収金
    ↓
Check In
    ↓
CheckInCompleted
    ↓
Ticket Revenue Recognition
    ↓
Journal Entry

Reservation成立時点では売上を認識しない。

来場してCheck Inが完了した時点で売上を認識する。

---

# Receivable Before Check In

Reservation成立後、Check In前は未収金として扱う。

未収金の具体的な会計処理は決済手段・Accounting Domainのルールに従うが、Ticket Revenue自体はCheckInCompleted前には認識しない。

No-Show等でCheckInCompletedが発生しない場合、通常のTicket Revenue Recognitionを生成しない。

---

# Revenue Recognition

CheckInCompletedはTicket Revenue Recognitionの契機となる。

一つのCheckInCompletedから、対応するTicket Revenue Journal Entryを高々一件生成する。

同一イベントの再処理による二重売上計上を防止する。

---

# Journal Entry Structure

Journal Entryは複数のJournal Entry Lineから構成される。

各Lineは、Account、Debit/Credit、Amount等を保持する。

確定可能なJournal Entryは、借方合計と貸方合計が一致しなければならない。

---

# Ticket Revenue Example

Check In時に3,000円のTicket Revenueを認識する場合、例えば以下のような仕訳を生成する。

Debit
  決済手段に対応するAsset / Receivable Account 3,000

Credit
  チケット売上 3,000

実際の決済手段が現金、預金、カード等のいずれであるかに応じてDebit側Accountを決定する。

---

# Price Snapshot

Ticket Revenueの金額はReservationに保存されたPrice Snapshotを正本として使用する。

現在のTicket Master価格を再参照して過去Reservationの金額を再計算してはならない。

GuestCountも必要な業務情報として保持するが、過去の取引価格を現在のTicket設定から再計算することはしない。

---

# Scope Relationship

Journal Entryは必ず一つのOrganizationに属する。

Project / Productionに関連するJournal Entryは、可能な限りそのScopeを追跡できる情報を保持する。

基本構造：

Organization
    ↓
Project
    ↓
Production
    ↓
Journal Entry

ただし、Journal EntryをProjectやProductionへ別会計帳簿として二重保存することはしない。

---

# Organization Accounting

Organization Accountingは、Organizationに属するJournal Entryを集計する。

団体全体の財務状況、収益、費用等を確認するためのScopeである。

---

# Project Accounting

Project Accountingは、Projectに関連するJournal EntryをScopeとして集計する。

複数Productionを含むProjectについて、企画全体のActualを確認できる。

Project ActualはJournal Entryから算出し、独立した正本Factとして二重保存しない。

---

# Production Accounting

Production Accountingは、Productionに関連するJournal EntryをScopeとして集計する。

個別公演の実績および最終的な収支を確認する。

Production SettlementはJournal Entryとは別の会計帳簿ではない。

---

# Budget Relationship

Budgetは計画、Journal Entryは実績Factとして責務を分離する。

Project Budget / Production BudgetとJournal Entryを同一Account体系で比較し、予実を算出する。

Actual、Variance、Planned Profit等はJournal EntryまたはBudget Lineから計算し、独立した会計Factとして二重保存しない。

---

# Account Relationship

Journal Entry LineはOrganizationに所属するAccountを参照する。

AccountはJournal Entryを所有しない。

AccountがINACTIVEになった場合でも、過去のPOSTED Journal Entryの参照整合性を失わせない。

---

# Posting Rules

POSTEDにできるJournal Entryは少なくとも以下を満たす。

- Organizationが正しい
- Journal Dateが有効
- Journal Entry Lineが存在する
- Debit / Creditが正しい
- Total Debit = Total Credit
- Accountが有効または過去Fact参照として許容される
- Accounting Periodが有効

POSTED後は通常編集しない。

---

# Reversal and Correction

POSTED Journal Entryを訂正する場合、元の仕訳を変更・削除しない。

Reversal Journal Entryを生成し、必要に応じてCorrect Journal Entryを追加する。

Original → Reversal → Correctionの履歴を追跡可能にする。

---

# Idempotency

Business EventからJournal Entryを自動生成する処理はIdempotentでなければならない。

特にCheckInCompletedについて、同一Source EventからTicket Revenueを二重計上してはならない。

Source Event ID等を利用して重複生成を防止する。

---

# Audit

Journal Entryは会計監査の対象となるため、作成者、作成日時、Posting者、Posting日時、Reversal情報等を追跡可能とする。

POSTED Journal Entryの履歴を破壊してはならない。

---

# Design Principle

StageArt会計の正本はJournal Entryである。

```text
Account = 分類マスタ
Budget = 計画
Journal Entry = 実績Fact
Actual = Journal Entryからの集計
```

チケット売上については、**予約ではなく来場・Check Inを売上認識の契機とする**。

これをOrganization / Project / Productionすべての会計Scopeで共通ルールとする。
