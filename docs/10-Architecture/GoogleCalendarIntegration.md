# StageArt Blueprint

# Google Calendar Integration

Version : 1.0

---

# Purpose

StageArtで管理するRehearsal / 稽古スケジュールを、利用者自身のGoogle Calendarへ登録できるようにするためのExternal Integration方針を定義する。

Google CalendarはStageArtのスケジュール正本ではない。

StageArtのRehearsalを正本とし、Google Calendarは利用者個人の予定管理を補助するExternal Calendarとして扱う。

---

# Source of Truth

StageArt
    ↓
Rehearsal
    ↓
Google Calendar

StageArtが稽古日時・場所・内容等の正本を保持する。

Google Calendar側に登録されたEventをStageArtのSchedule Factとして扱わない。

Google Calendar側で直接変更された内容をStageArtへ自動反映することは、今回の基本要件としない。

---

# Primary Use Case

利用者がStageArt上で複数のRehearsalを選択し、Google Calendarへ一括登録できること。

例：

☑ 9/10 18:00–21:00 稽古
☑ 9/12 13:00–17:00 稽古
☐ 9/15 18:00–21:00 稽古
☑ 9/17 18:00–21:00 稽古

「選択した予定をGoogle Calendarに登録」

という一つの操作で、選択した複数のRehearsalをGoogle Calendarへ登録する。

---

# Individual Calendar

基本対象は、操作を行ったPerson自身が利用するGoogle Calendarとする。

StageArtがProduction関係者全員のGoogle Calendarを一括操作することを基本仕様としない。

例えば、

Person A
    ↓
自分が参加するRehearsalを選択
    ↓
自分のGoogle Calendarへ登録

Person B
    ↓
自分が参加するRehearsalを選択
    ↓
自分のGoogle Calendarへ登録

という利用を想定する。

---

# Selection

複数のRehearsalを一覧上で選択し、一括登録できることを要求する。

全てのRehearsalを一括登録することも、必要なRehearsalだけを選択することもできるUIを想定する。

既にGoogle Calendarへ登録済みのRehearsalについては、重複登録を避けるための識別方法を実装時に設計する。

---

# Google OAuth

初回利用時にGoogle OAuthを利用してGoogle Calendarへのアクセス許可を取得する。

Google固有のOAuth処理はDomain Layerへ持ち込まず、External Integration / Infrastructure Layerで扱う。

取得する権限は、Google Calendar連携に必要な最小限のScopeとする。

Googleアカウントの認証Identityと、StageArtのPerson Identityは同一概念として扱わない。

---

# Event Mapping

StageArtのRehearsalから、Google Calendar Eventを生成する。

基本的なMapping：

| StageArt | Google Calendar |
|---|---|
| Rehearsal Title | Event Summary |
| Start DateTime | Event Start |
| End DateTime | Event End |
| Location | Event Location |
| Description | Event Description |
| Online URL | Event Conference / Description等 |

具体的なGoogle Calendar APIフィールドへのMappingはInfrastructure Layerで定義する。

---

# Event Title

Google Calendar側のEventタイトルは、StageArt上のRehearsal情報を識別できるものとする。

例：

【StageArt】○○公演 稽古

具体的なタイトルフォーマットは実装時に決定する。

---

# Event Description

必要に応じて以下の情報をDescriptionへ含められる構造とする。

- Production名
- Rehearsal内容
- StageArt上の関連情報
- StageArtへの参照情報
- 注意事項

StageArtの内部情報を必要以上に外部Calendarへ複製しない。

---

# Location

RehearsalのLocationをGoogle Calendar EventのLocationへ反映する。

オンラインRehearsalの場合は、Online URLを必要に応じてEventへ反映する。

---

# Rehearsal Update

StageArt上でRehearsalの日時・場所・内容等が変更された場合、既にGoogle Calendarへ登録されたEventを更新できる構造を将来的に持つ。

この更新はStageArtを正本として行う。

Google Calendar側で直接変更された内容をStageArtへ逆同期しない。

---

# Rehearsal Cancellation

CANCELLEDとなったRehearsalについて、既にGoogle Calendarへ登録済みのEventを更新または削除できる構造を持つ。

Google Calendar側のEventを削除するか、キャンセル状態として残すかは実装時に決定する。

---

# Synchronization Level

初期実装では、以下を優先する。

1. StageArtからGoogle Calendarへの手動一括登録
2. 登録済みEventの識別
3. StageArtからGoogle Calendarへの更新
4. CANCELLED時のCalendar Event処理

Google CalendarからStageArtへの双方向同期は初期実装の対象外とする。

---

# Notification / Invitation

初期実装では、Google Calendarのattendeesを利用した関係者への招待を必須としない。

StageArtのParticipant / RehearsalAttendanceとGoogle Calendarの参加者情報を自動同期することは別途設計する。

StageArt内の通知・共有とGoogle Calendarの招待を混同しない。

---

# Security

Google OAuth Token等の認証情報は、通常のStageArt業務データと同じ方法で平文保存しない。

Google Calendarへのアクセス権は、操作対象となる利用者自身のCalendarに限定することを基本とする。

OAuth Scopeは必要最小限とする。

---

# Domain Boundary

Google CalendarはStageArtのDomainではない。

Rehearsal Domainは、Google Calendar APIそのものを知らず、Rehearsalの日時・場所・内容等の業務Factを提供する。

Google Calendar固有のEvent ID、OAuth Token、API Request等はExternal Integration / Infrastructure Layerで管理する。

---

# Business Rules

- RehearsalはStageArtのScheduleの正本である。
- Google Calendarは個人の予定管理を補助するExternal Calendarである。
- 複数のRehearsalを選択してGoogle Calendarへ一括登録できる。
- 基本対象は操作したPerson自身のGoogle Calendarである。
- Production関係者全員のCalendarをStageArtが一括操作することを基本としない。
- 初回利用時にGoogle OAuthによるCalendar権限を取得する。
- OAuth Scopeは必要最小限とする。
- Rehearsalの日時・場所・内容をGoogle Calendar Eventへ反映できる。
- StageArt側の変更を将来的にGoogle Calendarへ反映できる構造とする。
- Google Calendar側の直接変更をStageArtへ逆同期しないことを初期方針とする。
- Google Calendar EventをStageArtのSchedule Factとして扱わない。
- Google Calendarへの登録済みEventを識別し、重複登録を避けられる構造とする。
- Google Calendarへの関係者招待は初期実装の必須要件としない。

---

# Open Questions

- Google Calendar Eventとの関連をどのEntity / Referenceで保持するか。
- 複数Google Calendarを持つ利用者が登録先を選択できるようにするか。
- 登録済みEventを更新するタイミングとトリガー。
- CANCELLED時にEventを削除するか、キャンセルEventとして残すか。
- Google Calendar Eventの重複判定方式。
- Google CalendarのattendeesへParticipantを同期するか。
- OAuth Tokenの保存方式と再認証方式。
- StageArt Mobile / WebそれぞれでのGoogle OAuth UX。

---

# Scope

本書はGoogle CalendarとのExternal Integrationに関するBlueprint定義である。

Google Calendar APIの具体的な実装、OAuth実装、REST API、DB構造、UI実装は後続Phaseで設計・実装する。
