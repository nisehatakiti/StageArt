# StageArt Blueprint

# Organization Setup / Feature Usage Policy

Version : 1.3

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

# Organization Registration / Representative

Organizationを新規登録した実行ユーザーを、そのOrganizationの代表者として自動設定する。

Organization登録時に別途代表者を選択・申告する画面を要求しない。

Organization作成直後の代表者設定は、登録操作を実行したPersonを起点として成立する。

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

Google連携は外部連携Optionの一つとして扱う。Organization登録時には、登録実行ユーザーが自身のGoogleアカウント連携を初期設定できる。Google Calendar等の個人単位の外部連携で利用する認証コンテキストはPerson側に紐付ける。

NotificationはOptionとして扱い、Organization単位の設定としてON/OFFを管理する。

Notification以外のCore / Option機能について、利用開始のための不要なON/OFF設定を乱立させない。

Public ContactはCoreのPublic Pageに付随する公開窓口であるが、スパム等の運用上の理由からOrganization管理者が停止できる例外的な設定として扱う。これは一般的なFeature Toggleを増やすことを目的としたものではない。

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

## Registration Flow

Organization登録は、以下の順序を基本とする。

Organization作成
    ↓
基本情報設定
    ↓
利用設定
    ↓
会計を使用する場合のみ開始残高設定
    ↓
Organization登録完了

## Step 1 : Organization Basic Information

Organization登録時に、少なくとも以下を入力できる。

- Organization Name
- Organization Logo
- Organization Description
- Organization Public Slug

Organization Public Slugは公開URLに使用するため、入力時に利用可能性を確認する。

Organization Timezoneは利用設定Stepで初期設定する。

## Step 2 : Organization Usage Settings

Timezone、Googleアカウント連携、Notification、Accountingの初期設定は、同一の利用設定画面でまとめて確認・設定できる。

基本項目：

- Organization Timezone
- Google Account Integration
- Notification ON / OFF
- Accounting ON / OFF

TimezoneはStageArtのBusiness DateTimeの基準であるため、Organization登録時に初期値を設定する。

Googleアカウント連携は、登録実行ユーザーが自身のGoogleアカウントをStageArtへ連携するための初期設定として扱う。連携しない場合でもOrganization登録を妨げない。

Notificationは初期状態をここで選択でき、後からOrganization設定で変更できる。

Accountingはここで使用開始を選択できる。Accountingを使用しない場合は、会計開始残高の入力を要求せず、そのままOrganization登録を完了してProduction等のCore機能へ進める。

Accountingについては、初期設定画面で「後から有効化できる」こと、および一度有効化した後はOFFへ戻せないことを利用者へ明示する。

## Step 3 : Accounting Opening Balance

Step 2でAccountingをONにした場合のみ、Organization登録時に現在の流動資産を入力する。

劇団会計の初期設定として、最低限以下の2区分を入力できる。

- 現金
- 預金

この入力値をAccounting開始時点のOpening Balanceとして扱う。

Organization登録時点で過去の仕訳をすべてStageArtへ移行することは要求しない。

AccountingをOFFにした場合、このStepを完全にスキップする。

## Organization Registration Completion

上記のOrganization Basic InformationおよびUsage Settingsを完了した時点で、Organization登録を完了できる。

AccountingをONにした場合はOpening Balanceの入力を完了してからOrganization登録を完了する。

Organization登録後は、Production、Rehearsal、Timetable等のCore機能へ進める。

---

# Production Setup Wizard

Production作成時もSetup Wizard形式を基本とする。

Production Setup Wizardは、Productionの開始に必要な情報を順序立てて入力できるようにし、設定漏れを防ぐことを目的とする。

基本的な対象項目には、必要に応じて以下を含める。

- Production基本情報
- Public Slug
- 公演日程
- 会場
- Production参加者
- Rehearsal / Timetableに関する初期設定
- Performance
- Ticket / Reservationに関する設定
- その他Productionに必要な情報

