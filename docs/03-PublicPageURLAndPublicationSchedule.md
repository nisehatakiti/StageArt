# StageArt Blueprint

# 03 - Public Page URL, Publication Schedule and Membership Onboarding

Version : 2.4
Status : Confirmed

---

## 目的

StageArt上で作成・管理されるOrganizationおよびProductionを、そのまま一般公開可能な団体ページ・公演ページとして利用できるようにする。

また、初回Onboardingにおいて、利用者が団体を作成する場合だけでなく、既存Organizationや既存Productionへの所属・参加を申請できるFlowを確定する。

---

# StageArt Subdomain Architecture

StageArtのWeb利用領域は、用途ごとに以下の3つのSubdomainへ分離する。

## Application

```text
https://app.stageart.top/
```

通常のStageArt利用、認証後のHome、My Page、Settings、Organization / Production Context、System Administration Context等のアプリケーション画面はすべて `app.stageart.top` 配下に配置する。

ログイン画面のCanonical URLは以下とする。

```text
https://app.stageart.top/login/
```

## API

```text
https://api.stageart.top/
```

REST APIその他のApplication Programming Interfaceは `api.stageart.top` 配下に配置する。

API Endpointは原則としてUI Application Domainとは分離し、Browser / Mobile等のClientから `api.stageart.top` を利用する。

## Public Site

```text
https://stageart.top/
```

一般公開されるOrganization Public PageおよびProduction Public Pageは、認証済みアプリケーションとは分離し、Root Domainである `stageart.top` 配下に配置する。

### Subdomain正本

```text
Application
https://app.stageart.top/

API
https://api.stageart.top/

Public Site
https://stageart.top/
```

この3分離をStageArtのProduction Web URL Architectureの正本とする。

## System Administration

```text
https://admin.stageart.top/
```

StageArtのシステム全体管理者向け管理画面は `admin.stageart.top` 配下に配置する。

System Administrationは通常のStageArt Applicationとは別Shellとして扱う。ただし、System Administratorは通常のUserAccountとして認証され、SystemAdministratorFlag等の既存権限設計に基づいて管理機能へのアクセスを許可する。

## Development Environment

Development環境については、Production環境と同じ用途分離を維持しつつ、独立したSubdomainを使用する。

### Public-Test

```text
https://dummy.stageart.top/
```

公開ページのDevelopment / Test用途には `dummy.stageart.top` を使用する。

### Development Application

```text
https://dev.stageart.top/
```

### Development API

```text
https://dev-api.stageart.top/
```

### Development Administration

```text
https://dev-admin.stageart.top/
```

### StageArt URL Architecture（正本）

```text
Production
├─ https://stageart.top/       Public
├─ https://app.stageart.top/   Application
├─ https://api.stageart.top/   API
└─ https://admin.stageart.top/ System Administration

Development
├─ https://dummy.stageart.top/     Public-Test
├─ https://dev.stageart.top/       Development Application
├─ https://dev-api.stageart.top/   Development API
└─ https://dev-admin.stageart.top/ Development Administration
```

この8つのURLを、StageArtのProduction / Development URL Architectureの正本とする。

---

# Application Management Page と Public Page の区別

## 基本原則

StageArtでは、同じOrganizationおよびProductionを対象としていても、**ログイン後に利用者が管理・参加するApplication画面**と、**一般利用者へ公開するPublic Page**を明確に別物として扱う。

「団体ページ」「公演ページ」という呼称だけで画面種別を判断してはならない。

今後の設計・実装・レビューでは、少なくとも次の4種類を区別する。

| 正式分類 | 対象 | 利用領域 |
|---|---|---|
| Organization Management Page | Organization | Application |
| Production Management Page | Production | Application |
| Organization Public Page | Organization | Public Site |
| Production Public Page | Production | Public Site |

## Application側

`app.stageart.top` は、認証されたStageArt利用者がOrganizationやProductionを**管理・運営・参加するためのApplication領域**である。

同じOrganization / Productionを表示していても、ここに配置される画面をPublic Pageとして扱ってはならない。

概念上のURL構造は以下とする。

