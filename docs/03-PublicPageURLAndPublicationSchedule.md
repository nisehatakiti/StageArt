# StageArt Blueprint

# 03 - Public Page URL and Publication Schedule

Version : 1.1
Status : Confirmed

---

## 目的

StageArt上で作成・管理されるOrganizationおよびProductionを、そのまま一般公開可能な団体ページ・公演ページとして利用できるようにする。

公開URLはOrganizationとProductionの親子関係を表現する。

また、団体管理者が初回OnboardingからOrganizationと直近Productionを作成する場合、既存Domain構造

```text
Organization
↓
Project
↓
Production
↓
Performance
```

を変更せず、その作成に必要な情報を画面単位でまとめて入力できるFlowを確定する。

---

# 01 公開URL

## Organization

```text
https://stageart.top/{organization-slug}
```

例:

```text
https://stageart.top/oyaji-du-soleil
```

## Production

```text
https://stageart.top/{organization-slug}/{production-slug}
```

例:

```text
https://stageart.top/oyaji-du-soleil/12-people
```

Productionは必ずOrganizationの配下のURLで公開する。

---

# 02 Slugの一意性

- Organization.slug はStageArt全体で一意とする。
- Production.slug は同一Organization内で一意とする。

したがって、異なるOrganizationでは同じProduction Slugを使用できる。

```text
/organization-a/spring-2027
/organization-b/spring-2027
```

---

# 03 OnboardingでのSlug設定

団体管理者が初回Onboarding中にOrganizationを作成する場合、Organization名と同じ画面でOrganization Slugを設定する。

Productionを作成する場合もProduction名と同じ画面でProduction Slugを設定する。

UX上は、名称入力からSlugを自動生成し、利用者が必要に応じて編集できるものとする。

Slug重複時は保存前に検証し、利用可能なSlugを利用者が選択できるようにする。

---

# 04 Production情報の公開管理

Productionの登録情報と一般公開を分離する。

Production自体、および少なくとも以下の公開対象情報について、公開状態または情報公開日を設定できる設計とする。

- Production基本情報
- 本番期間および公演回（Performance）
- 会場
- 出演予定者
- チケット情報

未決定の情報は未入力または非公開のままProductionを作成できる。

公開予定日を設定した情報は、指定日時以前は一般公開ページに表示しない。

Organization管理者および権限を持つ内部利用者には、公開前情報を管理画面で表示できる。

---

# 05 初回OnboardingにおけるOrganization / Production設定

団体管理者を選択したPersonは、初回Onboarding中にOrganizationを作成する。

Organization作成後、直近のProduction予定を確認する。

```text
直近に予定している公演はありますか？

[ はい ]
[ いいえ ]
```

「いいえ」の場合はOrganization作成完了後、Onboardingを完了してPerson Homeへ進む。

「はい」の場合は、Person Homeへ到達する前に直近Productionの基本情報からチケット情報までを設定する。

## F01-ORG-01 団体作成開始

```text
利用形態選択
↓
団体管理者を選択
↓
団体を作成する
```

## F01-ORG-02 団体基本情報

Organization作成に必要な情報を一画面にまとめる。

```text
団体名
Organization Slug
説明
Logo
```

団体名とOrganization Slugは同じ画面で入力する。

入力完了後、Organizationを作成し、作成者を適切なOrganization管理者としてMembershipへ登録する。

---

## F01-PROD-01 公演基本情報

直近Productionがある場合、Production名とProduction Slugを同じ画面で入力する。

```text
公演名
Production Slug
```

名称からSlugを自動生成し、必要に応じて編集できる。

---

## F01-PROD-02 本番期間・公演回

本番期間と、そのProductionに含まれる具体的な公演回を同じFlowで設定する。

Production側では、少なくとも以下を入力できる。

```text
本番開始日
本番終了日
日程未定設定
本番期間情報の公開設定 / 情報公開日
```

さらに、既存の `Performance` を利用して複数の公演回を登録できる。

```text
公演回

公演日
開場時刻
開演時刻
終演予定時刻

＋ 公演回を追加
```

例えば、同一Productionについて

```text
8月10日 14:00
8月10日 19:00
8月11日 13:00
```

