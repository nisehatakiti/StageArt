# PR #7 R6：Repository読み取りエラー処理の監査（2026-11-10）

## 背景

`$wpdb` は読み取りに失敗しても例外を投げず、`get_row()` は `null`、`get_results()` は空配列を返し、`last_error` だけを設定する。そのため、失敗した読み取りが「0件」「見つからない」として扱われ、後続処理がそれを前提に書き込んでしまうことがある。

R3で精算・決算完了・経費の経路（Reservation / Performance / Ticket / Settlement / Expense）に `WpdbReadGuard` を導入した。R6では、それ以外のRepositoryを監査し、下表のとおり分類した。

- **A**：会計・予約・公演完了の整合性に影響する（誤った書き込みが確定しうる）。R6で修正済み。
- **B**：処理は停止するか表示のみが誤る。エラー応答・表示の改善が望ましい。
- **C**：現在の処理では影響が小さい。別課題で対応可能。

## A：R6で修正したもの（`WpdbReadGuard` を追加、`ReadFailureTest` でテスト）

| Repository::メソッド | 使われる処理 | 失敗を「なし」と扱った場合の結果（修正前） |
| --- | --- | --- |
| `JournalEntryRepository::findBySourceEvent` | Check-in時の「この予約の入金は計上済みか」、Check-in取消（返金）時の元仕訳の検索、経費承認時の既存仕訳の確認 | **同じ予約の入金・売上を二重計上**（`journal_entries` に一意キーはない。`JournalEntrySourceUniqueness-DesignNote.md` 参照）。返金時は逆仕訳が作られず売上が過大のまま。実MySQL（R5コード、REST経由）で二重計上を再現済み |
| `CheckInRepository::findLatestByReservationId` | 上記の検索（旧形式の仕訳の確認）、Check-in取消 | 旧形式で計上済みの入金を二重計上。取消は「取消対象なし」（422）と誤って応答 |
| `AccountRepository::findByOrganizationId` | 入金仕訳の標準勘定（現金・チケット売上）の検索 | 勘定が無いと判断し、**標準勘定を重複作成** |
| `ProductionRepository::findById` | 入金仕訳の団体の特定（ほか全般） | 団体が見つからず、**入金・売上を計上しないままCheck-inが確定** |
| `ProjectRepository::findById` | 同上（Production → Project → 団体） | 同上 |
| `OrganizationRepository::findById` | 同上（会計利用の有無の判定） | 会計未使用と判断し、入金・売上を計上しない |

修正後は、いずれも例外 → トランザクション全体をROLLBACK → HTTP 500（`stageart_internal_error`）。読み取りが成功して0件の場合の動作は変えていない。

## B：処理は止まるが、エラー応答・表示が誤る（別課題）

B・Cの「主な影響」は、呼び出し元をコード上でたどった調査結果であり、障害を発生させて確認したものではない。

| Repository | 主な影響 |
| --- | --- |
| `PersonRepository` | 本人の特定に失敗 → 403「StageArt Personが紐づいていない」と誤応答 |
| `ParticipantRepository`、`ProductionDelegateRepository`、`MembershipRepository`（読み取り） | 権限判定が拒否側に倒れ403。紹介者のメンバー判定は422。精算サマリーのメンバー一覧が空になる（精算・決算完了そのものには影響しない） |
| `JournalEntryRepository`（`findByProductionId`、`findPosted*`）、`BudgetRepository` | 会計レポート・予算実績が0円と表示される（保存データは変わらないが、誤った金額表示） |
| `ProductionRepository`（`findBySlug`、`findByIds`、`findByProjectIds`、`findByPrimaryManagerPersonId`、`searchPublished`）、`OrganizationRepository`（`findBySlug`、`findByIds`）、`ProjectRepository::findByOrganizationIds` | 公開ページ404、一覧が空 |
| `CheckInRepository`（`findById`、`findByPerformanceId`）、`IssuedTicketRepository` | 受付画面の一覧・QRの照合が空・見つからない |
| `WalkUpIdempotencyStore::findReservationId` | 当日券の重複送信時に、元の結果を返せず409 |
| 認証系（`UserAccount`、`EmailCredential`、`RefreshToken`、各種Token、`ExternalIdentity`） | ログイン・再発行が401等で失敗（書き込みは伴わない） |
| 招待の自動解決（`Resolve*InvitationUseCase`） | 失敗をログに記録して続行する設計。招待が未解決のまま残る |

## C：影響が小さいもの（別課題）

Timetable / TimetableItem / TimetableVersion通知、Rehearsal / RehearsalAttendance、ScheduleComment、Questionnaire関連、Notification関連、PushPreference、Favorite、OrganizationFollow、外部リンク・SNS、JoinKey。いずれも読み取り失敗時は表示が空になるか操作が止まり、会計・予約・公演完了の記録には影響しない。

## 対応方針（提案）

- B・Cは、全Repositoryの読み取りに共通の失敗検知を入れる別タスクとして扱う（R6の指示どおり一括修正はしていない）。
- `journal_entries` の (source_event_type, source_event_id) の一意性は、DBの一意キーで保証していない。A の修正で「読み取り失敗による二重計上」は防いだが、根本対策（一意キーまたは同等の仕組み）は `JournalEntrySourceUniqueness-DesignNote.md` の検討課題として残る。