```text
Organization Management Page
https://app.stageart.top/{organization-slug}/...

Production Management Page
https://app.stageart.top/{organization-slug}/{production-slug}/...
```

Application側のOrganization / Production画面では、ログイン後Application Shell、共通左サイドメニュー、Context Area、Role / Permissionに応じた操作を利用する。

## Public Site側

`stageart.top` は、OrganizationおよびProductionの情報を**一般公開するPublic領域**である。

公開ページはApplicationの管理画面とは別Shell、別Navigation、別の利用目的を持つ。

Canonical URLは以下とする。

```text
Organization Public Page
https://stageart.top/{organization-slug}

Production Public Page
https://stageart.top/{organization-slug}/{production-slug}
```

## ホスト名による責務分離

同じPath構造を使用していても、Hostが異なれば画面種別と責務は異なる。

```text
https://app.stageart.top/kujira
    = Organization Management / Application

https://stageart.top/kujira
    = Organization Public Page

https://app.stageart.top/kujira/kappa
    = Production Management / Application

https://stageart.top/kujira/kappa
    = Production Public Page
```

したがって、URLまたは画面を評価する際は、**Pathだけではなく必ずHostを確認してApplicationかPublic Siteかを判定する**。

## 実装・設計時の禁止事項

以下の混同を禁止する。

- `app.stageart.top/{organization-slug}` をOrganization Public Pageとして扱うこと
- `app.stageart.top/{organization-slug}/{production-slug}` をProduction Public Pageとして扱うこと
- Public PageのNavigation仕様をApplication Management Pageへ流用すること
- Application Shellの左メニュー仕様をPublic PageのNavigation仕様と混同すること
- 「団体ページ」「公演ページ」という曖昧な呼称だけで画面仕様を決定すること

画面仕様を記述する際は、可能な限り以下の正式分類を使用する。

```text
Organization Management Page
Production Management Page
Organization Public Page
Production Public Page
```

---

# 01 公開URL

## Organization

```text
https://stageart.top/{organization-slug}
```

このURLをOrganization Public Pageの正式なCanonical URLとする。

## Production

```text
https://stageart.top/{organization-slug}/{production-slug}
```

このURLをProduction Public Pageの正式なCanonical URLとする。

ProductionはOrganizationの配下で公開する。

### URL正本

StageArtの公開ページURLについては、本書に記載する以下の2形式を正本とする。

```text
Organization Public Page
https://stageart.top/{organization-slug}

Production Public Page
https://stageart.top/{organization-slug}/{production-slug}
```

過去の設計資料や旧記述に異なるドメイン・パス形式が残っている場合は、本書の上記URL形式を優先し、旧URL形式は廃止された設計として扱う。

---

# 02 Slug

- Organization.slug はStageArt全体で一意。
- Production.slug は同一Organization内で一意。
- 名称入力からSlugを自動生成し、必要に応じて編集可能。

---

# 03 共通作成Flowの再利用

## 基本方針

Onboarding専用のOrganization作成画面およびProduction作成画面を別途実装しない。

初回Onboarding中にOrganizationまたはProductionを作成する場合も、通常のApplicationからOrganizationまたはProductionを作成する場合と同一の作成Flow・画面・入力項目・バリデーションを利用する。

```text
Onboarding
    ↓
Organization / Production作成を開始
    ↓
共通Create Flow
    ↓
Organization / Production作成完了
    ↓
呼び出し元のFlowへ復帰
```

## Organization Create Flow

以下の内容は、Onboardingから作成する場合と通常のApplicationから作成する場合で共通とする。

```text
団体名
Organization Slug
説明
Logo
```

作成者はOrganizationの管理者として登録する。

Onboardingから開始した場合は、Organization作成完了後にOnboardingの次のStepへ戻る。

通常のApplicationから開始した場合は、Organization作成完了後に通常のOrganization Management Flowへ遷移する。

## Production Create Flow

以下の内容は、Onboardingから作成する場合と通常のApplicationから作成する場合で共通のProduction作成Flowを利用する。

