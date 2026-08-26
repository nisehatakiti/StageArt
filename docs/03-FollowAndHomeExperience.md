# StageArt Blueprint

# 03 - Follow and Home Experience

Version : 1.0
Status : Confirmed

---

# 目的

StageArtのPerson Homeを、単なる団体管理用ダッシュボードではなく、舞台芸術に関わるすべてのPersonの活動拠点とする。

StageArtは以下のPersonを同時に想定する。

- 団体を管理するPerson
- 団体に所属するPerson
- 公演・活動に参加するPerson
- 一般の観客

そのためHomeは、全Personへ同じ空情報を表示するのではなく、Personが実際に持つ活動関係と関心関係から構成する。

---

# 基本原則

Homeに「存在しない状態」を表示しない。

例えば、団体にも公演にも参加していない一般観客に対して、以下を表示しない。

```text
所属団体：なし
参加公演：なし
次回稽古：なし
```

Homeは、存在する情報だけを表示し、情報が少ない場合は次の行動への導線を表示する。

---

# Home構成

## 1. Greeting

最上部にPersonへのGreetingを表示する。

```text
おはようございます、[姓]さん
```

時間帯に応じてGreetingを変更できる。

---

## 2. 自分の次の活動

実際のMembership、Participant、Production等のFactから、次の予定を表示する。

例：

- 次回稽古
- 次回公演
- 参加中Productionの重要な予定

参加している活動が存在しない場合、このSectionを「なし」と表示する必要はない。

---

## 3. Follow中の新着

PersonがFollowしているOrganizationの公開情報を表示する。

初期実装では、Organizationが新しいProduction / 公演情報を公開した場合、その新着をHomeへ表示する。

```text
フォロー中の新着

劇団○○
新しい公演が公開されました

『○○○○』
```

これにより、一般観客は自分から毎回団体を検索しなくても、気になる団体の新作を発見できる。

---

## 4. 観劇履歴

Personが観劇履歴を持つ場合、最近の観劇履歴をHomeへ表示できる。

観劇履歴が存在しない場合、「観劇履歴なし」を表示する必要はない。

---

## 5. Empty / Next Action

活動予定、Follow新着、観劇履歴等が存在しない場合、Homeは次の行動への導線を表示する。

例：

- 団体を探す
- 公演・活動を探す
- 観劇履歴を記録する
- 気になる団体をFollowする

---

# FollowとOnboarding

初回Onboardingで「観劇を楽しみたい」を選択したPersonは、OrganizationまたはProductionへの所属を必須としない。

Onboarding完了後、観客は以下のような行動を追加できる。

```text
団体を探す
↓
Organization Public Profileを見る
↓
Followする
↓
以後、新しい公演情報がPerson Homeに表示される
```

FollowはOnboardingの必須Stepではない。

Onboardingで何も所属先を決めないPersonも、Person Homeへ到達できる。

---

# Favoriteとの役割分担

Followは、継続的な更新情報を受け取るための関係である。

Favoriteは、Person自身が後で見返すための保存である。

したがって、初期Homeの新着情報源として優先するのはFollowとする。

Favoriteは将来、以下のような対象に拡張できる。

- Organization
- Production
- Performance

---

# Notificationへの拡張

Follow中Organizationの新作公開を最初はHome上の新着として表示する。

将来的には同じ公開Eventを利用して、Notification CenterやPush Notificationを追加できる。

ただし、初期実装ではPush Notificationを必須としない。

---

# 確定事項

1. StageArtは一般観客も主要利用者として扱う。
2. Organizationへの所属とOrganization Followは別Conceptとする。
3. Followによって権限やMembershipを付与しない。
4. PersonはOrganizationをFollowできる。
5. FollowしているOrganizationの新しい公開Production / 公演情報はPerson Homeへ表示できる。
6. Homeは「所属なし」「次回稽古なし」など、存在しない状態を情報として表示しない。
7. 観劇履歴が存在するPersonには観劇履歴をHomeへ表示できる。
8. 観劇履歴が存在しない場合、映画記録などStageArt外の別サービス概念をHomeへ混在させない。
9. FavoriteはFollowとは別Conceptとして将来拡張可能な構造とする。
10. Notification / Push NotificationはFollow公開Eventを利用して将来追加可能とする。
