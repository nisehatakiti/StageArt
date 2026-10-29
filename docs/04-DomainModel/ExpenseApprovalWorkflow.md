# StageArt Blueprint

# Domain Model : Expense Approval Workflow

Version : 1.7

---

# Purpose

本書は、Production / Projectにおける小口立替経費の登録・承認・会計転記に関する確定方針を定義する。

Version 1.4では、一般Memberの経費区分と承認時の仕訳、差し戻しの扱い、支払済み（SETTLED）、立替金の受取人、領収書の保存・閲覧を追加した。各節では、確定事項・暫定案・未確認事項を区別して記載する。暫定案・未確認事項を確定仕様として扱ってはならない。

Version 1.5では、承認権限（Primary Managerと有効なACCOUNTING_MANAGERに限定）、必要な勘定科目がない場合の扱い（自動作成せず拒否）、支払先（初期実装では申請者本人）、支払記録の取消（初期実装では設けない）、領収書のDB保存の禁止と保存期間の扱い、初期実装の画面範囲、予算対実績の分離を確定した。実装済みの事実と今後の実装方針は「Implementation Status」で区別する。

Version 1.6では、2026-10-28確定事項に従い、Expense申請の承認・差し戻しとProduction決算の確定の権限者に代理人を加え（Primary Manager・代理人・会計管理者）、承認可能者への通知にも同じ条件を適用した。支払済みにする操作の権限は変更しない。

Version 1.7では、2026-10-29確定事項に従い、本人による承認の禁止を差し戻しにも適用した。申請者・作成者・立替払いを受ける者は、Primary Manager・代理人・会計管理者のいずれであっても、当該Expenseを承認も差し戻しもできない。申請者本人による取り下げ・編集・再申請はこの禁止の対象外とする。

本Workflowは、一般Memberが日常的な小口支出を登録できるようにしつつ、承認前の申請を正式なAccounting Actualへ反映させないための最小限の業務フローを定義する。

StageArtは会計業務を複雑化させないことを基本とし、承認済みExpenseは承認時点で直ちに会計へ転記する。

---

# Scope

Memberが登録できるExpenseは、Production運営上発生した小口の立替精算を主対象とする。

対象例：

- 消耗品
- 小道具
- 文具等の少額物品
- その他、Memberが個人で立替購入し精算することを想定した小口支出

以下のような大口・計画的な支出は、Member向けの小口立替Expense登録の対象外とする。

- 会場費
- 外注費
- 出演料・人件費
- 大型設備・高額物品
- その他、予算管理者が直接管理すべき大口支出

大口支出については、Primary Manager / Production Delegateが適切な業務フローから登録・管理する。

具体的な金額上限は本書では固定せず、必要になった時点で別途定義する。

---

# Accounting Visibility

Accountingの予算・実績は、一般Memberには公開しない。

| 情報 | Primary Manager | Production Delegate | Member |
|---|---:|---:|---:|
| Budgetを見る | ○ | ○ | × |
| Budgetを入力・変更 | ○ | ○ | × |
| Actualを見る | ○ | ○ | × |
| 小口立替Expenseを登録 | ○ | ○ | ○ |
| Expense申請を承認・差し戻し（Version 1.6） | ○ | 代理人・ACCOUNTING_MANAGER | × |
| 承認済みExpenseを支払済みにする（Version 1.4） | ○ | ACCOUNTING_MANAGERのみ | × |
| Journal Entryを確定 | ○ | × | × |
| Production決算を確定（Version 1.6） | ○ | 代理人・ACCOUNTING_MANAGER | × |

Version 1.6（2026-10-28確定事項の反映）：Expense申請の承認・差し戻しとProduction決算の確定は、Primary Manager、代理人、会計管理者が実行できる。代理人はPARTICIPANT_MANAGERとREHEARSAL_MANAGERを併せ持ち、その両方が有効（ACTIVE）なProduction Delegate、会計管理者は有効なACCOUNTING_MANAGERを持つProduction Delegateとする。それ以外のRoleのみを持つProduction Delegateは承認・差し戻し・決算確定できない。判定はBackendの権限 `Expense.Approve` / `Production.Complete` で行い、承認・差し戻しの通知先（承認可能者）にも同じ条件を用いる。承認・差し戻しを行う者は、当該Expenseの申請者・作成者・立替払いを受ける者のいずれでもあってはならない（本人による承認・差し戻しの禁止、「Approval」「Return for Correction」参照。差し戻しはVersion 1.7で追加）。この制約はPrimary Manager・代理人・会計管理者のいずれにも適用する。承認・差し戻しの権限は、承認のために当該ProductionのExpenseを閲覧することを含むが、Budgetの入力・変更、他者のExpenseの編集、支払済みにする操作等、他の会計権限を付与しない。

「ACCOUNTING_MANAGERのみ」は、対象Productionで有効（ACTIVE）なACCOUNTING_MANAGERのRoleを持つProduction Delegateを指す。Production Delegateであることだけでは、Expenseの承認・差し戻し・支払の権限を持たない（Version 1.5）。

Memberは、自分が登録した小口立替Expenseの申請状態など、当該申請を処理するために必要な情報のみ確認できればよい。

