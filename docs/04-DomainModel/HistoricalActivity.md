# StageArt Blueprint
# Domain Model : HistoricalActivity

Version : 1.0

---

# Purpose

HistoricalActivityは、
PersonがProfileに登録する
過去の活動実績を管理する子Entityである。

HistoricalActivityは、
StageArt上で発生したFactから自動生成されるHistoryとは異なり、
Person本人が入力・編集する。

例えば、

- StageArt登録前の出演歴
- StageArt登録前のスタッフ歴
- 過去の演出歴
- 過去の制作歴
- その他本人が紹介したい活動実績

などを登録できる。

---

# Concept

基本構造：

Person
  ↓
Profile
  ↓
HistoricalActivity[]

HistoricalActivityはProfileに属する。

HistoricalActivityは、
Personの過去の活動実績を
本人申告情報として保持する。

---

# Relationship with History

HistoricalActivityとHistoryは、
似た構造を持つが異なるEntityである。

History：

StageArt上で発生したFactから
自動生成される正式な活動履歴。

HistoricalActivity：

Person本人がProfileへ登録する
過去の活動実績。

基本構造：

StageArt上の活動

Participant
  ↓
Domain Event
  ↓
History

本人申告の過去活動

Profile
  ↓
HistoricalActivity

HistoricalActivityをHistoryへ変換しない。

HistoryをHistoricalActivityへ複製しない。

---

# Ownership

HistoricalActivityは、
一つのProfileに属する。

Profile
  ↓
HistoricalActivity

HistoricalActivityは
Organizationに直接所属しない。

---

# Parent Profile

HistoricalActivityは、
必ずProfileを親として持つ。

Profile
  ↓
HistoricalActivity[]

一つのHistoricalActivityが
複数Profileに所属することはない。

---

# Identity

HistoricalActivityは、
HistoricalActivityIdによって
一意に識別する。

HistoricalActivityIdは変更しない。

---

# History Type

HistoricalActivityは、
Historyと同様にHistoryTypeを持つ。

基本的に以下を使用する。

- PARTICIPATION
- AUDIENCE

---

# PARTICIPATION

過去の舞台活動への参加実績を表す。

例えば、

- CAST
- STAFF
- DIRECTOR
- PRODUCER
- ORGANIZER
- SPONSOR
- SUPPORTER

など。

---

# AUDIENCE

過去に観客として
観劇した実績を表す。

ただし、
StageArt上で確認された観劇実績ではなく、
Person本人が入力した過去の観劇実績である。

---

# Participant Type

HistoricalActivityが
PARTICIPATIONの場合、
ParticipantTypeを保持できる。

例えば、

- CAST
- STAFF
- DIRECTOR
- PRODUCER
- ORGANIZER
- SPONSOR
- SUPPORTER

など。

HistoricalActivityでは、
本人申告による過去実績であるため、
StageArt上のParticipantとは直接関連付けない。

---

# Production

HistoricalActivityは、
活動対象となった作品・公演を表す情報を持つ。

ただし、
HistoricalActivityでは
StageArt上のProductionを必須参照とはしない。

StageArt登録前の活動では、
対象となるProductionが
StageArtに存在しない場合があるためである。

---

# Production Reference

対象となる作品がStageArt上に存在する場合、
Productionを参照できる。

例えば、

HistoricalActivity
  ↓
Production

という関連を持てる。

ただし、
Production Referenceは任意である。

---

# External Production Name

StageArtに存在しない過去作品については、
本人が作品名を入力できる。

例えば、

External Production Name
  = 「○○劇団公演『作品A』」

など。

これにより、
StageArt登録前の活動実績も
登録できる。

---

# Performance

HistoricalActivityは、
必要に応じてPerformance情報を持つ。

StageArt上にPerformanceが存在する場合は、
Performanceを参照できる。

ただし、
StageArt外の過去活動では
Performanceが存在しない場合がある。

そのため、
Performanceは任意とする。

---

# External Performance Information

StageArtに存在しない過去公演については、
本人が公演日時などを
入力できる。

例えば、

- Event Date Time
- Venue Name
- Performance Name

など。

具体的な項目は
Profile UI / Data Modelで定義する。

---

# Event Date Time

HistoricalActivityは、
活動が発生した日時を保持できる。

例えば、

2023-05-20 14:00

など。

日時が正確に分からない場合は、
年や年月などの
簡易的な情報を保持できる構造へ
将来的に拡張できる。

---

# Venue

HistoricalActivityは、
過去活動の会場情報を保持できる。

