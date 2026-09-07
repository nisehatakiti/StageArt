# StageArt BluePrint

## 04. 全画面共通ナビゲーション設計

### 1. 基本方針

StageArtのHomeは「機能一覧」ではなく、そのユーザーが現在必要としている情報・操作への入口とする。

一方、主要機能へ移動するための共通ナビゲーションをWeb / Mobileで統一する。

**Web版とMobile版でナビゲーションの考え方を分けない。**

Web版では左サイドメニューを表示し、Mobile版では同じ左サイドメニューをハンバーガーメニューから開閉できるようにする。

画面サイズに応じて表示方法は変えるが、メニュー項目・階層・権限制御・業務上の意味は共通とする。

ただし、QRコード受付など、端末固有の操作特性を活かす必要がある機能については、共通サイドメニューの通常画面とは別の専用画面を設けてよい。

---

## 2. 共通サイドメニュー

StageArtの主要な業務ナビゲーションはサイドメニューを基本とする。

共通サイドメニューは、**全画面で固定表示する領域**と、**現在のContextに応じて置き換える領域（Context Area）**で構成する。

### 2.1 固定領域

以下はContextに関係なく全画面で共通表示する。

- ホーム
- マイページ
- 設定
- ログアウト

「探す」と「お気に入り」は共通サイドメニューには常駐させない。これらはHome画面から利用する。

### 2.2 Context Area

固定領域の中央には、現在操作しているContextに応じて内容を置き換えるContext Areaを配置する。

Context Areaは以下の順序で構成する。

```text
ホーム

────────────

[現在のContext名]

────────────

[Context固有メニュー]

────────────

マイページ
設定
ログアウト
```

Context名は単なるメニュー項目ではなく、現在どのOrganization / Production / System Administration Contextを操作しているかを示す見出しとして表示する。

Context固有メニューのみがContextおよびRole / Permissionに応じて変化する。固定領域は置き換えない。

### 2.3 Home Context

Homeを押すと、現在のOrganization / Production等のContextを解除し、StageArt全体のHomeへ戻る。

Home ContextではContext Areaを表示しない。

```text
ホーム

────────────

マイページ
設定
ログアウト
```

「探す」「お気に入り」「所属団体」「参加中の公演・活動」「観劇履歴」等の個別入口は、必要に応じてHome画面内に配置する。

### 2.4 Production Contextの表示例

```text
ホーム

────────────

第10回公演

────────────

概要
参加者
スケジュール
稽古

────────────

マイページ
設定
ログアウト
```

Production名をContext見出しとして表示し、その下にProduction固有メニューを表示する。

### 2.5 Organization Contextの表示例

```text
ホーム

────────────

劇団StageArt

────────────

概要
メンバー
公演・活動

────────────

マイページ
設定
ログアウト
```

Organization管理権限を持つ場合も、管理項目は固定メニューを増やすのではなく、Organization Context Area内に追加する。

### 2.6 System Administration Contextの表示例

System Administrator権限を持つAccountがシステム管理画面に入った場合も、同じContext Area置換ルールを用いる。

```text
ホーム

────────────

システム管理

────────────

ダッシュボード
全体通知
アカウント管理
団体管理
公演管理
ログ管理

────────────

マイページ
設定
ログアウト
```

System Administrationは別アプリケーションや特殊ななりすまし画面として扱わず、StageArt内のContextの一つとして扱う。

### Web

Web版では左サイドメニューとして常時表示する。

### Mobile

Mobileでは画面常時表示のサイドバーを持たせるのではなく、画面上部のハンバーガーボタンから同じサイドメニューを開く。

ハンバーガーメニューは開閉式とし、メニューを閉じれば現在の画面を最大限利用できるようにする。

Mobile専用のBottom Navigationを主要ナビゲーションとして併設しない。

---

## 3. ナビゲーション項目と表示責務

### 固定項目