MemberにProduction全体のBudget / Actualを表示する画面を提供しない。

---

# Expense Approval Lifecycle

小口立替Expenseの基本状態は以下とする（Version 1.3：申請前の下書きDRAFTと取り下げを追加。Version 1.4：支払済みSETTLEDを方針として追加）。

```text
Expense登録
    ↓
DRAFT（申請前の下書き）
    ↓ 申請者が明示的に申請
SUBMITTED
    ↓
承認者が内容・金額を確認・必要に応じて承認金額を変更
    ↓
承認
    ↓
POSTED
    ↓
Journal Entryへ転記
    ↓
Actualへ反映
    ↓ 立替金を実際に支払った時点で支払処理（Version 1.4 方針・未実装）
SETTLED（支払済み）
```

承認者が差し戻した場合：

```text
SUBMITTED
    ↓
REJECTED（画面表示：差し戻し）
    ↓
修正
    ↓
SUBMITTED
```

状態の表示名（Version 1.4）：

| 内部状態 | 画面・通知での表示 | 備考 |
|---|---|---|
| DRAFT | 下書き | |
| SUBMITTED | 申請中 | |
| REJECTED | 差し戻し | 修正して再申請できる状態。終端の「却下」ではない |
| POSTED | 承認済み | 支払済みを意味しない |
| SETTLED | 支払済み | Version 1.4 方針・未実装。「精算」とは表示しない |

内部状態REJECTEDの値、API・DBの既存の状態値・操作名は変更しない。変更が必要な場合は別途承認を得る。

「精算」はTicket Backのメンバー精算（29-ProductionSettlementScreen.md）と紛らわしいため、Expenseの支払処理・状態の表示には用いない。

承認されたExpenseは、承認時点で会計へ転記する。

承認後に別の「会計転記承認」「仕訳確認」「Posting確認」等の中間Workflowを設けない。

---

# Submission

Member / Production Delegate / Primary Managerは、対象範囲内のExpenseを登録できる。

Memberが登録する場合は、小口立替精算を目的としたExpenseとして扱う。

Expense登録時点では、まだ正式なActualへ反映しない。

申請されたExpenseの状態はSUBMITTEDとし、Primary Manager、代理人、または有効なACCOUNTING_MANAGERのProduction Delegateによる承認を待つ（登録直後はDRAFT。Version 1.3。承認者はVersion 1.5で明確化し、Version 1.6で代理人を追加）。

Version 1.3（修正8で確定）：

- 新規登録したExpenseはDRAFT（申請前の下書き）とし、自動でSUBMITTEDにしない。申請者が明示的に申請した時点でSUBMITTEDとなる。
- 申請・取り下げ・再申請は申請者本人（Expenseの作成者で、Productionのメンバーであり続けている者）のみが行える。
- 申請者は承認前のSUBMITTEDのExpenseを編集・取り下げ（DRAFTへ戻す）できる。承認前の編集は新しい申請回（revision）として扱い、申請金額を更新する。
- SUBMITTED / REJECTEDのExpenseを編集できるのは申請者本人のみとし、会計権限保持者が他人の申請内容を承認前に書き換えることはできない。DRAFTについては従来どおり会計権限保持者も編集できる。

---

# Member Submission and Expense Category

Version 1.4（確定）。一般Memberによる小口立替Expenseの申請項目を以下とする。

| 項目 | 必須 | 備考 |
|---|---|---|
| 経費区分 | 必須 | 「物品購入」「交通費」のいずれか |
| 立替金額 | 必須 | 1円以上の整数 |
| 摘要・説明 | 任意 | |
| 領収書画像 | 任意 | 「Receipt Storage and Access」に従う。未添付を理由に申請を拒否しない |

- 交通費には、公共交通機関、駐車料金、車両関連の移動費用を含める。従来「車両交通費（レンタカー・駐車場代等）」としていた費用も、今後の申請では交通費として扱う。
- 申請者・申請日時など、システムが特定できる情報は自動記録し、手入力させない。
- 一般Memberは勘定科目・仕訳明細・借方・貸方を入力しない。勘定科目は承認時に経費区分からシステムが決定する。
- 一般Memberの申請は1申請・1金額とする。明細の分割・配分は行わない。
- 申請時点ではJournal Entryを生成せず、Actualにも計上しない。

承認時に、承認金額を用いて以下のJournal Entryをシステムが生成する。

| 経費区分 | 借方 | 貸方 |
|---|---|---|
| 物品購入 | 消耗品費（承認金額） | 未払金（承認金額） |
| 交通費 | 旅費交通費（承認金額） | 未払金（承認金額） |

勘定科目の扱い：

