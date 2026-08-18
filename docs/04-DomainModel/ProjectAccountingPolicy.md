# StageArt Blueprint

# Domain Model : Project Accounting Policy

Version : 1.0

---

# Purpose

StageArtの会計情報は、Organization / Project / Productionの各単位で参照できるものとする。

各階層で目的を分け、利用者が「団体全体」「企画全体」「個別公演」の財務状況をそれぞれ確認できる構造とする。

---

# Accounting Scope Hierarchy

基本構造：

Organization
    ↓
Project
    ↓
Production

会計情報は、これらのScopeに応じて集計・表示する。

---

# Organization Accounting

Organization単位では、団体全体の財務状況を確認する。

Organization Accountingは、Journal Entryを正本とする団体全体の会計情報を扱う。

主な用途：

- 現金・預金等の流動資産の確認
- 団体全体の収益・費用の確認
- 団体全体の財務状態の確認
- Organization全体の会計管理

Organization Accountingは、特定のProductionだけを対象とするProduction決算とは異なる。

---

# Project Accounting

Projectは、一つ以上のProductionを束ねる活動・企画単位として扱う。

Projectには、複数Productionを所属させることができる。

例：

Project「河童ホームラン2027」
    ├─ Production「東京公演」
    └─ Production「大阪公演」

Project Accountingでは、Project全体の予算と実績を確認することを基本とする。

主な用途：

- Project全体の予算管理
- Project全体の予実管理
- 複数Productionを含む企画全体の収支確認
- Production間を横断した計画と実績の比較

Projectに複数Productionが存在する場合、それらをまとめた企画全体の予実を確認できるものとする。

---

# Production Accounting

Productionは、具体的な公演実施単位である。

Production Accountingでは、個々の公演について最終的な実績を確認することを基本とする。

主な用途：

- Production単位の予算確認
- Production単位のActual確認
- Production単位の最終収支確認
- 公演ごとの決算確認

Productionの実績は、Accounting DomainにおけるJournal Entryを基礎として集計する。

---

# Budget and Actual by Scope

基本的な見せ方は以下とする。

| Scope | 主な用途 |
|---|---|
| Organization | 団体全体の財務状況 |
| Project | Project全体の予実管理 |
| Production | 個別公演の決算・収支確認 |

Budgetは計画値、Actualは実績値として扱う。

Actualの正本はJournal Entryであり、ScopeごとのActual表示はJournal Entryから集計する。

---

# Project Budget

Projectには、Project全体を対象とするBudgetを保持できる。

Project Budgetは、複数Productionを含む企画全体の計画を表す。

例えば、東京公演と大阪公演を一つのProjectとして管理する場合、Project Budgetでは両公演を含む企画全体の予算を管理できる。

Production単位のBudgetとは別の計画単位として扱う。

---

# Production Budget

Productionには、Production単位のBudgetを保持できる。

Production Budgetは、個別公演の計画を表す。

Projectに複数Productionが存在する場合、各ProductionのBudgetを個別に管理できる。

Project全体の予算とProductionごとの予算は、同じBudgetを二重管理するのではなく、それぞれの計画Scopeとして扱う。

---

# Project Actual

Project Actualは、Projectに所属するProduction等の実績をProject Scopeで集計して確認する。

Actualの正本をProjectに別途二重保存するのではなく、Accounting Journal Entry等の正本データからProject Scopeに応じて集計することを基本とする。

---

# Production Settlement

Production Settlementは、個々のProductionについて最終的な収支を確認するための概念とする。

Productionの終了後、実際に発生した収益・費用を確認し、Production単位の決算として表示できるようにする。

Production SettlementはJournal Entryを正本として集計する。

Production Settlement専用の会計帳簿を別に持つことはしない。

---

# Cross Scope Consistency

Organization / Project / Productionで表示される会計情報は、同一のAccounting正本を異なるScopeで集計した結果として整合する必要がある。

同一のActualをOrganization、Project、Productionで別々に入力して二重管理してはならない。

例えばProduction「東京公演」で発生したJournal Entryは、該当するProjectおよびOrganizationのScopeから集計可能であることを基本とする。

---

# User Interface Principle

StageArtでは、利用者が必要な粒度で会計情報を確認できるようにする。

通常の公演管理ではProductionを中心に表示する。

Project Accountingは、複数Productionをまとめて管理する必要がある場合に利用する。

Organization Accountingは、団体全体の財務状況を確認するために利用する。

Projectという内部概念を、通常のProduction管理において過度に意識させないUIとする。

---

# Example

Project：河童ホームラン2027

Project Budget：
- 収入 ¥2,000,000
- 支出 ¥1,500,000
- 計画収支 ¥500,000

Production：東京公演
- Budget ¥800,000
- Actual ¥850,000
- Settlement -¥50,000

Production：大阪公演
- Budget ¥700,000
- Actual ¥600,000
- Settlement +¥100,000

この場合、Project Scopeでは東京・大阪を含む企画全体の予実を確認できる。

Production Scopeでは、東京公演・大阪公演それぞれの決算を確認できる。

---

# Design Principle

StageArtの会計表示は、以下の役割分担を基本とする。

**Organization = 団体全体の財務状況**

**Project = 企画全体の予実管理**

**Production = 個別公演の決算・収支確認**

この役割分担により、通常の単独公演から、東京・大阪等の複数Productionを含む大きなProjectまで同一のDomain Modelで扱えるようにする。
