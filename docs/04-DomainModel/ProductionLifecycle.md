# StageArt Blueprint

# Production Lifecycle

Version : 1.2

---

# Purpose

ProductionのLifecycleを、企画から決算完了・Archiveまでの一連の業務として定義する。

Productionは公演当日をもって終了するものではなく、精算および決算完了までをProductionのLifecycleに含める。

---

# Lifecycle and Status

Production LifecycleとProduction Statusの対応は以下を確定仕様とする。

| Lifecycle | Production Status | 意味 |
|---|---|---|
| 企画 | DRAFT | Productionの企画・検討段階 |
| 予算策定 | PLANNING | 予算・体制・計画を固める段階 |
| 制作 | ACTIVE | 制作活動を開始した段階 |
| 稽古・広報・販売 | ACTIVE | 制作活動を継続している段階 |
| 公演 | ACTIVE | 公演を実施している段階 |
| 精算 | ACTIVE | 公演後の支払・未払金精算・会計処理を行う段階 |
| 決算完了 | COMPLETED | Productionの決算処理を完了した状態 |
| Archive | ARCHIVED | 完了したProductionを保管する状態 |

基本的なProduction StatusのLifecycleは、以下とする。

DRAFT
    ↓
PLANNING
    ↓
ACTIVE
    ↓
COMPLETED
    ↓
ARCHIVED

---

# Lifecycle Transition and Management GO

Production Statusは、単純な設定値の直接書き換えによって任意に変更することを基本としない。

Lifecycleの進行は、管理者がProductionの状況を確認し、次の段階へ進めることを明示的に承認した時点で実行する。

この承認を、Production Lifecycle上の管理者による「GO」として扱う。

基本Flow：

Productionの準備・確認
    ↓
管理者がGOを判断
    ↓
Lifecycle Transition Action
    ↓
次のProduction Status

例えば、企画・体制・計画を確認した管理者がGOを出した時点でDRAFTからPLANNINGへ遷移させる。

同様に、制作開始のGOによってPLANNINGからACTIVEへ遷移させる。

UIではStatusそのものを自由編集するのではなく、現在のLifecycleに応じた業務ActionとしてGOを提示することを基本とする。

Server Sideは、現在Statusから許可された遷移であることを検証する。

Lifecycle Transitionの最終判断はFrontendではなくServer Sideで行う。

---

# ACTIVE Scope

ACTIVEは、以下のProduction活動をすべて含む。

1. 制作
2. 稽古・広報・販売
3. 公演
4. 精算

これらをProduction Statusとして個別に追加しない。

Production StatusはLifecycle上の大分類を表し、ACTIVE内部の活動段階は別Statusとして管理しない。

---

# Completion Rule

公演終了だけではProductionをCOMPLETEDへ遷移させない。

Productionは、決算完了のGOを管理者が行った時点でCOMPLETEDとなる。

通常の会計利用時は、以下の流れを基本とする。

公演終了
    ↓
精算
    ↓
未払金・支払・差額調整等の会計処理
    ↓
決算完了の確認
    ↓
管理者が決算完了GO
    ↓
COMPLETED

ただし、Accountingを利用していないProduction、または会計データをStageArt上で管理しないProductionについては、決算金額をゼロベースとして確定することでCOMPLETEDへ遷移できるものとする。

ゼロベースでの決算確定は「実際の収支が必ず0円だった」という意味ではなく、「StageArt上で追加の会計処理を行わず、Productionを完了状態として確定する」ための完了方法である。

したがって、Accounting未使用のProductionを、会計機能が未実装であることだけを理由にCOMPLETEDへ進められない状態にしてはならない。

ゼロベース決算を選択した場合でも、ProductionがCOMPLETEDとなった事実およびゼロベースで決算を確定したことを履歴として保持できる構造を基本とする。

具体的な決算完了条件、ゼロベース決算の記録方法、およびAccounting側のClose処理はAccounting Domainで定義する。

## 公演活動の制限（2026-11-10、PR #7 レビュー R4・R5）

Production Statusごとに、受け付ける操作を以下に確定する。「拒否」はHTTP 409 `stageart_production_closed` を返し、何も変更しない。

| 操作 | DRAFT / PLANNING / ACTIVE | CANCELLED（中止） | COMPLETED（決算完了） / ARCHIVED |
| --- | --- | --- | --- |
| 予約・当日券販売 | 可 | 拒否 | 拒否 |
| Check-in・NO_SHOW・attribution変更・受付での人数減 | 可 | 拒否 | 拒否 |
| セルフサービスの予約変更（人数・氏名等） | 可 | 拒否 | 拒否 |
| セルフサービスの予約取消 | 可 | **可**（中止公演の予約を整理するため） | 拒否 |
| Check-in取消（返金あり・なし） | 可 | **可**（来場済みの返金のため） | 拒否 |
| メンバー精算・精算取消 | 可 | 可（実施済み公演分の精算） | 拒否 |
| チケットバック・ノルマ設定の変更 | 可 | 可 | 拒否 |
| チケット価格・アーカイブ、販売設定、基本情報の変更 | 可 | 可 | 可（既存のルールどおり） |
| Performanceの編集 | 可 | 可 | 拒否（既存どおり422） |
| Performanceの中止 | 可 | 可 | 拒否（409。R6で変更） |