- ホーム：StageArt全体のHomeへ戻り、Contextを解除する
- マイページ：Person / UserAccount自身に関する情報への入口
- 設定：UserAccountまたはアプリケーション設定への入口
- ログアウト：認証セッションを終了する

### Context固有項目

Organization / Production / System Administration等の業務機能はContext Areaにのみ表示する。

表示可否は既存のRole / Permissionに従う。権限のない機能は表示しない。

### Home画面内の入口

以下は共通サイドメニューの固定項目ではなく、Home画面の情報・操作導線として扱う。

- 探す
- お気に入り
- 所属団体
- 参加中の公演・活動
- 観劇履歴

Homeは「機能一覧」ではなく、現在必要な情報・操作への入口であるという既存方針を維持する。

---

## 4. Home

Homeは機能一覧ではなく、現在のユーザーに必要な情報・操作への入口とする。

例：

- 所属団体の管理
- 参加中の公演・活動
- 次回稽古
- 出欠確認
- チケット・受付関連の作業
- 公演に関する通知
- 観劇予定

など、ユーザーの実際の状態に応じて表示する。

登録直後で所属・参加がないユーザーに対しても、Homeは正常な画面として成立する。

その場合は、団体・公演を探す、プロフィールを設定する等の一般的な入口を提供する。

---

## 5. 探す

「探す」から以下へ進める。

- 団体を探す
- 公演・活動を探す

これらは全ユーザー共通の発見機能である。

検索しただけではOrganization MembershipやProduction Participantは成立しない。

公演・活動を探した結果から、必要に応じて以下へ進める。

- 公開情報を見る
- It's MEを申請する
- 参加方法を確認する
- チケットを予約する

---

## 6. 所属団体

利用者が複数のOrganizationに所属することを前提とする。

所属団体から対象Organizationを選択し、そのOrganizationの情報・操作へ進む。

```text
所属団体
    ↓
Organization
    ├─ 概要
    ├─ メンバー
    ├─ 公演・活動
    ├─ 会計
    ├─ 備品
    └─ 団体公開ページ
```

表示・操作できる内容は、そのOrganizationにおけるMembership / Role / Permissionに従う。

Organization Membershipを持っているだけで、そのOrganization配下のすべてのProduction内部情報を自動的に閲覧できるようにしてはならない。

---

## 7. 参加中の公演・活動

Production Participantとして参加しているProductionを一覧化する。

```text
参加中の公演・活動
    ↓
Production
    ├─ 概要
    ├─ 参加者
    ├─ 稽古
    ├─ スケジュール
    ├─ ファイル
    └─ その他の公演情報
```

Production内部の情報は、そのProductionへの参加・権限等に基づいて表示する。

Organizationに所属していることだけを理由として、参加していないProductionの内部情報を表示してはならない。

また、Production Participantであることだけを理由として、Production管理者と同じ管理権限を与えてはならない。

---

## 8. 観劇履歴

観客としてチケットを予約した公演・活動について、Personに紐付く観劇履歴を確認できる。

```text
観劇履歴
    ↓
公演・活動
    ↓
公演回 / Ticket / Reservation等の履歴
```

観劇履歴はOrganization MembershipやProduction Participantとは独立したPerson側の情報として扱う。

---

## 9. お気に入り

お気に入りは、ユーザーが後から確認したい団体・公演・活動への入口とする。

最低限、以下を扱える構造とする。

- お気に入りの団体
- お気に入りの公演・活動

お気に入りの存在自体は所属や権限を意味しない。

---

## 10. マイページ

マイページはPerson自身に関する設定・情報への入口とする。

例：

- プロフィール
- ユーザー情報
- SNS連携
- 団体・公演への参加
- 招待・参加申請の確認
- その他設定
- ログアウト

参加導線として、必要に応じて以下を提供する。

- 招待URLを開く
- 参加コードを入力する
- 団体を探す
- 公演・活動を探す
- It's MEを申請する

ただし、実際の参加方法はOrganization / ProductionそれぞれのBusiness Flowに従う。

---

