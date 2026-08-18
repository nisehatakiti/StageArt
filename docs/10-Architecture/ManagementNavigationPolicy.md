# StageArt Blueprint

# Management Navigation Policy

Version : 1.0

---

# Purpose

StageArtのManagement Clientにおける団体管理・公演管理のNavigationと、StageArt利用開始以前の過去公演を登録するための導線を定義する。

Management NavigationはTechnical Entityの構造をそのまま画面メニューへ反映するのではなく、Organization / Productionを中心とした業務Contextで構成する。

---

# Management Menu

基本的な団体管理メニューは以下を基準とする。

- Home
- Production Management
- Rehearsal / Schedule Management
- Ticket / Reservation
- Accounting
- Organization Management
- Settings

実際の表示項目は、Organizationの利用状態およびAuthorization Scopeに応じて構成する。

---

# Production Management

Production Managementは、Productionを管理するための主要なManagement Contextとする。

基本メニュー：

- Production List
- Create New Production
- Migrate Historical Production
- Production Detail / Management

Historical Productionは通常のProductionとは別Entityとして扱わないため、過去公演の登録入口もProduction Managementに置く。

---

# New Production vs Historical Migration

通常の新規公演登録とStageArt利用開始以前の過去公演登録は、UI上の入口を分ける。

Create New Production：

新しく実施する公演をStageArt上で準備・管理するための通常のProduction作成。

Migrate Historical Production：

StageArt利用開始以前に実施済みの公演を、既存資料からProductionとして登録するためのMigration入口。

Historical Migrationは、過去のLifecycleを最初から再演するための操作ではない。

---

# Historical Production Registration

「過去公演を登録」はProduction Managementから実行できるものとする。

基本Flow：

Production Management
    ↓
Migrate Historical Production
    ↓
Historical Production Migration
    ↓
Productionとして登録
    ↓
必要に応じてPublic Visibilityを設定

団体管理メニューにHistorical Production専用の登録入口を設けることを基本としない。

---

# Partial Historical Registration

Historical Production Migrationでは、StageArt導入前の資料が完全ではないことを前提とする。

そのため、登録画面は過去資料から確認できる範囲だけを入力できる構造とする。

不足している情報を架空の値で補完してMigrationを完了させてはならない。

詳細なHistorical MigrationルールはHistorical Production Migration Policyに従う。

---

# Organization Management Boundary

Organization ManagementはOrganizationそのものを管理するContextとする。

主な対象：

- Organization Profile
- Organization Slug
- Members / Membership
- Public Page Settings
- SNS / External Integration Settings
- Contact Settings
- Organization-level Setup / Settings

Historical Productionの登録・編集はOrganization Managementの責務ではなく、Production Managementの責務とする。

---

# Production Context

Production ManagementでProductionを選択した後は、そのProductionをContextとして以下のManagementへ遷移できる構造を基本とする。

Production
    ↓
- Overview
- Rehearsal / Schedule
- Timetable
- Participants
- Performances
- Ticket / Reservation
- Survey
- Public Page
- Accounting (when applicable)

実際の利用可能なOperationは、Backendの実装状況およびAuthorizationによって決定する。

---

# Accounting Visibility

AccountingはOrganization単位の会計利用状態に従ってManagement Navigationへ反映する。

会計を使用していないOrganizationでは、Accountingを通常の主要メニューとして表示しない。

会計を開始する場合はOrganization Setup / Accounting Setupの所定のFlowから開始し、開始時点の流動資産を登録する。

会計開始後に会計機能を無効化する通常のToggleを提供しない。

---

# Core and Optional Functions

Management Navigationは、すべての機能を初期状態から同列に表示する設計としない。

- Core機能は基本Navigationから利用できる。
- External Integration等のOption機能は設定後に利用できる。
- NotificationのON/OFFは既定のPreferenceとして扱う。
- OrganizationごとのSetup状態により、未設定の機能について適切なSetup導線を表示できる。

不要なFeature Toggleを増やし、団体ごとに多数の機能をON/OFF管理させる設計を基本としない。

---

# Business Rules

- Management NavigationはBusiness Contextを基準に構成する。
- ProductionはProduction Managementの主要Contextである。
- StageArt利用開始以前の過去公演の登録入口はProduction Managementに置く。
- Historical Productionを通常のProductionと別Entityとして扱わない。
- 通常の新規公演登録とHistorical Production MigrationはUI上の入口を分ける。
- Historical Migrationでは過去Lifecycleを最初から再演する必要はない。
- Historical Migrationでは不足情報を架空の値で補完してはならない。
- Historical Productionの登録・編集をOrganization Managementの責務としない。
- AccountingのNavigation表示はOrganizationの会計利用状態に従う。
- 一度開始した会計機能を通常のON/OFF Toggleで無効化する設計を基本としない。
- Feature Toggleを過剰に増やさず、Core / Option / Preferenceの区分を維持する。
