# StageArt Blueprint

# Chapter 2 : Design Principles

Version : 2.0

---

# Architect's Note

Design Principlesは、StageArtを設計・開発するすべての人が守るべき設計原則である。

新しい機能を追加する場合も、既存機能を変更する場合も、本章に定義された原則を優先する。

一時的な利便性のために、設計原則を破ってはならない。

StageArtは、高機能・大規模なシステムを目指さない。

必要な機能を、利用者が迷わず使える形で提供することを優先する。

---

# Principle 1

## Domain First

StageArtは画面やデータベースから設計しない。

すべての設計はDomain Modelを中心として行う。

画面はDomainを操作するためのUIであり、
データベースはDomainを永続化する手段である。

Business Flow、Domain Model、API、Database、UIの順に設計を具体化する。

---

# Principle 2

## User First

利用者はシステムの内部構造を意識しない。

利用者が入力するのは、

・団体を作る
・公演を作る
・メンバーを登録する
・稽古日を調整する
・予約する
・受付する
・アンケートを送る

などの「やりたいこと」である。

ProjectやProductionなどの内部ドメインは、
利用者が必要以上に意識することなくシステムが管理する。

---

# Principle 3

## Simple UI, Rich Domain

UIは可能な限りシンプルにする。

複雑さはDomain Modelが吸収する。

利用者に複雑な設定や専門的な概念を要求しない。

特に小規模な団体では、
システムを覚えるための時間そのものが負担となる。

StageArtは、
「何ができるか」ではなく
「何をしたいか」を起点として操作できるUIを優先する。

---

# Principle 4

## Multi Tenant

StageArtは複数の団体が同一システム上で安全に利用できることを前提とする。

すべての団体固有データはOrganization単位で管理される。

あるPersonが複数のOrganizationに所属することを許容する。

Personは一つのIdentityを持ち、
Organizationごとに異なるMembershipおよびRoleを持つ。

例えば、

劇団AではManager、
劇団BではCast、
劇団CではReception

という状態を許容する。

Organization Contextを切り替えることで、
利用者はそのOrganizationにおけるRoleに応じたPortalを利用する。

他Organizationのデータへアクセスできてはならない。

---

# Principle 5

## API First

すべての機能はREST APIとして提供する。

Web画面はAPIの利用者であり、
将来のFlutterアプリやLINE連携なども同一APIを利用できる構造とする。

APIは内部Domain Modelをそのまま公開するものではない。

Business Resourceを中心として設計し、
Domainの内部構造を外部へ不用意に露出しない。

---

# Principle 6

## Mobile Ready

スマートフォンで利用されることを前提として設計する。

特に、

・QR Check In
・Reservation受付
・公演当日の受付業務
・稽古管理
・お知らせ確認
・タイムテーブル確認

など、公演現場で利用される機能はMobile利用を優先する。

ただし、管理業務など情報量の多い機能ではDesktop利用も考慮する。

---

# Principle 7

## Event Driven

利用者の操作やDomain上の事実を起点として、
必要な内部データやArtifactをシステムが生成・更新する。

例）

「公演を作る」

↓

Project生成

↓

Production生成

↓

制作に必要な領域生成

↓

Public Page生成

↓

Internal Page生成

また、

「稽古日を確定する」

↓

Rehearsal生成

↓

Google Calendar連携

↓

確定後の参加確認

など、
Business Flowに基づいて必要な処理を連鎖させる。

---

# Principle 8

## Single Source of Truth

同じ情報を複数箇所で管理しない。

情報には必ず唯一の管理主体（Owner）が存在する。

他の情報は参照または集計によって表現する。

例えば、

・予約情報はReservationが管理する
・出演情報はAssignmentが管理する
・団体情報はOrganizationが管理する
・稽古情報はRehearsalが管理する
・会計情報はAccountingが管理する
・備品の所在はEquipmentが管理する

Public Pageや各種レポートは、
これらのFactを参照・集計して生成する。

---

# Principle 9

## Fact and Artifact

StageArtは、

Fact（事実）

と

Artifact（成果物）

を区別する。

例）

Fact

・団体
・公演
・出演
・予約
・受付
・稽古
・アンケート回答
・会計仕訳
・備品の所在