- 交通費・車両関連費用は、今後の処理では「旅費交通費」に統一する。
- 消耗品費・旅費交通費・未払金は、Organizationの有効な既存Accountを確認して再利用する。確認せずに重複作成しない。
- 必要な勘定科目がOrganizationに存在しない場合は、Accountを自動生成せず、承認（支払処理の場合は支払）を拒否し、理由を管理者に示す（Version 1.5。Version 1.4の「存在しない場合は作成する」を置き換え）。
- 勘定科目の重複作成を防ぐ排他制御・一意制約は、実装時の課題とする。現段階ではDB制約の追加や既存データの修正を行わない。
- 既存の「車両交通費」Account、それを使った過去のJournal Entry、Budgetデータは、削除・改名・書き換えしない。移行や統合が必要な場合は別途承認を得る。
- 新しい交通費の申請は、Organizationに「車両交通費」Accountが存在する場合でも「旅費交通費」に計上する。

入力方式の区別（Version 1.5）：

| 入力方式 | 利用者 | 入力内容 | 借方の決め方 |
|---|---|---|---|
| 区分選択方式 | 一般Member | 経費区分・立替金額・摘要（任意）・領収書（任意） | 承認時に経費区分から決定する |
| 明細入力方式（既存） | Primary Manager / 会計の権限を持つ者 | 明細ごとの勘定科目・金額・説明 | 各明細で指定された勘定科目 |

両方式は同じ承認・差し戻し・支払のWorkflowに従う。明細入力方式は、今回の仕様変更だけを理由に廃止・変更しない。

既存業務との関係：

- Primary Manager / Production Delegateによる既存の複数明細のExpense入力（各明細で科目を指定するもの）は変更しない。
- 「その他雑費（大道具・小道具・消耗品等）」の運用は、合意なく変更しない。
- 承認時の金額制限（「Approval Amount Adjustment」の複数明細に関する制約、1円以上の整数）は維持する。一般Memberの申請は1明細のため、承認金額の変更は既存の規則の範囲で行える。

Member向け画面（確定）：

- 一般Member向けの申請画面は、公演画面の「マイページ」タブに配置する。
- 同タブで、自分の申請の一覧と各申請の状態（下書き・申請中・差し戻し・承認済み・支払済み）を確認できるようにする。
- MemberにProduction全体のBudget / Actualを表示しない原則（「Accounting Visibility」）は維持する。
- グローバルメニューに独立した「経費精算」項目を追加することは、初期実装の対象外とする（Version 1.5。`12-FunctionalStructure.md`の「経費精算」メニューとの関係は別途決定する）。

---

# Approval Notification

SUBMITTEDになったExpense申請について、承認可能なPrimary Manager / 代理人 / 有効なACCOUNTING_MANAGERのProduction Delegateへ、出欠登録等の既存業務通知と同様の通知を行えるものとする（Version 1.5で承認可能者を明確化し、Version 1.6で代理人を追加）。

通知から対象Expense申請の確認・承認・差し戻しへ直接進められることを基本とする。

通知は承認処理への入口であり、通知を受け取らなかった場合でも別の承認入口から処理できる構造を妨げない。

Version 1.3：申請・再申請の通知は、承認可能者のうち当該Expenseを自ら承認できない者（申請者・作成者・立替払いを受ける者）を除いた全員へ送る。承認・差し戻しの結果は申請者へ通知する（Version 1.4：通知の文言でも「却下」ではなく「差し戻し」と表示し、差し戻し理由を確認できるようにする）。通知はトランザクションの確定後に送り、確定しなかった操作を通知しない。

同一Expenseに対して承認可能な複数Roleへ通知する場合でも、最初に有効な承認処理が行われた時点で処理済みとする。

---

# Approval Entry / Manager Review

承認処理には、少なくとも以下の2つの入口を許容する。

1. 業務通知から対象Expenseを開いて承認・差し戻しする。
2. Primary Manager / 代理人 / 有効なACCOUNTING_MANAGERのProduction Delegate向けの管理者メニューから、承認待ちExpenseを一覧・確認して処理する。

管理者メニューへの「会計承認」または同等の承認待ち一覧画面は、通知だけに依存せず未処理申請を確認できるようにするためのUIとして実装してよい。

ただし、この一覧画面を別個の会計Workflowとして扱わない。通知と管理者メニューは同じExpense承認処理への入口とする。

Role別の表示制御だけに依存せず、承認可否はBackendのAuthorization / Domain Ruleで担保する。

---

# Approval Amount Adjustment

承認者は、承認時に申請金額とは異なる金額を承認額として指定できるものとする。

申請金額は「申請者が実際に支出した金額」を表し、承認金額は「Production / Organizationとして会計・精算対象として認める金額」を表す。

例えば、Memberが衣装代として5,000円の衣服を購入して申請したが、劇団として支給するのはその半額2,500円と判断した場合、承認者は承認金額を2,500円に変更して承認できる。

この場合、会計へ転記するActualは承認金額2,500円を基準とする。

申請金額5,000円と承認金額2,500円の差額については、申請履歴・承認履歴として追跡可能とする。申請者が実際に5,000円を支払ったという事実を、承認金額2,500円で上書きして失わせてはならない。

承認金額は申請全体の合計金額として管理する。承認金額の初期値は申請金額とする。

承認金額は1円以上の整数とし、0円以下の承認は認めない（Version 1.3で確定。旧「UI実装時に別途判断」を置き換え）。

明細が複数あるExpenseで承認金額を申請金額と異なる額にする承認は、明細への配分規則が未確定のため拒否する。単一明細のExpense、または承認金額が申請金額と同じ場合は、既存の明細構造を維持して転記する。