```text
公演基本情報
├─ 公演名
└─ Production Slug

本番期間・公演回
├─ 本番開始日
├─ 本番終了日
├─ 日程未定設定
├─ 本番期間情報の公開設定 / 情報公開日
└─ 公演回（複数）

会場・公開設定
├─ 会場
├─ 会場未定設定
└─ 会場情報の公開設定 / 情報公開日

出演予定者・公開設定
├─ 出演予定者
└─ 出演予定者情報の公開設定 / 情報公開日

チケット情報・公開設定
├─ チケット情報
└─ チケット情報の公開設定 / 情報公開日
```

未決定情報は後から追加・変更可能とする。

稽古日程は初回Onboardingでは入力せず、Home到達後に設定する。

Onboardingから開始した場合は、Production作成完了後にOnboardingの次のStepまたは完了処理へ戻る。

通常のApplicationから開始した場合は、Production作成完了後に通常のProduction Management Flowへ遷移する。

## 共通化の範囲

共通化するのは画面の見た目だけではなく、作成Flowそのものとする。

以下をOnboarding専用に重複実装してはならない。

- 入力項目
- 入力バリデーション
- Slug生成・編集処理
- Organization / Production作成処理
- 作成画面の画面仕様

Onboardingと通常作成で異なってよいのは、作成Flowの呼び出し元、作成完了後の遷移先、および画面Shell（Navigation表示）である。

## Onboarding利用時のNavigation

通常のApplicationからOrganization Create FlowまたはProduction Create Flowを利用する場合は、Application標準の画面Shellを使用し、左メニューを表示する。

一方、Onboardingから同じCreate Flowを利用する場合は、Onboarding専用の画面Shellで表示し、左メニューを表示しない。

```text
通常Application

┌────────────┬──────────────────────────┐
│ 左メニュー │ Organization / Production │
│            │ Create Flow              │
└────────────┴──────────────────────────┘


Onboarding

┌───────────────────────────────────────┐
│ Onboarding Header / Progress          │
├───────────────────────────────────────┤
│                                       │
│ Organization / Production             │
│ Create Flow                           │
│                                       │
└───────────────────────────────────────┘
```

Organization Create FlowおよびProduction Create Flowの画面本体、入力項目、入力バリデーション、Slug生成・編集処理、作成処理は共通とする。

共通FlowをOnboardingから呼び出す場合は、Flow本体を複製せず、Onboarding Shellで包んで表示する。

```text
CreateOrganizationFlow
├─ Onboardingから呼び出す
└─ 通常Applicationから呼び出す

CreateProductionFlow
├─ Onboardingから呼び出す
└─ 通常Applicationから呼び出す
```

作成内容そのものは呼び出し元によって変更しない。

---

# Organization Context Navigation and Accounting Feature

## 基本方針

Organization Management Contextでは、Application標準の左サイドメニュー内にOrganization Context Menuを表示する。

Organization Context Menuは、選択中のOrganizationを管理するための機能入口とする。

## 団体管理Home画面

Organization Contextに入った際のHome画面（団体管理Home）は、選択中のOrganizationを明確に識別できる画面とする。

### 上部表示

画面上部に以下を表示する。

- Organization名
- 登録されているOrganization Logo

```text
［Organization Logo］
Organization Name
```

### 公開ページへのリンク

Organizationの公開ページURLは、以下とする。

```text
stageart.top/[Organization Slug]
```

団体管理Home画面には、Organizationの公開ページを確認するための「公開ページを見る」リンクを表示する。

「公開ページを見る」は、現在のOrganization Slugを使用した公開ページURLを別ウィンドウで開く。

```text
［Organization Logo］ Organization Name

[ 公開ページを見る ↗ ]
```

## Organization Slugの一意性チェック

Organization Slugは公開ページURLの識別子として使用するため、Organization間で重複してはならない。

Organization作成時およびOrganization Information画面でSlugを変更する場合、保存前に既存Organizationで同じSlugが使用されていないかをチェックする。

```text
Organization Slug入力 / 変更
    ↓
既存Slugの使用状況をチェック
    ↓
未使用 → 保存可能
使用済み → 保存不可
```

使用済みの場合は、Slugが既に使用されていることを画面上で通知し、別のSlugを入力するまで保存できない。