会計を利用していないOrganizationでは、Production Setup WizardにAccounting設定を必須項目として表示してはならない。

Ticket / Reservationを利用する場合は、Production Setup Wizardから必要な設定へ進められる構造とする。

Production Public PageのURLに使用するPublic Slugは、Production登録時に設定する。

Setup Wizardは、Productionを登録するための最低限の入力と、後から設定できる任意項目を区別する。

---

# Public Slug Setup

OrganizationおよびProductionは、公開URLに使用するPublic Slugを持つ。

Public Slugは内部のOrganizationId / ProductionIdとは別の公開識別子である。

基本構造：

Organization
    id   = UUID等の内部識別子
    slug = Public Slug

Production
    id   = UUID等の内部識別子
    slug = Public Slug

Public Slugを内部Entity IDの代替として扱ってはならない。

Organization Public SlugはOrganization Setup時に設定する。

Production Public SlugはProduction Setup時に設定する。

Public SlugはPublic PageのURLを構成するため、入力時および登録時に利用可能性を確認する。

一意性はClient側の事前確認だけに依存せず、Server SideでBusiness Ruleとして保証する。

Organization Public SlugはOrganizationの公開URL第1階層、Production Public SlugはOrganization配下の公開URL第2階層として利用する。

基本URL構造：

https://hatakiti.com/StageArt/[Organization Slug]/
https://hatakiti.com/StageArt/[Organization Slug]/[Production Slug]/

Production Public SlugはOrganization内で一意であればよく、異なるOrganization間で同じSlugを使用することを妨げない。

Public Slugの文字種、予約語、変更可否、変更時の旧URLの扱い等の詳細ルールはPublic Page / URL Policyで定義する。

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
- Organization登録を実行したユーザーをOrganizationの代表者として自動設定する。
- Ticket / ReservationはCoreであり、Accountingを利用しなくても使用できる。
- 外部連携はOptionであり、接続設定を行ったOrganizationのみ利用できる。
- Google連携は初期設定時に登録実行ユーザーが自身のGoogleアカウントを連携できる。
- NotificationはOptionであり、Organization単位でON/OFFを管理する。
- Notification以外の機能について、不要なON/OFF設定を乱立させない。
- Organization Public PageおよびProduction Public PageはCoreとして自動生成する。
- Public ContactはPublic Pageに付随する公開窓口であり、一般的なFeature Toggleとして乱立させない。
- Public Contactはスパム等の運用上の理由からOrganization管理者がOFFにできる。
- Organization利用開始時はSetup Wizard形式を基本とする。
- Organization登録では、基本情報と利用設定を順に設定する。
- Timezone、Google連携、Notification、Accountingの初期設定は同一の利用設定画面でまとめて行える。
- AccountingをOFFにしたOrganizationには、会計開始残高の入力を要求しない。
- AccountingをONにした場合は、Organization登録時に現金と預金の開始残高を入力する。
- Accounting開始時点で過去の仕訳移行を必須としない。
- Accountingは後から有効化できる。
- 一度有効化したAccountingはOFFに戻せない。
- Production作成時もSetup Wizard形式を基本とする。
- Organization登録時にPublic Slugを設定する。
- Production登録時にPublic Slugを設定する。
- Public Slugは内部Entity IDとは別の公開識別子である。
- Public Slugの利用可能性は登録時に確認し、最終的な一意性はServer Sideで保証する。
- Organization Public Slugは公開URLの第1階層、Production Public Slugは第2階層を構成する。
- Production Public SlugはOrganization内で一意とする。
- Setup Wizardは設定漏れを防ぐが、不要な機能の初期設定を利用開始条件にしてはならない。
- StageArtはProgressive Setupを基本とし、必要な機能を後から設定できる。
- Organizationの日時はOrganization Timezoneを基準として管理する。
- Organization TimezoneはFrontend表示だけの設定ではなく、Business DateTimeの解釈基準である。
