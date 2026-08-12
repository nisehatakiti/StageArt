# StageArt Blueprint

# Domain Model : Timetable

Version : 2.0

---

# Purpose

Timetableは、
Rehearsalの詳細な進行内容を管理するDomainである。

Timetableは、

「そのRehearsalの中で、何を、何時から、どの順番で行うか」

を管理する。

Rehearsalが
「いつ、どこで、何をする予定か」
という稽古そのものを表すのに対し、

Timetableは
「そのRehearsalの中で何を行うか」
という詳細な進行を表す。

---

# Concept

基本構造：

Production
  ↓
Rehearsal
  ↓
Timetable
  ↓
Timetable Item

Timetableは、
Rehearsalの詳細な進行を管理する。

Timetableの基本的な親DomainはRehearsalである。

---

# Relationship

TimetableはRehearsalに関連付ける。

Rehearsal
  ↓
Timetable

一つのRehearsalに、
一つのTimetableを設定できる。

Timetableが不要なRehearsalも許可する。

例えば、

「8/20 19:00〜22:00 稽古」

だけを登録し、
詳細な進行を設定しないこともできる。

---

# Timetable and Production

Timetableは、
Productionに直接所属する独立した予定表としては扱わない。

Productionの稽古・活動を詳細化する場合は、
Rehearsalを介してTimetableを関連付ける。

基本構造：

Production
  ↓
Rehearsal
  ↓
Timetable

本番期間の進行管理など、
Rehearsalだけでは表現できない活動については、
将来的に別のActivity Domainなどで拡張する。

Version 2.0では、
Timetableの親をRehearsalに統一する。

---

# Timetable Item

Timetableは、
複数のTimetable Itemから構成される。

基本構造：

Timetable
  ↓
Timetable Item

Timetable Itemは、
その時間帯に行う一つの作業・活動を表す。

例：

19:00
  集合

19:15
  発声

19:30
  第一場

20:30
  休憩

20:45
  第二場

21:45
  振り返り

---

# Schedule

Timetable Itemは、
開始時刻と終了時刻を持つ。

基本情報：

- Start Time
- End Time

必要に応じて、
所要時間からEnd Timeを自動計算できる。

Timetable Itemの時間情報は、
Rehearsalの開始・終了時刻の範囲内で設定することを基本とする。

---

# Title

Timetable Itemには、
活動内容を表すTitleを設定する。

例：

- 集合
- 発声
- 第一場
- 通し稽古
- 休憩
- 衣裳合わせ
- 殺陣
- ダンス
- 振り返り

---

# Description

Timetable Itemには、
詳細情報を記載できる。

例：

- 稽古内容
- 注意事項
- 使用する場面
- 必要な道具
- 持ち物

Descriptionは、
Timetable Itemの補足情報として扱う。

---

# Order

Timetable Itemには、
表示順を設定する。

基本的にはStart Timeの順に表示する。

同時刻に複数Itemが存在する場合は、
Orderによって表示順を決定する。

Orderは、
時間情報とは別の表示上の順序を表す。

---

# Category

Timetable Itemには、
活動の種類を設定できる。

例えば、

- REHEARSAL
- BREAK
- MEETING
- SETUP
- TECHNICAL
- OTHER

など。

Categoryは、
表示や集計に利用できる。

Categoryは、
権限や参加区分を表すものではない。

---

# Venue

Timetable Itemには、
必要に応じて場所を設定できる。

例えば、

- 舞台
- 楽屋
- ロビー
- 稽古場
- 客席

など。

同じRehearsal内でも、
時間帯によって場所が変わる場合に対応する。

---

# Participants

Timetable Itemには、
対象となるPersonを設定できる。

例えば、

19:00〜20:00

第一場

対象：

キャストA
キャストB
キャストC

という指定ができる。

---

# Participant Type

個別Personだけでなく、
Participantの参加区分を対象条件として利用できる。

例えば、

