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

チケット売上相当額については、会計仕訳の生成と来場実績の記録を分離し、以下をCanonical Flowとする。

標準の当日精算：

Reservation
    ↓
Price Snapshot（予約金額の確定。予約成立だけでは仕訳なし）

当日受付で実際に代金を受領 ──→ Payment Fact ──→ 入金・Ticket Revenueの同時計上
                │
                └── Check In ──→ CheckInCompleted（来場実績Fact）

スタッフ事前集金：スタッフが観客から集金しただけでは劇団側の受領・売上成立としない。受付担当者がスタッフから現金を受領し、Reservationの支払済みを確定した時点でPayment Factを記録し、入金とTicket Revenueを同時に計上する。後日のCheckInCompletedは来場記録のみとし、再計上しない。

会計期間末（Production決算未確定の場合）
    ↓
既存の売上Journal Entryを仮売上へ振替
    ↓
Production決算確定時
    ↓
仮売上を正式な売上へ振替

予約成立時点では売上・入金・未収金を計上しない。CheckInCompleted単独では仕訳を生成せず、実際の代金受領を示すPayment Factを根拠に入金とTicket Revenueを同時に計上する。

Check In時点では仮売上への振替を行わない。会計期間末に決算未確定Productionの売上残高を仮売上へ振り替え、Production決算確定時に正式な売上へ振り替える。

---

# Receivable Before Check In

標準の当日精算運用では、Reservation成立後からCheck In前まで、予約があるという理由だけで未収金を計上しない。

Check In時に実際に代金を受領した場合は、Payment Factに基づき入金とTicket Revenueを同時に計上する。CheckInCompleted単独では仕訳を生成せず、仮売上への振替は会計期間末に行う。

No-Show等の扱いは既存のTicket取引・取消ルールに従う。CheckInCompletedの有無だけを仮売上振替の判定条件にしない。

---

# Revenue Recognition

仮売上への振替は会計期間末に行う。CheckInCompletedを仮売上仕訳の契機にしない。正式な売上への振替はProduction決算確定時に行う。

CheckInCompletedから仮売上Journal Entryを直接生成しない。会計期間末の仮売上振替は、対象Productionおよび元の売上Journal Entryを基に一意に生成する。

同一イベントの再処理による二重売上計上を防止する。

---

# Journal Entry Structure

Journal Entryは複数のJournal Entry Lineから構成される。

各Lineは、Account、Debit/Credit、Amount等を保持する。

確定可能なJournal Entryは、借方合計と貸方合計が一致しなければならない。

---

# Ticket Revenue Example

例：当日Check In時に3,000円を現金で受領した場合

Debit：現金 3,000円
Credit：チケット売上 3,000円

実際の決済手段に応じてDebit側Accountを選択する。これはPayment Factに基づく入金・売上の同時計上例であり、CheckInCompleted単独では仕訳を生成しない。会計期間末の仮売上振替仕訳は別途生成する。

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

CheckInCompletedの再処理で来場Factを重複生成してはならない。入金・売上仕訳はPayment Factを根拠とし、同一Payment FactからTicket Revenueや入金を二重計上してはならない。Source Event ID等を利用して重複生成を防止する。

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

チケット会計について、予約成立だけでは売上・入金・未収金を計上しない。標準運用では当日Check In時に実際に代金を受領した時点で、Payment Factに基づき入金とTicket Revenueを同時に計上する。CheckInCompleted単独では仕訳を生成しない。仮売上への振替は会計期間末、正式な売上への振替はProduction決算確定時に行う。

これをOrganization / Project / Productionすべての会計Scopeで共通ルールとする。
