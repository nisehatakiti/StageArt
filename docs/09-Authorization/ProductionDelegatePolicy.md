# StageArt Blueprint

# Production Delegate Policy

Version : 1.3

Version 1.3（2026-11-25、D-1 設計確定）：「決算確定」を公演の完了から独立した操作として表に加えた（公演管理者・公演代理人・会計担当）。公演の完了（公演管理者のみ）は決算確定後にのみ行える（`ProductionLifecycle.md` Version 1.3）。未実装。

---

# Purpose

Production ScopeにおけるPrimaryManagerとProductionDelegateの責務を明確化する。

PrimaryManagerは、公演について最終的な責任を持つ。
ProductionDelegateは、PrimaryManagerを補佐して公演を実務として運営する。

---

# PrimaryManager

PrimaryManagerはProduction Scopeにおける全権限を持つ。

特に以下をPrimaryManagerの責務とする。

- Production Lifecycle Action（公演の完了を含む）
- 公演管理者（PrimaryManager）の変更
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

Budgetの確定・予算実績の確定・決算・精算の最終確定・決算確定は、公演代理人と会計担当に許可する（Version 1.2、決算確定はVersion 1.3。「担当区分と権限一覧」参照）。それ以外のProductionDelegateは、これらの確定権限を持たない。

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
- Production Lifecycleの変更（公演の完了を含む）
- 公演管理者（PrimaryManager）の変更
- ProductionのArchive
- Organization自体の管理
- Role / Permissionそのものの変更

Budgetの確定・決算・精算の最終確定は、Version 1.2で公演代理人・会計担当に許可した。決算確定（公演会計の確定・ロック）も、Version 1.3で公演代理人・会計担当に許可した（公演の完了とは別の操作）。公演代理人による担当（公演代理人・会計担当・受付担当）の任命・変更は「担当区分と権限一覧」に従う。

ProductionDelegateがProduction Memberに対して資料を直接配布する機能は提供しない。

---

# Lifecycle Relationship

ProductionDelegateはACTIVE期間中の実務を担当できる。

ただし、Production Lifecycleを進めるActionはPrimaryManagerのみが実行する。ACTIVE → COMPLETED（公演の完了）もPrimaryManagerのみとする（Version 1.2、2026-11-24確定。Version 1.1では会計管理者と代理人も実行できるとしていた）。

DRAFT → PLANNING
    PrimaryManagerのみ

PLANNING → ACTIVE
    PrimaryManagerのみ

ACTIVE → COMPLETED（公演の完了）
    PrimaryManagerのみ
    かつ決算確定済みであることが必須（Version 1.3）
    （決算・精算の最終確定と決算確定は、公演管理者・公演代理人・会計担当が行う。
     決算確定はStatusを変えない会計上の操作であり、Lifecycle Actionではない）

COMPLETED → ARCHIVED
    PrimaryManagerのみ

---

# 担当区分と権限一覧（Version 1.2、2026-11-24確定）

PR #8 統合で確定した正式仕様。担当区分ごとの権限は次の表に従う。

| 操作 | 公演管理者（PrimaryManager） | 公演代理人 | 会計担当 |
|---|:---:|:---:|:---:|
| 公演情報編集 | ○ | ○ | × |
| 公開ページ編集 | ○ | ○ | × |
| 公開・非公開切替 | ○ | ○ | × |
| Slug変更 | ○ | ○ | × |
| 定員・公演回設定・上演時間変更 | ○ | ○ | × |
| 参加者・メンバー管理 | ○ | ○ | × |
| Join Key管理 | ○ | ○ | × |
| 稽古管理 | ○ | ○ | × |
| 予算策定・編集 | ○ | ○ | ○ |
| 予算確定 | ○ | ○ | ○ |
| 経費入力・編集（自分が申請者の経費） | ○ | ○ | ○ |
| 他人の経費編集 | × | × | × |
| 経費承認 | ○ | ○ | ○ |
| 予算実績の確定 | ○ | ○ | ○ |
| 決算・精算の最終確定 | ○ | ○ | ○ |
| 決算確定（決算確定日時・実行者を記録し、公演会計をロック。Version 1.3） | ○ | ○ | ○ |
| 公演代理人の任命・変更 | ○ | ○ | × |
| 会計担当の任命・変更 | ○ | ○ | × |
| 受付担当の任命・変更 | ○ | ○ | × |
| 公演管理者の変更 | ○ | × | × |
| 公演の完了（決算確定後のみ。Version 1.3） | ○ | × | × |
| 公演資料の配布 | ○ | × | × |

