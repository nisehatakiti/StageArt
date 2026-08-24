# StageArt Blueprint

# 03 - Public Page URL and Publication Schedule

Version : 1.0
Status : Confirmed

---

## 目的

StageArt上で作成・管理されるOrganizationおよびProductionを、そのまま一般公開可能な団体ページ・公演ページとして利用できるようにする。

公開URLはOrganizationとProductionの親子関係を表現する。

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

団体管理者が初回Onboarding中にOrganizationを作成する場合、団体名に加えてOrganization Slugを設定する。

Productionを作成する場合もProduction Slugを設定する。

UX上は、名称入力からSlugを自動生成し、利用者が必要に応じて編集できるものとする。

Slug重複時は保存前に検証し、利用可能なSlugを利用者が選択できるようにする。

---

# 04 Production情報の公開管理

Productionの登録情報と一般公開を分離する。

Production自体、および少なくとも以下の公開対象情報について、公開状態または情報公開日を設定できる設計とする。

- Production基本情報
- 本番期間
- 会場
- 出演予定者
- チケット情報

未決定の情報は未入力または非公開のままProductionを作成できる。

公開予定日を設定した情報は、指定日時以前は一般公開ページに表示しない。

Organization管理者および権限を持つ内部利用者には、公開前情報を管理画面で表示できる。

---

# 05 初回OnboardingにおけるProduction設定

Organization作成後、直近のProduction予定を確認する。

```text
直近に予定している公演はありますか？
```

「はい」の場合、Person Homeへ到達する前に以下を順番に設定できる。

1. Production名
2. Production Slug
3. 本番期間（未定可）
4. 会場（未定可）
5. 各情報の公開設定または情報公開日
6. 出演予定者
7. 出演予定者情報の公開設定または情報公開日
8. チケット情報
9. チケット情報の公開設定または情報公開日

稽古日程の設定は初回Onboardingの必須工程に含めない。

稽古日程はPerson Home到達後、OrganizationまたはProductionの管理画面から設定する。

---

# 06 公開ページの位置付け

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

# 07 Blueprint確定事項

1. 団体公開URLは `https://stageart.top/{organization-slug}` とする。
2. 公演公開URLは `https://stageart.top/{organization-slug}/{production-slug}` とする。
3. Organization SlugはStageArt全体で一意とする。
4. Production Slugは同一Organization内で一意とする。
5. Organization作成時およびProduction作成時にSlugを設定できる。
6. 名称からSlugを自動生成し、利用者が編集できるUXを基本とする。
7. Productionの各公開情報には公開状態または情報公開日を設定できる。
8. 公開前情報は一般公開ページに表示しない。
9. 初回OnboardingではProduction基本情報、出演予定者、チケット情報まで設定できる。
10. 稽古日程はHome到達後に設定する。

---

End of Blueprint
