# StageArt Blueprint

# Production Lifecycle

Version : 1.3

Version 1.3（2026-11-25、D-1 設計確定）：公演終了・決算確定・公演完了を区別した。決算確定を公演完了から独立した会計上の操作とし（PM・公演代理人・会計担当）、決算確定日時・実行者を公演に記録する。公演完了（ACTIVE → COMPLETED、PMのみ）は決算確定後にのみ行える。決算確定後は会計結果・金額に影響する操作をすべて拒否する。Version 1.2までの「COMPLETED＝決算完了、PMの決算完了GOで決算と完了を同時に行う」は廃止した。**本版は設計の確定であり、実装は未着手**（「実装状況」参照）。

---

# Purpose

ProductionのLifecycleを、企画から決算確定・公演完了・Archiveまでの一連の業務として定義する。

Productionは公演当日をもって終了するものではなく、精算・決算確定・公演完了までをProductionのLifecycleに含める。

---

# Lifecycle and Status

Production LifecycleとProduction Statusの対応は以下を確定仕様とする。

| Lifecycle | Production Status | 記録 | 意味 |
|---|---|---|---|
| 企画 | DRAFT | | Productionの企画・検討段階 |
| 予算策定 | PLANNING | | 予算・体制・計画を固める段階 |
| 制作 | ACTIVE | | 制作活動を開始した段階 |
| 稽古・広報・販売 | ACTIVE | | 制作活動を継続している段階 |
| 公演 | ACTIVE | | 公演を実施している段階 |
| 公演終了 | ACTIVE | （Statusなし。公演回はFINISHED） | 上演が終了した段階。公演完了ではない |
| 精算 | ACTIVE | | 公演後の支払・未払金精算・会計処理を行う段階 |
| 決算確定 | ACTIVE（または CANCELLED） | 決算確定日時・実行者 | 公演の会計を確定し、ロックした段階 |
| 公演完了 | COMPLETED | | 決算確定後、PrimaryManagerが公演を完了した状態 |
| Archive | ARCHIVED | | 完了したProductionを保管する状態 |

> **実装との差（PR #7 R14-A、2026-11-14 時点）**：実装ではDRAFTを廃止しており、新しいProductionはPLANNINGから始まる（`ProductionStatus`）。本文のDRAFTの記述は未整理のまま残っている（仕様の整理はユーザー判断）。main で作られたDRAFTのProductionは、スキーマ1.48.0のアップグレードでPLANNINGへ移す（ユーザー判断 2026-11-14。該当行のstatusだけを変更し、削除はしない）。

基本的なProduction StatusのLifecycleは、以下とする。

DRAFT
    ↓
PLANNING
    ↓
ACTIVE（公演終了 → 精算 → 決算確定 を含む）
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

決算確定はProduction StatusのTransitionではない（Statusを変えず、公演に決算確定の記録を付ける会計上の操作）。

---

# ACTIVE Scope

ACTIVEは、以下のProduction活動をすべて含む。

1. 制作
2. 稽古・広報・販売
3. 公演
4. 公演終了
5. 精算
6. 決算確定

これらをProduction Statusとして個別に追加しない（Version 1.3でも、公演終了・決算確定のために公演全体のStatusを追加しない）。

Production StatusはLifecycle上の大分類を表し、ACTIVE内部の活動段階は別Statusとして管理しない。

---

# 公演終了・決算確定・公演完了の区別（Version 1.3）

| 用語 | 意味 | 表現 | 操作 |
|---|---|---|---|
| 公演終了 | 上演が終了したこと | 公演全体のStatusは持たない。公演回（Performance）ごとのStatus `FINISHED` がある | 公演回ごとの状態変更（`Performance.md`） |
| 決算確定 | 公演の会計を確定し、以後の会計変更を禁止すること | 公演の決算確定日時・決算確定実行者（「DB」参照）。Statusは変えない | 決算確定（PM・公演代理人・会計担当） |
| 公演完了 | 決算確定後に、公演を完了状態にすること | Production Status `COMPLETED` | 公演完了（ACTIVE → COMPLETED、PMのみ） |

- 公演終了は公演完了を意味しない。公演終了後も、決算確定までは会計処理を継続できる。
- 公演回の `FINISHED` と、公演全体の `COMPLETED` を区別する。すべての公演回がFINISHEDになっても、Productionは自動でCOMPLETEDにならず、決算確定もされない。
- 画面の表記は「公演を終了する（決算完了）」を使わない。公演完了の操作は「公演を完了する」、決算確定の操作は「決算を確定する」と表記する。

