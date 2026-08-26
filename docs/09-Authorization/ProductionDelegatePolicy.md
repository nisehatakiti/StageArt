# StageArt Blueprint

# Production Delegate Policy

Version : 1.0

---

# Purpose

Production ScopeにおけるPrimaryManagerとProductionDelegateの責務を明確化する。

PrimaryManagerは、公演について最終的な責任を持つ。
ProductionDelegateは、PrimaryManagerを補佐して公演を実務として運営する。

---

# PrimaryManager

PrimaryManagerはProduction Scopeにおける全権限を持つ。

特に以下をPrimaryManagerの責務とする。

- Production Lifecycle Action
- Budgetの確定
- 決算完了の確定
- Production Archive
- Documentの公開・配布
- Google Drive Document Integrationの管理
- Production Delegateの管理
- Production全体の管理

Documentについては、PrimaryManagerが正式な資料公開を一手に担う。

Production Member間での資料交換・共有は行わない。

---

# ProductionDelegate

ProductionDelegateは、PrimaryManagerから委任された実務を担当する。

ProductionDelegateに付与する権限は以下を基本仕様とする。

## Member Management

- Memberの追加
- Memberの削除

## Schedule Management

- Rehearsal / Scheduleの作成
- Rehearsal / Scheduleの更新
- Rehearsal / Scheduleの削除

## Attendance Management

- 出欠・参加状況の確認
- 出欠の代理入力
- 出欠情報の更新

## Accounting Management

- 会場費・外注費等の会計データ入力
- Journal Entry等の会計データ入力
- 会計データの確認
- Budget / Actual等の予実状況の確認

ProductionDelegateはBudget確定および決算完了の確定権限を持たない。

## Ticket Management

- チケット状況の確認
- チケット情報の入力
- チケット情報の更新

## Announcement

Production Memberに対する業務上のお知らせ・連絡を発信できる。

資料の公開・配布はAnnouncementとは別のDocument管理機能であり、ProductionDelegateには付与しない。

## Google Calendar

Rehearsal / Timetableを選択してGoogle Calendarへ一括登録できる。

Google Calendarへの登録はStageArtのScheduleを外部Calendarへコピーする操作であり、StageArtのScheduleそのものを変更する権限とは別に扱う。

---

# ProductionDelegate Restrictions

ProductionDelegateは以下の操作を行わない。

- Documentの公開・配布
- Google Drive連携の管理
- Production Lifecycleの変更
- Budgetの確定
- 決算完了の確定
- ProductionのArchive
- Organization自体の管理
- Role / Permissionそのものの変更

ProductionDelegateがProduction Memberに対して資料を直接配布する機能は提供しない。

---

# Lifecycle Relationship

ProductionDelegateはACTIVE期間中の実務を担当できる。

ただし、Production Lifecycleを進めるActionはPrimaryManagerのみが実行する。

DRAFT → PLANNING
    PrimaryManagerのみ

PLANNING → ACTIVE
    PrimaryManagerのみ

ACTIVE → COMPLETED
    PrimaryManagerのみ
    かつ決算完了が必須

COMPLETED → ARCHIVED
    PrimaryManagerのみ

---

# Document Distribution Principle

Document管理は上意下達方式とする。

PrimaryManager
    ↓
Document公開
    ↓
Production Members

ProductionDelegate
    └─ Document公開権限なし

Production Member
    └─ 他Memberへの資料共有権限なし

Google Driveを利用する場合も、対象DriveはPrimaryManager等の公演管理者に紐づくGoogle Driveに限定する。

---

# Business Rules

- ProductionにはPrimaryManagerを設定する。
- PrimaryManagerはProduction Scopeの全権限を持つ。
- ProductionDelegateはRole / Permissionによって限定された実務権限のみを持つ。
- ProductionDelegateの権限はProduction単位で適用する。
- Member管理はProductionDelegateに許可する。
- Schedule管理はProductionDelegateに許可する。
- Attendanceの確認・代理入力はProductionDelegateに許可する。
- 会計データの入力・確認はProductionDelegateに許可する。
- Budget確定および決算完了の確定はPrimaryManagerのみが行う。
- Ticket状況の確認・入力・更新はProductionDelegateに許可する。
- Production Memberへの業務連絡はProductionDelegateに許可する。
- Google Calendarへの予定一括登録はProductionDelegateに許可する。
- Documentの公開・配布はPrimaryManagerが一手に担う。
- Production Member間の資料交換・共有は行わない。
- Google Drive連携の管理はPrimaryManager等の公演管理者に限定する。
- Production Lifecycleの変更はPrimaryManagerに限定する。
- Production ArchiveはPrimaryManagerに限定する。
- ProductionDelegateはOrganization自体の管理やRole / Permissionの変更を行わない。