のような複数回の上演を、それぞれ個別のPerformanceとして登録する。

この変更は新しいEntityを追加するものではなく、既存の `Production → Performance` 構造を初回Onboardingから利用できるようにするものである。

---

## F01-PROD-03 会場・公開設定

会場情報と、その情報の公開時期を同じ画面で設定する。

```text
会場
会場未定設定
会場情報の公開設定 / 情報公開日
```

会場が未決定の場合でもProduction作成を継続できる。

---

## F01-PROD-04 出演予定者・公開設定

出演予定者の登録と、その情報の公開時期を同じ画面で設定する。

```text
出演予定者を登録
出演予定者情報の公開設定 / 情報公開日
```

出演者が未確定の場合でも、後から追加・変更できる。

---

## F01-PROD-05 チケット情報・公開設定

チケット情報の登録と、その情報の公開時期を同じ画面で設定する。

```text
チケット情報
チケット情報の公開設定 / 情報公開日
```

販売情報が未確定の場合でも、Production作成を完了できる。

---

## F01-END Onboarding完了

Organizationおよび、必要に応じたProduction初期情報の設定後、Person Homeへ進む。

```text
Onboarding完了
↓
Person Home
```

稽古日程は初回Onboardingの必須工程に含めない。

稽古日程はPerson Home到達後、OrganizationまたはProductionの管理画面から設定する。

---

# 06 初回Onboarding Flow全体

団体管理者として直近Productionまで登録する場合の確定Flowは以下とする。

```text
アカウント登録 / Google認証
↓
姓名入力・確認
↓
初回目的選択
↓
団体管理者を選択
↓
F01-ORG-01 団体作成開始
↓
F01-ORG-02 団体名・Organization Slug・説明・Logo
↓
Organization作成
↓
直近の公演予定があるか確認
├─ いいえ → F01-END → Person Home
└─ はい
    ↓
    F01-PROD-01 公演名・Production Slug
    ↓
    F01-PROD-02 本番期間・公演回（Performance）・公開設定
    ↓
    F01-PROD-03 会場・会場情報公開設定
    ↓
    F01-PROD-04 出演予定者・出演情報公開設定
    ↓
    F01-PROD-05 チケット情報・チケット情報公開設定
    ↓
    F01-END → Person Home
```

画面は、入力対象とその情報公開時期を可能な限り同じ画面にまとめる。

特に以下を確定する。

```text
会場 ＋ 会場情報公開設定
出演予定者 ＋ 出演情報公開設定
チケット情報 ＋ チケット情報公開設定
```

---

# 07 公開ページの位置付け

Organization PageおよびProduction Pageは、StageArt内の管理データを基に生成される公式公開ページとする。

Organization Pageの代表URLは以下である。

```text
/{organization-slug}
```

Production Pageの代表URLは以下である。

```text
/{organization-slug}/{production-slug}
```

Production PageからOrganization Pageへ戻れる導線を持たせる。

---

# 08 Blueprint確定事項

1. 団体公開URLは `https://stageart.top/{organization-slug}` とする。
2. 公演公開URLは `https://stageart.top/{organization-slug}/{production-slug}` とする。
3. Organization SlugはStageArt全体で一意とする。
4. Production Slugは同一Organization内で一意とする。
5. Organization名とOrganization Slugは同じOnboarding画面で設定する。
6. Production名とProduction Slugは同じOnboarding画面で設定する。
7. Organization基本情報として、団体名・Organization Slug・説明・Logoを一画面にまとめる。
8. Organization作成後、直近の公演予定があるか確認する。
9. 直近Productionがある場合、Production基本情報、日程・公演回、会場、出演予定者、チケット情報までを初回Onboardingで設定できる。
10. 本番期間と公演回は既存のProduction / Performance構造を利用する。
11. PerformanceはProductionにおける個別の公演回として複数登録できる。
12. Productionの各公開情報には公開状態または情報公開日を設定できる。
13. 会場、出演予定者、チケット情報は、それぞれの入力と情報公開時期を同じ画面で設定する。
14. 未決定の情報は未入力または未定状態のままOnboardingを進められる。
15. 公開前情報は一般公開ページに表示しない。
16. 稽古日程はHome到達後に設定する。

---

End of Blueprint
