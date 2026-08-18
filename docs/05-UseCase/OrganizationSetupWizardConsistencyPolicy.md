# StageArt Blueprint

# Use Case Consistency Policy : Organization Setup Wizard

Version : 1.0

---

# Purpose

Organization初回登録時のSetup Wizardについて、現在までに確定したOrganization Domain、Authentication / Authorization、External Integration、Accounting、Media仕様との整合性を定義する。

---

# Canonical Principle

Organization登録時の実行Userを、そのOrganizationの代表者兼Ownerとして登録する。

初回Setupでは代表者を別途選択する操作を設けない。

```text
Authenticated User
      ↓
Create Organization
      ↓
Organization Owner
```

Ownerは後続のOrganization Membership / Authorization仕様に従って変更・移譲可能とするが、初回登録時点では実行Userを固定する。

---

# Setup Wizard Scope

初回Organization登録は、以下を一連のWizardで完了させる。

1. 外部アカウント連携・基本環境設定
2. 団体基本情報
3. 団体公開情報
4. 会計管理設定
5. 会計ON時の初期流動資産
6. 登録完了

初回登録後に変更可能な設定については、Wizard内で「後から変更可能」であることを明示する。

---

# Step 1 : External Connection and Environment

最初の画面で、Google Accountとの連携を行えるようにする。

同一画面で以下を設定する。

- Google Account Connection
- Timezone
- Notification Settings

Google Account連携はExternal Identity / Integrationとして扱い、StageArt UserAccountそのものをGoogle Accountと同一視しない。

TimezoneはOrganizationの基準Timezoneとして保存する。

Notification Settingsは初期値を設定できるが、後から変更可能とする。

Google Account Connection、Timezone、Notificationは同一画面で初回設定できるものとする。

---

# Google Account Connection

Google Account連携は、Google DriveをDocument Canonical Sourceとして利用するためのIntegration基盤として扱う。

連携時には必要なOAuth権限のみを要求する。

Googleの認証CredentialやAccess TokenをStageArtの通常Business Dataとして平文保存しない。

具体的なCredential StorageはIntegration / Security Architectureに従う。

Google連携を後から解除・再接続できる設計を基本とする。

---

# Timezone

OrganizationにはTimezoneを保持する。

Timezoneは以下のDomainで共通利用する。

- Production日時
- Performance日時
- Reservation関連の表示
- Check In日時表示
- Accounting Date / Period表示
- Notification Schedule

初期値は利用環境から合理的に提示できるが、最終的にはUserが確認・変更できる。

Timezone変更によって保存済みのUTC等のFact Timestampを破壊的に変換してはならない。

---

# Notification

Organization Setup時にNotification設定を行えるようにする。

Notificationは後から変更可能とする。

具体的な通知種類・Channel・FrequencyはNotification Domainで定義する。

Setup Wizardでは、通知設定の詳細を過度に増やさず、初期利用に必要な基本設定を行える構成とする。

---

# Step 2 : Organization Basic Information

団体登録画面では少なくとも以下を入力する。

- 団体名
- Organization Slug
- 団体説明
- 団体Logo

Organization SlugはPublic URL等のOrganization識別子として利用する。

LogoはMedia Policyに従い1個まで登録できる。

---

# Organization Slug

Organization SlugはOrganization内で一意とする。

Public URLの基本形は、

`/StageArt/[Organization Slug]/`

とする。

Slug変更はPublic URLへの影響を伴うため、通常の表示名変更とは別の管理操作として扱う。

---

# Organization Owner

Organizationを作成したAuthenticated Userを初期Ownerとする。

OwnerはOrganizationの初期設定、Membership承認、公開設定等のOrganization-level Operationを行う権限を持つ。

具体的なPermissionはRole Authorization Policyに従う。

---

# Step 3 : Accounting Enablement

Organization登録途中で、会計管理を有効にするか確認する。

選択肢：

- 会計管理を有効にする
- 会計管理を有効にしない

会計管理は後から変更可能であることを画面上で明示する。

会計をOFFにした場合、初回Wizardでは流動資産残高の入力を要求しない。

---

# Step 4 : Initial Liquid Assets

会計管理をONにした場合、初期流動資産を入力する。

最低限、以下を分けて入力する。

- 現金
- 預金

現時点では預金を一つの初期残高として扱い、金融機関・口座単位の詳細管理は別Domainで拡張可能とする。

初期残高はAccounting SetupによりJournal Entryとして正本化する。

Account Masterの標準Asset Accountと対応付ける。

---

# Initial Account Setup

会計ON時には、StageArtの標準Account Setを利用可能にする。

初期WizardではAccount Masterを一件ずつ詳細設定させることを目的としない。

標準Accountを初期生成し、必要に応じて後から追加・変更・無効化できるようにする。

---

# Completion

以下を満たした時点でOrganization Setupを完了とする。

- Organization Nameが確定
- Organization Slugが確定
- 初期Ownerが確定
- 必要な公開情報が登録または確認済み
- Google Account Connection等の初期External Connection設定が完了またはSkip済み
- Timezoneが確定
- Notification設定が確定
- Accounting Enablementが確定
- Accounting ONの場合、現金・預金の初期残高が入力済み

Setup完了後は通常のOrganization Managementへ遷移する。

---

# Resume / Partial Setup

Wizard途中で離脱した場合に備え、未完了Setupを再開できる設計とする。

ただし、Organization自体の作成タイミングは実装上のTransaction Boundaryを考慮する。

初期Ownerを失わないことを必須とする。

---

# Post Setup Changes

以下はSetup完了後も変更可能とする。

- Organization Name
- Organization Description
- Organization Slug（影響を明示）
- Logo
- Google Account Connection
- Timezone
- Notification Settings
- Accounting Enablement
- Account Master
- Organization Members

Accountingを後から有効化した場合の初期残高入力フローはAccounting Setupとして再実行できるものとする。

---

# Design Principle

Organization Setupは、利用者に必要な初期設定を一度に完了させつつ、後から変更できる項目を過剰に固定しない。

基本原則は、

```text
Authenticated User
    ↓
Organization Owner
    ↓
External Connection / Timezone / Notification
    ↓
Organization Basic Information
    ↓
Accounting Enablement
    ↓
Initial Cash / Deposit
    ↓
Organization Ready
```

とする。

初回Wizardは「団体登録を完了させるための最小限の設定」に限定し、詳細な会計・権限・公演・Project設定は登録後の各Management Domainで行う。
