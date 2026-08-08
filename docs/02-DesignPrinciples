# StageArt Blueprint
# Chapter 2 : Design Principles

Version : 1.0

---

# Architect's Note

Design Principlesは、StageArtを設計・開発するすべての人が守るべき設計原則である。

新しい機能を追加する場合も、既存機能を変更する場合も、本章に定義された原則を優先する。

一時的な利便性のために、設計原則を破ってはならない。

---

# Principle 1
## Domain First

StageArtは画面やデータベースから設計しない。

すべての設計はDomain Modelを中心として行う。

画面はDomainを操作するためのUIであり、
データベースはDomainを永続化する手段である。

---

# Principle 2
## User First

利用者はシステムの内部構造を意識しない。

利用者が入力するのは、

・劇団を作る
・公演を作る
・予約する
・受付する

などの「やりたいこと」である。

ProjectやProductionなどの内部ドメインはシステムが自動生成・管理する。

---

# Principle 3
## Simple UI, Rich Domain

UIは可能な限りシンプルにする。

複雑さはすべてDomain Modelが吸収する。

---

# Principle 4
## Multi Tenant

StageArtは複数劇団が同一システム上で安全に利用できることを前提とする。

すべてのデータはOrganization単位で管理される。

他劇団のデータへアクセスできてはならない。

---

# Principle 5
## API First

すべての機能はREST APIとして提供する。

Web画面はAPIの利用者であり、
将来のFlutterアプリやLINE連携も同一APIを利用する。

---

# Principle 6
## Mobile Ready

スマートフォンで利用されることを前提として設計する。

受付業務やQRチケットなど、
モバイル利用を最優先に考える。

---

# Principle 7
## Event Driven

利用者の操作を起点として、
必要な内部データをシステムが自動生成する。

例）

「公演を作る」

↓

Project生成

↓

Production生成

↓

制作チェックリスト生成

↓

ドキュメント領域生成

↓

ホームページ生成

---

# Principle 8
## Single Source of Truth

同じ情報を複数箇所で管理しない。

情報には必ず唯一の管理主体（Owner）が存在する。

他の情報は参照または集計によって表現する。

---

# Principle 9
## Fact and Artifact

StageArtは

Fact（事実）

と

Artifact（成果物）

を区別する。

例）

Fact

・予約

・出演

・受付

Artifact

・QRチケット

・プロフィール

・ホームページ

・収支レポート

ArtifactはFactから生成される。

---

# Principle 10
## Backward Compatibility

アップデートによって既存データを破壊しない。

将来の機能追加を前提として設計する。

---

# Principle 11
## Plugin First

StageArtはWordPress Pluginとして実装する。

WordPressはCMSであり、
StageArtのBusiness Logicではない。

Business LogicはDomain Layerに実装する。

---

# Principle 12
## Framework Independent

StageArtのDomainはWordPressへ依存しない。

WordPressはInfrastructureとして扱う。

将来、他プラットフォームへ移植できる構造を維持する。

---

# Principle 13
## Incremental Development

MVPを最優先とする。

一度にすべての機能を実装しない。

小さく作り、
実際に利用しながら改善を続ける。

---

# Principle 14
## Blueprint First

コードを書く前にBlueprintを更新する。

Blueprintが唯一の設計基準（Single Source of Truth）である。

すべての実装はBlueprintに従う。

---

# Principle 15
## Theatre First

StageArtはITシステムではない。

舞台芸術のためのプラットフォームである。

設計判断に迷った場合は、

「舞台芸術に関わる人が創作活動へ集中できるか」

を最優先に判断する。