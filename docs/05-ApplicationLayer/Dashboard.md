# StageArt Blueprint

# Application Specification : Production Dashboard

Version : 1.0

---

# Purpose

Production Dashboardは、現在選択しているProduction（公演）について、運営者が「現在の公演状況」「予約状況」「会計状況」「直近の予定」を一画面で把握するための集約表示画面とする。

Dashboard自身は業務Factを保持せず、各Domainの正本データから集計・表示する。

---

# Scope

Dashboardの基本コンテキストはProductionとする。

主な集計対象：

- Production
- Performance
- Ticket
- Reservation
- Budget
- JournalEntry
- 公開予約等の予定情報

---

# Layout

管理画面には一文字幕を使用しない。

管理画面は左サイドメニューを基本ナビゲーションとし、Dashboardのメイン領域は以下の順序で構成する。

```text
Dashboard Header
    ↓
お知らせ・アラート
    ↓
今日・直近の予定
    ↓
2 × 2 Dashboard Panels
    ├── 各公演回の空席状況
    ├── チケット予約推移
    ├── 会計予実
    └── 予約ベース収支
```

上部の「お知らせ・アラート」「今日・直近の予定」は画面幅を使用する横長エリアとする。

その下の主要情報は2列×2段のパネルとして表示する。

---

# Panel 1 : Performance Capacity

## Purpose

各Performanceの現在の予約状況と空席を把握する。

## Display

最低限、以下を表示する。

- 公演日時
- 定員
- 予約人数
- 空席数
- 予約率
- 完売状態

例：

| 公演回 | 定員 | 予約 | 空席 | 予約率 |
|---|---:|---:|---:|---:|
| 10/10 14:00 | 100 | 82 | 18 | 82% |
| 10/11 14:00 | 100 | 96 | 4 | 96% |
| 10/11 19:00 | 100 | 73 | 27 | 73% |
| 10/12 14:00 | 100 | 100 | 0 | 完売 |

## Calculation

```text
有効予約人数
= RESERVED + CHECKED_IN のGuestCount合計

空席数
= Performance Capacity - 有効予約人数

予約率
= 有効予約人数 / Performance Capacity
```

CANCELLEDは定員計算から除外する。

CHECKED_INは公演終了まで有効予約人数に含める。

Capacityの設定値はPerformanceを正本とし、Productionの標準予約定員から継承された値またはPerformanceでOverrideされた値を使用する。

完売時は100%表示ではなく「完売」と表示できる。

Panelを選択した場合、対象Performanceの管理画面またはReservation一覧へ遷移できる。

---

# Panel 2 : Ticket Reservation Trend

## Purpose

チケット予約が受付開始後どのように積み上がってきたかを把握する。

## Display

累計予約枚数の時系列グラフを基本とする。

- 横軸：日付
- 縦軸：累計有効予約枚数

グラフ上部に以下を表示する。

- 現在予約枚数
- Production全体の総定員
- 現在予約率
- 予約受付開始日

## Calculation

予約推移は、その時点で有効だったReservationの枚数を時系列に反映する。

予約成立時に加算し、キャンセル時に減算する。

したがって、キャンセルされた予約を現在の予約枚数に含めない。

このグラフは将来の予約数を予測するものではない。

## Comparison

前年Production、過去Production、目標値等との比較は将来拡張可能な設計とするが、初期版では現在Productionの予約推移を基本とする。

Panelを選択した場合、Reservation一覧へ遷移できる。

---

# Panel 3 : Accounting Actual vs Budget

## Purpose

Productionの会計について、Budgetと確定実績を比較する。

## Display

最低限、以下を表示する。

| 区分 | 予算 | 実績 | 差異 |
|---|---:|---:|---:|
| 収入 | Budget | Actual | Actual - Budget |
| 支出 | Budget | Actual | Actual - Budget |
| 収支 | Budget | Actual | Actual - Budget |

必要に応じて、収入・支出のカテゴリ別内訳を参照できる。

## Data Source

```text
Budget
    ↓
Budget Plan

JournalEntry
    ↓
Accounting Actual
```

Dashboardは会計Factを独自に保存しない。

確定実績はJournalEntryを正本とする。

## Navigation

PanelからBudget / Accounting画面へ遷移できる。

---

# Panel 4 : Reservation-Based Balance

## Purpose

「現時点で予約されているチケットの予約者が全員来場した場合」の収支を把握する。

これは将来の予約増加を予測するForecastではない。

UI上の正式名称は「予約ベース収支」とする。

## Definition

予約ベース収支とは、現時点で有効なReservationに基づくTicket収入と、現時点で確定している収入・支出を用いて算出した現在地点の収支である。