- CANCELLEDは「新たな公演活動はしないが、既存の予約の取消・返金と、実施済み公演分の精算は行う」状態とする。Performanceの編集・中止も既存どおり受け付ける。
- COMPLETED（決算完了）は精算が締まった状態であり、精算結果を変える操作（予約取消、Check-in取消、精算・精算取消、チケットバック設定の変更）と、公演の事実を変える操作（Performanceの編集・中止）はすべて拒否する。決算完了後の訂正は下記「決算完了後の訂正（設計課題）」による。
- Performanceの中止は、以前（Phase 2、R5）は決算完了後も「訂正」として許可していたが、R6（2026-11-10）で拒否に変更した。現在のチケットバック計算はPerformanceの状態を参照しないため、中止によって確定額が変わることはないが、決算完了後の記録は変更しないという原則に合わせる。
- 決算完了時にRESERVEDの予約はすべて取り消されるため、COMPLETEDの公演に取消可能な予約は残らない。

## Production単位のロック規約

上の制限を、実行中の操作と同時に発生した決算完了・中止・設定変更に対しても保つため、各操作はトランザクションの最初にProduction行をロックし、以下の順序でロックを取得する。逆順の取得はしない。

| 順 | ロック | 取得する処理 |
| --- | --- | --- |
| 1 | Production行・共有ロック（`LOCK IN SHARE MODE`、取得時にStatusを確認） | 予約、当日券、Check-in、NO_SHOW、Check-in取消、人数変更、attribution変更、セルフサービスの変更・取消、Performanceの中止 |
| 1 | Production行・排他ロック（`FOR UPDATE`） | 決算完了、中止、ACTIVE化、アーカイブ、メンバー精算、精算取消、Production基本情報・定員・公演時間の変更、PrimaryManager変更、チケット販売設定、チケットバック・ノルマ設定、チケット価格の変更・アーカイブ、Performanceの編集（定員を含むため。R6）|
| 2 | Performance行（`FOR UPDATE`、ロック下で再読込） | 席数を確保する処理（予約・当日券・人数増）、Performanceの編集・中止 |
| 3 | Reservation行（`FOR UPDATE`、ロック下で再読込） | 既存Reservationを変更する処理 |
| 4 | 発券・CheckIn・仕訳・精算記録・設定の書き込み | 上記の後 |

- 予約・受付は互いに並行して実行できる（共有ロック同士）。排他ロックを取る処理は、実行中の予約・受付の確定を待ってから実行され、その間に始まった予約・受付は、排他ロック側の確定を待ってから確定後の状態を読む。
- 状態の判定（決算完了・中止の判定、チケットバック確定額、精算済み額、定員、チケット価格）は、すべてロックの下で同じトランザクション内で読んだ値で行う。ロック取得前に読んだ値は、権限確認と早期の入力チェックにだけ使う。
- Production行・Performance行・Ticketの保存は行全体を書き込むため、必ずロック下で読み直したものを変更して保存する。ロック前に読んだ古いコピーを保存しない（例：設定変更の保存で、直前に確定した決算完了をACTIVEに戻さない）。
- 予約・当日券は、ロック取得後にTicketを読み直し、その時点の価格をpriceSnapshotとして記録する。価格変更は排他ロックを取るため、予約は「変更前の価格で先に確定する」か「変更の確定を待って新しい価格で確定する」かのどちらかになる。確定済みReservationのpriceSnapshotは価格変更で変わらない。
- Performanceの定員変更（Performanceの編集、およびProduction定員変更による一括上書き）はProduction行の排他ロックの下で行うため、実行中の予約・当日券・人数増の確定を待ち、その間に新しい予約は確定しない。ロック下で予約済み席数（CANCELLED以外のReservationの人数の合計。予約時の定員判定と同じ）を読み直し、定員を引き下げて予約済み席数を下回る場合は拒否する（409 `stageart_capacity_below_reservations`、定員も予約も変更しない）。詳細は `23-CapacityPolicy.md`。
- 同一メンバーへの精算が同時に行われた場合、後の処理は先の処理の結果を読み、未精算額がなければ精算しない（NothingToSettle）。

## ロック失敗とDB障害