## 11. Organization / Production / Performance Context

StageArtでは複数のOrganizationおよびProductionを扱うため、詳細画面では現在どのコンテキストを操作しているかを常に識別可能にする。

例：

```text
劇団○○
  ↓
公演「○○○○」
  ↓
2026年10月10日 14:00 公演
```

Organization管理、公演管理、稽古管理、チケット管理等では、対象となるOrganization / Production / Performanceが分かるHeaderまたはContext表示を設ける。

特にチケット・受付では、どのProductionのどのPerformanceを扱っているかを明確にする。

---

## 12. 戻る操作

サイドメニューによる主要画面の移動とは別に、階層画面では明示的な戻る操作を提供する。

ユーザーがスワイプ操作を覚えていることを前提としない。

例：

```text
Home
 ↓
所属団体
 ↓
劇団○○
 ↓
公演「○○○○」
 ↓
稽古
```

稽古画面には明示的な「戻る」操作を設ける。

Web / Mobileで戻る操作の考え方を統一する。

---

## 13. QR受付等の専用画面

QRコード読み取りなど、端末のカメラやリアルタイム操作を中心とする業務は、通常のサイドメニュー画面とは別の専用画面として実装してよい。

代表例：

```text
Performance
    ↓
受付
    ↓
QRコード読み取り専用画面
```

専用画面では、カメラ起動、QRスキャン結果、受付処理など、その業務に必要な操作を優先する。

ただし、専用画面であっても、対象となるOrganization / Production / Performance Contextは明確に表示する。

専用画面を設けることは、WebとMobileで業務概念を分けることを意味しない。

---

## 14. Web / Mobileで共通にするもの

以下はWeb / Mobileで共通とする。

- Business Flow
- Domain Model
- 認証
- 権限・Permission
- Organization / Production / PerformanceのContext
- サイドメニューの業務上の階層
- 画面名称・主要な業務用語
- 明示的な戻る操作

以下はデバイス特性に応じて変えてよい。

- サイドメニューの表示方法
- 画面幅に応じたレイアウト
- 情報の同時表示量
- QRコード読み取り等の専用UI
- タッチ操作を活かした入力UI

原則として、Mobileだから別のBusiness Flowを作るのではなく、同じBusiness FlowをMobileに適したUIで提供する。

---

## 15. 管理機能の配置

管理機能を一般利用者向けの固定メニューとして常時表示しない。

ユーザーが実際に管理権限を持つOrganization / Production / Performance Contextに入った場合、その権限に応じて管理操作を表示する。

例：

```text
Organization
 ├─ 概要
 ├─ メンバー
 ├─ 公演・活動
 ├─ 会計
 └─ 設定
```

```text
Production
 ├─ 概要
 ├─ 参加者
 ├─ 稽古
 ├─ 公演回
 ├─ ファイル
 └─ 設定
```

権限のない項目は表示しない。

同じサイドメニュー構造を持たせつつ、利用可能な機能だけをユーザーへ提示する。

---

## 16. 確定事項

- Web版は左サイドメニューを基本ナビゲーションとする。
- Mobile版もWeb版と同じサイドメニュー構造を利用する。
- Mobile版ではハンバーガーメニューからサイドメニューを開閉する。
- Mobile版の主要ナビゲーションとしてBottom Navigationを採用しない。
- Web / MobileでBusiness Flow、Domain、権限、Context、メニューの業務上の意味を共通化する。
- ユーザーの権限がない機能はメニューに表示しない。
- Organization MembershipとProduction Participantのアクセス範囲を混同しない。
- Organization所属だけで、その配下のProduction内部情報を見せない。
- Production Participantだからといって管理権限を自動付与しない。
- 階層画面には明示的な戻る操作を設け、スワイプ操作だけに依存しない。
- QR受付等、端末特性を利用する業務は専用画面として実装してよい。
- 専用画面でも対象Contextを明示する。
- Homeは機能一覧ではなく、現在必要な情報・操作への入口とする。
