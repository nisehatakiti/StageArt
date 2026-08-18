# StageArt Blueprint

# Role / Authorization Consistency Policy

Version : 1.0

---

# Purpose

StageArtのRole / Permission / ScopeによるAuthorizationについて、Organization、Project、Production、Performance、Person、Document等のDomainと整合するCanonical Ruleを定義する。

---

# Canonical Model

Authorizationは以下の関係で解決する。

```text
Person
  ↓
Scope
  ↓
Role
  ↓
Permission
  ↓
Use Case
```

AuthenticationとAuthorizationは分離する。

UserAccountを持っていることだけでは、OrganizationやProductionのInternal DataへのAccessを許可しない。

---

# Scope Hierarchy

基本Scopeは以下とする。

```text
Organization
  ↓
Project
  ↓
Production
  ↓
Performance
```

下位ScopeへのAccessは、原則として上位ScopeへのAccessを前提とする。

---

# Organization Roles

Organization単位では、少なくとも以下の役割を想定する。

- Owner
- Administrator
- Member

具体的なPermissionの粒度は実装時に定義するが、Role名だけでSecurity Boundaryを構築せず、ScopeとPermissionの組み合わせで判定する。

Organization Ownerは団体登録時の実行Userを初期Ownerとする。

Owner権限は後から移譲できる設計を妨げない。

---

# Project Roles

Projectでは、Projectの企画・管理に必要なRole / Permissionを設定できる。

Project RoleはOrganization Membershipとは別に扱う。

ProjectへのAccessが必要なProject DocumentやProject Budget等は、Project ScopeのAuthorizationを適用する。

---

# Production Roles

Production管理者はStageArt Accountを所持するUserに限る。

Production管理者は後から移譲できる。

Production DelegateはPersonとProductionのAccess関係を表し、Role / Permissionを付与できる。

Production ManagerであることだけでOrganization全体の管理権限を得ることはない。

---

# Performance Roles

PerformanceはProduction配下のOperation Scopeとして扱う。

Performance固有の権限が必要な場合はPerformance Scopeで制御するが、基本的にはProduction Roleを継承する。

---

# Document Authorization

Document AliasのStageArt上の表示・操作可否は、Documentが属するScopeのRole / Permissionに従う。

```text
Project Document
  → Project Authorization

Production Document
  → Production Authorization
```

StageArtのRole Policyは、Google Driveの実ファイル権限を置き換えない。

Google Driveを正本とするDocumentについては、

```text
StageArt Authorization
        AND
Google Drive Access Control
```

の双方を満たす場合に実ファイルへ到達できることを基本とする。

StageArtがGoogle Drive側の共有権限を自動的に拡張してはならない。

---

# Public Data

一般公開ページはInternal Role Authorizationとは別のPublic Visibilityによって制御する。

Public VisibilityがOFFのProductionは一般公開ページを生成しない。

Public VisibilityがONでも、未確定情報はComing Soon等のPresentation Ruleに従う。

Public ProfileについてはPerson Profileの各項目Visibilityに従う。

---

# Personal Profile

Personは自身のProfileを編集できる。

ただし、一般公開可否はProfile Item単位のVisibility設定に従う。

Organization所属情報はOrganization Membershipの承認状態を正本とし、本人Profileだけで所属を確定できない。

---

# Production Membership / Participant

Production Participantの追加・変更はProduction管理権限に従う。

Person自身が既存ProductionのParticipantとして履歴へ自己認定することはできない。

本人が該当Participantを申告し、Production管理者の承認を経て本人履歴との関連を確定する。

---

# Accounting Authorization

Account、Budget、Journal Entry等の会計Dataは、会計管理権限を必要とする。

Production管理権限だけではOrganization Account MasterやOrganization全体のJournal Entryを変更できない。

Project BudgetはProject Scope、Production BudgetはProduction ScopeのAuthorizationを適用する。

Journal EntryのPosting、Reversal、Correction等の重要操作は適切なAccounting Permissionを必要とする。

---

# Scope-aware Query

AuthorizationはServer Sideで必ず実行する。

所属外DataをFrontendへ取得してから非表示にする設計は禁止する。

```text
Request
  ↓
Authentication
  ↓
Authorization
  ↓
Scope Resolution
  ↓
Scope-aware Query
  ↓
Response
```

Resource IDを知っているだけではAccessを許可しない。

---

# Role Inheritance

下位Scopeが明示的なRoleを持たない場合、親ScopeのRoleを継承できる設計とする。

ただし、継承によって上位Scopeの権限を意図せず拡張してはならない。

特定Operationについては明示的Permissionを要求できる。

---

# Role vs Visibility

Role / PermissionとPublic Visibilityを混同しない。

- Role / Permission：誰がInternal Operation / DataへAccessできるか
- Public Visibility：一般公開するか
- Profile Visibility：個人情報の項目を一般公開するか
- External ACL：Google Drive等外部サービス側のAccess制御

これらは別のSecurity / Presentation Contextとして扱う。

---

# Audit

以下の重要なAuthorization-sensitive OperationはAudit対象とする。

- Owner変更
- Production Manager変更
- Role変更
- Permission変更
- Document Access Policy変更
- Budget確定
- Journal Entry Posting
- Journal Entry Reversal / Correction
- Participant承認
- Membership承認

---

# Design Principle

StageArtのAuthorizationは「Role名だけで許可する」のではなく、

```text
Actor
+ Scope
+ Role
+ Permission
+ Resource State
```

によってServer Sideで判定する。

Public Visibility、Profile Visibility、外部サービスACLはRoleとは別管理とし、それぞれの境界を越えて権限を拡張しない。