承認金額を変更した場合でも、承認後の追加Posting操作は要求しない。変更後の承認金額をそのまま承認・会計転記する。

---

# Approval

Expense申請は、Primary Manager、代理人、または対象Productionで有効なACCOUNTING_MANAGERのRoleを持つProduction Delegateが承認できる。Production Delegateであることだけでは承認権限を持たない（Version 1.5。代理人はVersion 1.6で追加）。

Primary Managerしか承認権限者がいないProductionで、Primary Manager自身が申請者となる場合は、自己承認の禁止により承認できない。この場合は、別の承認権限者（ACCOUNTING_MANAGERまたは代理人）を任命する必要がある。代理人・会計管理者が自身の申請を承認できないことも同様である。差し戻しについても同様に、自身の申請を差し戻すことはできない（Version 1.7）。

承認者は申請金額を確認し、必要に応じて承認金額を変更した上で承認できる。

承認権限および承認金額変更権限はRole別UIの表示切替だけではなく、BackendのAuthorization / Domain Ruleとして担保する。

承認者は、Expenseの内容・申請金額・承認金額・対象Production等、承認に必要な業務情報を確認できるものとする。承認操作時には「この内容で承認しますか？」という確認ダイアログを表示する。

Version 1.3（修正8で確定）：

- 承認者は、当該Expenseの申請者（submitted_by）・作成者（created_by）・立替払いを受ける者（payer_person_id）のいずれでもあってはならない（自己承認の禁止）。API側で検証する。
- Version 1.7：差し戻しにも同じ禁止を同じ判定で適用する（「Return for Correction」）。
- 承認者が確認した申請回（revision）と承認時点の申請回が異なる場合、古い内容を承認せず拒否する（409 Conflict）。
- 会計が有効でないOrganizationでは、承認・転記を拒否する。
- 承認時のJournal Entryは、借方を各明細の科目（承認金額が申請金額と異なる単一明細では当該明細の科目に承認金額）、貸方を未払金（LIABILITY）とする。一般Memberの経費区分付き申請では、借方を経費区分から決まる科目（物品購入：消耗品費、交通費：旅費交通費）とする（Version 1.4、「Member Submission and Expense Category」）。未払金はOrganizationの有効な既存Accountを再利用する。利用者に借方・貸方を入力させない。
- Version 1.5：必要な勘定科目（未払金・消耗品費・旅費交通費）が存在しない場合は、Accountを自動生成せず承認を拒否し、理由を承認者に示す。現在の実装は未払金が存在しない場合に自動作成するため、実装時にこの方針へ合わせる（「Implementation Status」）。
- 承認とJournal Entryの作成・確定は同一トランザクションで行い、行ロック・条件付き状態更新・履歴の一意制約・既存仕訳の確認によって、同じ申請から複数のJournal Entryが作成されないことを保証する。

承認者は、Expenseの内容・申請金額・承認金額・対象Production等、承認に必要な業務情報を確認できるものとする。

---

# Return for Correction（差し戻し）

Primary Manager、代理人、または対象Productionで有効なACCOUNTING_MANAGERのRoleを持つProduction Delegateは、Expense申請を差し戻しできる（Version 1.5で権限者を明確化し、Version 1.6で代理人を追加）。

Version 1.7（2026-10-29確定）：差し戻しを行う者は、当該Expenseの申請者（submitted_by）・作成者（created_by）・立替払いを受ける者（payer_person_id）のいずれでもあってはならない（本人による差し戻しの禁止）。判定は承認と共通であり、Primary Manager・代理人・会計管理者のいずれにも適用する。API側で検証し、拒否した場合は権限エラー（403）とし、Expenseの状態・履歴・Journal Entry・通知を変更しない。申請者本人による取り下げ・編集・再申請は承認者の判断ではないため、この禁止の対象外とする。画面は自分の申請に承認・差し戻しの操作を表示しない（APIと同じ判定）。

差し戻されたExpenseは内部状態REJECTEDとして履歴を保持する。REJECTEDは、申請者が修正して再申請できる「差し戻し」を表し、終端の「却下」ではない。画面表示と通知では「差し戻し」と表示する（Version 1.4）。

差し戻し理由は必須とし、空白のみの理由は認めない（Version 1.3）。

申請者は差し戻された申請を編集し、差し戻し理由に従って金額・摘要・領収書を修正して再申請できるものとする（摘要・領収書はVersion 1.4で追加）。

差し戻し中はJournal Entryを生成せず、Actualにも反映しない。

再申請時は新しい会計Factを重複生成せず、承認前のExpense申請として再びSUBMITTEDに戻す。再申請後に承認された場合も、「Approval」の重複防止によって、同じExpenseから承認のJournal Entryが二重に生成されないことを保証する。

内部状態の値・API（既存の差し戻し操作の名称を含む）・DBの値は変更しない。変更が必要な場合は別途承認を得る。

---

# Settlement（支払済み）

Version 1.4：以下を方針として定める。SETTLEDは未実装であり、状態の追加・Schema変更は実装時に別途行う。

