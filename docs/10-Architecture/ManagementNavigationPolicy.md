# StageArt Blueprint

# Management Navigation Policy

Version : 1.2

---

# Purpose

StageArtのManagement Clientにおける団体管理・公演管理のNavigation、Dashboard、およびMemberの管理権限によるメニュー表示を定義する。

Management NavigationはTechnical Entityの構造をそのまま画面メニューへ反映するのではなく、Organization / Productionを中心とした業務Contextで構成する。

Projectは利用者向けの独立した必須Navigation単位として表示しない。

---

# Global Management Menu

基本的なManagement Clientの構成は以下を基準とする。

```text
HOME / ダッシュボード
│
├─ 団体
│   └─ 団体情報
│        ├─ メンバー
│        ├─ 権限・代理人
│        ├─ 外部リンク
│        ├─ 劇団ホームページ
│        └─ 団体規約
│
├─ 公演
│   ├─ 公演情報
│   │   ├─ 出演者・スタッフ
│   │   ├─ 会場
│   │   ├─ チケット
│   │   └─ 公演ページ
│   ├─ 稽古・予定
│   └─ 会計
│        ├─ 予算
│        ├─ 実績
│        ├─ 経費承認
│        └─ 決算
│
├─ 会計
│   ├─ 概要
│   ├─ BS / 貸借対照表
│   ├─ PL / 損益計算書
│   └─ 決算
│
├─ 通知
│
└─ 設定
```

実際の表示項目は、Organizationの利用状態およびAuthorization Scopeに応じて構成する。

---

# Dashboard

HOME / ダッシュボードはManagement Client全体を横断して現在の状況と要対応事項を確認するContextとする。

複数Productionを同時に管理できることを前提とし、Dashboardも複数公演を扱う。

基本構成：

1. 横断アラート / 要対応事項
2. 公開中Productionのカード一覧
3. 各Productionカードの次回稽古予定
4. 通知・更新情報

Productionカードは公演の状況を確認するために使用し、アラートは利用者が対応すべき事項をProduction横断で表示する。

各Productionカードには、そのProductionに紐づく次回稽古予定を表示できる。

---

# Organization Management

Organization ManagementはOrganizationそのものを管理するContextとする。

主な対象：

- Organization Profile / ホームページ公開情報
- Organization Slug
- Members / Membership
- 権限・代理人
- External Links
- Google Drive連携
- Public Page Settings
- Organization-level Setup / Settings
- 団体規約

Historical Productionの登録・編集はOrganization Managementの責務ではなく、Production Managementの責務とする。

---

# Organization Rules

団体規約はOrganization Managementから作成・管理できるものとする。

団体規約はStageArtが提供するひな形を起点として作成できる構造とする。

基本Flow：

```text
団体規約
  ↓
ひな形を選択
  ↓
団体情報を差し込み
  ↓
必要箇所を編集
  ↓
文書として出力
```

ひな形は劇団運営に一般的な規約項目を含むことを想定するが、団体ごとに内容を編集できるものとする。

StageArtは規約の法的有効性を保証するものではなく、団体運営用の文書作成支援・たたき台として提供する。

---

# Production Management

Production Managementは、Productionを管理するための主要なManagement Contextとする。

基本的な公演情報には以下を含む。

- ホームページ公開情報
- 公演肩書
- 公演タイトル
- Slug
- Google Drive連携
- 出演者・スタッフ
- 会場
- チケット
- 公演ページ

Google Drive連携はOrganization設定を既定値として継承できる。特定のProductionでは、必要に応じてOrganizationとは異なるGoogle Driveアカウントへ置き換え可能とする。

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

# Partial Historical Registration

Historical Production Migrationでは、StageArt導入前の資料が完全ではないことを前提とする。

そのため、登録画面は過去資料から確認できる範囲だけを入力できる構造とする。

不足している情報を架空の値で補完してMigrationを完了させてはならない。

詳細なHistorical MigrationルールはHistorical Production Migration Policyに従う。

---

# Production Context

Production ManagementでProductionを選択した後は、そのProductionをContextとして以下のManagementへ遷移できる構造を基本とする。

```text
Production
    ↓
- 公演情報
- 稽古・予定
- 会計
    - 予算
    - 実績
    - 経費承認
    - 決算
```

実際の利用可能なOperationは、Backendの実装状況およびAuthorizationによって決定する。

---

# Organization Accounting vs Production Accounting

Accountingは二つのContextから利用する。

**Production Context**では、その公演単体の会計を管理する。

- 予算
- 実績
- 経費承認
- 決算

**Organization Context**では、団体全体を横断する会計を確認・管理する。