Participant Type
  = CAST

とすることで、
キャスト全体を対象とできる。

スタッフを対象とする場合は、

Participant Type
  = STAFF

とする。

Participant Typeは、
Timetable Itemの対象者を決定するために利用する。

Participant Type自体は、
権限を付与するものではない。

---

# Participant and Timetable

Timetable Itemは、
Participantそのものを複製して保持しない。

ProductionのParticipantを参照して、
対象者を決定する。

Participant情報の正本は、
Participant Domainである。

Participantの名前や所属などを
Timetable Itemへ複製して保持しない。

---

# Role and Timetable

Roleは、
Timetableへのアクセスや操作権限を決定する。

RoleとParticipant Typeは、
異なる責務を持つ。

Role：

誰がTimetableを作成・変更・公開できるかを決定する。

Participant Type：

誰をTimetable Itemの対象者とするかを決定する。

例えば、

Role
  = Rehearsal Manager

によって、
Timetableを管理する権限を付与できる。

一方、

Participant Type
  = CAST

は、
そのTimetable Itemの対象者を
キャスト全体として指定するために利用する。

Participant Typeによって、
Timetable管理権限を付与してはならない。

---

# Authorization

Timetableの作成・変更・公開は、
稽古管理権限を持つPersonが行う。

権限はRoleを通じて管理する。

Organization管理者は、
自身のOrganizationについて
全権限を持つ。

Production Delegateとして、
稽古管理権限を委任することもできる。

Timetable Domain自身は、
RoleやPermissionの定義を管理しない。

権限の正本はRole / Permission Domainである。

---

# Optional Participants

Timetable Itemの対象者は、
必須ではない。

例えば、

「全体ミーティング」

のように全員を対象とするItemや、

「自主練習」

のように特定のPersonを指定しないItemも作成できる。

---

# Notes

Timetable Itemには、
自由記述のNoteを設定できる。

例えば、

- 台本○ページ
- 小道具持参
- 衣裳着用
- 音源使用
- 15分前集合

など。

---

# Rehearsal Relationship

Timetableは、
Rehearsalの詳細な進行を表す。

基本構造：

Rehearsal
  ↓
Timetable
  ↓
Timetable Item

Rehearsalの日時が変更された場合、
Timetableの時間情報との整合性を確認する。

Timetableは、
Rehearsalの日時そのものを正本として保持しない。

Rehearsalが、
稽古日時の正本である。

---

# Rehearsal as Source of Truth

Rehearsalは、
稽古の基本予定を管理する。

例えば、

- Date
- Start Time
- End Time
- Venue
- Rehearsal Type
- Status

など。

Timetableは、
Rehearsalに関連する詳細な進行を管理する。

そのため、

Rehearsal
  = 稽古予定の正本

Timetable
  = 稽古進行の正本

として責務を分離する。

---

# Production Activity

Productionに関する活動のうち、
Rehearsalとして管理されるものについては、
Timetableを利用できる。

例えば、

- 稽古
- 通し稽古
- 場当たり
- ゲネプロ

など。

本番期間の活動について、
Rehearsalでは表現できない要件が発生した場合は、
将来的に別のActivity Domainなどで管理する。

TimetableをProduction直下へ直接関連付ける構造は、
Version 2.0では採用しない。

---

# Publishing

Timetableは、
内部向け情報として管理する。

必要に応じて、
Productionのメンバーへ共有できる。

一般観客向けProduction Public Pageへ
自動公開するものではない。

---

# Internal Sharing

Timetableは、
内部共有機能と連携できる。

共有対象は、

- キャスト
- スタッフ
- 個別Person
- Production管理者

などから指定できる。

Participant Typeを
共有対象条件として利用できる。

共有対象の指定と、
Timetableの編集権限は別に管理する。

---

# Notification

Timetableが作成・変更された場合、
必要に応じて対象者へ通知できる。

通知機能そのものは、
Notification Domainの責務とする。

