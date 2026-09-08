# StageArt Blueprint

# Domain Consistency Policy : Rehearsal Management

Version : 1.2
Status : Confirmed MVP policy

---

# Purpose

本書は、既存のRehearsal / RehearsalAttendance Domainを前提として、稽古管理のDomain整合性を定義する。

画面・通知・業務操作の詳細な確定仕様は、以下を正本とする。

- docs/20-RehearsalManagementScreen.md

本書では、Domainとして維持すべき整合性と実装上の基本方針を定義する。

---

# Domain Principle

既存のRehearsal DomainおよびRehearsalAttendance Domainを可能な限り維持する。

MVP仕様のために新しい稽古管理モデルを新設するのではなく、既存の以下を土台として改修する。

- Rehearsal
- RehearsalAttendance
- RehearsalStatus
- RehearsalAttendancePhase
- RehearsalAttendanceStatus

既存の本人回答フェーズと管理者による実績更新フェーズが分離されている場合は、その構造を維持してよい。

---

# Rehearsal Information

Rehearsalは少なくとも以下の業務情報を扱う。

- 日付
- 開始時刻
- 終了時刻
- 場所
- 連絡事項
- 回答期限
- 参加対象メンバー

稽古名はMVPの利用者向け業務仕様では使用しない。

既存DomainやAPIにTitle等が存在する場合でも、内部互換性のために保持することは許容するが、利用者に入力・表示させない。

---

# Attendance Target

参加対象者はProductionの現行メンバーから選択する。

稽古作成時のUIでは、Productionメンバー全員を初期状態で選択済みとする。

管理者はそこからチェックを調整する。

稽古作成後も参加者を追加できる。

新規追加者にのみ通知し、既存参加者には重複通知を行わない。

---

# Attendance Model

## Planning Stage

調整中の本人回答は既存実装の状態を正とする。

- UNANSWERED
- AVAILABLE
- UNAVAILABLE

UI上の表示は既存実装に対応する「未回答」「参加可能」「参加不可」とする。

本仕様では、調整中の回答を「出席／欠席／未定」へ変更しない。

## Confirmed / Actual Stage

既存の確定後回答および実績更新構造を維持する。

管理側の最終的な出欠状態として、少なくとも以下を扱えるようにする。

- 出席
- 遅刻
- 早退
- 欠席

遅刻・早退について既存Statusが存在する場合は、それを活用する。

---

# Attendance Comment

出欠回答には自由記述の備考を持たせる。

備考は回答ステータスとともに保持する。

回答期限前は本人が回答と備考を変更できる。

管理者が出欠を変更した場合、本人への通知は不要とする。

---

# Response Deadline

調整中のRehearsalには回答期限を必須とする。

回答期限は日時として保持する。

期限前は本人による回答・変更・備考変更を許可する。

期限後は本人による回答・変更・備考変更を拒否する。

この制御はUIだけでなくApplication / API側でも保証する。

---

# Reminder

回答期限の24時間前をリマインド基準時刻とする。

対象は未回答の参加対象者のみとする。

回答期限変更時は、新しい期限を基準にリマインド時刻を再計算する。

期限延長時は即時通知を行わない。

期限前倒し時、新しいリマインド時刻が既に経過している場合は未回答者へ即時通知する。

通知履歴のUIは不要とする。

---

# Rehearsal Lifecycle

Rehearsalは既存Lifecycleを基本として維持する。

- 調整中
- 確定
- 完了
- 中止

中止済みRehearsalは削除せず履歴として保持する。

中止済みRehearsalはメンバー実績集計の対象外とする。

---

# Editing Rules

調整中のRehearsalは通常の編集を許可する。

確定済みRehearsalは以下の制御を行う。

- 日付変更：不可
- 開始時刻変更：可能
- 終了時刻変更：可能
- 場所変更：可能
- 連絡事項変更：可能

日付変更が必要な場合は、既存Rehearsalを中止し、新しい日付で新規作成する。

---

# Authorization

以下の権限を持つユーザーが稽古管理操作を行える。

- 公演管理者
- 稽古管理代理人

管理操作には以下を含む。

- 稽古作成
- 稽古編集
- 稽古確定
- 稽古中止
- 参加者追加
- 管理者による出欠変更

参加対象メンバーは、自分自身の出欠回答を行える。

---

# Member Performance Aggregation

公演終了後のメンバー実績では、中止を除き、そのメンバーが参加対象として紐づいているRehearsalを集計対象とする。

集計項目：

- 出席
- 遅刻
- 早退
- 欠席
- 稽古回数

参加実績が確定していないものは、公演終了後の実績集計では欠席として扱う。

したがって、

出席 ＋ 遅刻 ＋ 早退 ＋ 欠席 ＝ 稽古回数

が成立する。

---

# Implementation Boundary

Rehearsal Managementの詳細な画面・通知・操作仕様はdocs/20-RehearsalManagementScreen.mdを正本とする。

本Policyと画面仕様が矛盾する場合、より新しいConfirmed Blueprintを優先する。