Organization Slugが変更された場合、公開ページそのものを別途作成・移動するのではなく、公開ページURLに使用されるSlugが更新される。

```text
変更前
stageart.top/old-slug

Slug変更
    ↓

変更後
stageart.top/new-slug
```

「公開ページを見る」リンクは常に現在保存されているOrganization Slugを参照する。

このリンクはOrganizationの管理画面から公開状態を確認するための閲覧用リンクであり、Organizationの公開 / 非公開状態を変更する操作ではない。Organizationは保存と同時に公開される仕様とする。

## Organization Context Menu

［Organization Name / Logo］

団体情報

メンバー管理
├─ メンバー管理・追加
├─ 代理人を設定
└─ 代表者交代

公演管理
├─ 公演を作る
├─ 過去公演を登録する
└─ 公演を編集する

会計管理（会計機能がONの場合のみ表示）
├─ 会計入力
├─ 仕訳一覧
├─ 貸借対照表
├─ 損益計算書
├─ 予算作成
└─ 会計締め処理

## メンバー管理の操作入口

Organization Context Menuの「メンバー管理」配下には、以下の操作入口を配置する。

- メンバー管理・追加
- 代理人を設定
- 代表者交代

既存のOrganization Member Management画面との整合は別途維持し、各操作の詳細仕様は既存のConfirmed Blueprintおよび後続の個別画面仕様を正本とする。

## 代理人設定

代理人設定は、Organizationの既存メンバーに対して、代表者が管理領域ごとの代理権限を委任するための画面とする。

代理権限の対象は以下の3領域とする。

- メンバー管理
- 公演管理
- 会計管理

### 画面構成

```text
代理人設定

メンバー          メンバー管理     公演管理      会計管理
──────────────────────────────────────────────
代表者A             ✓             ✓             ✓

メンバーA            □             □             □
メンバーB            □             □             □
メンバーC            □             □             □

                                      [ 保存 ]
```

Organizationの登録メンバーを一覧表示し、各メンバー行の管理領域ごとにチェックボックスを表示する。

チェックされた項目は、そのメンバーに当該管理領域の代理権限を委任していることを示す。

### 代表者の表示

代表者も一覧に表示する。代表者は全管理権限を持つため、以下を固定表示する。

```text
代表者
メンバー管理     公演管理      会計管理
──────────────────────────────
代表者A             ✓             ✓             ✓
```

代表者の権限表示は固定とし、代理人設定画面から変更できない。

### 代理人設定ルール

- 同一メンバーに複数の管理領域を委任できる。
- 同じ管理領域を複数のメンバーに委任できる。
- メンバーごと、管理領域ごとに代理権限を個別に設定できる。
- 代理人設定対象はOrganizationの登録メンバーとする。

### 保存後の動作

代理人設定の変更後に「保存」を実行し、保存が成功した場合は同じ代理人設定画面を表示する。最新の代理人設定を再取得・再表示する。

```text
チェック変更
    ↓
[ 保存 ]
    ↓
代理権限を更新
    ↓
保存しました
    ↓
同じ代理人設定画面
    ↓
最新設定を再取得・再表示
```

## 代表者交代

代表者交代は、Organizationの既存メンバーから新しい代表者を選択して実行する。

### 画面構成

```text
代表者交代

新しい代表者
[ メンバーを選択してください ▼ ]

[ 保存 ]
```

プルダウンには、Organizationに登録されているメンバーを表示する。新しい代表者を選択して「保存」を押すことで代表者交代を開始する。

### 確認

「保存」押下後、代表者交代の確認メッセージを表示する。

```text
本当に代表者を ○○ さんに交代しますか？

[ キャンセル ] [ OK ]
```

OKを選択した場合に代表者交代を確定する。キャンセルの場合は代表者交代を行わず、代表者交代画面に戻る。

### 交代後の動作

代表者交代が確定した場合、実行した旧代表者はOrganizationの通常メンバー権限となる。代表者交代後、旧代表者には代表者としてのOrganization管理権限を保持させない。

処理成功後は、Organization ContextではなくHome画面へ移動する。