Artifact

・QRチケット
・プロフィール
・公演ページ
・団体ページ
・収支レポート
・アンケート集計
・タイムテーブル
・各種公開情報

ArtifactはFactから生成される。

同じ事実をArtifact側で再管理しない。

---

# Principle 10

## Public and Internal Separation

StageArtでは、
団体・公演に関する情報をPublicとInternalに明確に分離する。

Publicは一般の利用者が閲覧する情報である。

Organization Public Pageでは、

・団体名
・沿革
・代表
・公開されているメンバー
・過去公演
・SNS情報

などを扱う。

Production Public Pageでは、

・公演情報
・出演者
・画像
・SNS情報
・公演実績
・公開対象として選定されたお客様の声

などを扱う。

Internalでは、

・稽古
・タイムテーブル
・関係者向けファイル
・内部お知らせ
・会計
・規約
・年度計画
・備品管理

など、団体内部でのみ利用する情報を扱う。

内部情報をPublic Pageへ露出させてはならない。

---

# Principle 11

## Organization Context and Role Based Access

利用者に対する権限はPerson単位ではなく、
OrganizationとのMembershipを基準として判断する。

同一Personが複数Organizationに所属する場合、
現在選択しているOrganization ContextにおけるRoleのみを適用する。

Portalには利用者が必要とするメニューのみを表示する。

主なRoleには、

・Manager
・Delegate
・Member
・Reception

などが存在する。

一人のMemberが複数の役割を持つ場合は、
そのOrganizationにおいて必要な権限をすべて利用できる。

Production単位でDelegateを設定することもできる。

---

# Principle 12

## External Service First

StageArt自身が不要な機能を抱え込まない。

既に広く利用されている外部サービスは、
必要に応じて適切に連携する。

Betaでは、

・Google Drive
・Google Calendar

との連携を行う。

Google Driveは公演ファイルの保存・共有先として利用する。

Google CalendarはStageArtで確定した予定を外部カレンダーへ連携する。

外部サービスとの連携によって、
StageArt自身が巨大なストレージやカレンダーシステムを持つことを避ける。

ただし、StageArt単体でも基本的な業務を完結できる構造を維持する。

---

# Principle 13

## Stage Production First

StageArtは、
舞台芸術の現場で実際に発生する業務を中心として設計する。

演劇を起点として設計・改善を行うが、
内部Domain Modelを特定のジャンルに固定しない。

劇団、音楽活動、朗読、セミナーなど、
舞台上で行われる様々な活動へ展開できる構造を維持する。

団体タイプによって、
利用者へ表示する用語やプロフィール項目などを変更できる。

基本的なDomain Structureは共通化する。

---

# Principle 14

## Production Lifecycle

StageArtは、
一つの公演・活動を単独の機能として扱わない。

企画から準備、実施、終了後のフィードバック、記録までを一つのLifecycleとして扱う。

基本的な流れは、

企画

↓

キャスティング・参加者設定

↓

公演ページ生成

↓

稽古・準備

↓

広報

↓

予約

↓

受付

↓

公演

↓

アンケート

↓

実績・収支

↓

アーカイブ

とする。

各機能は独立した便利機能ではなく、
活動Lifecycleを構成する一つの要素として設計する。

---

# Principle 15

## Incremental Development

StageArtは段階的に開発する。

Betaで必要な機能を優先し、
将来必要になる可能性がある機能を先回りして実装しない。

実際の利用によって必要性が確認された機能を、
将来のVersionで追加する。

Betaでは、

・リセール
・キャンセル待ち
・高度なチケット販売
・高度な会計・税務
・固定資産管理
・減価償却
・高度な在庫管理
・大規模CRM
・ファンクラブ
・グッズ販売

などを対象外とする。

StageArtは、
機能数の多さではなく、
必要な機能が簡単に使えることを優先する。

---

# Principle 16

## Blueprint First

コードを書く前にBlueprintを更新する。

Blueprintが唯一の設計基準（Single Source of Truth）である。

すべての実装はBlueprintに従う。

既存コードがBlueprintと異なる場合、
コードを基準としてBlueprintを変更するのではなく、
まず設計意図を確認する。

