# StageArt Blueprint

# Organization Setup / Feature Usage Policy

Version : 1.0

---

# Purpose

Organizationの利用開始時に必要となる初期設定、Organization単位の機能利用方針、およびStageArtを段階的に導入できるための基本ルールを定義する。

StageArtは、利用開始時にすべての機能の初期設定を要求してはならない。

---

# Organization as the Feature Scope

StageArtにおける機能の利用状態および設定は、原則としてOrganization単位で管理する。

同一Personが複数Organizationに所属する場合でも、各Organizationの機能利用状態は独立する。

例えば、同じPersonが以下のOrganizationに所属している場合でも、それぞれの設定は独立する。

Organization A
    会計 = 未開始
    通知 = ON

Organization B
    会計 = ACTIVE
    通知 = OFF

Organizationごとの機能状態をPerson単位で共通化してはならない。

---

# Core and Optional Features

StageArtの機能は、CoreとOptionを区別する。

## Core

以下は基本機能として利用できる。

- Organization
- Project
- Production
- Person / Membership / Participant
- Rehearsal
- Timetable
- Ticket / Reservation関連機能
- Organization Public Page
- Production Public Page

Ticket / ReservationはCoreであり、利用する場合に追加設定を行えば利用できる。Ticket / Reservationを使うためにAccountingを有効化することを要求しない。

Public PageはCoreであり、OrganizationおよびProductionの登録情報から自動生成される。

## Optional

外部連携はOptionとして扱う。

外部サービスとの接続設定を行ったOrganizationのみ、その外部連携機能を利用できる。

NotificationはOptionとして扱い、Organization単位の設定としてON/OFFを管理する。

Notification以外のCore / Option機能について、利用開始のための不要なON/OFF設定を乱立させない。

---

# Accounting

AccountingはOrganization単位のOptional Featureである。

Accountingを使用しなくても、StageArtのCore機能を利用できる。

Accountingの有効化、現在資金の入力、Opening Balance、途中からの有効化、および有効化後の不可逆性については、Accounting Policyで定義する。

Accountingを使用しないOrganizationに、Account Master、Budget、Journal Entry等の会計初期設定を要求してはならない。

---

# Organization Setup Wizard

Organizationの利用開始時は、Setup Wizard形式を基本とする。

Setup Wizardは、必要な初期設定を順序立てて案内し、設定漏れを防ぐことを目的とする。

ただし、Setup Wizardのすべての項目を完了しなければStageArtの利用を開始できない構造にはしない。

基本的な考え方：

Organization作成
    ↓
基本情報設定
    ↓
機能利用方針の確認
    ↓
必要な機能のみ初期設定
    ↓
StageArt利用開始

会計を使用しない場合は、会計初期設定をスキップしてProduction管理へ進める。

外部連携を使用しない場合も、外部サービスの接続設定を要求しない。

NotificationはSetup Wizard内で初期設定を確認できるが、後からOrganization設定で変更できる。

---

# Production Setup Wizard

Production作成時もSetup Wizard形式を基本とする。

Production Setup Wizardは、Productionの開始に必要な情報を順序立てて入力できるようにし、設定漏れを防ぐことを目的とする。

基本的な対象項目には、必要に応じて以下を含める。

- Production基本情報
- 公演日程
- 会場
- Production参加者
- Rehearsal / Timetableに関する初期設定
- Performance
- Ticket / Reservationに関する設定
- その他Productionに必要な情報

会計を利用していないOrganizationでは、Production Setup WizardにAccounting設定を必須項目として表示してはならない。

Ticket / Reservationを利用する場合は、Production Setup Wizardから必要な設定へ進められる構造とする。

Setup Wizardは、Productionを登録するための最低限の入力と、後から設定できる任意項目を区別する。

---

# Progressive Setup

StageArtは、OrganizationおよびProductionを一度に完全設定することを要求しない。

利用者は、まず基本機能を開始し、必要になった機能を後から設定できる。

例えば、以下の利用開始を許可する。

Organization作成
    ↓
会計を使用しない
    ↓
Production作成
    ↓
Rehearsal / Timetable利用
    ↓
後からAccountingを有効化

この場合、Accountingを有効化するために既存のProductionやRehearsalを作り直すことを要求しない。

---

# Organization Timezone

StageArtの日時は、OrganizationのTimezoneで管理する。

Organizationは、日時を解釈するためのTimezoneを保持する。

Rehearsal、Timetable、Performance、Reservation、Ticket関連の日時など、Organizationに属するBusiness DateTimeは、原則としてOrganization Timezoneを基準として扱う。

Organization Timezoneは、単なるFrontend表示設定ではなく、Business DateTimeを解釈するためのOrganization Contextである。

例えばOrganization TimezoneがAsia/Tokyoの場合、利用者が入力する「19:00」は、そのOrganizationにおける19:00として扱う。

Clientが別Timezoneからアクセスした場合でも、OrganizationのBusiness DateTimeを別のTimezoneのBusiness Factとして扱ってはならない。

外部サービスとの連携時には、必要に応じてUTC等へ変換して通信してよいが、StageArt内部のBusiness DateTimeの意味はOrganization Timezoneによって決定する。

Organization Timezoneの具体的な変更ルール、および既存Business DateTimeに対する影響については、Timezone関連の詳細設計で定義する。

---

# Business Rules

- OrganizationはStageArtの基本的なFeature Scopeである。
- Featureの利用状態・設定は原則Organization単位で管理する。
- Ticket / ReservationはCoreであり、Accountingを利用しなくても使用できる。
- 外部連携はOptionであり、接続設定を行ったOrganizationのみ利用できる。
- NotificationはOptionであり、Organization単位でON/OFFを管理する。
- Notification以外の機能について、不要なON/OFF設定を乱立させない。
- Organization Public PageおよびProduction Public PageはCoreとして自動生成する。
- Organization利用開始時はSetup Wizard形式を基本とする。
- Production作成時もSetup Wizard形式を基本とする。
- Setup Wizardは設定漏れを防ぐが、不要な機能の初期設定を利用開始条件にしてはならない。
- StageArtはProgressive Setupを基本とし、必要な機能を後から設定できる。
- Organizationの日時はOrganization Timezoneを基準として管理する。
- Organization TimezoneはFrontend表示だけの設定ではなく、Business DateTimeの解釈基準である。