---

# 決算確定（Accounting Close、Version 1.3）

## 実行者

決算確定を実行できるのは、次の者とする（`ProductionDelegatePolicy.md`「担当区分と権限一覧」の「決算・精算の最終確定」と同じ）。

- PrimaryManager（公演管理者）
- 公演代理人（PARTICIPANT_MANAGERとREHEARSAL_MANAGERを併せ持つ有効なProductionDelegate）
- 会計担当（ACCOUNTING_MANAGERを持つ有効なProductionDelegate）

それ以外のProductionDelegate、Organization Owner・Organization Manager（公演の担当でない場合）、一般参加者は実行できない。

## 対象となるProduction Status

- ACTIVE：通常の決算確定。
- CANCELLED：中止した公演も、決算確定まで会計処理を許可し、決算確定によってロックする。中止した公演はCOMPLETEDへ遷移しない（従来どおり）。
- PLANNING：**未確定**（予算のみで中止に至らない公演の扱いと合わせて決める）。
- COMPLETED・ARCHIVED：既に決算確定済みとして扱うため、決算確定の操作の対象外（「既存データの扱い」）。

## 記録

決算確定時に、公演に次を記録する。

- 決算確定日時
- 決算確定実行者（Person）

決算確定は一度だけ行う。決算確定の取消・再確定・訂正は本版の対象外（「決算確定後の訂正（設計課題）」）。

## 前提条件

次をすべて満たさない場合、決算確定できない（何も変更せずに拒否する）。判定は決算確定のトランザクション内で、Production行の排他ロックの下で読んだ値で行う。

| # | 条件 | 判定方法・根拠 | 実装状況（2026-11-25） |
|---|---|---|---|
| 1 | チケットバック等の必要な精算が完了している | 全メンバーで「精算済み額 ＝ 確定チケットバック額」（未精算・払い過ぎがない）。下記「精算条件」と同じ判定 | 判定ロジックは公演完了のガードとして実装済み。決算確定での判定は未実装 |
| 2 | 下書き・申請中・差し戻し状態の経費が残っていない | 公演の経費にDRAFT・SUBMITTED・REJECTEDがない | 未実装 |
| 3 | 未収金・未払金が残っていない | 会計上計上された未収金・未払金の公演に帰属する残高が0（`AccountingPolicy.md`「Production Settlement」「Production Completion」）。チケット管理上の未収金（代済み）は含めない | **未実装。かつ現状では満たせない**：経費承認の仕訳は貸方「未払金」を計上するが、未払金を消し込む支払（Expense SETTLED、支払・立替精算）が未実装のため、承認済み経費のある公演は残高が0にならない。条件3の扱いは「未確定事項」 |
| 4 | 必要な仕訳が計上されている | 公演に紐付くDRAFTの仕訳が残っていない（POSTEDでない仕訳がない） | 未実装 |
| 5 | 必要な振替・集計が完了している | 期末に仮売上・公演仕掛金へ振り替えた残高があれば、決算確定時に正式な売上・売上原価へ振り替える（`OrganizationAccountingAndProductionCostPolicy.md`） | **未実装**（仮売上・仕掛品の科目・Journal Date・対象原価の範囲が確定するまで実装しない、と既存設計書が定めている） |
| 6 | 会計設計書に定められた決算条件を満たしている | `AccountingUIAndReportingPolicy.md`「Production Closing」（BS / PLの確認と確認ダイアログを含む） | 確認ダイアログは画面の実装時に追加。BS / PLの公演単位出力は未実装 |

- 条件3・5は、実装済みと扱わない。決算確定の実装時に、これらを「判定する／未実装のため判定しない」のどちらとするかは利用者の判断による（勝手に条件を緩めない・強めない）。
- Accountingを利用していない公演（Organizationの会計が無効）は、ゼロベースでの決算確定とする（下記）。

## ゼロベース決算

Accountingを利用していないProduction、または会計データをStageArt上で管理しないProductionは、決算金額をゼロベースとして決算確定し、その後PrimaryManagerが公演完了できる。

ゼロベースでの決算確定は「実際の収支が必ず0円だった」という意味ではなく、「StageArt上で追加の会計処理を行わず、決算を確定する」ための方法である。

