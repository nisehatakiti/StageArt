# StageArt Blueprint

# Domain Consistency Policy : Document

Version : 1.0

---

# Purpose

Document Domainについて、Project / Productionの責務、Google Driveを正本とする外部ストレージ方針、およびStageArtのRole Policyとの整合性を定義する。

---

# Canonical Role

StageArtのDocumentは、実ファイルそのものを保管するEntityではなく、外部に存在する正本Documentへの参照・管理情報として扱う。

StageArtはDocument Aliasを保持し、実ファイルの正本はGoogle Driveに置く。

```text
Google Drive
    └─ Canonical File
           ↑
           │ external reference
           │
StageArt
    └─ Document Alias
```

---

# Storage Principle

Project資料・Production資料等の実ファイルをStageArtの通常ストレージへコピーして正本として保存しない。

StageArtからGoogle Drive上の正本を開けることを基本とする。

StageArtはファイルそのものではなく、Documentの名称、所属Scope、Google Drive参照情報、表示・アクセスに必要なメタデータを管理する。

---

# Project Documents

Project単位で管理するDocumentは、企画全体に関係する資料を対象とする。

例：

- 企画書
- 制作計画
- Project共通資料
- 複数Productionに共通する資料
- 契約・管理資料

Project DocumentはProjectに直接紐づく。

---

# Production Documents

Production単位で管理するDocumentは、個別公演に固有の資料を対象とする。

例：

- 台本
- 稽古資料
- 公演進行資料
- 当日資料
- 公演固有の制作資料

Production DocumentはProductionに直接紐づく。

---

# No Direct Performance Document Ownership

PerformanceはDocumentの主要な所有Scopeとしない。

公演回固有の資料が将来必要になった場合でも、まずProduction Documentとして扱うことを基本とする。

---

# Alias Metadata

Document Aliasは少なくとも以下を管理できるものとする。

- Display Name
- Scope Type
- ProjectId または ProductionId
- External Provider
- External Document Identifier
- External URL / Reference
- Document Type
- Status
- Created At / Updated At

External Providerは将来Google Drive以外を扱える余地を残すが、初期実装ではGoogle Driveを正本Providerとする。

---

# Google Drive as Canonical Source

初期実装ではGoogle DriveをCanonical Sourceとする。

Google Drive上のファイル内容をStageArt側へ同期コピーして正本化しない。

StageArt上のDocument Aliasは、Google Drive上の正本Documentを識別できる情報を保持する。

---

# Access Control

Document AliasのStageArt上での表示・操作可否は、StageArtのRole / Authorization Policyに従う。

例えば、Project DocumentはProjectに対する権限、Production DocumentはProductionに対する権限を基準として制御する。

Document Aliasが表示可能であっても、Google Drive側のアクセス権限をStageArtが自動的に拡張してはならない。

実ファイルへの最終的なアクセス可否は、Google Drive側の共有権限にも従う。

したがって、StageArt Role PolicyとGoogle Drive ACLの双方を満たす場合に実ファイルへ到達できる設計を基本とする。

---

# Public Access

Project / Production Documentを一般公開することは、通常のPublic Pageの公開とは別に扱う。

Document AliasをStageArt上で一般表示可能にする場合でも、Google Drive上の正本を一般公開することをStageArtが自動的に行ってはならない。

公開WebページにGoogle Driveの非公開Document URLを埋め込むことは禁止する。

---

# External File Status

Google Drive上の正本が移動・削除・権限変更等された場合、Document Aliasが参照不能になる可能性がある。

StageArtは、可能な範囲でExternal Documentの状態を検証し、Broken / Access Denied等を識別できるようにする。

参照不能になった場合でも、Document Alias自体の履歴を不用意に削除しない。

---

# Lifecycle

Document Aliasは以下の状態を基本とする。

- ACTIVE
- ARCHIVED
- BROKEN

ARCHIVEDは利用停止を表すが、履歴・監査上必要な情報を保持する。

BROKENは外部正本へ到達できない状態を表し、StageArt内部で勝手にファイルを複製して復旧することはしない。

---

# Project / Production Separation

Project DocumentとProduction Documentを混同しない。

複数Productionに共通する資料はProject Documentとする。

特定Productionだけに必要な資料はProduction Documentとする。

例えば、東京公演と大阪公演の両方で使用する制作計画書はProject Document、東京公演専用の当日進行表は東京Production Documentとする。

---

# Design Principle

```text
Project
    └─ Project Document Alias
             ↓
       Google Drive Canonical File

Production
    └─ Production Document Alias
             ↓
       Google Drive Canonical File
```

StageArtは「ファイルストレージ」ではなく「企画・公演情報と外部正本Documentを結びつける管理層」としてDocumentを扱う。

アクセス制御は、StageArtのRole PolicyとGoogle Driveの権限を分離して考え、StageArtが外部ストレージ側の権限を意図せず拡張しないことを原則とする。
