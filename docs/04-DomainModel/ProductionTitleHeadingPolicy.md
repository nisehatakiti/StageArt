# StageArt Blueprint

# Production Title Heading Policy

Version : 1.0

---

# Purpose

Productionにおける「公演肩書」を、公演タイトルとは独立した正式なProduction情報として定義する。

公演肩書は、一般利用者向けの表示だけの装飾情報ではなく、Productionに属するBusiness Dataとして扱う。

---

# Terminology

Productionは、少なくとも以下の二つの文字情報を区別して保持できる。

- 公演肩書（Production Title Heading）
- 公演タイトル（Production Title）

公演肩書は、公演タイトルの上部に表示するための補助的な見出し情報である。

公演タイトルは、公演そのものの名称である。

公演肩書は公演タイトルの一部として連結保存しない。

---

# Examples

公演肩書には、以下のような自由な表現を設定できる。

- 旗揚げ公演
- 第3回公演
- 第10回記念公演
- ○○プロデュース公演
- 特別公演
- 番外公演
- 15周年記念公演

StageArt側で「第○回」「旗揚げ」等の意味を解釈する必要はない。

公演肩書は自由入力文字列として扱う。

---

# Production Domain

公演肩書はProduction固有の属性として管理する。

基本構造：

Project
    ↓
Production
    ├── Production Title Heading
    └── Production Title

ProjectはInternal Domainであり、公演肩書をProject側の属性として管理しない。

Projectに複数Productionが存在する場合でも、公演肩書は各Productionごとに独立して設定できる。

同一Project内のProductionごとに、異なる公演肩書を設定できる。

---

# Production Information

Productionの基本情報として、少なくとも以下を区別する。

- Production Name / Title
- Production Title Heading
- Description
- Venue
- Start Date
- End Date
- Status
- Public Settings

公演肩書の未設定は許可する。

未設定の場合、表示側は公演タイトルのみを表示する。

公演肩書が設定されている場合は、公演タイトルより上部に表示する。

---

# API

Production APIは、公演肩書をProduction情報の正式な属性として扱う。

ProductionのCreate / Get / Updateにおいて、公演肩書を扱える構造とする。

ProjectはInternal Domainであり、Production APIの公開Resourceとして公開しない。

公演肩書はProject情報としてAPIに持たせず、Production Resourceの属性として扱う。

---

# Logical ER

Logical ERでは、Production Entityの属性として公演肩書を保持する。

概念上、以下の構造とする。

Production
    ├── ProductionId
    ├── ProjectId
    ├── ProductionTitleHeading
    └── ProductionTitle

公演肩書を独立Entityにはしない。

公演肩書と公演タイトルを別Entityとして管理しない。

---

# Public Site

Production Public Siteでは、公演肩書が設定されている場合、原則として公演タイトルの上部に表示する。

表示例：

```text
第3回公演
○○○○○○
```

```text
○○プロデュース公演
△△△△△△
```

Organization Public Siteでも、最新公演および過去公演一覧において公演肩書を利用できる。

公演肩書が未設定の場合は、公演タイトルのみを表示する。

---

# Search / Listing

公演肩書はProductionに属する公開可能情報として扱う。

Production検索・一覧・公開ページ等で利用する場合、Production Titleと混同せず独立したフィールドとして扱う。

公演肩書をProductionの識別子として使用しない。

ProductionIdが正規の識別子であり、公演肩書および公演タイトルはいずれも変更可能な表示情報である。

---

# Update Behavior

公演肩書を変更した場合はProduction情報の変更として扱う。

公開済みProductionについて公演肩書を変更し、公開ページの表示内容に影響する場合は、Production Public Siteの更新対象とする。

Organization Public Siteに掲載されている最新公演または過去公演一覧の表示内容に影響する場合は、Organization Public Siteの更新対象とする。

---

# Authorization

公演肩書の登録・変更権限はProduction情報の更新権限に従う。

PrimaryManagerは更新できる。

ProductionDelegateは、割り当てられたRole / PermissionにProduction情報更新権限がある場合に更新できる。

公演肩書専用のRoleやPermissionは作成しない。

---

# V1

V1では、公演肩書を自由入力文字列として実装する。

「旗揚げ」「第○回」「記念公演」等をマスター化・分類化・自動採番しない。

公演肩書の意味をStageArtが解析して公演回数などの別データを生成しない。

---

# Consistency Rule

Production関連のBlueprint、API、Logical ER、Public Site設計、管理画面設計、Mobile設計において、公演肩書はProduction属性として扱う。

Project側に公演肩書を持たせない。

Public Siteだけに存在する表示用フィールドとして扱わない。

公演肩書を公演タイトルへ連結して保存する実装は禁止する。