承認と支払は別の処理として扱う。承認済み（POSTED）のExpenseについて、立替金を実際に申請者へ支払った時点で支払記録を行い、状態をSETTLEDとする。

- SETTLEDへの状態遷移はPOSTED → SETTLEDのみとする。
- 支払記録の取消機能は初期実装では設けない。誤って支払済みにした場合等の訂正の運用は別途定義する（Version 1.5。Version 1.4の「未確定」を置き換え）。
- 画面表示は「支払済み」とし、「精算」とは表示しない。
- 支払処理を行えるのは、Primary Managerおよび明示的に任命された（対象Productionで有効な）ACCOUNTING_MANAGERとする。権限はBackendのAuthorizationで担保する。
- 自己承認は禁止するが、別の正当な権限者が承認したExpenseについて、Primary Managerが支払記録を行うことはできる（Version 1.5）。
- 支払処理の実行日時（操作を実行した日時）と支払実行者を記録する（Version 1.5で支払実行者の記録を明記）。
- 一部支払い・分割支払いは対象外とする。承認金額の全額を1回で支払済みにする。
- Organizationの会計機能が無効な場合、支払処理を拒否する。
- 支払方法と仕訳は次のとおりとする。

| 支払方法 | 借方 | 貸方 |
|---|---|---|
| 現金 | 未払金（承認金額） | 現金（承認金額） |
| 銀行振込 | 未払金（承認金額） | 預金（承認金額） |

- 貸方の「現金」「預金」は、Organization Setupで作成される既存のASSET Accountを利用する。「普通預金」等の重複するAccountを追加しない。
- 「未払金」「現金」「預金」のいずれかが存在しない場合は、Accountを自動生成せず支払処理を拒否し、理由を示す（Version 1.5）。
- 支払の仕訳は貸借対照表の科目同士の振替であり、Production Actual（費用）の金額を変えない。

POSTEDとの関係：

- POSTEDは承認・会計転記済みを表し、支払済みを意味しない。
- 既存のPOSTEDのExpense（旧CONFIRMEDから移行したものを含む）は、実際の支払済み状態を確認できない限り未払い（未精算）として扱い、支払済みと推定しない。

冪等性・重複防止（必須要件）：

- 承認・支払処理の再試行や同時実行によって、同じExpenseから承認・支払のJournal Entryが二重に生成されてはならない。
- 支払処理には、承認と同じく、行ロック・条件付き状態更新・履歴の一意制約・既存仕訳の確認を用いる。
- 既にSETTLEDのExpenseへの支払処理は、新しいJournal Entryを作らずに拒否する。

---

# Reimbursement Payee（立替金の受取人）

Version 1.5（確定）：初期実装では、Expenseの支払先・精算対象者は申請者本人とする。申請者以外の人物を支払先に指定する機能は、初期実装の対象外とする。

既存の`payer_person_id`は、立替金の受取人（ExpenseによってReimbursementを受けるPerson）を意味する。申請者（submitted_by）・作成者（created_by）とは別の項目として扱う。

現在の実装（調査時点の事実）：

- 作成時に任意で指定でき、更新では変更できない。
- 値の検証はIDの形式のみで、対象Productionのメンバーであるかは検証していない。
- 本人による承認・差し戻しの禁止の判定と、申請通知の送付先からの除外に用いる。
- 受取人として指定された者は、作成者でない限り当該Expenseを閲覧できない。

将来、申請者以外を支払先にできるようにする場合の課題（未決）：

- 一般Memberの申請で`payer_person_id`を指定させるか、指定できる者を誰にするか。
- 指定された受取人が対象Productionのメンバーであることの検証。
- 受取人による閲覧・通知の可否。
- 初期実装で`payer_person_id`を指定した既存のExpenseがあった場合の扱い。

---

# Budget and Actual by Expense Category（予算と実績）

Version 1.5：経費区分別の予算対実績表示は、本Workflow（申請・承認・支払）の実装とは分離し、別工程とする。本Workflowの実装では、既存の予算集計・予算テンプレートを変更しない。

別途決定すべき事項：

- 一般Memberの物品購入の実績は「消耗品費」に計上されるが、予算では「その他雑費（大道具・小道具・消耗品等）」に消耗品を含める運用があるため、科目ごとの予実比較で予算と実績が別の科目に分かれる。この対応関係。
- 予算テンプレートに「消耗品費」「旅費交通費」を標準項目として持つか（予算関連文書の間でも記載が揃っていない）。
- 既存の「車両交通費」の予算・実績と「旅費交通費」の実績を、予実比較でどう扱うか。

---

# Receipt Storage and Access（領収書）

Version 1.4。領収書画像は任意項目とし、未添付の申請も提出できる。

必須のセキュリティ要件（確定）：