Timetable Domainは、
通知処理そのものを管理しない。

---

# Google Calendar

Timetableそのものを
Google CalendarのEventとして管理しない。

Google Calendar連携の正本はRehearsalである。

基本構造：

Rehearsal
  ↓
Google Calendar Event

Timetableは、
Rehearsalの詳細情報として
Calendar EventのDescriptionなどへ
反映できる。

---

# Calendar Update

Timetableが変更された場合、
必要に応じて連携済みGoogle Calendar Eventを
更新できる。

ただし、
Google Calendar側を正本にはしない。

StageArtのRehearsal / Timetableを正本とする。

Rehearsalの日時変更は、
Rehearsalを正本としてCalendarへ反映する。

Timetableの変更は、
必要に応じてCalendar Eventの詳細情報へ反映する。

---

# Status

Timetableは以下の状態を持つ。

- DRAFT
- PUBLISHED
- ARCHIVED

---

# Draft

DRAFTは、
作成中のTimetableを表す。

編集可能であり、
対象者への正式共有前の状態。

---

# Published

PUBLISHEDは、
確定したTimetableを表す。

対象者へ共有できる。

PUBLISHEDになった後も、
権限を持つPersonは必要に応じて変更できる。

変更した場合は、
必要に応じて再度共有・通知を行う。

---

# Archived

ARCHIVEDは、
過去のTimetableなど、
通常の編集対象から外した状態。

Timetableそのものは削除しない。

---

# Version

Timetableは、
変更履歴を管理できる構造を持つ。

例えば、

Version 1
  ↓
Version 2
  ↓
Version 3

という形で変更を追跡できる。

具体的なVersion管理方式は、
Data Modelで定義する。

---

# Audit

Timetableには、
変更を追跡できるよう監査情報を保持する。

基本情報：

- Created By
- Created At
- Updated By
- Updated At

---

# Template

将来的に、
TimetableをTemplateとして保存できる構造を持つ。

例えば、

「通常稽古」

Template：

19:00 集合
19:15 発声
19:30 稽古
20:30 休憩
20:45 稽古
21:45 振り返り
22:00 終了

Templateから新しいTimetableを作成できる。

ただし、
Template自体はVersion 2.0の必須機能ではない。

---

# Copy

既存Timetableを複製して、
別のRehearsalへ利用できる構造を持つ。

例えば、

8/20のTimetable

↓

8/21のTimetable

としてコピーし、
必要な部分だけ変更できる。

Copyによって、
元のTimetableを変更しない。

---

# Timetable Item Copy

Timetable全体だけでなく、
Timetable Itemを複製できる構造を持つ。

ただし、
CopyされたItemは新しいTimetable Itemとして扱う。

元のTimetable ItemとのIdentityを共有しない。

---

# Business Rules

- TimetableはRehearsalの詳細な進行を管理する。
- Timetableの基本的な親DomainはRehearsalである。
- Timetableが存在しないRehearsalも許可する。
- Timetableは複数のTimetable Itemから構成される。
- Timetable Itemは時間帯ごとの活動を表す。
- Timetable ItemはStart TimeとEnd Timeを持つ。
- Timetable ItemはTitleを持つ。
- Timetable ItemはDescriptionを持つ。
- Timetable Itemは表示順を持つ。
- Timetable ItemにはCategoryを設定できる。
- Timetable Itemには場所を設定できる。
- Timetable Itemには対象者を設定できる。
- Participant Typeを対象者条件として利用できる。
- 個別Personを対象者として指定できる。
- Participant情報をTimetable内へ複製しない。
- Participantの正本はParticipant Domainである。
- Participant Typeは対象者を決定するために利用する。
- Participant Typeは権限を付与しない。
- RoleはTimetableの操作権限を決定する。
- Timetable DomainはRoleやPermissionを定義しない。
- Organization管理者は自身のOrganizationについて全権限を持つ。
- Production Delegateとして稽古管理権限を委任できる。
- Timetableは内部共有を基本とする。
- Timetableを一般観客向けに自動公開しない。
- Timetableの共有対象と編集権限を分離する。
- TimetableはGoogle Calendar Eventそのものではない。
- Google Calendar連携の正本はRehearsalである。
- Google Calendar側をStageArtの正本としない。
- Timetable変更時には必要に応じてCalendar Eventを更新できる。
- TimetableはDRAFT / PUBLISHED / ARCHIVEDの状態を持つ。
- Timetableは原則として物理削除しない。
- Timetableには監査情報を保持する。
- Timetable TemplateはVersion 2.0では必須ではない。
- Timetable Copyでは元のTimetableを変更しない。
- Timetable ItemをCopyした場合は新しいIdentityを持つ。