したがって、Accounting未使用のProductionを、会計機能が未実装であることだけを理由に決算確定・公演完了できない状態にしてはならない。ゼロベース決算でも、条件1（チケットバックの精算）は適用する（チケットバックはAccountingの有無と独立して計算されるため）。

ゼロベースで決算確定したことを履歴として保持できる構造を基本とする。記録方法（決算確定日時・実行者に加えて区分を持つか）は**未確定**。

## 決算確定後のロック

決算確定後は、会計結果や金額に影響する操作をすべて拒否する（HTTP 409 `stageart_production_closed`、何も変更しない）。Production Statusが何であっても、決算確定済みであれば適用する。

| 区分 | 拒否する操作 |
|---|---|
| 予算 | 作成・変更・確定（有効化） |
| 経費 | 作成・変更・申請・取り下げ・再申請・承認・差し戻し |
| 仕訳 | 作成・変更・計上（POST）・取消（Reversal） |
| 精算 | メンバー精算・精算取消 |
| チケットバック | チケットバック・ノルマ設定の変更 |
| 予約 | 予約・当日券販売の追加、予約の変更（セルフサービスを含む）、予約取消（セルフサービスを含む） |
| 受付 | Check-in・NO_SHOW・attribution変更・受付での人数減・Check-in取消（返金あり・なし） |
| 公演回 | 公演回の追加・編集・中止（売上・精算額に影響するため） |

会計に影響しない公演情報（説明文・写真・フライヤー・公開設定等）の編集、稽古・参加者・アンケート等の会計に関係しない操作は、決算確定のロックの対象外とする（それぞれの既存ルールに従う）。チケット価格・販売設定・定員の変更は、決算確定後は予約・販売自体を受け付けないため会計結果に影響しないが、扱いは「未確定事項」とする（現行どおり可とするか、拒否するか）。

## 決算確定と公演完了の精算条件（PR #7 R7の規則を維持）

公演完了（`PATCH /productions/{id}/complete`）は、従来どおり、すべてのメンバーについて「精算済み額 ＝ 確定チケットバック額」のときにだけ受け付ける。決算確定の前提条件1も同じ判定とする。

| 状態 | 公演完了 | HTTP |
| --- | --- | --- |
| 精算済み額 ＜ 確定額（未精算あり） | 拒否（既存） | 422 `stageart_production_settlement_incomplete` |
| 精算済み額 ＞ 確定額（払い過ぎ） | 拒否（R7） | 422 `stageart_production_settlement_overpaid`。`data.total_overpaid_amount` と、メンバーごとの `person_id`・`confirmed_ticket_back_amount`・`settled_amount`・`overpaid_amount` を返す |
| すべてのメンバーで一致 | 可 | 200 |

- 払い過ぎは、精算の後にCheck-inを取り消した（返金した）場合に起きる。取消で確定額が減っても、精算記録は変わらないため。
- 判定の対象は、確定額のあるメンバーと精算記録のあるメンバーの和集合とする。担当したCheck-inがすべて取り消されたメンバーは確定額の一覧から消えるため、精算記録の側からも確認する。
- 判定はトランザクション内で、Production行の排他ロックの下で行う。
- 払い過ぎの解消は、決算確定の前に現在ある操作で行う：そのメンバーの精算を取り消し（最後の1回分）、確定額で精算し直す。精算明細・返金の会計処理・精算の仕訳化は、R7では追加していない（「決算確定後の訂正（設計課題）」）。
- **制約（未解決）**：精算取消は「最後の1回分」を1回だけ戻す操作である。精算を複数回に分けて行った後に払い過ぎになった場合（例：300円＋300円を精算した後、確定額が0円になった）、取消しても払い過ぎが残り、それ以上は取り消せない。この場合、現在の操作では決算確定・公演完了できない。精算の調整（減額・返金の記録）を行う手段が必要であり、「決算確定後の訂正（設計課題）」と合わせて仕様判断が必要。
- 精算サマリーAPI（`GET /productions/{id}/settlement`）は、メンバーごとに `overpaid_amount`（精算済み額 − 確定額、正のときのみ。それ以外は0）を返す。`outstanding_amount` は従来どおり0未満にしない。
- 決算確定後は精算・Check-in取消を受け付けないため、決算確定時点で一致していれば、公演完了時にも一致している。公演完了の精算ガードは、二重の安全策として従来どおり残す。