```text
新しい代表者を選択
    ↓
[ 保存 ]
    ↓
「本当に代表者を ○○ さんに交代しますか？」
    ↓
[ OK ]
    ↓
代表者交代を確定
    ↓
旧代表者は通常メンバー権限へ変更
    ↓
Home画面へ移動
```

## 会計機能の表示条件

Organization単位で会計機能の有効 / 無効を設定する。

会計機能がONの場合のみ、Organization Context Menuに「会計管理」およびその配下のメニューを表示する。

会計機能がOFFの場合は、「会計管理」カテゴリ自体を左メニューに表示しない。

## Organization Informationへの追加

Organization Informationには、既存の基本情報に加えて機能設定を持たせる。

基本情報
├─ 団体名
├─ Organization Slug
├─ 説明
└─ Logo

機能設定
└─ 会計機能
   └─ ON / OFF

会計機能のON / OFFはOrganization単位の設定とし、設定変更はOrganization Context Menuの表示制御に反映する。
## Organization保存後の動作

Organizationの作成・団体情報の保存に、非公開状態を設けない。保存が成功したOrganizationは保存と同時に公開状態とする。

Organization作成直後に「団体を公開する」操作を要求する作成完了画面は使用しない。

### OnboardingからOrganization Create Flowを利用した場合

Organizationの保存・作成完了後は、Onboardingの次のStepへ戻る。

```text
Organization保存
    ↓
Organization公開（保存と同時）
    ↓
直近の公演・活動を作成しますか？
[ はい ] [ いいえ ]
```

### 通常の団体管理からOrganization Create / Information Flowを利用した場合

保存成功後は「保存しました」を通知し、作成完了専用画面を表示せず、元の画面へ戻る。

通常利用では、保存後にOnboardingの「直近の公演・活動を作成しますか？」へ遷移しない。

---

# 04 団体管理者の初回Onboarding

団体管理者を選択したPersonは、初回Onboarding中にOrganizationを作成する。

## F01-ORG-02 団体基本情報

同一画面で設定する。

```text
団体名
Organization Slug
説明
Logo
```

作成者はOrganizationの管理者として登録する。

## 直近Production確認

```text
直近に予定している公演はありますか？

[ はい ]
[ いいえ ]
```

「いいえ」はHomeへ進む。

「はい」は以下を設定する。

### F01-PROD-01 公演基本情報

```text
公演名
Production Slug
```

### F01-PROD-02 本番期間・公演回

```text
本番開始日
本番終了日
日程未定設定
本番期間情報の公開設定 / 情報公開日

公演回（複数）
・公演日
・開場時刻
・開演時刻
・終演予定時刻
＋ 公演回を追加
```

公演回は既存の `Production → Performance` 構造を利用する。

### F01-PROD-03 会場・公開設定

```text
会場
会場未定設定
会場情報の公開設定 / 情報公開日
```

### F01-PROD-04 出演予定者・公開設定

```text
出演予定者
出演予定者情報の公開設定 / 情報公開日
```

### F01-PROD-05 チケット情報・公開設定

```text
チケット情報
チケット情報の公開設定 / 情報公開日
```

未決定情報は後から追加・変更可能。

稽古日程は初回Onboardingでは入力せず、Home到達後に設定する。

---

# 05 団体・公演所属者の初回Onboarding

団体管理者ではないPerson、または初回目的選択で団体・公演所属者を選択したPersonは、既存OrganizationおよびProductionへの所属・参加を設定できる。

この設定は必須ではなく、それぞれ個別にスキップできる。

## 全体Flow

```text
団体に所属しますか？
│
├─ スキップ
│
└─ 所属する
    ↓
    Key入力 / QR読取 / 団体検索
    ↓
    対象団体確認
    ↓
    所属申請
    ↓
    団体側承認
    ↓
    正式所属

↓

公演に所属しますか？
│
├─ スキップ
│
└─ 参加する
    ↓
    Key入力 / QR読取 / 公演検索
    ↓
    対象公演確認
    ↓
    参加申請
    ↓
    公演側または団体側承認
    ↓
    正式参加

↓
Home
```

団体と公演は独立してスキップ可能とする。

