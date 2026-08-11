# StageArt Blueprint

# Domain Model : Profile

Version : 1.0

---

# Purpose

Profileは、
PersonがStageArt上で公開・共有する
プロフィール情報を管理するDomainである。

ProfileはPersonに属する。

Profileには、
本人が自由に入力・編集できる情報を保持する。

また、
StageArt外で過去に行った活動実績など、
StageArt上のFactから自動生成されない情報については、
HistoricalActivityとして管理する。

---

# Concept

基本構造：

Person
  ↓
Profile
  ├── Biography
  ├── Profile Image
  ├── Website
  ├── SNS
  └── HistoricalActivity[]

Profileは、
Person自身が作成・編集できる。

---

# Ownership

Profileは一つのPersonに属する。

Person
  ↓
Profile

一つのPersonに対して、
Profileは一つとする。

ProfileはOrganizationには所属しない。

---

# Person Relationship

ProfileはPersonのプロフィール情報を表す。

PersonはBusiness Identityを表し、
ProfileはPersonが公開・共有する情報を表す。

PersonのIdentityそのものを
Profileで管理しない。

---

# Editable By Person

Profileは、
Person本人が作成・編集できる。

本人による編集を前提とする。

Organizationの管理者が
PersonのProfileを自由に編集する仕組みは
Profile Domainでは定義しない。

Organizationから参照されるプロフィール情報については、
別途公開・権限ルールに従う。

---

# Biography

Biographyは、
Person本人が入力する自己紹介文である。

例えば、

- 自己紹介
- 舞台活動について
- 得意分野
- その他本人が紹介したい内容

などを記載できる。

Biographyは自由記述を基本とする。

---

# Profile Image

Profile Imageは、
Personのプロフィール画像を表す。

画像データそのものを
Profile Domainで管理するとは限らない。

Profileは、
画像を参照するための情報を保持する。

具体的な画像ストレージや
アップロード処理はInfrastructure / Media Domainで扱う。

---

# Website

Websiteは、
Person本人が登録する
外部Webサイトへの参照情報である。

例えば、

- 個人サイト
- ポートフォリオ
- 所属サイト

など。

WebsiteはProfile情報として保持する。

---

# SNS

SNSは、
Person本人が登録する
外部SNSへの参照情報である。

例えば、

- X
- Instagram
- Facebook
- YouTube
- その他SNS

など。

具体的なSNSサービスを
Profile Domainが限定する必要はない。

---

# HistoricalActivity

HistoricalActivityは、
StageArt上でFactとして記録されていない
Person本人の過去の活動実績を管理する。

例えば、

- StageArt登録前の出演歴
- StageArt登録前のスタッフ歴
- 過去の演出歴
- 過去の制作歴
- その他本人が紹介したい活動実績

など。

HistoricalActivityはProfileの子Entityとして管理する。

---

# HistoricalActivity Relationship

基本構造：

Person
  ↓
Profile
  ↓
HistoricalActivity[]

HistoricalActivityは
Profileから独立した活動Factではない。

Profileに登録された
本人申告の過去実績である。

HistoricalActivityの詳細な構造・ルールは、
HistoricalActivity Domainで定義する。

---

# History Relationship

HistoryとHistoricalActivityは、
異なる概念として扱う。

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

本人が入力する過去実績

Profile
  ↓
HistoricalActivity

---

# History and Profile

Profileは、
StageArt上で自動生成されるHistoryを
直接保持しない。

Personのプロフィール画面で
Historyを表示する場合は、
History Domainから取得して表示する。

例えば、

Profile
  ↓
StageArt Activity
  ↓
History

という表示は可能である。

ただし、
HistoryそのものをProfileの子Entityとして
複製・保存しない。

---

# HistoricalActivity and History

HistoricalActivityとHistoryは、
表示上の構造を揃えることができる。

例えば、

HistoricalActivity
  ├── HistoryType
  ├── ParticipantType
  ├── Production
  ├── Performance
  └── EventDateTime

History
  ├── HistoryType
  ├── ParticipantType
  ├── Production
  ├── Performance
  └── EventDateTime

という構造を基本とする。

ただし、
HistoricalActivityは本人入力データであり、
HistoryはStageArt上のFactから生成されたデータである。

両者を同一Entityとして扱わない。

---

# Public Profile

Profileは、
公開プロフィールとして利用できる。

ただし、
すべてのProfile情報を
常に公開する必要はない。

公開範囲は、
ProfileおよびStageArtの公開ルールに従う。

---

# Profile Visibility

Profile情報には、
必要に応じて公開範囲を設定できる。

例えば、

- PUBLIC
- PRIVATE

など。

具体的なVisibilityの仕様は、
公開設定Domain / APIで定義する。

Profile Domainは、
公開範囲という概念を保持できる構造を持つ。

---

# Personal Information

Profileには、
Person本人が公開したい情報のみを登録する。

連絡先などの個人情報を
Profileに無制限に保存しない。

Contact Informationなどの情報は、
Person Domainまたは
適切なContact Domainで管理する。

---

# Organization

ProfileはOrganizationに所属しない。

Personが複数Organizationに所属していても、
ProfileはPerson単位で一つである。

例えば、

Person A
  ↓
Profile

Person A
  ├── Membership → Organization A
  └── Membership → Organization B

という構造。

Organizationごとに異なるProfileを
作成しない。

---

# Membership Relationship

Membershipは、
PersonとOrganizationの所属関係を表す。

ProfileはMembershipとは
別の責務を持つ。

Membership：

「このPersonがこのOrganizationに所属している」

Profile：

「このPersonが自分について紹介する」

という違いである。

---

# Participant Relationship

Participantは、
PersonとProductionの参加関係を表す。