---

# Domain Events

Timetableに関する主なDomain Event：

- TimetableCreated
- TimetableUpdated
- TimetablePublished
- TimetableArchived

Timetable変更によって、
必要に応じてNotificationや
External Calendar Integrationを実行できる。

Timetable Domain自身が、
NotificationやGoogle Calendarの
処理を直接管理することはない。

---

# Design Decisions

Timetableは、
Rehearsalそのものではなく、
Rehearsalの詳細な進行内容を管理する。

Rehearsalは、

「いつ、どこで、何をする予定か」

を表す。

Timetableは、

「その時間の中で、何を、どの順番で行うか」

を表す。

この2つを分離する。

Timetableの基本的な親はRehearsalとする。

Productionから直接Timetableを参照する
独立したProduction Timetableは、
Version 2.0では採用しない。

本番期間など、
Rehearsalでは表現できない活動については、
将来的に別のActivity Domainなどで拡張する。

Participant Typeは、
Timetable Itemの対象者指定に利用する。

Roleは、
Timetableを操作できる主体の権限管理に利用する。

Participant TypeとRoleを混同しない。

Google Calendarの正本はRehearsalであり、
TimetableはCalendar Eventへ反映される
詳細情報として扱う。

Timetableの共有対象と、
Timetableの編集権限は分離する。

---

# Future

将来的に以下へ対応できる。

- Timetable Template
- Timetable Copy
- Version管理
- 自動時間計算
- 参加者別表示
- キャスト別スケジュール
- スタッフ別スケジュール
- 本番進行専用Activity
- 小屋入り専用Activity
- 搬入・仕込み管理
- 場当たり管理
- ゲネプロ管理
- 本番進行管理
- PDF出力
- 印刷用タイムテーブル
- Google Calendarへの詳細反映

将来、
本番期間の進行管理を追加する場合も、
RehearsalとTimetableの責務を混同しない。

---

# Design Principles

- TimetableはRehearsalの詳細な進行を表す。
- RehearsalとTimetableを分離する。
- Rehearsalは稽古予定の正本である。
- Timetableは稽古進行の正本である。
- Timetableの基本的な親はRehearsalである。
- TimetableをProduction直下の独立予定表として扱わない。
- Timetableは複数のTimetable Itemから構成される。
- Timetable Itemは時間帯ごとの活動を表す。
- Participant情報をTimetable内へ複製しない。
- Participantの正本はParticipant Domainである。
- Participant Typeは対象者指定に利用する。
- Participant Typeは権限を付与しない。
- RoleはTimetableの操作権限を管理する。
- Timetable DomainはRoleやPermissionを定義しない。
- Timetableの共有対象と編集権限を分離する。
- Timetableは内部共有を基本とする。
- Timetableを一般観客向けに自動公開しない。
- Google Calendar EventはTimetableの正本ではない。
- Google Calendar連携の正本はRehearsalである。
- Timetable変更を必要に応じてCalendarへ反映する。
- TimetableはDRAFT / PUBLISHED / ARCHIVEDで管理する。
- Timetableは原則として物理削除しない。
- Blueprintを唯一の設計基準とする。