- 領収書を公開WordPressメディア（wp-content/uploads等）や公開URLへ保存してはならない。
- 領収書をDBのBLOBとして保存しない（Version 1.5）。
- 保存先は`public_html`の外に設ける専用の非公開ディレクトリとし、開発環境・本番環境ごとに明示的に設定する（例：`~/stageart-private/<environment>/receipts`。例示であり、実在する設定値ではない）。
- 保存先が未設定、書き込み不能、または安全性を確認できない場合は、領収書機能を無効化する。公開領域へフォールバックしてはならない。
- 保存ディレクトリの権限は所有者のみがアクセスできる設定（例：700）を基本とする。
- 領収書の取得は認証付きAPIを通す（例：`GET /expenses/{id}/receipts/{receiptId}`）。公開URLを共有する方式は用いない。
- 取得のたびに、サーバー側でアクセス権限を検証する。閲覧できるのは、申請者、Primary Manager、対象Productionで有効なACCOUNTING_MANAGERとする。
- 領収書のレスポンスには、少なくとも`Cache-Control: no-cache, must-revalidate, max-age=0, no-store, private`と`X-Content-Type-Options: nosniff`を設定する。
- 実装後、開発環境でリバースプロキシのキャッシュが`HIT`にならないことを検証する。
- MIMEタイプ・ファイル形式は、ファイル名・拡張子だけで判断せず、ファイル内容から検証する。
- アップロードは1画像ずつのリクエストを基本とする。
- 承認後は、証憑保全のため申請者が領収書を変更・削除できないことを基本とする。承認前（下書き・申請中・差し戻し中）は申請者が追加・差し替え・削除できる。
- 公開画像向けの`10-Architecture/MediaPolicy.md`（原本を保存しない、公開表示用に正規化する等）を、領収書の保存・認可ポリシーとして流用しない。

暫定設計値（最終確定値ではない。実装前に環境制約を確認して確定する）：

- 1申請につき最大5枚。
- 1画像につき最大10MB。
- 受け付ける形式の候補はJPEG、PNG、WebP、HEIC。
- 原本を保持する。HEICは原本を保持し、表示用のJPEGを生成する。
- 表示用画像は長辺約2000pxを目安とし、EXIF等の位置情報メタデータの除去、ファイルサイズの制限を検討する。
- 保存するファイル名は推測できないものとし、URLや画面に保存パスを出さない。
- 原本と表示用画像の保存方針は、実装前に確定する必要がある。

未決事項：

- 領収書の保存期間・廃棄ルールは今回決定しない。法務・会計面の確認が必要な未決事項とし、具体的な年数を確定値として扱わない（Version 1.5）。

---

# Implementation Preconditions（実装前の確認事項）

以下は2026-10-25の読み取り調査の時点の情報であり、その後に再確認したものではない。

開発環境（dev-api）の調査結果：

- DBのスキーマバージョンは1.43.0、配置済みコードの`CURRENT_VERSION`も1.43.0だった。
- Schema 1.44.0の変更（Expenseの申請回等のカラム、Expense Review用テーブル）は開発DBに未反映だった。
- 開発DBにはAccount・Journal Entry・Budget Line・Expenseの既存データがなかった。
- MySQLの`max_allowed_packet`は16MBだった。大容量画像をDBのBLOBへ格納する設計を安易に採用しない。
- Web PHPから`public_html`外へ実際に書き込めるか、Web PHPの`open_basedir`、Web側のアップロード上限は未確認である（コマンドラインのPHPの設定値のみ確認）。確認済みとして扱わない。

本番環境：

- 本番DBは権限上の理由で確認できておらず、スキーマバージョン、Account、既存データは未確認である。
- 本番DBを確認するための権限チェックを迂回しない。
- 本番環境へのマイグレーションは、スキーマ・既存データ・バックアップの確認後、別途承認を得てから行う。

バックアップ：

- `public_html`外に保存する領収書ファイルが既存のバックアップに含まれるかは未確認であり、実装前に確認する。

---

# Implementation Status（実装状況）

Version 1.5。以下は2026-10-26時点のこのブランチの作業ツリー（未コミット）のコードを読んで確認した事実であり、本番・開発環境に反映済みであることを意味しない。

実装済み（作業ツリー、未コミット）：

- DRAFT → SUBMITTED → POSTED / REJECTED → 再申請の状態遷移、取り下げ、差し戻し理由の必須化。
- 承認時のJournal Entry生成（借方は明細の科目、貸方は未払金）と、行ロック・条件付き状態更新・履歴の一意制約・既存仕訳の確認による二重生成の防止。
- 承認・差し戻しの権限（Primary Manager、代理人、有効なACCOUNTING_MANAGER。Backendの権限 `Expense.Approve`。Version 1.6）、承認可能者への申請通知、本人による承認・差し戻しの禁止（差し戻しはVersion 1.7）、申請回の確認。
- Schema 1.44.0（申請回等のカラム、Expense Review用テーブル）。旧CONFIRMEDの移行はスキーマ更新から分離し、運用者がWP-CLIで明示的に実行する（2026-10-29）。

未実装・方針との差異（後続実装の対象）：

- 一般Memberの区分選択方式（経費区分・摘要の保存、区分から借方を決める処理）。現在の登録は明細ごとの勘定科目の指定を必須としている。
- 必要な勘定科目がない場合の拒否。現在は未払金が存在しない場合に自動作成している。
- 画面表示・通知文言の「差し戻し」への統一。現在は「却下」と表示している。
- SETTLED（支払記録）と支払の仕訳。
- 領収書の保存・取得。
- 公演画面の「マイページ」タブでの申請・一覧。現在のマイページタブはプロフィールを表示している。

