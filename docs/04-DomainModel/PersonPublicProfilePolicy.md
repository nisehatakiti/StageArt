# StageArt Blueprint

# Domain Model : Person Public Profile Policy

Version : 1.1

---

# Purpose

Person Public Profileは、StageArt上の個人が自身の舞台活動プロフィールを公開するための仕様である。

Personは役者・スタッフ・演出・制作その他の舞台芸術関係者を区別せず、一つのBusiness Identityとして扱う既存Person設計を前提とする。

公開プロフィールはOrganizationのMemberページとは独立し、Person本人を主体とする。

---

# Public Profile Identity

個人公開ページでは、Personの名前を最も主要な情報として表示する。

Person Public Profileには、OrganizationやProductionのような公開用Slugを持たせない。公開URL上ではPerson IDを利用し、個人ごとのSlug管理・一意性管理を行わない。

承認済みのOrganization Membershipが存在し、公開対象として扱える場合は所属Organization名を名前と併記できる。

本Blueprintでは複数Organizationへの同時所属表示を考慮しない。表示上の所属は単一Organizationを基本とする。

所属がないPersonは名前のみを表示できる。

Organization側のMembership変更によって、過去のProduction Participantの所属表示を遡及変更してはならない。

---

# Profile Information

Person本人は、以下のプロフィール情報を任意で登録できる。

- Profile Image
- Biography / Self Introduction
- Date of Birth
- Age
- Birthplace
- Height
- Weight
- BWH
- Foot Size
- Special Skills
- Qualifications

将来必要となるプロフィール項目の追加を妨げない。

各項目は未入力でもよい。

---

# Public / Private Control

プロフィール情報は「入力できること」と「公開すること」を分離する。

Person本人は、各プロフィール項目について公開・非公開を選択できる。

例えば、Date of Birthを非公開としAgeのみ公開すること、Profile Imageのみ公開すること、Heightは公開しWeightは非公開とすることなどを可能とする。

一般公開ページには、本人が公開対象として設定した情報のみを表示する。

Organizationは、Person本人のプロフィール項目の公開設定を変更してはならない。

Membershipの承認・管理とPerson自身のProfile Public Settingsは別責務として扱う。

---

# Age and Date of Birth

Date of Birthを正本情報とし、Ageを独立した正本データとして二重管理しない。

Ageを表示する場合はDate of Birthおよび現在日時から算出する。

Date of BirthとAgeは、それぞれ独立した公開・非公開設定を持つことができる。

---

# Participation History

個人公開ページには、そのPersonの出演・スタッフ参加履歴を一覧表示できる。

対象には少なくとも以下を含む。

- CAST
- STAFF
- DIRECTOR
- PRODUCER
- ORGANIZER
- その他Productionへの参加区分

役者だけでなく、スタッフとしての舞台活動履歴も同じ個人ページで扱う。

履歴についてはPerson本人が追加・編集できる。

---

# StageArt Production History

StageArt上に存在するProductionへの参加履歴は、Production Participantを正本とする。

Person本人が既存Productionを自身の履歴へ追加したい場合、Production管理者へ自身がどのParticipant / Personに該当するかを申告し、Production管理者の承認を必要とする。

本人の自己申告だけで既存Productionの公式Participantを変更してはならない。

承認後は既存のProduction Participantとの関係を利用して、個人の参加履歴として表示できる。

---

# Historical Activities Outside StageArt

StageArt導入前など、StageArt上にProductionが存在しない活動については、Person本人がHistoricalActivityとして登録できる。

StageArt外の活動について、StageArtが外部事実を自動的に検証することは要求しない。

本人が登録したHistoricalActivityと、StageArt上のProduction Participantに基づく公式なParticipation Historyは区別する。

---

# Organization Membership Display

現在の所属Organizationは、承認済みのMembershipを基準とする。

本Blueprintでは複数Organization所属を考慮しない。

所属が存在しない場合は所属名を表示しない。

Organization MembershipのStatusや内部Role・Permissionなどの内部情報を一般公開してはならない。

退団等によって現在のMembershipが終了した場合でも、過去Productionにおける所属表示はその公演時点のスナップショットとして保持する。

---

# Relationship with Organization Member Page

Organization Member Pageは現在のOrganization Membershipを基準とする。

Organization管理者はMemberページ上のメンバー表示順を変更できる。並び順はOrganizationごとに管理し、自動的な入団順等を必須ルールとしない。

Person Public ProfileはPerson本人を基準とする。

MemberページからPerson Public Profileへは、StageArt上でPersonとの確実な紐付けが存在する場合にリンクできる。

Personとの紐付けが存在しない表示対象者については、名前のみを表示し、個人ページへのリンクを必須としない。

プロフィール画像が存在しない場合、Memberページ等では名前のみを表示する。

Organization Member PageとPerson Public Profileの責務を混在させない。

---

# Relationship with Production Participant

Production Participantは、その公演への参加Factを表す。

ProductionのMember / Cast / Staff表示では、対象Personに利用可能なバストアップ画像がある場合、そのThumbnailを標準表示できる。画像がない場合は名前のみを表示する。

Person Public ProfileのParticipation Historyは、StageArt上のProductionについてはParticipantを参照して表示する。

Participant登録だけでPersonのOrganization Membershipを自動作成・変更してはならない。

また、Membershipの変更だけで過去ProductionのParticipant表示を遡及変更してはならない。

Production Participantに表示される所属は、公演時点の承認済みMembership等に基づくスナップショットとして扱い、現在の所属変更によって過去公演表示を書き換えない。

---

# Privacy

Date of Birth、Weight、Contact Information等の個人情報を、本人の公開設定なしに一般公開してはならない。

Public Profileで公開する情報と、StageArt内部でのみ利用するBusiness / Contact Informationを分離する。

本人が非公開を選択した情報は、一般公開ページに表示しない。

---

# Business Rules

1. 個人公開ページの主役はPersonの名前である。
2. Person Public Profileには公開用Slugを持たせず、Person IDを公開URL上の識別子として利用する。
3. 承認済み所属がある場合は所属Organization名を表示できる。
4. 本Blueprintでは複数Organization所属を考慮しない。
5. Organization Member Pageの並び順はOrganization管理者が変更できる。
6. プロフィール項目は任意入力とする。
7. プロフィール項目ごとに本人が公開・非公開を選択できる。
8. OrganizationはPersonのプロフィール公開設定を変更しない。
9. Date of Birthを正本とし、Ageを二重管理しない。
10. StageArt上の出演・スタッフ参加履歴はProduction Participantを正本とする。
11. 既存Productionの参加履歴を本人が追加する場合はProduction管理者の承認を必要とする。
12. StageArt外の過去活動は本人申告のHistoricalActivityとして登録できる。
13. 本人申告のHistoricalActivityとStageArt上の公式Participation Historyを混同しない。
14. Membershipの変更によって過去Productionの所属表示を遡及変更しない。
15. 画像がないMember / Participantは名前のみを表示する。
16. MemberページからPerson Public Profileへのリンクは、Personとの確実な紐付けがある場合に限り提供する。
17. 内部権限・Membership管理情報を一般公開しない。

---

# Design Principle

Person Public Profileは、劇団のMember紹介ページではなく、個人自身の舞台活動プロフィールとして設計する。

これにより、所属劇団が変わった場合、プロデュース公演・客演・フリーランスとして活動する場合でも、一人のPersonとして継続的な活動履歴を保持できる。

個人ページのURL管理を簡素化するため、Person固有のPublic Slugは設けずPerson IDを利用する。