---

# Completion Rule（公演完了）

公演終了だけではProductionをCOMPLETEDへ遷移させない。決算確定だけでもCOMPLETEDへ遷移させない。

Productionは、決算確定の後に、PrimaryManagerが公演完了のGOを行った時点でCOMPLETEDとなる。

- 公演完了（ACTIVE → COMPLETED）を行えるのはPrimaryManagerのみとする（2026-11-24確定、PR #8 統合）。
- **決算確定前の公演完了は禁止する**（Version 1.3）。決算確定されていないACTIVEのProductionは、PrimaryManagerであっても完了できない。
- 公演完了の既存処理（Production行の排他ロック、精算ガード、払い過ぎチェック、トランザクション、RESERVEDの予約の取消）は維持する。Version 1.3で追加するのは「決算確定済みであること」の確認だけである。
- 決算確定前のACTIVEに対する拒否の応答コードは実装時に決める（案：422 `stageart_production_accounting_not_closed`）。

通常の会計利用時は、以下の流れを基本とする。

公演終了
    ↓
精算（チケットバック・経費の承認・未払金の支払 等）
    ↓
未払金・支払・差額調整等の会計処理
    ↓
決算確定の条件の確認
    ↓
決算確定（PM・公演代理人・会計担当）… 以後、会計をロック
    ↓
公演完了（PMのみ、「公演を完了する」）
    ↓
COMPLETED

---

## 公演活動の制限（2026-11-10、PR #7 レビュー R4・R5。Version 1.3で決算確定を追加）

Productionの状態ごとに、受け付ける操作を以下に確定する。「拒否」はHTTP 409 `stageart_production_closed` を返し、何も変更しない。「決算確定済み」は、決算確定の記録がある、またはStatusがCOMPLETED・ARCHIVEDであることをいう（「既存データの扱い」）。

| 操作 | DRAFT / PLANNING / ACTIVE（決算確定前） | CANCELLED（中止・決算確定前） | 決算確定済み（ACTIVE・CANCELLED） | COMPLETED / ARCHIVED |
| --- | --- | --- | --- | --- |
| 予約・当日券販売 | 可 | 拒否 | 拒否 | 拒否 |
| Check-in・NO_SHOW・attribution変更・受付での人数減 | 可 | 拒否 | 拒否 | 拒否 |
| セルフサービスの予約変更（人数・氏名等） | 可 | 拒否 | 拒否 | 拒否 |
| セルフサービスの予約取消 | 可 | **可**（中止公演の予約を整理するため） | 拒否 | 拒否 |
| Check-in取消（返金あり・なし） | 可 | **可**（来場済みの返金のため） | 拒否 | 拒否 |
| メンバー精算・精算取消 | 可 | 可（実施済み公演分の精算） | 拒否 | 拒否 |
| チケットバック・ノルマ設定の変更 | 可 | 可 | 拒否 | 拒否 |
| 予算・経費・仕訳の書き込み（「決算確定後のロック」の各操作） | 可 | 可 | 拒否 | 拒否 |
| 公演回の追加 | 可 | 既存ルールどおり | 拒否 | 拒否 |
| Performanceの編集 | 可 | 可 | 拒否 | 拒否（既存どおり422） |
| Performanceの中止 | 可 | 可 | 拒否 | 拒否（409。R6で変更） |
| チケット価格・アーカイブ、販売設定、基本情報の変更 | 可 | 可 | 未確定（「決算確定後のロック」） | 可（既存のルールどおり） |

- CANCELLEDは「新たな公演活動はしないが、既存の予約の取消・返金と、実施済み公演分の精算は行う」状態とする。決算確定までは会計処理も行える。
- 決算確定済みの公演は、会計結果・金額に影響する操作をすべて拒否する。決算確定後の訂正は下記「決算確定後の訂正（設計課題）」による。
- Performanceの中止は、以前（Phase 2、R5）は決算完了後も「訂正」として許可していたが、R6（2026-11-10）で拒否に変更した。Version 1.3では決算確定後も拒否する。
- 公演完了時にRESERVEDの予約はすべて取り消されるため（既存）、COMPLETEDの公演に取消可能な予約は残らない。決算確定後は予約取消も拒否するため、決算確定時点でRESERVEDの予約が残っている場合、それは公演完了時の取消まで残る（決算確定の前提条件に含めるかは「未確定事項」）。