実装順序の案（提案。確定ではない）：

1. 一般Memberの申請と、区分に基づく勘定科目の決定
2. 差し戻し・再申請と表示文言の整備
3. 支払処理とSETTLED状態
4. 領収書機能（保存環境の安全性の確認後）

---

# Posting Rule

Expenseの承認と会計転記を分離しない。

以下を確定原則とする。

> Expenseを承認した時点で、承認金額を基準として必要なJournal Entryを会計へ転記し、Production Actualへ反映する。

承認済みExpenseについて、さらに別のPosting操作を要求しない。

これにより、利用者が複数の会計Workflowを順番に処理する必要をなくす。

---

# Journal Entry Relationship

Expenseは業務上の支出情報を表し、Journal Entryは会計上の正本を表す。

SUBMITTED中のExpenseは、正式なActualの構成要素として扱わない。

承認されたExpenseは、既存のExpense / Journal Entry方針に従ってJournal Entryを生成し、そのJournal EntryをActualの集計対象とする。Journal Entryの金額は承認金額を基準とする。

申請金額と承認金額が異なる場合でも、申請者の実支出額と承認額の双方を履歴として追跡可能とする。

POSTED済みJournal Entryを直接変更・削除しないという既存のJournal Entry原則を維持する。

申請・取り下げ・承認・差し戻し・再申請の各操作は、申請回・操作種別・申請金額・承認金額・明細のスナップショット・差し戻し理由・操作者・操作日時を追記専用の履歴として記録し、過去の履歴を上書き・削除しない（Version 1.3。Version 1.4で表記を「差し戻し」に統一。支払処理を実装する際は同じ履歴に記録する）。

承認後の訂正は、既存Journal Entryを上書きせず、既存のReversal / Adjustment原則に従う。

---

# Legacy CONFIRMED Expense

本Workflow以前の確定方式（CONFIRMED。確定時に下書きJournal Entryを作成し、別途Postingを要した）で登録されたExpenseは、旧データとの互換性のため読み取り可能に保つが、新たな状態遷移は行わない（Version 1.3）。

Schema 1.44.0の移行では、CONFIRMEDのうち、POSTED済みのJournal Entryがちょうど1件あり、その金額が明細合計と一致するものだけを、既存Journal Entryを変更せずにPOSTEDへ移行する。2026-10-29の改訂で、金額に加えて、Journal EntryのOrganization・Production、Accountごとの借方、未払金（LIABILITY）への単一の貸方が旧確定処理の作成した形と一致することも条件とした。

移行はコードの配置やスキーマ更新では実行しない。運用者が事前確認（読み取りのみ）で対象件数・判定不能件数を確認し、承認を得たうえで、事前確認の指紋を指定して実行する。各Expenseは個別のトランザクションで処理し、いずれかの段階で失敗した場合はCONFIRMEDのまま残す。

下書きJournal Entryしかないもの、Journal Entryがない・複数ある・金額が一致しないもの等は自動転記・自動変換せず、個別確認の対象としてCONFIRMEDのまま残す。

本番移行の判定条件・事前確認・バックアップ・復旧の手順案は`docs/handoffs/LegacyExpenseMigration-Runbook.md`に記載する（2026-10-29。本番での実施は別途承認を得る）。

旧確定APIは呼び出し元の移行確認まで削除せず、新しいCONFIRMEDを作らないよう明示的に拒否する。

---

# Large Expense Boundary

Member向けExpense登録機能は、会場費・外注費等の大口支出を登録するための汎用入力画面として設計しない。

大口支出はBudgetとの関係、未払金、支払予定、取引先等を含む管理対象となるため、Primary Manager / Production Delegateが管理する業務フローから登録する。

小口立替Expenseと大口支出の具体的な金額境界は、UI実装時に恣意的な固定値を導入せず、別途必要性を確認して決定する。

---

# Business Rules

