# StageArt Blueprint
# Value Object : Status

Version : 1.0

---

# Purpose

StatusはStageArt全体で利用する状態(Value Object)の設計指針を定義する。

各ドメインはBusiness Ruleに応じたStatusを持つ。

StatusはEntityの現在の状態を表現する。

---

# Concept

StatusはEntityではない。

StatusはEntityの状態を表すValue Objectである。

StatusはBusiness Ruleを表現するために存在する。

---

# Design Rules

Statusは以下のルールに従う。

- Immutableである。
- Business Ruleを表現する。
- 同じStatusは同じ意味を持つ。
- 表示名とは独立する。
- Entityの状態遷移を管理する。

---

# State Transition

Statusは状態遷移を持つ。

許可されない状態遷移はBusiness Ruleとして扱う。

例）

Draft

↓

Published

↓

Closed

↓

Archived

---

# Common Characteristics

すべてのStatusは以下を満たす。

- 一意の意味を持つ。
- Business Ruleを持つ。
- 表示文言へ依存しない。
- 実装方式へ依存しない。

---

# Domain Status

StageArtでは以下のStatusを定義する。

- MembershipStatus
- ProductionStatus
- PerformanceStatus
- ReservationStatus

将来的には

- PaymentStatus
- BudgetStatus
- ProjectStatus
- TaskStatus

などを追加する。

---

# Design Principles

- StatusはValue Objectである。
- StatusはImmutableである。
- 状態遷移はBusiness Ruleである。
- 表示名称と内部状態を分離する。
- Domainごとに専用Statusを定義する。