StageArtにVenueが存在する場合は、
Venueを参照できる。

StageArt外の活動の場合は、
会場名などを本人が入力できる。

---

# Description

HistoricalActivityには、
本人が補足情報を記載できる。

例えば、

- 役名
- 担当業務
- 公演概要
- その他の補足

など。

---

# Role

PARTICIPATIONの場合、
活動上の役割を記録できる。

例えば、

CAST
  Role
    = 主演

STAFF
  Role
    = 舞台監督

DIRECTOR
  Role
    = 演出

など。

RoleはParticipantTypeとは異なる。

ParticipantType：

活動上の大分類。

Role：

具体的な役割・担当。

---

# Organization

HistoricalActivityは、
Organizationを任意で参照できる。

StageArtに存在するOrganizationの場合は、
Organizationを参照できる。

StageArt外のOrganizationについては、
本人が名称を入力できる構造へ
将来的に対応できる。

---

# External Organization Name

StageArtに登録されていない
劇団・団体・制作団体などについては、
本人が名称を入力できる。

例えば、

External Organization Name
  = 「○○劇団」

など。

---

# Audience Historical Activity

過去の観劇実績を
本人が登録することもできる。

例えば、

HistoricalActivity

HistoryType
  = AUDIENCE

External Production Name
  = 「作品A」

Event Date Time
  = 2022-10-15

など。

この情報は、
StageArtのCheck In実績ではない。

---

# Audience and History

StageArt上の観劇実績：

Reservation
  ↓
CheckInCompleted
  ↓
History
  ↓
AUDIENCE

本人申告の過去観劇：

Profile
  ↓
HistoricalActivity
  ↓
AUDIENCE

この二つを混同しない。

---

# Editing

HistoricalActivityは、
Person本人が追加・編集・削除できる。

HistoricalActivityの編集は、
History Domainには影響しない。

History Domainの変更も、
HistoricalActivityには影響しない。

---

# Ordering

HistoricalActivityは、
Profile上で一覧表示できる。

基本的には、
Event Date Timeの新しい順に表示する。

ただし、
本人が表示順を設定できる構造へ
将来的に拡張できる。

---

# Visibility

HistoricalActivityは、
Profileの公開設定に従う。

Profileが公開されている場合でも、
HistoricalActivityを非公開にできる構造を
将来的に持てる。

Version 1.0では、
Profileの公開範囲を基本とする。

---

# Verification

HistoricalActivityは、
本人申告情報である。

StageArtは、
HistoricalActivityの内容を
自動的に正式な活動Factとして認定しない。

例えば、

HistoricalActivity
  = CAST

と登録されても、

Participant
  = CAST

は自動生成されない。

---

# No Automatic Conversion

HistoricalActivityから、

- Participant
- Reservation
- Check In
- History

を自動生成しない。

HistoricalActivityは、
Profile上の本人申告情報として完結する。

---

# Relationship with Participant

Participantは、
StageArt上のProductionへの参加Factを表す。

HistoricalActivityは、
本人が申告する過去活動を表す。

したがって、

Participant
  ≠
HistoricalActivity

である。

---

# Relationship with Reservation

Reservationは、
StageArt上のチケット予約Factを表す。

HistoricalActivityは、
本人申告の過去実績を表す。

HistoricalActivityから
Reservationを生成しない。

---

# Relationship with Check In

Check Inは、
StageArt上の来場Factを表す。

HistoricalActivityは、
本人申告の過去実績を表す。

HistoricalActivityから
Check Inを生成しない。

---

# Relationship with Profile

Profileは、
Personのプロフィール情報を管理する。

HistoricalActivityは、
Profileに含まれる過去活動実績である。

基本構造：

Profile
  ├── Biography
  ├── Profile Image
  ├── Website
  ├── SNS
  └── HistoricalActivity[]

---

# Audit

HistoricalActivityには、
変更を追跡できるよう
監査情報を保持する。

基本情報：

- Created At
- Updated At

必要に応じて、

- Created By
- Updated By

を保持する。

基本的にCreated By / Updated Byは
Profile本人となる。

---

# Deletion

HistoricalActivityは、
本人によって削除できる。

HistoricalActivityを削除しても、

- History
- Participant
- Production
- Performance

などのStageArt上のFactには
影響しない。

---

# Domain Events

HistoricalActivityに関する
主なEvent：

- HistoricalActivityAdded
- HistoricalActivityUpdated
- HistoricalActivityRemoved

これらは、
Profile内部の変更を
必要に応じて通知するために利用できる。

HistoricalActivityの変更によって、
StageArt上の正式な活動Factを
生成・変更しない。

