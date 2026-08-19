# StageArt Blueprint

# Domain Consistency Policy : Rehearsal Management

Version : 1.1

---

# Purpose

既存のRehearsal / RehearsalAttendance Domainを前提として、V1の稽古管理における参加対象、出欠回答、回答期限、当日管理、Google Calendar自動連携の具体的な運用を定義する。

Rehearsal自体のLifecycleはRehearsal Domainで定義し、権限はAuthorization Domainで管理する。

---

# Rehearsal Type

Rehearsalには、稽古種別を自由入力できる項目を持つ。

例：

- 立ち稽古
- 通し稽古
- 読み合わせ
- ダンス稽古
- スタッフ打ち合わせ

固定Enumとして限定せず、Productionごとの実際の運用に合わせて自由に入力できるものとする。

---

# Rehearsal Information

基本的な稽古情報は以下を保持する。

- 日時
- 場所
- 稽古種別
- 稽古内容・連絡事項
- 出欠回答期限

既存Rehearsal DomainのTitle / Description等と重複する項目は、実装時に既存フィールドへ対応させる。

---

# Attendance Target

稽古の参加対象者は、キャストとスタッフで扱いを分ける。

## Cast

キャストは、原則としてProductionに所属するキャスト全員を参加対象とする。

個別の細かい参加条件や連絡事項は、稽古の稽古種別・内容・連絡事項等の自由入力欄を利用して共有する。

V1では、稽古ごとにキャスト一人ひとりを選択して参加対象から外すことを基本としない。

## Staff

スタッフは、稽古ごとに参加対象者を選択できる。

すべてのスタッフが毎回参加対象になるとは限らないため、稽古ごとの対象者設定を許可する。

演出は通常のスタッフ選択対象には含めず、別扱いとする。

---

# Attendance Status

出欠回答・当日出欠は、以下の状態を扱う。

- 出席
- 欠席
- 遅刻
- 早退
- 不明
- 未回答

既存RehearsalAttendance Domainの予定段階・実施段階のStatusへ対応させる。

具体的な対応例：

```text
未回答 → UNANSWERED
出席   → ATTENDING / ATTENDED
欠席   → NOT_ATTENDING / ABSENT
遅刻   → LATE
早退   → 早退状態として管理
不明   → 未確定の出欠情報として管理
```

既存Domainで状態Enumが定義されている場合、V1 UI上の表示名とDomain Statusを分離して対応する。

---

# Attendance Comment

出欠回答にはコメント欄を設ける。

遅刻理由、早退予定、欠席理由、その他の連絡事項等の詳細はコメントへ記載できる。

コメントを理由別の固定項目として細分化しない。

---

# Response Deadline

稽古管理者は、Rehearsalごとに出欠回答期限を設定できる。

回答期限は固定値ではなく、管理者が具体的な日時を入力できるものとする。

用途に応じた目安：

- 稽古予定の参加確認：登録日から3日後程度
- 稽古本体の最終確認：稽古前日まで

上記は目安であり、システム上の固定期限とはしない。

---

# Notification

Rehearsalの予定確認・変更等について、対象者へStageArtの通知を行える構造とする。

出欠回答期限を設定し、対象者は通知から出欠回答へ進めることを基本とする。

---

# Day-of-Rehearsal Management

稽古当日の管理画面では、そのRehearsalの出席予定者だけを表示する。

Production全メンバーを一覧表示して対象者を探す方式にはしない。

基本表示例：

```text
8/25 19:00～
○○スタジオ

出席予定者
- A：出席
- B：遅刻
- C：未回答
- D：不明
```

実施後はRehearsalAttendanceの実績状態を保持し、過去の稽古履歴として参照できる。

---

# Google Calendar Integration

Google Calendar連携はStageArt V1から自動連携を基本とする。

StageArtのRehearsalを正本とし、Google Calendarは連携先とする。

稽古日程が確定した段階で、Google Calendar連携を有効にしている利用者へ予定を自動登録する。

個人連携：

```text
Person
  ↓
自身のGoogle Accountを連携
  ↓
自身のGoogle Calendar
  ↓
確定したRehearsalを自動登録
```

団体連携：

```text
Organization
  ↓
団体設定のGoogle Account
  ↓
Google Calendar
  ↓
確定したRehearsalを自動登録
```

団体のGoogle Accountは、既存のDrive公開用Google連携と同一の連携設定を利用できる構造を基本とする。

個人のGoogle Accountは、団体Google Accountとは独立して各Person自身が連携する。

---

# Calendar Source of Truth

StageArtがRehearsalの正本である。

Google Calendar側で予定を直接変更しても、その変更をStageArtへ逆同期しない。

StageArt上で以下が変更された場合は、連携済みGoogle Calendar Eventへ自動反映する。

- 日時変更
- 場所変更
- 稽古内容変更

RehearsalがCANCELLEDとなった場合は、連携済みGoogle Calendar Eventを削除する。

Google Calendar Eventの削除後も、StageArt側のRehearsalおよびRehearsalAttendance履歴は保持する。

StageArt側で再度確定した場合は、新しいGoogle Calendar Eventとして連携する。

Google Calendar EventとStageArt Rehearsalの対応関係を保持し、変更・削除対象を特定できるようにする。

具体的なGoogle Calendar API操作はExternal Integration / Infrastructureで定義する。

---

# V1 Principle

V1では、StageArtの稽古管理を中心とし、Google Calendarは予定確認を容易にするための自動外部連携として利用する。

Google Calendarを稽古管理の正本にしない。

出欠情報もGoogle Calendarへ同期することを基本要件とせず、StageArt内のRehearsalAttendanceを正本とする。

---

# Business Rules

- 稽古種別は自由入力とする。
- キャストは原則としてProduction所属キャスト全員を参加対象とする。
- スタッフはRehearsalごとに参加対象者を選択できる。
- 演出は通常のスタッフ選択対象には含めない。
- 出欠UIでは出席・欠席・遅刻・早退・不明・未回答を扱う。
- 出欠回答には自由記述コメントを付けられる。
- 出欠回答期限は稽古管理者がRehearsalごとに設定する。
- 回答期限の目安は、予定調整では登録日から3日後程度、最終確認では稽古前日までとする。
- 当日の管理画面には出席予定者だけを表示する。
- Google Calendar連携はStageArtを正本として行う。
- 個人Google Calendarは各Person自身のGoogle Account連携によって利用する。
- 団体Google Calendarは団体設定のGoogle Accountを利用できる。
- 団体Google AccountはDrive公開用の既存Google連携設定と共有できる構造を基本とする。
- 稽古が確定した時点で、連携対象のGoogle CalendarへEventを自動登録する。
- StageArt側で稽古の日時・場所・内容を変更した場合、連携済みGoogle Calendar Eventを自動更新する。
- StageArt側で稽古をCANCELLEDにした場合、連携済みGoogle Calendar Eventを自動削除する。
- Google Calendar側の変更をStageArtへ逆同期しない。
- Google Calendar Event削除後もStageArt側の稽古履歴を保持する。
