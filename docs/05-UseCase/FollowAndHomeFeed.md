# StageArt Blueprint

# Use Case : Follow and Home Feed

Version : 1.0
Status : Confirmed

---

# Follow Organization

## Actor

Person

## Precondition

対象Organizationの公開ページを閲覧できること。

## Flow

```text
Organization Public Profile
↓
Follow Organization
↓
OrganizationFollow作成
↓
Person HomeのFollow対象に追加
```

## Result

Follow後に公開される新しいProduction / 公演情報を、Person HomeのFollow新着として取得できる。

---

# Unfollow Organization

## Actor

Person

## Flow

```text
Organization Public Profile / Follow一覧
↓
Unfollow Organization
↓
OrganizationFollowを終了または無効化
↓
今後のFollow新着対象から除外
```

既存のMembershipやParticipantには影響しない。

---

# Publish Production to Followers

## Actor

Organization管理者またはProduction公開権限を持つPerson

## Trigger

新しいProduction / 公演情報が公開状態になる。

## Flow

```text
Production公開
↓
公開Event発生
↓
親Organizationを解決
↓
OrganizationFollowを持つPersonを解決
↓
Home Feed Itemとして表示可能にする
```

Follow者へOrganization内部情報を公開してはならない。

公開対象として定義されたProduction情報のみをFeed Itemとして利用する。

---

# Get Person Home

Person Homeは固定Widget一覧ではなく、Personの実際のFactから構成する。

```text
Greeting
+
参加中活動の次の予定
+
Follow中Organizationの新着
+
観劇履歴
+
必要に応じたNext Action
```

各Sectionは情報が存在する場合のみ表示する。

「なし」を表示するためだけのSectionは作らない。

---

# Initial Scope

初期実装では、Follow対象はOrganizationを必須対象とする。

Favorite、Production Follow、Notification Center、Push Notificationは、同じ公開Event基盤を将来利用できるように設計するが、初期実装の必須機能には含めない。
