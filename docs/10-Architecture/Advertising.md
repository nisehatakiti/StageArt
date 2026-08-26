# StageArt Blueprint

# 10 - Architecture
# Advertising Architecture

Version : 1.0

---

# Purpose

Advertising Architectureは、
StageArtにおける広告表示の基本方針とClient上の表示責務を定義する。

広告は、
StageArtのBusiness FactやBusiness Ruleそのものではなく、
Client上に表示されるPresentation / Commercial Contentとして扱う。

本Architectureでは、
広告表示位置・表示範囲・Client間の共通方針を定義し、
特定の広告Providerや配信技術の採用までは確定しない。

---

# 1. Advertising Principles

StageArtの広告表示は、
以下を基本原則とする。

- 広告は業務情報の閲覧・操作を妨げない。
- 広告をコンテンツ本文の途中へ挿入しない。
- 広告は画面上部または画面下部の専用領域へ集約する。
- Web ClientとMobile Clientの双方で広告表示に対応できる。
- 広告表示領域と広告配信Providerを分離する。
- 特定の広告ProviderをFrontend Architectureへ直接固定しない。
- 広告の表示可否・位置はClient / 画面単位で制御できる構造とする。
- 広告表示によってBusiness Operationの安全性・視認性を損なわない。
- 広告表示用のDataをBusiness Factの代替として利用しない。

---

# 2. Ad Placement

広告の表示位置は、
以下のいずれかを基本とする。

- Top Placement（画面上部）
- Bottom Placement（画面下部）

同一画面で上下両方へ必ず表示することを基本要件とはしない。

画面ごとに、
上部または下部の広告枠を選択できる。

広告は、
Navigation、主要Action、Business Dataの間へ割り込ませない。

---

# 3. No Inline Advertising

以下のような広告配置は採用しない。

- 業務一覧の途中への広告挿入
- Form入力項目の途中への広告挿入
- Timetable Itemの間への広告挿入
- 会計明細の間への広告挿入
- 重要なConfirmation画面への広告挿入
- Error MessageやBusiness Resultの途中への広告挿入

広告は、
Business Contentと明確に区別できる専用領域に表示する。

---

# 4. Client Scope

広告表示は、
以下のClientで利用可能とする。

- Web Client
- Mobile Client

Public Client、Management Client、Reception Clientなどについては、
各Clientの用途とUXを考慮して広告枠の利用可否を個別に決定できる。

ただし、
広告表示のためにBusiness RuleをClient間で分岐させない。

---

# 5. Timetable Safety

Timetableは、
仕込み・テクニカルリハーサル・ゲネプロ・本番等において、
舞台使用状況を共有するための業務情報である。

そのため、
Timetableの主要Content領域には広告を表示しない。

特に、

- Stage Usage
- Activity
- Location
- Participant / Person
- 時間

などの安全上重要な情報を広告によって分断・遮蔽してはならない。

---

# 6. Accounting and Critical Operations

会計、受付、Check In、確定操作など、
業務上重要な操作を行う画面では、
広告が操作対象や結果表示を妨げないことを保証する。

広告枠は、
Business Operationの主要領域とは独立したUI領域として扱う。

---

# 7. Advertising Presentation Boundary

広告表示は、
概念的に以下の構造とする。

Application / API
        ↓
Advertising Data / Provider Adapter
        ↓
Advertising Presentation Boundary
        ↓
Client Ad Placement
        ↓
Top / Bottom Ad Slot

広告Provider固有のAPIやSDKを、
Business Domainへ直接持ち込まない。

---

# 8. Provider Independence

現時点では、
特定の広告Providerを採用しない。

将来的に、

- 外部広告Network
- 自社広告
- StageArt利用団体向け広告
- 舞台関連サービス広告

などへ拡張できる構造を妨げない。

Provider変更によって、
Business DomainやBusiness Ruleが変更されない構造を基本とする。

---

# 9. Advertising Data

現時点では、
広告専用のBusiness Entityを新設しない。

広告配信・広告枠管理に必要なData Modelについては、
具体的な広告Provider、課金方式、掲載主体、掲載期間、審査・管理要件が確定した段階で別途定義する。

紙媒体のTimetable Print Viewについては、
本ArchitectureのWeb / Mobile広告要件をそのまま適用せず、
Print View固有のPresentation要件として別途判断する。

---

# 10. Future Considerations

以下は本Versionでは未確定とする。

- 広告Provider
- 広告配信方式
- 広告枠サイズ
- 広告フォーマット
- 広告ローテーション
- 広告表示条件
- Organization / Production単位の広告設定
- 広告主・掲載期間・料金等の管理Model
- 広告効果計測
- User単位の広告制御
- 有料プラン等による広告非表示

これらは、
広告機能を実装するPhaseで別途確定する。

---

# 11. Design Decisions

- 広告は画面上部または下部へ集約する。
- コンテンツ途中への広告挿入は禁止する。
- Web / Mobileの双方で広告表示可能とする。
- Timetableの主要Content領域には広告を表示しない。
- 会計・受付・Check In等の重要操作を広告で妨げない。
- 広告ProviderとClient UIを分離する。
- 現時点では広告専用Business Entityを追加しない。
- 広告表示はPresentation Concernとして扱い、Business Fact / Business Ruleと分離する。