## Ticket Revenue

```text
予約ベースチケット収入
= 現在有効なReservationのPrice Snapshot合計
```

Reservation成立時点のPrice Snapshotを使用し、現在のTicket価格から再計算しない。

CANCELLED Reservationは含めない。

## Overall Calculation

```text
予約ベース収入
= 予約ベースチケット収入
  + 現時点で確定しているその他収入

予約ベース支出
= 現時点で確定している支出

予約ベース収支
= 予約ベース収入 - 予約ベース支出
```

必要に応じてBudgetとの比較を表示する。

例：

```text
予約ベース収支

収入       ¥490,000
支出       ¥300,000
──────────────
収支       ¥190,000

予算収支   ¥230,000
差異       -¥40,000
```

## Explanation

画面上に以下の説明を表示する。

> 現在の予約者が全員来場した場合の収支です。

「公演日までにあと何枚売れるか」等の将来予測は行わない。

PanelからBudget / Accounting画面へ遷移できる。

---

# Alerts

Dashboard上部に、お知らせ・アラートを表示する。

これは単なる通知一覧ではなく、運営者が把握・対応すべき情報を優先して表示する。

想定例：

- Performance完売
- Performance残席少
- 情報公開予定
- 会計上の注意事項
- その他StageArt上で対応が必要な状態

優先度は、概念上以下の順序とする。

```text
要対応
↓
注意
↓
情報
```

該当する情報がない場合は、対応項目がないことを明示する。

Alertを選択すると、原因となっている管理画面へ遷移できる。

---

# Upcoming Schedule

Dashboard上部に「今日・直近の予定」を表示する。

公演期間中はPerformanceを優先して表示する。

表示例：

```text
今日
10/11 14:00　河童ホームラン　予約 96 / 100
10/11 19:00　河童ホームラン　予約 73 / 100

明日
10/12 14:00　河童ホームラン　予約 100 / 100
```

Performance以外にも、StageArt上で期限・日時を持つ重要イベントを表示できる。

例：

- 情報公開日時
- チケット販売開始
- 公演日時

Calendar DomainをDashboardに統合するものではなく、直近の重要予定を把握するための集約表示とする。

予定を選択すると関連する管理画面へ遷移できる。

---

# Dashboard Navigation

Dashboardは管理操作そのものを集約するのではなく、状況把握から各管理機能へ遷移する入口とする。

| Dashboard Item | Navigation Target |
|---|---|
| Performance Capacity | Performance管理 / Reservation一覧 |
| Reservation Trend | Reservation一覧 |
| Accounting Actual vs Budget | 会計 / Budget |
| Reservation-Based Balance | 会計 / Budget |
| Alert | 該当する管理画面 |
| Upcoming Schedule | 該当Performance等 |

---

# Data Responsibility

Dashboardは集計表示層であり、以下のような業務Factを独自に保持しない。

```text
Performance
    ↓
Reservation
    ↓
Capacity / Reservation Trend

Budget
    ↓
Budget Actual Comparison

JournalEntry
    ↓
Accounting Actual

Reservation + Price Snapshot + JournalEntry
    ↓
Reservation-Based Balance
```

Dashboard用のCacheやMaterialized View等を導入する場合は、PerformanceやAccounting等の正本を置き換えない。

---

# Non-Goals

初期Dashboardでは以下を提供しない。

- 自由形式のBI分析
- 利用者による任意グラフ作成
- 複雑な経営指標
- AIによる公演評価・経営分析
- Dashboard上での会計仕訳直接操作
- 将来の予約数を予測するForecast

Dashboardは「現在の公演状態を短時間で把握し、必要な管理画面へ移動する」ことに集中する。

---

# Canonical Component Structure

```text
ProductionDashboard
    ├── DashboardHeader
    ├── AlertSummary
    ├── UpcomingSchedule
    └── DashboardGrid
          ├── PerformanceCapacityPanel
          ├── TicketReservationTrendPanel
          ├── AccountingActualVsBudgetPanel
          └── ReservationBasedBalancePanel
```

各Panelは対応するDomainの正本データから集計し、Dashboardはそれらを横断して表示する。

---

# Design Principle

Dashboardは「何でも見られる画面」ではなく、Production運営に必要な情報を一画面で把握するためのOperational Dashboardとする。

特に、

1. 今どの公演回が埋まっているか
2. 予約がどのように増えているか
3. 会計の予算と確定実績がどうなっているか
4. 現時点の予約者が全員来場した場合、収支がどうなるか
5. 今日・直近に何があるか
6. 対応が必要なことがあるか

を最優先情報とする。