設計変更が必要な場合は、
先にBlueprintを更新する。

---

# Principle 17

## Theatre First

StageArtはITシステムそのものを目的としない。

舞台芸術に関わる人の活動を支えるためのプラットフォームである。

設計判断に迷った場合は、

「舞台芸術に関わる人の負担を減らし、
創作活動へ集中できる時間を増やせるか」

を最優先に判断する。

高機能であることより、
現場で迷わず使えることを優先する。

---

# Principle 18

## UI Theme and Design System

StageArtのUIは、
特定の色やスタイルを画面ごとに直接定義しない。

UIの基本的な色、文字、余白、境界線、角丸、影などは
Theme Tokenとして定義し、
各UI ComponentはTheme Tokenを参照して表示する。

UIの見た目とBusiness Logicを分離し、
Themeを変更してもDomain ModelやBusiness Logicへ影響しない構造とする。

---

# Theme Token

UIではCSS Custom Propertiesを利用して、
Theme Tokenを定義する。

例）

--stageart-color-primary
--stageart-color-secondary
--stageart-color-accent
--stageart-color-background
--stageart-color-surface
--stageart-color-text
--stageart-color-muted
--stageart-color-success
--stageart-color-warning
--stageart-color-error

Componentは直接カラーコードを指定せず、
Theme Tokenを参照する。

例）

.button-primary {
    background-color: var(--stageart-color-primary);
}

---

# Component Independence

UI Componentは、
具体的なThemeの色やデザインへ直接依存しない。

Button、Form、Table、Card、Navigation、Modalなどの
共通ComponentはTheme Tokenを利用して表示する。

これによりThemeを変更しても、
個々のComponentを変更する必要がない構造とする。

---

# Theme Scope

Themeは将来的に以下の単位で変更できる構造とする。

- StageArt Global
- Organization
- Production

Betaでは、
Theme設定機能そのものを提供する必要はない。

ただし、
将来的にOrganizationやProductionごとに
Brand Colorなどを設定できるよう、
UI実装はTheme Tokenを経由する。

---

# CSS Scope

StageArtのCSSは、
WordPress Themeや他のWordPress PluginのCSSと
不用意に干渉しないようにする。

StageArt独自のCSS Scopeを設け、
Global CSSを汚染しない。

StageArtのComponent Styleは、
StageArtのUI領域内でのみ適用されることを基本とする。

---

# Responsive Design

StageArtのUIは、
Desktop、Tablet、Mobileの各画面サイズに対応する。

特に以下の業務ではMobile利用を優先する。

- QR Check In
- Reservation受付
- 公演当日の受付業務
- 稽古管理
- タイムテーブル確認
- お知らせ確認

---

# Accessibility

Theme Tokenは、
視認性とアクセシビリティを考慮して定義する。

特に、

- Text
- Background
- Button
- Link
- Error
- Warning
- Success

などの組み合わせについて、
十分なコントラストを確保する。

色だけを情報伝達の唯一の手段にしない。

---

# Design System

StageArtのUIは、
共通ComponentとTheme Tokenによって構成する。

新しい画面を追加する場合、
既存Componentを優先して利用する。

画面ごとに独自の色、
独自のButton、
独自のForm Styleなどを作成しない。

共通Componentで表現できない場合は、
既存Componentを拡張するか、
Design Systemへ新しいComponentを追加する。

---

# Future

将来的に以下へ対応できる構造とする。

- Organization Brand Color
- Production Theme
- Dark Mode
- Custom Theme
- Theme Preset
- Logo
- Typography
- Component Variation

Theme変更によって、
Business LogicやDomain Modelが影響を受けてはならない。

---

# Final Design Principle

StageArtのすべての設計判断は、
以下の優先順位に従う。

1. 舞台に関わる人の負担を減らせるか
2. 創作活動に使える時間を増やせるか
3. 利用者が迷わず使えるか
4. Domain Modelとして正しく表現できるか
5. 将来の拡張性を維持できるか

「高機能であること」や
「技術的に高度であること」は、
これらより優先されない。

StageArtは、
凄いシステムを作ることを目的としない。

必要なものを、
必要な人へ、
必要なだけ、
簡単に提供する。

それがStageArtの設計原則である。
