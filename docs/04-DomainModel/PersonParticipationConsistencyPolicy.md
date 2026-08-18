# StageArt Blueprint

# Domain Consistency Policy : Person / Membership / Participant / Profile

Version : 1.0

---

# Purpose

Person、Membership、Participant、Profileについて、現在までに確定したStageArt仕様との整合性を定義する。

---

# Identity Boundary

```text
UserAccount
    ↓
Person
    ├─ Profile
    ├─ Membership
    ├─ Participant
    └─ Reservation Booker
```

UserAccountは認証、Personは個人Business Identityとして扱う。

PersonにSlugは持たせない。

個人ページはPersonを直接識別する内部ID等で管理し、公開URLにPerson Slugを要求しない。

---

# Organization Membership

MembershipはPersonとOrganizationの所属関係を表す。

Organizationから承認されたMembershipをもって、PersonのOrganization所属を確定する。

Personが複数Organizationに所属する場合をDomain上は許容する。

ただし、現行StageArtの団体Member表示では複数所属を特別扱いせず、必要な所属情報を表示する基本仕様とする。

---

# Production Participant

ParticipantはProductionへの参加Factであり、Organization Membershipとは別の概念である。

一つのProductionには、Organization所属者だけでなく外部Personも参加できる。

そのため、Production ParticipantをOrganization Membershipの単純な一覧として扱わない。

---

# Participant Affiliation Display

ProductionのMember表示では、Participantの時点における所属情報をSnapshotとして扱う。

所属があるPerson：

```text
名前（所属）
```

所属がないPerson：

```text
名前
```

と表示する。

Production参加時点で所属していたOrganizationを基準とし、後日の退団・所属変更によって過去Productionの表示を自動変更しない。

退団者は、そのProduction時点の表記を維持する。

---

# Membership Approval

Organization Membershipの所属情報は、本人が一方的にOrganization所属として確定させるのではなく、Organization側の承認を経て確定する。

これにより、Production Member表示に利用する所属情報の信頼性を確保する。

---

# Existing StageArt Production History Claim

Person自身が、StageArt上に既に存在するProductionを出演・スタッフ参加履歴として追加したい場合、Production管理者へ自分がどのParticipantに該当するかを申告する。

Production管理者の承認を経て、本人の履歴として関連付ける。

本人が自由に既存ProductionのParticipantを自己認定してはならない。

---

# Personal Activity History

Personページでは、StageArt上のProduction参加履歴を一覧表示できる。

対象は出演だけではなく、スタッフ参加を含む。

既存StageArt Productionからの履歴はParticipant Factを基礎とし、本人による申告・管理者承認を経て本人との関連を確定する。

StageArt登録以前の活動については、Person自身がHistoricalActivityとして登録できる。

---

# Profile

PersonにはProfileを1つ持たせる。

Person自身がProfileを編集できる。

Profileでは、入力された項目ごとに公開／非公開を選択できるものとする。

---

# Profile Fields

以下の情報を登録可能とする。

- 名前
- 所属
- プロフィール写真
- 年齢
- 生年月日
- 出身地
- 身長
- 体重
- BWH
- 足のサイズ
- 特技
- 資格
- 自己紹介等のプロフィール情報

未入力項目は表示しない。

公開設定が非公開の項目は一般公開しない。

---

# Profile Images

Person Profileでは以下の画像を登録できる。

- バストアップ
- 全身

画像アップロード時はStageArtの共通画像正規化ルールに従う。

原画像の長辺を1600pxに調整した保存用画像と、長辺600pxのサムネイルを生成する。

画像が存在しない場合、Member表示では画像を表示せず、名前を表示する。

Production Memberページ等では、バストアップ画像の縮小版を優先して表示する。

---

# Personal Page

Personページでは、名前を主要表示項目とする。

所属がある場合は所属を表示する。

個人ページには、Productionへの出演・スタッフ参加履歴を一覧表示する。

個人ページのURL設計にPerson Slugは使用しない。

---

# Profile and Production Member Separation

Person Profileは現在の本人情報であり、Production Participant表示はProduction参加時点の情報を基準とする。

したがって、Personが後から所属を変更しても、過去ProductionのMember表示を現在のProfileから再構成してはならない。

---

# Member Ordering

Production Memberページの並び順は、Production管理者が変更できる。

Personページの履歴についても、必要な表示順を個人側で管理できるものとする。

並び順はPersonのIdentityとは独立したPresentation情報として扱う。

---

# Participant Management

ProductionのMemberは、Production管理者が後から追加・変更・削除できる。

公演作成Wizardで登録したMemberは初期設定であり、Productionのライフサイクルを通じて変更可能とする。

代理人等のProduction管理上の設定も、Participant / Production Managementの仕様に従って管理する。

---

# Production Manager Requirement

Production管理者はStageArt Accountを持つUserに限る。

Personとして登録されているだけではProduction Managerになれない。

---

# Design Principle

```text
UserAccount = 認証
Person = 個人Identity
Membership = 団体所属
Participant = 公演参加
Profile = 現在の個人プロフィール
HistoricalActivity = StageArt外を含む本人申告履歴
History = StageArt上のFactから生成される履歴
```

現在のPerson情報と、過去Production時点の参加・所属情報を混同しない。

Organizationの承認を経たMembershipを所属情報の基礎とし、Production Participantには参加時点の表示Snapshotを保持する。