- MemberはProductionのBudgetを閲覧できない。
- MemberはProductionのActualを閲覧できない。
- Primary ManagerとProduction DelegateはProductionのBudgetを閲覧できる。
- Primary ManagerとProduction DelegateはProductionのBudgetを入力・変更できる。
- Primary ManagerとProduction DelegateはProductionのActualを閲覧できる。
- Memberは小口立替精算を目的としたExpenseを登録できる。
- Production DelegateとPrimary Managerも対象範囲の小口立替Expenseを登録できる。
- Member向けExpense登録は消耗品・小道具等の小口支出を主対象とする。
- 会場費・外注費等の大口支出をMember向け小口Expense登録の対象としない。
- Expense登録直後はDRAFTとし、申請者の申請によりSUBMITTEDとなる。いずれも正式なActualへ反映しない（Version 1.3）。
- 一般Memberの申請の必須項目は経費区分（物品購入／交通費）と立替金額とし、摘要・説明と領収書画像は任意とする。
- 一般Memberは勘定科目・仕訳明細を入力しない。
- 承認時の借方は、物品購入は消耗品費、交通費は旅費交通費とし、貸方は未払金とする。
- 今後の交通費・車両関連費用は旅費交通費に統一し、既存の車両交通費Account・過去の仕訳・Budgetデータは変更しない。
- SUBMITTEDのExpenseについて承認可能者へ業務通知を行える。
- 通知からExpenseの確認・承認・差し戻しへ進められる。
- Primary Manager、代理人、または対象Productionで有効なACCOUNTING_MANAGERのProduction DelegateがExpense申請を承認・差し戻しできる。それ以外のProduction Delegateであることだけでは承認できない（Version 1.6）。
- 申請者・作成者・受取人による承認・差し戻しは禁止する（差し戻しはVersion 1.7）。Primary Manager・代理人・会計管理者のいずれにも適用する。承認権限者が自分しかいない場合に自身が申請したExpenseは、別の承認権限者の任命が必要となる。
- Production決算の確定権限はPrimary Manager、代理人、会計管理者とする。それ以外のProduction DelegateおよびMemberは確定できない（Version 1.6）。
- 会計管理者のBudget閲覧・変更、Actual閲覧、Expense登録、Journal Entry確定に関する権限は本表では定義せず、各機能の権限仕様に従う。
- 承認者は申請金額を変更し、承認金額を指定できる。
- 承認金額はProduction / Organizationとして会計・精算対象として認める金額を表す。
- 承認金額が申請金額と異なる場合、双方を履歴として追跡可能にする。
- 承認時に「この内容で承認しますか？」の確認ダイアログを表示し、YESでExpense承認・POSTED化とJournal Entry転記を同一処理で実行する。
- NOまたはキャンセルの場合は承認・転記せず、申請状態を維持する。
- 承認時点で承認金額をProduction Actualへ反映する。
- 承認後に別途Posting操作を要求しない。
- Primary Manager、代理人、または有効なACCOUNTING_MANAGERのProduction DelegateはExpense申請を差し戻し（内部状態REJECTED）にできる（代理人はVersion 1.6で追加）。差し戻し理由は必須とする。
- REJECTEDは画面・通知で「差し戻し」と表示する。REJECTEDのExpenseは履歴を保持し、修正後に再申請できる。
- 差し戻し中はJournal Entryを生成しない。
- SUBMITTEDのExpenseが承認されるまで、Actualへ二重計上しない。
- 支払処理によりPOSTEDからSETTLED（表示：支払済み）とする方針とする（未実装）。
- 支払処理はPrimary Managerおよび明示的に任命されたACCOUNTING_MANAGERが行える。一部支払いは対象外とし、会計機能が無効なOrganizationでは拒否する。
- 支払記録の取消機能は初期実装では設けない（Version 1.5）。
- 支払日時と支払実行者を記録する。
- 支払の仕訳は、現金は未払金／現金、銀行振込は未払金／預金とし、既存の預金Accountを利用する。
- 必要な勘定科目が存在しない場合は、Accountを自動生成せず承認・支払を拒否する（Version 1.5）。
- 初期実装では、支払先は申請者本人とする（Version 1.5）。
- POSTEDは支払済みを意味せず、支払済みを確認できないExpenseを支払済みと推定しない。
- 承認・支払処理の再試行・同時実行によって二重のJournal Entryを生成しない。
- 領収書は公開領域・DBのBLOBに保存せず、認証付きAPIを通じて、権限を持つ者だけが取得できる。
- 経費区分別の予算対実績表示は、本Workflowの実装と分離する（Version 1.5）。
- POSTED済みJournal Entryを直接変更・削除しない。
- 承認後の訂正はReversal / Adjustment原則に従う。
- Expense申請の承認可否および承認金額変更可否はBackendのAuthorization / Domain Ruleで担保する。
- 会計Workflowは必要最小限とし、承認と会計転記を分離しない。

---

# Design Principle

StageArtのExpense Workflowは、会計上の厳密性を保ちながら、現場利用者に不要な会計Workflowを要求しないことを原則とする。

基本操作は、

> 登録 → 通知 → 確認・必要に応じて承認金額変更 → 承認 / 差し戻し → 会計転記 →（立替金の支払後）支払済み

とし、通知は承認処理を促す入口、管理者メニューは未処理申請を確認する補助入口として扱う。

承認後に追加のPosting操作を要求しない。支払済みへの支払処理は、Postingではなく、立替金の実際の支払を記録する操作として扱う。

申請金額と承認金額が異なる場合でも、申請者の実支出額を履歴として保持し、会計Actualには承認金額のみを反映する。

---

# Related Documents

- `Expense.md`：Expense Domain全体（大口支出の未払計上・精算差額を含む）。
- `AccountConsistencyPolicy.md`：消耗品費・旅費交通費・未払金・現金・預金等の標準Account。
- `10-Architecture/MediaPolicy.md`：公開画像のPolicy。領収書には適用しない。
- `ExpenseSubmissionAndJournalRules.md`：リモートブランチにのみ存在する補足文書（本書Version 1.4の作成時点で、このブランチには未統合）。同文書は銀行振込の貸方を「普通預金」としているが、本書では既存の「預金」Accountを用いる。統合時に整合させる。