- 概要
- BS / 貸借対照表
- PL / 損益計算書
- 決算

Production AccountingをOrganization Accountingの単なる別名として扱わない。

Organization-level BS / PLは、Organization全体の会計状況を財務諸表として確認するための機能とする。

BS / PLはManagement Client上で表示できるだけでなく、出力できる構造を前提とする。

出力形式の詳細は別途Output / Reporting設計で確定する。

会計を使用していないOrganizationでは、Accountingを通常の主要メニューとして表示しない。

会計を開始する場合はOrganization Setup / Accounting Setupの所定のFlowから開始し、開始時点の流動資産を登録する。

会計開始後に会計機能を無効化する通常のToggleを提供しない。

---

# Member Roles and Management Permissions

基本Roleと担当管理権限を区別する。

基本Roleは以下とする。

- Primary Manager
- Production Delegate
- Member

Member登録・編集画面では、追加の担当管理権限をチェックボックスで付与できる。

- 稽古管理者
- 会計管理者

稽古管理者と会計管理者の両方を同一Memberへ付与することを許可する。

担当管理権限を複雑なRole階層として別管理せず、Memberに付与する明示的な権限として扱う。

---

# Management Menu by Permission

権限に応じてManagement Clientのメニューを表示する。

```text
Primary Manager
├─ 団体
├─ 公演
│   ├─ 会計
│   └─ 稽古・予定
├─ 会計
├─ 通知
└─ 設定

稽古管理者
├─ 団体
├─ 公演
│   └─ 稽古・予定
├─ 通知
└─ 設定

会計管理者
├─ 団体
├─ 公演
│   └─ 会計
├─ 会計
├─ 通知
└─ 設定

一般Member
├─ 団体
├─ 公演
├─ 通知
└─ 設定
```

Primary Managerは全管理機能を利用できる。

稽古管理者は稽古・予定の管理機能を利用できる。

会計管理者はProduction会計およびOrganization横断会計の管理機能を利用できる。

一般Memberには管理者向けの稽古管理・会計管理メニューを表示しない。

---

# Role and UI Principle

Roleごとに別のManagement UIを作らない。

Primary Manager、Production Delegate、Member、および追加の稽古管理者・会計管理者権限は、同一のManagement Clientを利用する。

権限に応じて表示されるメニューおよび操作可能な機能だけが異なる構造とする。

メニュー非表示はUI上の利便性のための制御であり、認可そのものの代替ではない。

Backendは各API / UseCaseで実際の権限を必ず検証する。

```text
Frontend
  └─ 権限のないメニュー・操作を表示しない

Backend
  └─ 権限のない操作を実行させない
```

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
- 最上位NavigationはHOME / 団体 / 公演 / 会計 / 通知 / 設定を基本とする。
- Projectは利用者向けの独立した必須Navigation単位として表示しない。
- ProductionはProduction Managementの主要Contextである。
- StageArt利用開始以前の過去公演の登録入口はProduction Managementに置く。
- Historical Productionを通常のProductionと別Entityとして扱わない。
- 通常の新規公演登録とHistorical Production MigrationはUI上の入口を分ける。
- Historical Migrationでは過去Lifecycleを最初から再演する必要はない。
- Historical Migrationでは不足情報を架空の値で補完してはならない。
- Historical Productionの登録・編集をOrganization Managementの責務としない。
- Production Informationにはホームページ公開情報、肩書、タイトル、Slug、Google Drive連携等を含む。
- ProductionのGoogle Drive連携はOrganization設定を継承でき、Production単位で置き換え可能とする。
- Organization Managementには団体規約の作成・編集・出力機能を置く。
- 団体規約はStageArt提供のひな形から作成できる。
- 団体規約は団体ごとに編集可能とする。
- 団体規約は法的有効性をStageArtが保証するものではない。
- Organization AccountingとProduction Accountingを別Contextとして扱う。
- Organization Accountingでは概要、BS、PL、決算を扱う。
- Organization-level BS / PLは表示および出力を可能とする。
- BS / PLの具体的な出力形式は別途定義する。
- Member登録・編集画面で稽古管理者・会計管理者をチェックボックスで付与できる。
- 稽古管理者・会計管理者の両方を同一Memberへ付与できる。
- 権限のない管理機能はManagement Clientのメニューから表示しない。
- メニュー非表示とは別にBackendで認可を実施する。
- Dashboardは複数Productionを前提とする。
- DashboardのProductionカードには次回稽古予定を表示する。
- DashboardのアラートはProductionを横断して表示する。
- Feature Toggleを過剰に増やさず、Core / Option / Preferenceの区分を維持する。