ProfileはParticipantを
直接管理しない。

StageArt上の出演・スタッフ実績は、

Participant
  ↓
History

として管理する。

StageArt登録前などの過去実績は、

Profile
  ↓
HistoricalActivity

として本人が登録する。

---

# Audience Relationship

Audienceとしての観劇実績は、
Profileに直接入力しない。

StageArt上で予約し、
Check Inが完了した場合、

Reservation
  ↓
CheckInCompleted
  ↓
History
  ↓
Audience History

として管理する。

Profileでは、
そのHistoryを表示することができる。

---

# Editing

PersonはProfileを編集できる。

編集対象には、

- Biography
- Profile Image
- Website
- SNS
- HistoricalActivity

などを含む。

編集操作によって、
History DomainのFactを変更してはならない。

---

# HistoricalActivity Editing

HistoricalActivityは、
Profileの一部として本人が
追加・変更・削除できる。

HistoricalActivityは、
本人申告情報であるため、
ParticipantやReservationなどの
StageArt上のFactとは異なる扱いとする。

---

# HistoricalActivity Deletion

HistoricalActivityは、
本人によって削除できる。

HistoricalActivityの削除は、
History DomainのHistoryには影響しない。

---

# Profile Deletion

PersonがProfileを削除する場合でも、
Person Identityそのものを削除するとは限らない。

Profileの削除・非公開化については、
Person StatusやProfile Visibilityの
ルールに従う。

HistoricalActivityについても、
Profileの状態に従う。

---

# Audit

Profileには、
変更を追跡できるよう
監査情報を保持する。

基本情報：

- Created At
- Updated At

必要に応じて、

- Created By
- Updated By

を保持する。

Profileは本人編集を基本とするため、
変更主体を記録できる構造を持つ。

---

# Domain Events

Profileに関する主なDomain Event：

- ProfileCreated
- ProfileUpdated
- ProfileArchived

HistoricalActivityについては、
必要に応じて以下を発行できる。

- HistoricalActivityAdded
- HistoricalActivityUpdated
- HistoricalActivityRemoved

具体的なEventの利用範囲は、
Domain Event設計で定義する。

---

# Business Rules

- ProfileはPersonに属する。
- 一つのPersonにProfileは一つとする。
- ProfileはOrganizationに所属しない。
- ProfileはPerson本人が作成・編集できる。
- Profileには本人が入力するプロフィール情報を保持する。
- Biographyは自由記述を基本とする。
- Profile Imageは画像参照情報として管理できる。
- Websiteを登録できる。
- SNSを登録できる。
- HistoricalActivityをProfileの子Entityとして保持できる。
- HistoricalActivityは本人申告の過去実績である。
- HistoricalActivityはStageArt上の正式なFactではない。
- StageArt上の活動実績はHistoryで管理する。
- Participantから生成される活動履歴をProfileへ複製しない。
- Reservationから生成される観劇履歴をProfileへ複製しない。
- CheckInCompletedから生成されるAudience HistoryをProfileへ複製しない。
- Profile画面からHistoryを参照・表示できる。
- HistoricalActivityとHistoryは別Entityとして管理する。
- HistoricalActivityはHistoryと基本構造を揃えることができる。
- HistoricalActivityは本人が追加・変更・削除できる。
- HistoricalActivityの変更はHistoryに影響しない。
- Historyの変更はHistoricalActivityに影響しない。
- ProfileはPerson単位で管理する。
- OrganizationごとにProfileを作成しない。
- MembershipとProfileは別の責務を持つ。
- ParticipantとProfileは別の責務を持つ。
- Audience HistoryをProfileへ直接入力しない。
- Profile情報には公開範囲を設定できる構造を持つ。
- Profileに不要な個人情報を無制限に保存しない。
- Profileには監査情報を保持する。

---

# Design Decisions

Profileは、
Personが自身を紹介するための
プロフィール情報を管理する。

ProfileはPersonに属するが、
OrganizationやProductionには所属しない。

Profileに含まれる情報は、
本人が自由に入力・編集できる。

一方、
StageArt上で実際に発生した活動については、
Profileへ直接入力させず、
各Domainで発生したFactからHistoryを生成する。

したがって、

本人申告の過去実績：

Profile
  ↓
HistoricalActivity

StageArt上の正式な活動履歴：

Participant / Reservation / Check In
  ↓
History

という責務分離を行う。

HistoricalActivityは、
Historyと同じ基本構造を利用することで、
Profile上で本人申告の過去実績と
StageArt上の正式な活動履歴を
同じ形式で表示できる。

ただし、
HistoricalActivityとHistoryは
同一Entityにはしない。

---

# Design Principles

- ProfileはPersonのプロフィール情報を表す。
- ProfileはPersonに属する。
- ProfileはOrganizationに属さない。
- ProfileはPerson本人が編集できる。
- Profileは本人が紹介したい情報を保持する。
- Biographyなどの基本情報は自由記述を基本とする。
- HistoricalActivityはProfileの子Entityである。
- HistoricalActivityは本人申告の過去実績を表す。
- HistoryはStageArt上で発生したFact由来の活動履歴である。
- HistoricalActivityとHistoryを混同しない。
- StageArt上のFactをProfileへ複製しない。
- ProfileからHistoryを参照・表示できる。
- HistoricalActivityはHistoryと基本構造を揃える。
- HistoricalActivityは本人が編集できる。
- HistoricalActivityの変更はHistoryに影響しない。
- Historyの変更はHistoricalActivityに影響しない。
- ProfileとMembershipを分離する。
- ProfileとParticipantを分離する。
- ProfileとReservationを分離する。
- ProfileとHistoryを分離する。
- Profileの公開範囲を適切に制御する。
- Blueprintを唯一の設計基準とする。
