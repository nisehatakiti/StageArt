# StageArt Blueprint

# Production Lifecycle

Version : 1.1

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
| 決算完了 | COMPLETED | Productionに必要な会計・精算を完了した状態 |
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

Productionは、以下を完了した時点でCOMPLETEDとなる。

公演終了
    ↓
精算
    ↓
未払金・支払・差額調整等の会計処理
    ↓
決算完了
    ↓
COMPLETED

具体的な決算完了条件およびAccounting側のClose処理はAccounting Domainで定義する。

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
- COMPLETEDは決算完了を意味する。
- ARCHIVEDは完了したProductionの保管状態を意味する。
- Production Statusの基本遷移はDRAFT → PLANNING → ACTIVE → COMPLETED → ARCHIVEDとする。
- CANCELLEDは通常Lifecycleとは別の中止状態として扱う。
- Lifecycle Transitionは管理者による明示的なGOを起点として実行する。
- Statusの任意の直接書き換えを、通常のLifecycle操作として扱わない。
- Server Sideで現在Statusから許可されたLifecycle Transitionであることを検証する。
- 決算完了条件の詳細はAccounting Domainで定義する。
- Production DomainはAccountingの詳細な仕訳処理そのものを管理しない。
