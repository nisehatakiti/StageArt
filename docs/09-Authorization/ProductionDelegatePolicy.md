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
- Documentの公開・配布（Production Document（台本・稽古資料等。Google Drive上の正本を参照するDocument Alias。DocumentConsistencyPolicy.md）のProduction Memberへの公開・配布。Production Public Pageの編集・公開ではない）
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

ここでいう資料の公開・配布は、Production Document（台本・稽古資料等。Google Drive上の正本を参照するDocument Alias。DocumentConsistencyPolicy.md）をProduction Memberへ公開・配布する操作を指す。Production Public Page（公演公開ページ）の編集や一般公開とは別の操作である（「公演公開ページの権限」参照）。

## Google Calendar

Rehearsal / Timetableを選択してGoogle Calendarへ一括登録できる。

Google Calendarへの登録はStageArtのScheduleを外部Calendarへコピーする操作であり、StageArtのScheduleそのものを変更する権限とは別に扱う。

---

# 担当区分と権限

上記の各権限は、ProductionDelegateであること自体ではなく、割り当てられた担当区分によって付与される。
担当区分の定義はProductionDelegate.md「担当区分」に従う。

| 担当区分 | 権限 |
|---|---|
| PrimaryManager | 公演管理者権限 |
| 公演代理人 | 公演管理者と同等の管理者権限 |
| 稽古担当 | 稽古管理に関する権限 |
| 会計担当 | 会計管理に関する権限 |

稽古担当・会計担当には、公演管理者権限を付与しない。

## Join Key Management

参加コード（Production Join Key）の一覧確認・発行・無効化は公演管理者権限に属する。

PrimaryManagerおよび対象Productionの公演代理人のみが行える。

稽古担当・会計担当は行えない。

Organization Join KeyはOrganizationのOwnerのみが管理し、Production Scopeの担当区分によって権限を与えない。

---

# ProductionDelegate Restrictions

ProductionDelegateは以下の操作を行わない。

- Documentの公開・配布（Production Document（台本・稽古資料等。Google Drive上の正本を参照するDocument Alias。DocumentConsistencyPolicy.md）の公開・配布）
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

# 公演公開ページの権限

Production Public Page（公演公開ページ）は、Document管理とは別の機能である。

| 操作 | PrimaryManager | 公演代理人 | 稽古担当 | 会計担当 |
|---|---|---|---|---|
| 公開ページに表示する公演情報の編集 | 可 | 可 | 不可 | 不可 |
| Slugの変更 | 可 | 可 | 不可 | 不可 |
| 一般公開・非公開の切替（Publicity / Public Visibility / 公開日時） | 可 | 可 | 不可 | 不可 |

- 公演代理人は、担当公演についてPrimaryManagerと同等の実務上の公演管理権限を持つ。代表者がIT操作に不慣れで、実務を代理人に委任する運用を想定する。
- 公開ページの内容を編集することと、公開ページを一般公開すること・公開と非公開を切り替えることは、設計上は別の操作である。
- いずれもPrimaryManagerと対象Productionの公演代理人に許可する。
- 詳細と実装状況はProductionPublicPagePolicy.md「Authorization」を参照する。

---

# 公演代理人の権限範囲

公演代理人（Participant ManagerとRehearsal Managerの両方をACTIVEで適用されたProductionDelegate）は、担当公演について次の操作を行える。

- 公演情報の編集
- 公演公開ページの編集
- 一般公開・非公開の切替
- Slugの変更
- 参加コードの管理
- 参加者の管理・参加申請の確認
- 稽古の作成・更新
- その他、担当公演の日常的な管理業務

次の操作は、最終責任者による確定・権限委任の操作として、引き続きPrimaryManagerのみが行う。

- Budgetの確定・決算完了の確定
- Production Lifecycleの変更
- ProductionDelegateの割当（代理人の割り当て）
- Production Document（台本・稽古資料等）のProduction Memberへの公開・配布

Production Documentの公開・配布は、Production Public Pageの公開とは別の機能である。

公演代理人の判定は、同一Productionに対するACTIVEなRoleをすべて取得して行う。データの取得順に依存しない。

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
- Documentの公開・配布（Production Document（台本・稽古資料等。Google Drive上の正本を参照するDocument Alias。DocumentConsistencyPolicy.md）の公開・配布）はPrimaryManagerが一手に担う。
- Documentの公開・配布はProduction Public Pageの編集・公開とは別の操作である。
- Production Member間の資料交換・共有は行わない。
- Google Drive連携の管理はPrimaryManager等の公演管理者に限定する。
- Production Lifecycleの変更はPrimaryManagerに限定する。
- Production ArchiveはPrimaryManagerに限定する。
- ProductionDelegateはOrganization自体の管理やRole / Permissionの変更を行わない。
- ProductionDelegateの権限は担当区分（公演代理人・稽古担当・会計担当）によって決まる。
- 公演代理人は公演管理者と同等の管理者権限を持つ。
- 稽古担当は稽古管理に関する権限のみ、会計担当は会計管理に関する権限のみを持つ。
- Production Join Keyの管理はPrimaryManagerおよび対象Productionの公演代理人に限定する。
- Organization Join Keyの管理はOrganizationのOwnerに限定する。
- 公演代理人は、担当公演の公開ページの内容の編集・Slugの変更・一般公開・非公開の切替を行える。
- Budgetの確定・決算完了の確定、Production Lifecycleの変更、ProductionDelegateの割当、Production Documentの公開・配布は、PrimaryManagerのみが行う。

---

# 改訂注記

- 2026-11-24：「Documentの公開・配布」「資料の公開・配布」の対象を、DocumentConsistencyPolicy.mdに基づきProduction Document（台本・稽古資料等。Google Drive上の正本を参照するDocument Alias。DocumentConsistencyPolicy.md）と明記した。Production Public Pageの編集・公開とは別の操作であることを明記した。既存の制限（ProductionDelegateにはDocumentの公開・配布を付与しない）は変更していない。
- 2026-11-24：「公演公開ページの権限」を追加した。
- 2026-11-24：公演代理人の権限方針の確定に基づき、公演公開ページの編集・Slug変更・公開切替を公演代理人に許可した（当初は「編集は方針、公開切替は未確定」としていた）。「公演代理人の権限範囲」を追加した。