## Production単位のロック規約

上の制限を、実行中の操作と同時に発生した決算確定・公演完了・中止・設定変更に対しても保つため、各操作はトランザクションの最初にProduction行をロックし、以下の順序でロックを取得する。逆順の取得はしない。

| 順 | ロック | 取得する処理 |
| --- | --- | --- |
| 1 | Production行・共有ロック（`LOCK IN SHARE MODE`、取得時に状態を確認） | 予約、当日券、Check-in、NO_SHOW、Check-in取消、人数変更、attribution変更、セルフサービスの変更・取消、Performanceの中止、**予算・経費・仕訳の書き込み（Version 1.3で追加）** |
| 1 | Production行・排他ロック（`FOR UPDATE`） | **決算確定（Version 1.3で追加）**、公演完了、中止、ACTIVE化、アーカイブ、メンバー精算、精算取消、Production基本情報・定員・公演時間の変更、PrimaryManager変更、チケット販売設定、チケットバック・ノルマ設定、チケット価格の変更・アーカイブ、Performanceの編集（定員を含むため。R6）|
| 2 | Performance行（`FOR UPDATE`、ロック下で再読込） | 席数を確保する処理（予約・当日券・人数増）、Performanceの編集・中止 |
| 2 | Budget・Expense行（`FOR UPDATE`、ロック下で再読込。Version 1.3） | 予算・経費の書き込み |
| 3 | Reservation行（`FOR UPDATE`、ロック下で再読込） | 既存Reservationを変更する処理 |
| 4 | 発券・CheckIn・仕訳・精算記録・設定の書き込み | 上記の後 |

- 予約・受付・会計の書き込みは互いに並行して実行できる（共有ロック同士）。排他ロックを取る処理（決算確定を含む）は、実行中の処理の確定を待ってから実行され、その間に始まった処理は、排他ロック側の確定を待ってから確定後の状態を読む。決算確定が先に確定すれば、後の会計の書き込みは409で拒否される。
- 状態の判定（決算確定・公演完了・中止の判定、チケットバック確定額、精算済み額、定員、チケット価格、未処理経費）は、すべてロックの下で同じトランザクション内で読んだ値で行う。ロック取得前に読んだ値は、権限確認と早期の入力チェックにだけ使う。
- Production行・Performance行・Ticketの保存は行全体を書き込むため、必ずロック下で読み直したものを変更して保存する。ロック前に読んだ古いコピーを保存しない（例：設定変更の保存で、直前に確定した決算確定・公演完了を元に戻さない）。
- 予約・当日券は、ロック取得後にTicketを読み直し、その時点の価格をpriceSnapshotとして記録する。価格変更は排他ロックを取るため、予約は「変更前の価格で先に確定する」か「変更の確定を待って新しい価格で確定する」かのどちらかになる。確定済みReservationのpriceSnapshotは価格変更で変わらない。
- Performanceの定員変更（Performanceの編集、およびProduction定員変更による一括上書き）はProduction行の排他ロックの下で行うため、実行中の予約・当日券・人数増の確定を待ち、その間に新しい予約は確定しない。ロック下で予約済み席数（CANCELLED以外のReservationの人数の合計。予約時の定員判定と同じ）を読み直し、定員を引き下げて予約済み席数を下回る場合は拒否する（409 `stageart_capacity_below_reservations`、定員も予約も変更しない）。詳細は `23-CapacityPolicy.md`。
- 同一メンバーへの精算が同時に行われた場合、後の処理は先の処理の結果を読み、未精算額がなければ精算しない（NothingToSettle）。
- 会計（Accounting Module）は、Production行のロックをCoreのContract（`ProductionActivityLockContract`）経由で取得する。Productionのテーブルを直接読まない（Module境界の維持）。

## ロック失敗とDB障害

| 状況 | 処理 | HTTP |
| --- | --- | --- |
| 状態が操作を許さない（決算確定済み・中止・完了等） | ROLLBACK、何も変更しない | 409 `stageart_production_closed` |
| ロック待ちタイムアウト（MySQL 1205）・デッドロック（1213） | トランザクション全体をROLLBACKし、何も確定しない | 503 `stageart_database_busy`（`data.retryable: true`、`Retry-After: 2`） |
| その他のDB障害（テーブル欠落、COMMIT失敗、接続断など） | ROLLBACK（可能な場合） | 500 `stageart_internal_error`（詳細はサーバーログのみ） |