- 公演代理人：Participant ManagerとRehearsal Managerの両方をACTIVEで適用されたProductionDelegate。公演管理者から現場運営を任された実務責任者として、担当公演の運営と会計業務を完結できる。
- 会計担当：ACCOUNTING_MANAGERをACTIVEで適用されたProductionDelegate。担当公演の会計業務に限定する。
- 予算策定から決算・精算の最終確定までの会計業務（予算策定・経費入力・自動仕訳・予算実績対比・経費承認・予算実績の確定・決算・精算の最終確定）は、公演管理者・公演代理人・会計担当が行える。決算確定も公演管理者・公演代理人・会計担当が行える。その後の公演の完了は公演管理者のみが、決算確定後にのみ行う。会計業務の最終確定・決算確定と公演の完了は別の権限・別の操作とする（Version 1.3）。決算確定後は、会計結果・金額に影響する操作を誰も行えない（`ProductionLifecycle.md`「決算確定後のロック」）。
- 経費の編集は申請者本人のみが行える。公演管理者・公演代理人・会計担当であっても、他人が入力した経費は編集できない（ExpenseApprovalWorkflow.md）。承認者は内容を直接編集せず、問題があれば理由を付けて差し戻し、申請者本人が修正して再申請する。
- 経費承認では、本人による承認・差し戻しの禁止（ExpenseApprovalWorkflow.md）を引き続き適用する。
- 公演代理人が任命・変更できるRoleは、公演代理人（PARTICIPANT_MANAGER・REHEARSAL_MANAGER）・会計担当（ACCOUNTING_MANAGER）・受付担当（CHECKIN_MANAGER）に限る。それ以外のRole（チケット担当等）の任命・変更はPrimaryManagerのみが行う。
- 公演代理人は担当公演のRoleだけを変更できる。他の公演のRoleは変更できない。
- 公演管理者はProductionDelegateとは別に必ず存在するため、公演代理人が自分自身や他の公演代理人のRoleを外しても、公演を管理できる者がいなくなることはない。
- 判定は同一Productionに対するACTIVEなRoleをすべて取得して行い、データの取得順に依存しない。ACTIVEでないRoleは権限を与えない。
- 公演情報の更新APIで公演代理人が変更できる項目は、公演名・肩書・Slug・公開状態（公開日時）・公開ページの各項目・定員・公演回の共通設定・上演時間に限る。Lifecycle Statusと公演管理者は変更できない。

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
- Budgetの確定・予算実績の確定・決算・精算の最終確定は、PrimaryManager・公演代理人・会計担当が行う（Version 1.2）。
- 決算確定は、PrimaryManager・公演代理人・会計担当が行う（Version 1.3）。
- 公演の完了（ACTIVE → COMPLETED）、公演管理者の変更、公演資料の配布はPrimaryManagerのみが行う（Version 1.2）。公演の完了は決算確定後にのみ行える（Version 1.3）。
- Ticket状況の確認・入力・更新はProductionDelegateに許可する。
- Production Memberへの業務連絡はProductionDelegateに許可する。
- Google Calendarへの予定一括登録はProductionDelegateに許可する。
- Documentの公開・配布はPrimaryManagerが一手に担う。
- Production Member間の資料交換・共有は行わない。
- Google Drive連携の管理はPrimaryManager等の公演管理者に限定する。
- Production Lifecycleの変更はPrimaryManagerに限定する。
- Production ArchiveはPrimaryManagerに限定する。
- ProductionDelegateはOrganization自体の管理やRole / Permissionの変更を行わない。