両方をスキップした場合も正常なOnboarding完了とし、Homeで「どこにも所属していない」といった状態を強調表示しない。

---

## F02-ORG-01 団体所属確認

```text
団体に所属しますか？

[ 団体に所属する ]
[ 今はスキップする ]
```

「団体に所属する」を選択した場合、以下の参加方法を提示する。

```text
所属Keyを入力
QRコードを読み取る
団体を検索する
```

### 所属Key

Organization管理側が発行した参加用Keyを入力する。

### QRコード

Organization管理側が発行した参加用QRコードを読み取る。

### 団体検索

検索画面を別画面またはモーダルで開き、検索結果から対象Organizationを選択する。

検索後は元のOnboarding画面に戻り、選択した対象団体を確認できる。

```text
選択中の団体
劇団○○

[変更]
[所属申請]
```

Key、QR、検索のいずれの方法でも、対象Organizationを確認してから所属申請を行う。

---

## F02-ORG-02 団体所属申請

利用者は対象Organizationに対して所属申請を行う。

```text
劇団○○に所属申請しますか？

[キャンセル]
[所属申請]
```

申請直後は正式所属ではない。

状態は少なくとも以下を持つ。

```text
pending
active
rejected
```

Organization管理側が承認した時点で `active` となり、正式所属とする。

検索から申請した場合は、必ずOrganization側の承認後に所属確定する。

---

## F02-PROD-01 公演参加確認

団体設定の後、または団体設定をスキップした後に表示する。

```text
出演・スタッフとして参加する公演はありますか？

[ 公演に参加する ]
[ 今はスキップする ]
```

「公演に参加する」を選択した場合、以下の参加方法を提示する。

```text
参加Keyを入力
QRコードを読み取る
公演を検索する
```

### 参加Key

ProductionまたはOrganization管理側が発行した公演参加用Keyを入力する。

### QRコード

Production側が発行した公演参加用QRコードを読み取る。

### 公演検索

検索画面を別画面またはモーダルで開き、検索結果から対象Productionを選択する。

検索後は元のOnboarding画面に戻り、選択した対象公演を確認できる。

```text
選択中の公演
○○○○
所属団体：劇団○○

[変更]
[参加申請]
```

Key、QR、検索のいずれでも対象Productionを確認してから参加申請を行う。

---

## F02-PROD-02 公演参加申請

利用者は対象Productionへの参加申請を行う。

```text
○○○○への参加を申請しますか？

[キャンセル]
[参加申請]
```

申請直後は正式参加ではない。

状態は少なくとも以下を持つ。

```text
pending
active
rejected
```

Production側または権限を持つOrganization側が承認した時点で `active` とする。

検索から申請した場合は、必ず承認後に正式参加となる。

---

# 06 Key / QRによる参加

OrganizationおよびProductionには参加用KeyとQRコードを発行できる。

QRコードは参加Keyまたは参加用トークンを表現し、読み取り後に対象Entityを直接特定する。

ただし、KeyやQRを入力・読み取った場合でも、対象Entityを確認する画面を経由する。

```text
Key / QR
↓
対象Organization / Productionを取得
↓
対象確認
↓
参加申請または参加処理
```

参加用KeyやQRは、Entityの内部IDを直接露出する仕組みではなく、参加用トークンとして管理する。

---

# 07 Membership状態

Organizationへの所属とProductionへの参加は、単純な所属有無ではなく状態を持つ。

少なくとも以下を扱う。

```text
pending  : 承認待ち
active   : 正式所属・正式参加
rejected : 拒否
```

将来的には退団・参加終了等の状態を追加可能とする。

概念構造は以下とする。

```text
Person
├─ Organization Membership / Join Request
│   ├─ pending
│   ├─ active
│   └─ rejected
│
└─ Production Membership / Join Request
    ├─ pending
    ├─ active
    └─ rejected
```

既存Domain ModelのMembership系Entityがある場合は、同じ概念を重複するEntityとして追加せず、既存構造に状態管理と参加申請Flowを整合させる。

---

# 08 所属なしユーザー

OrganizationにもProductionにも所属していないPersonは正常な利用者状態である。

例えば観劇客は、所属なしのまま以下を利用できる。

