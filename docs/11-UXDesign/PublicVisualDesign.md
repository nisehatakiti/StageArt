# StageArt Blueprint

# Public Visual Design : Ichimonji Curtain Navigation

Version : 1.0

---

# Purpose

劇団ページおよび個人ページを中心としたStageArtのPublic UIについて、StageArt固有のビジュアルアイデンティティを定義する。

本デザインでは、舞台上部に吊られる「一文字幕（いちもんじまく）」をモチーフとした上部ナビゲーションを採用する。

管理画面Dashboard / 業務管理画面では、この一文字幕ナビゲーションを使用しない。

---

# Design Concept

StageArtのPublic UIは、一般的なSaaS管理画面ではなく、演劇・舞台の世界観を感じられるPublic Webとして設計する。

ただし、演劇的な装飾を過剰に施すのではなく、実際の劇場・舞台機構をモチーフとして利用することで、StageArt固有の意味を持たせる。

主要なモチーフ：

- 一文字幕
- 舞台上部の灯体
- 幕の向こうから漏れる舞台照明
- 舞台を想起させる水平構造

---

# Ichimonji Curtain

一文字幕は、舞台上部に横長に張られる幕をモチーフとする。

StageArtでは、Public UIの上部に**横方向に一枚の連続した一文字幕**を配置する。

重要な原則：

- 一文字幕そのものは一枚の横長の幕として扱う
- メニューごとに幕を分割しない
- 幕自体を発光させない
- 幕の表面に光るボタン表現を作らない
- 幕の下側に灯体があるように見せる
- 選択中のメニュー位置に対応する灯体だけを点灯させる

---

# Lighting Behavior

選択中のメニューの下だけ、幕の下から舞台照明が当たっているように見せる。

基本状態：

```text
一文字幕（一枚の横長の幕）
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   ○          ○          ○          ○
   ○          ○          ○          ○
   ↓          ↓          ↓          ↓
  消灯       点灯       消灯       消灯
```

選択されたメニューの下にある灯体だけが光る。

光は幕自体を照らして発光させるのではなく、**幕の下側から漏れる照明**として表現する。

---

# Interaction

## Default

選択されていないメニューは通常状態とする。

灯体は消灯または極めて弱い状態とする。

## Hover

Hover時には対象メニューの灯体を弱く点灯させることができる。

ただし、Hoverによって幕そのものを発光させてはならない。

## Active

現在表示しているページに対応するメニューの下の灯体を点灯させる。

Active状態では、選択中であることが一目で分かる程度の照明強度とする。

## Transition

メニュー変更時には、灯体の点灯位置が移動するような短いTransitionを使用する。

過度なアニメーションは避け、舞台照明が切り替わるような自然な動きを基本とする。

---

# Navigation Structure

一文字幕の上または周辺に、Public UIの主要ナビゲーションを配置する。

対象は主として：

- 劇団トップ
- 公演
- メンバー
- その他、Publicに公開する劇団情報

個人ページでは、個人プロフィールおよび公開履歴等へのNavigationを同じビジュアルルールで構成する。

具体的なメニュー項目は各Public Pageの情報構造に従う。

---

# Organization Page

劇団ページでは、Public Navigationと一文字幕をページ全体の共通Headerとして使用する。

想定構造：

```text
┌─────────────────────────────────────────────┐
│ StageArt / Organization                     │
│                                             │
│      一文字幕（横一枚）                      │
│      ─────────────────────                  │
│       灯体   ●   灯体   灯体                │
│              ↑                              │
│           選択中                             │
├─────────────────────────────────────────────┤
│                                             │
│              Public Content                 │
│                                             │
└─────────────────────────────────────────────┘
```

劇団Logo、劇団説明、公演情報、Member情報等のPublicコンテンツを配置する。

---

# Person Page

個人ページも劇団ページと同じ一文字幕のVisual Languageを使用する。

ただし、個人ページでは個人プロフィール、所属、出演・Staff履歴等を主コンテンツとする。

劇団ページと個人ページでNavigationの情報構造が異なる場合でも、以下は共通とする。

- 一文字幕は一枚
- 幕は発光しない
- 選択中のメニューの下の灯体だけ点灯
- 舞台照明を想起させる光の表現

---

# Public Production Page

Production Public Pageについても、劇団ページ / 個人ページと整合するPublic Visual Languageを使用する。

公演ページでは、Flyer、Production Title、概要、Performance、Venue、Ticket、Member等を中心に構成する。

情報公開済みで会場・公演回・Ticket等が未確定の場合は、既定のPublic Page構造を維持しつつComing Soon表示を行う。

---

# Relationship with Management UI

Public UIとManagement UIでは目的を明確に分離する。

## Public UI

- 一文字幕を使用する
- 舞台照明をVisual Identityとして使用する
- 劇団・個人・公演を魅力的に見せる
- 演劇らしい世界観を表現する

## Management UI

- 一文字幕を使用しない
- 左サイドメニューを基本Navigationとする
- 情報量と操作性を優先する
- Dashboardは業務状況の把握を目的とする

---

# Visual Principle

一文字幕は単なる装飾ではなく、StageArtのPublic UIにおける「舞台」を象徴するVisual Elementとする。

幕そのものは静かに存在し、必要な場所だけ灯体が点灯することで、舞台の向こう側に何かが始まっていることを感じさせる。

したがって、UI全体を派手な劇場風にするのではなく、**一文字幕とその下の照明をStageArt固有のアクセントとして使う**。

---

# Implementation Notes

一文字幕はHTML/CSS等によるUI Componentとして実装可能な構造とする。

推奨Component概念：

```text
PublicHeader
 ├─ Brand
 ├─ IchimonjiCurtain
 │   ├─ NavigationItem
 │   ├─ NavigationItem
 │   ├─ NavigationItem
 │   └─ NavigationItem
 └─ PublicHeaderContent
```

灯体はNavigationItemのactive状態に連動する。

幕本体と灯体を別Visual Layerとして扱い、幕の発光と灯体の発光を混同しない。

具体的な色、光量、Blur、Transition Duration、Typography、Responsive Layout等はVisual Design System / UI実装工程で定義する。