| 状況 | 処理 | HTTP |
| --- | --- | --- |
| Statusが操作を許さない | ROLLBACK、何も変更しない | 409 `stageart_production_closed` |
| ロック待ちタイムアウト（MySQL 1205）・デッドロック（1213） | トランザクション全体をROLLBACKし、何も確定しない | 503 `stageart_database_busy`（`data.retryable: true`、`Retry-After: 2`） |
| その他のDB障害（テーブル欠落、COMMIT失敗、接続断など） | ROLLBACK（可能な場合） | 500 `stageart_internal_error`（詳細はサーバーログのみ） |

- 503は「何も保存されていないので、同じ操作をもう一度実行できる」ことを表す。クライアントは自動で再送せず、利用者に「保存されなかったので再度実行してほしい」と伝える。
- 500は結果が不明な場合を含むため、再試行可能とは表示しない。

## 決算完了後の訂正（設計課題、R6時点で未実装）

決算完了後に誤りが見つかった場合の訂正方法は、以下の方向で将来設計する。公演の再開機能は実装しない（R5・R6の決定）。

- 決算完了後は、精算記録・予約・Check-in・チケットバック設定を変更・削除しない（現在の実装で拒否している）。
- 訂正は、元の記録を残したまま、追加精算・返金・調整として新しい記録を追加する方向とする。既存の会計仕様（`AccountingConsistencyPolicy.md`「Reversal and Adjustment」、`JournalEntryConsistencyPolicy.md`「Reversal and Correction」：POSTED仕訳は変更・削除せず、Reversal / Adjustmentで記録し履歴を追跡する）と同じ考え方である。
- 訂正ごとに、訂正理由・実行者・日時・対象金額（対象メンバー・対象公演）を監査履歴として残す。
- 既存の精算履歴を保持する。

設計時に決める必要がある点（現行仕様との差。勝手に確定しない）：

1. 現在の精算記録（`ProductionMemberSettlement`）は、累計精算額と「最後の1回」（金額・実行者・日時）だけを保持し、精算・精算取消のたびに上書きする。1回ごとの精算履歴は残らない。上記の「訂正ごとの監査履歴」には、精算の明細（履歴テーブル）が新たに必要。
2. メンバー精算は現在Journal Entryを生成しない。訂正を「調整仕訳」として記録する場合、精算そのものを会計（未払金・支払）として仕訳するかどうかを先に決める必要がある。
3. 調整を記録できる権限（PrimaryManagerのみか）、決算完了の取消（COMPLETED→ACTIVE）を認めないこととの関係、ARCHIVED後の扱い。

この課題は `docs/handoffs/PR7-RoadmapAdditions.md` に記録している（全体ロードマップ `docs/StageArtDevelopmentRoadmap.md` は現在 `main` にだけあるため、取り込み後に本体へ反映する）。

---

# Archive Rule

COMPLETEDとなったProductionは、必要な参照期間を経てARCHIVEDへ移行できる。

ARCHIVEDは、業務が完了したProductionの保管状態を表す。

ARCHIVED後の新規Business Activityは原則として禁止し、過去データの参照はAuthorizationに従って許可する。

---

# Cancelled

CANCELLEDは通常のLifecycle進行とは別の中止状態として保持する。

CANCELLEDは、DRAFT、PLANNING、ACTIVE等の通常Lifecycleからの中止を表すものであり、通常の完了経路であるCOMPLETEDとは区別する。

---

# Business Rules

- Production Lifecycleは企画から決算完了までを対象とする。
- 公演終了だけではProductionをCOMPLETEDにしない。
- DRAFTは企画段階を表す。
- PLANNINGは予算策定段階を表す。
- ACTIVEは制作、稽古・広報・販売、公演、精算を含む活動中の状態を表す。
- 制作、稽古・広報・販売、公演、精算をProduction Statusとして個別追加しない。
- COMPLETEDは決算処理を完了したことを意味する。
- Accounting未使用またはStageArt上で会計処理を行わないProductionは、ゼロベースで決算を確定してCOMPLETEDへ遷移できる。
- ゼロベース決算は実際の収支が0円だったことを自動的に意味しない。
- ゼロベース決算を選択した事実は履歴として保持できる構造を基本とする。
- Production Statusの基本遷移はDRAFT → PLANNING → ACTIVE → COMPLETED → ARCHIVEDとする。
- CANCELLEDは通常Lifecycleとは別の中止状態として扱う。
- Lifecycle Transitionは管理者による明示的なGOを起点として実行する。
- Statusの任意の直接書き換えを、通常のLifecycle操作として扱わない。
- Server Sideで現在Statusから許可されたLifecycle Transitionであることを検証する。
- 決算完了条件、ゼロベース決算の記録方法、およびClose処理の詳細はAccounting Domainで定義する。
- Production DomainはAccountingの詳細な仕訳処理そのものを管理しない。