- Organizationをフォロー
- Productionをお気に入り
- 公演情報を閲覧
- チケット情報を確認
- 観劇記録を管理

Homeに「どこにも所属していない」という状態を不要に強調表示しない。

後からマイページ等からOrganizationまたはProductionへの所属・参加申請を開始できる。

---

# 09 初回Onboarding Flow全体

```text
アカウント登録 / Google認証
↓
姓名入力・確認
↓
初回目的選択
│
├─ 団体管理者
│   ↓
│   Organization作成
│   ↓
│   直近Production設定（任意）
│   ↓
│   Home
│
├─ 団体・公演所属者
│   ↓
│   団体に所属しますか？（任意）
│   ↓
│   Key / QR / 検索
│   ↓
│   所属申請（承認待ち）またはスキップ
│   ↓
│   公演に参加しますか？（任意）
│   ↓
│   Key / QR / 検索
│   ↓
│   参加申請（承認待ち）またはスキップ
│   ↓
│   Home
│
└─ 観客
    ↓
    必要な初期設定のみ
    ↓
    Home
```

---

# 10 Blueprint確定事項

1. 団体所属と公演参加は初回Onboardingでそれぞれ個別に設定できる。
2. 団体と公演はそれぞれスキップ可能。
3. 団体への参加方法はKey入力、QRコード読み取り、団体検索の3方式。
4. 公演への参加方法はKey入力、QRコード読み取り、公演検索の3方式。
5. 検索画面は別画面またはモーダルで開き、選択後に元のOnboarding画面で対象を確認する。
6. 検索からの所属申請はOrganization側の承認後に正式所属となる。
7. 検索からの公演参加申請はProduction側または権限を持つOrganization側の承認後に正式参加となる。
8. 申請状態として少なくとも pending / active / rejected を持つ。
9. KeyおよびQRによる参加でも対象確認画面を経由する。
10. KeyやQRは内部Entity IDを直接公開するのではなく、参加用トークンとして管理する。
11. OrganizationにもProductionにも所属していないPersonは正常な利用者状態とする。
12. 所属なし状態をHomeで不要に強調表示しない。
13. 後からいつでも所属・参加申請を開始できる。

---

End of Blueprint


---

# ApplicationからPublic Pageへのリンクと管理入口の責務

## 「管理する」と「公開ページ」は別の操作

Organization / Production一覧など、Application側の管理画面に同一Entityについて「管理する」と「公開ページ」の両方を表示する場合、両者の遷移先を混同してはならない。

### Productionの例

    Production
    ├─ 管理する
    │   └─ Application
    │      └─ Production Management Context
    │         ├─ 概要
    │         ├─ 公開情報設定
    │         ├─ 参加者
    │         ├─ スケジュール
    │         ├─ 稽古
    │         └─ その他の管理機能
    │
    └─ 公開ページ
        └─ Public Site
           └─ https://stageart.top/{organization-slug}/{production-slug}

「公開ページ」リンクをApplication画面内のスケジュール画面やProduction内部画面へ接続してはならない。

逆に、「管理する」をPublic Pageへ接続してもならない。

## 公開情報設定とPublic Pageの区別

Application側には、Organization / Productionの公開ページに表示する情報を設定・管理するための機能を持たせる。

これはPublic Pageそのものではない。

    Application
    公開情報を編集する
    公開状態を変更する
    公開日時を設定する
    公開内容を確認する
            ↓
    Public Site
    一般利用者が公開済み情報を閲覧する

同じ公開情報を扱う場合でも、

- Application：管理・編集・公開制御
- Public Site：一般閲覧

という責務を分離する。

## 画面レビュー時の確認ルール

Organization / Productionに関する画面を確認する際は、必ず次の順で判定する。

1. Hostは app.stageart.top か stageart.top か
2. その画面の利用者は管理・参加者か一般閲覧者か
3. その画面は情報を編集・運営するのか、公開済み情報を閲覧するのか
4. 「管理する」導線と「公開ページ」導線が別責務として実装されているか

URLのPathや画面タイトルだけで「団体ページ」「公演ページ」を判定してはならない。