- 503は「何も保存されていないので、同じ操作をもう一度実行できる」ことを表す。クライアントは自動で再送せず、利用者に「保存されなかったので再度実行してほしい」と伝える。
- 500は結果が不明な場合を含むため、再試行可能とは表示しない。

## 決算確定後の訂正（設計課題、未実装・本版の対象外）

決算確定後に誤りが見つかった場合の訂正・取消・再確定の方法は、本版の対象外とし、別途仕様決定する。公演の再開機能（COMPLETED → ACTIVE）は実装しない（R5・R6の決定）。検討の方向は次のとおり（確定ではない）。

- 決算確定後は、予算・経費・仕訳・精算記録・予約・Check-in・チケットバック設定・公演回を変更・削除しない。
- 訂正は、元の記録を残したまま、追加精算・返金・調整として新しい記録を追加する方向とする。既存の会計仕様（`AccountingConsistencyPolicy.md`「Reversal and Adjustment」、`JournalEntryConsistencyPolicy.md`「Reversal and Correction」、`AccountingPolicy.md`「Post-Closing Adjustment」、`AccountingUIAndReportingPolicy.md`「Production Closing Lock」：確定済みの仕訳は変更・削除せず、Organization Accounting側の調整仕訳で記録し履歴を追跡する）と同じ考え方である。
- 訂正ごとに、訂正理由・実行者・日時・対象金額（対象メンバー・対象公演）を監査履歴として残す。
- 既存の精算履歴を保持する。

設計時に決める必要がある点（現行仕様との差。勝手に確定しない）：

1. 現在の精算記録（`ProductionMemberSettlement`）は、累計精算額と「最後の1回」（金額・実行者・日時）だけを保持し、精算・精算取消のたびに上書きする。1回ごとの精算履歴は残らない。上記の「訂正ごとの監査履歴」には、精算の明細（履歴テーブル）が新たに必要。
2. メンバー精算は現在Journal Entryを生成しない。訂正を「調整仕訳」として記録する場合、精算そのものを会計（未払金・支払）として仕訳するかどうかを先に決める必要がある。
3. 調整を記録できる権限、決算確定の取消（再確定）を認めるか、公演完了の取消（COMPLETED→ACTIVE）を認めないこととの関係、ARCHIVED後の扱い。

---

# 既存データの扱い（Version 1.3）

- 既存のCOMPLETED・ARCHIVEDのProductionは、決算確定済みとして扱う。決算確定の記録（日時・実行者）は空のまま残し、推測で補完しない。既存行の書き換えは行わない。
- したがって「決算確定済み」は「決算確定日時の記録がある、またはStatusがCOMPLETED・ARCHIVEDである」と判定する。決算確定日時が空のCOMPLETED・ARCHIVEDは「Version 1.3以前に完了した公演」を表す。
- 既存のACTIVE・PLANNING・CANCELLEDのProductionは決算確定前として扱い、従来どおり会計処理を行える。
- 過去公演の移行（`HistoricalProductionMigrationPolicy.md`）でCOMPLETED / ARCHIVEDとして登録する過去公演も、同じく決算確定済みとして扱う。

---

# DB（Version 1.3、方式A。未実装）

公演（`stageart_productions`）に次の列を追加する（追加のみ。既存の列・行は変更しない）。列名は実装時の案。

| 列（案） | 型（案） | 内容 |
|---|---|---|
| `accounting_closed_at` | DATETIME NULL | 決算確定日時（UTC） |
| `accounting_closed_by_person_id` | CHAR(36) NULL | 決算確定実行者のPerson ID |

- 既存行はNULLのまま（推測で補完しない）。
- スキーマバージョンは、R14-B（1.49.0）の次の **1.50.0** とする案。1.50.0は、1.49.0の変更（R14-B）を含むコードに対してのみ付ける（`SchemaUpgrader` は版ごとの差分適用ではなく、保存済みの版と `CURRENT_VERSION` が異なるときにInstaller全体を再実行する方式のため、同じ版番号が異なるスキーマ内容を指してはならない）。
- Production Statusの値（VARCHAR）・遷移表は変更しない。

---

# 実装状況（2026-11-25時点）

