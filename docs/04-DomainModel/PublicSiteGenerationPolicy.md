# StageArt Blueprint

# Public Site Generation Policy

Version : 1.0

---

# Purpose

StageArtから劇団の公開ホームページを生成・公開する方式を定義する。

StageArtのManagement Coreと、一般観客が閲覧する公開サイトは同一アプリケーションとして直接結合させず、公開サイトを生成物として独立させる。

この分離により、StageArt Coreの機能追加・改修・障害・仕様変更が、既に公開済みの劇団ホームページの表示や構造へ直接影響することを防ぐ。

---

# Canonical Architecture

基本構造は以下とする。

```text
StageArt Core
    │
    │ Organization / Production / Public Page Business Data
    │
    ▼
Generate / Publish
    │
    ▼
Organization Public Site
    ├─ Organization information
    ├─ Production information
    ├─ Past productions
    ├─ Public announcements
    └─ Ticket / Reservation entry points
```

StageArt Coreは公開サイトのSource of Truthである。

公開サイトはCoreのデータを直接参照して毎回画面を生成する方式を基本とせず、Coreから生成された公開用成果物を独立して配置・公開する。

---

# Organization Public Site

Organization Public Siteは、劇団そのものを紹介する公開ホームページとして扱う。

Production Public Pageは、そのOrganization Public Site内またはOrganizationから導かれる公開導線として扱う。

既存のProduction Public Page Policyで定義されたProductionの公開状態・Public Visibility・公開情報を、生成時のSourceとして利用する。

---

# Generation Independence

公開サイト生成後は、StageArt Coreの実装変更によって既存公開サイトのHTML/CSS/構造が自動的に変更されてはならない。

Core側で以下のような変更が行われても、既に公開済みのサイトは最後に生成・公開した状態を維持できる構造を基本とする。

- Domain Modelの変更
- REST APIの変更
- Management UIの変更
- CoreのFrontend変更
- Plugin内部実装の変更
- StageArtのバージョンアップ

公開サイトを更新する場合は、Coreから明示的に再Generate / Publishする。

---

# Source of Truth

公開サイトの情報源はStageArt Coreに一本化する。

公開サイト専用にProductionやOrganizationのBusiness Factを二重管理しない。

基本Flow：

```text
StageArt CoreでBusiness Dataを更新
        ↓
公開内容を確認
        ↓
Generate / Publish
        ↓
独立した公開サイトへ反映
```

生成済み公開サイトを直接編集してBusiness Dataを変更する運用は基本としない。

---

# Publish Boundary

公開サイトの生成と一般公開は、Production / OrganizationのPublicity Policyに従う。

Publicity StatusがPRIVATEのProductionを一般公開用成果物へ含めてはならない。

Public VisibilityがOFFのProductionを公開サイト上に表示してはならない。

Publicity StatusがPUBLICとなり、公開条件を満たしたProductionは、Generate / Publishによって公開サイトへ反映できる。

---

# Ticket / Reservation Boundary

StageArtはチケット販売・予約基盤そのものを担う。

外部チケット販売サービスをV1の基本構成として利用しない。

公開サイトは、StageArtで管理されるTicket / Reservationの販売・予約導線を提供する。

ただし、公開サイトのコンテンツ表示部分と、販売・予約処理のCore機能は分離する。

```text
Public Site
   │
   └─ Ticket / Reservation entry point
             ↓
       StageArt Core
             ↓
       Reservation / Ticket processing
```

公開サイトの生成物が独立していても、チケット販売処理自体を公開サイト内へ複製して二重管理してはならない。

---

# Failure Isolation

公開サイトはStageArt Coreの通常画面を直接レンダリングする構造に依存しない。

これにより、Core側の一時的な障害や更新によって、既に公開済みの劇団ホームページ全体が同時に利用不能になるリスクを低減する。

ただし、Ticket / Reservation等のリアルタイム業務機能はCoreとの接続を必要とするため、公開サイトのコンテンツ表示と業務処理の可用性を同一視しない。

---

# Versioned Publication

Generate / Publishされた公開サイトは、生成時点のBusiness Dataと公開テンプレートの組み合わせによる独立した公開版として扱える構造を基本とする。

再Generate / Publishを行うまでは、以前の公開版を維持できることを基本とする。

Generateに失敗した場合、既存の正常な公開版を破壊してはならない。

新しい公開版の生成・検証が完了した後に公開切替を行う方式を推奨する。

---

# Custom Domain / Hosting

公開サイトの配置先、独自ドメイン、DNS、CDN等の具体的なInfrastructureは別途決定する。

本Policyでは、公開サイトがStageArt Coreから独立した生成物として扱われることのみを確定する。

---

# V1 Scope

V1では、以下を優先する。

1. Organization Public Siteの生成
2. Production Public Pageの生成
3. 公演情報・劇団情報の公開
4. Ticket / ReservationへのStageArt内導線
5. Core変更から公開サイトを分離するGenerate / Publish構造

高度なCMS機能、公開サイトの自由なテーマ編集、外部販売サービス連携等はV1の必須条件としない。

---

# Business Rules

- StageArt CoreをOrganization / Productionの公開情報におけるSource of Truthとする。
- 劇団ホームページはStageArt Coreから生成・公開する。
- 公開サイトはCoreのManagement UIをそのまま一般公開する方式としない。
- 生成済み公開サイトはCoreの実装変更から独立して維持できるものとする。
- 公開サイトを更新する場合は明示的なGenerate / Publishを行う。
- 公開サイト専用にBusiness Factを二重管理しない。
- PRIVATEまたはPublic Visibility OFFのProductionを公開サイトへ表示しない。
- StageArt自身がTicket / Reservation基盤を担い、外部チケットサービス連携をV1の前提としない。
- 公開サイトのコンテンツ表示とTicket / Reservation処理を分離する。
- 新しい公開版の生成に失敗した場合、既存の正常な公開版を破壊しない。
- Core障害時にも、既に生成済みの公開コンテンツは可能な限り独立して閲覧できる構造とする。

---

# Design Principle

> StageArt Coreで作り、公開サイトは独立して公開する。

StageArtのCoreは劇団運営の正本であり、公開サイトはCoreの現在の実装そのものではなく、Coreから生成された公開成果物として扱う。

これにより、StageArtの進化と劇団の公開サイトの安定性を両立する。
