# StageArt Blueprint

# 10 - Architecture

Version : 1.0

---

# Purpose

Architectureは、
StageArt Domain Modelを実際のソフトウェアとして実現するための
システム構造と責務分離を定義する。

Architectureは、
Domain Modelで定義されたBusiness Conceptを変更しない。

Domain Modelが、

「StageArtが何を管理するか」

を定義するのに対して、

Architectureは、

「StageArtをどのようなソフトウェア構造で実現するか」

を定義する。

---

# 1. Architecture Principles

StageArtのArchitectureは、
以下を基本原則とする。

- DomainをInfrastructureから独立させる。
- Business RuleをUIに置かない。
- Business RuleをControllerに置かない。
- 外部Serviceへの依存をDomainへ持ち込まない。
- AuthenticationとBusiness Identityを分離する。
- AuthorizationをPerson / Scope / Role / Permissionで評価する。
- Domain FactをSingle Source of Truthとする。
- ArtifactをDomain Factの代替にしない。
- Domain間の責務を明確に分離する。
- 外部IntegrationをAdapterとして分離する。
- UIはBusiness Operationを提供する。
- Domain ModelをそのままUI構造へ変換しない。
- Database SchemaをDomain Modelそのものとして扱わない。
- Infrastructureの変更によってDomain Ruleが変更されない構造を目指す。

---

# 2. System Boundary

StageArtは、
舞台芸術活動を管理するApplicationである。

StageArt自身が管理する主な領域：

- Identity
- Organization
- Project
- Production
- Participant
- Rehearsal
- Performance
- Ticket
- Reservation
- Check In
- History
- Accounting
- Communication
- Document
- Promotion
- Equipment
- Regulation
- Survey

StageArtは、
以下のExternal Serviceと連携できる。

- Authentication Provider
- External Storage
- Calendar Service
- Social Media
- Email Service
- その他External Service

External Serviceは、
StageArtのDomain Modelそのものではない。

External Serviceとの接続は、
Integration Layerで吸収する。

---

# 3. High Level Architecture

StageArtの基本構造：

UI
↓
API
↓
Application
↓
Domain
↓
Infrastructure
↓
External Service / Database / Storage

基本的な責務は以下の通り。

UI：

利用者とのInteractionを提供する。

API：

外部からのRequestを受け取り、
Applicationへ処理を委譲する。

Application：

Business Use Caseを実行する。

Domain：

Business Rule、
Entity、
Value Object、
Domain Eventなどを管理する。

Infrastructure：

Database、
External API、
File Storage、
Authentication Providerなどの
具体的な技術実装を担当する。

---

# 4. Layered Architecture

StageArtでは、
以下のLayerを基本とする。

```text
Presentation
      ↓
Application
      ↓
Domain
      ↓
Infrastructure