| 項目 | 状況 |
|---|---|
| 公演完了（ACTIVE → COMPLETED、PMのみ、精算ガード・払い過ぎチェック・RESERVED取消） | 実装済み（`CompleteProductionUseCase`） |
| 公演完了の「決算確定済み」の確認 | 未実装 |
| 決算確定の操作・記録・前提条件 | 未実装 |
| 決算確定後の予算・経費・仕訳のロック | 未実装（2026-11-25のDev確認で、COMPLETED後も経費の登録・申請・承認と予算作成ができることを確認。ロードマップ D-1） |
| 予約・受付・精算・チケットバック・公演回のロック | COMPLETED・ARCHIVED（CANCELLEDは一部）に対して実装済み。「決算確定済み」への拡張は未実装 |
| 公演完了の画面表記「公演を完了する」 | 未実装（現在の表記は「公演を終了する（決算完了）」） |

---

# Archive Rule

COMPLETEDとなったProductionは、必要な参照期間を経てARCHIVEDへ移行できる。

ARCHIVEDは、業務が完了したProductionの保管状態を表す。

ARCHIVED後の新規Business Activityは原則として禁止し、過去データの参照はAuthorizationに従って許可する。

---

# Cancelled

CANCELLEDは通常のLifecycle進行とは別の中止状態として保持する。

CANCELLEDは、DRAFT、PLANNING、ACTIVE等の通常Lifecycleからの中止を表すものであり、通常の完了経路であるCOMPLETEDとは区別する。

CANCELLEDのProductionも、決算確定までは会計処理を行え、決算確定後は会計をロックする（Version 1.3）。CANCELLEDからCOMPLETEDへは遷移しない。

---

# Business Rules

- Production Lifecycleは企画から決算確定・公演完了までを対象とする。
- 公演終了は上演が終了したことを表し、公演完了を意味しない。公演全体の公演終了Statusは持たない。
- 公演回のFINISHEDと公演全体のCOMPLETEDを区別する。
- 公演終了後も、決算確定までは会計処理を継続できる。
- 決算確定は公演完了とは独立した会計上の操作であり、PrimaryManager・公演代理人・会計担当が行う。決算確定日時・実行者を記録する。
- 決算確定は前提条件（精算完了、未処理経費なし、未収金・未払金なし、必要な仕訳・振替、会計設計書の決算条件）を満たす場合にのみ行える。未実装の条件を実装済みと扱わない。
- 決算確定後は、会計結果・金額に影響する操作（予算・経費・仕訳・精算・チケットバック設定・予約・受付・公演回）を拒否する。
- 決算確定後の訂正・取消・再確定は別途仕様決定する（本版の対象外）。
- 公演完了（ACTIVE → COMPLETED）はPrimaryManagerのみが、決算確定後にのみ行える。決算確定前の公演完了は禁止する。
- 公演完了の既存処理（ロック・精算ガード・払い過ぎチェック・トランザクション・RESERVEDの取消）は維持する。
- DRAFTは企画段階を表す。
- PLANNINGは予算策定段階を表す。
- ACTIVEは制作、稽古・広報・販売、公演、公演終了、精算、決算確定を含む活動中の状態を表す。
- 制作、稽古・広報・販売、公演、公演終了、精算、決算確定をProduction Statusとして個別追加しない。
- COMPLETEDは、決算確定後に公演を完了したことを意味する。
- Accounting未使用またはStageArt上で会計処理を行わないProductionは、ゼロベースで決算確定し、その後に公演完了できる。
- ゼロベース決算は実際の収支が0円だったことを自動的に意味しない。
- ゼロベース決算を選択した事実は履歴として保持できる構造を基本とする。
- 既存のCOMPLETED・ARCHIVEDは決算確定済みとして扱い、記録を補完しない。
- Production Statusの基本遷移はDRAFT → PLANNING → ACTIVE → COMPLETED → ARCHIVEDとする。
- CANCELLEDは通常Lifecycleとは別の中止状態として扱う。CANCELLEDも決算確定の対象とする。
- Lifecycle Transitionは管理者による明示的なGOを起点として実行する。
- Statusの任意の直接書き換えを、通常のLifecycle操作として扱わない。
- Server Sideで現在Statusから許可されたLifecycle Transitionであることを検証する。
- 決算確定の会計上の詳細（仕訳・振替・BS / PL）はAccounting Domainで定義する。
- Production DomainはAccountingの詳細な仕訳処理そのものを管理しない。