---

# Business Rules

- HistoricalActivityはProfileの子Entityである。
- HistoricalActivityはPerson本人が入力する。
- HistoricalActivityはStageArt上の正式なFactではない。
- HistoricalActivityとHistoryは別Entityである。
- HistoricalActivityはHistoryと基本構造を揃える。
- HistoryTypeを持つ。
- HistoryTypeはPARTICIPATION / AUDIENCEを基本とする。
- PARTICIPATIONの場合、ParticipantTypeを保持できる。
- AUDIENCEの場合、ParticipantTypeは保持しない。
- Production Referenceは任意である。
- StageArtに存在しない過去作品も登録できる。
- StageArtに存在しない作品はExternal Production Nameで表現できる。
- Performance Referenceは任意である。
- StageArtに存在しない過去公演も登録できる。
- Event Date Timeを保持できる。
- Venueを参照できる。
- StageArtに存在しない会場は名称を入力できる。
- Organizationを参照できる。
- StageArtに存在しない団体は名称を入力できる。
- Descriptionを保持できる。
- PARTICIPATIONでは具体的なRoleを記録できる。
- ParticipantTypeとRoleを区別する。
- HistoricalActivityは本人が追加・変更・削除できる。
- HistoricalActivityの変更はHistoryに影響しない。
- Historyの変更はHistoricalActivityに影響しない。
- HistoricalActivityからParticipantを生成しない。
- HistoricalActivityからReservationを生成しない。
- HistoricalActivityからCheck Inを生成しない。
- HistoricalActivityからHistoryを生成しない。
- HistoricalActivityは本人申告情報として扱う。
- HistoricalActivityはProfileの公開設定に従う。
- HistoricalActivityには監査情報を保持する。
- HistoricalActivityの削除はStageArt上のFactに影響しない。

---

# Data Examples

## Historical Participation

{
  "historyType": "PARTICIPATION",
  "participantType": "CAST",
  "externalProductionName": "劇団○○『作品A』",
  "eventDateTime": "2023-05-20T14:00:00",
  "role": "主演",
  "externalOrganizationName": "劇団○○"
}

---

## Historical Staff Activity

{
  "historyType": "PARTICIPATION",
  "participantType": "STAFF",
  "externalProductionName": "作品B",
  "eventDateTime": "2022-09-10T18:00:00",
  "role": "舞台監督"
}

---

## Historical Audience Activity

{
  "historyType": "AUDIENCE",
  "externalProductionName": "作品C",
  "eventDateTime": "2021-11-03T14:00:00",
  "venueName": "○○劇場"
}

---

# Design Decisions

HistoricalActivityは、
Profileの子Entityとして管理する。

HistoricalActivityは、
Person本人が入力する過去活動実績である。

Historyと同じ基本構造を採用することで、
Profile上で本人申告の過去実績と
StageArt上の正式な活動履歴を
同じ形式で表示できる。

ただし、
HistoricalActivityとHistoryは
同一Entityにはしない。

HistoricalActivityには、
StageArt上に存在しない作品・公演・団体・会場を
登録できる必要がある。

そのため、
ProductionやPerformanceなどの
StageArt Entityへの参照を必須としない。

StageArt上のEntityが存在する場合は、
任意で参照できる。

HistoricalActivityは本人申告情報であり、
StageArtがその内容を
自動的に検証・認定するものではない。

---

# Design Principles

- HistoricalActivityはProfileの子Entityである。
- HistoricalActivityは本人申告の過去実績である。
- HistoricalActivityはHistoryではない。
- HistoricalActivityはHistoryと基本構造を揃える。
- HistoricalActivityはPerson本人が編集できる。
- HistoricalActivityはStageArt上の正式なFactではない。
- StageArt上の活動はHistoryで管理する。
- StageArt外の過去活動はHistoricalActivityで管理できる。
- StageArtに存在しない作品を登録できる。
- StageArtに存在しない公演を登録できる。
- StageArtに存在しない団体を登録できる。
- StageArtに存在しない会場を登録できる。
- Production / Performance / Organizationへの参照は任意とする。
- HistoryTypeはPARTICIPATION / AUDIENCEを基本とする。
- ParticipantTypeとRoleを区別する。
- HistoricalActivityからStageArt上のFactを生成しない。
- HistoricalActivityの変更はHistoryに影響しない。
- Historyの変更はHistoricalActivityに影響しない。
- 本人申告情報とStageArt上の正式Factを明確に区別する。
- Blueprintを唯一の設計基準とする。
