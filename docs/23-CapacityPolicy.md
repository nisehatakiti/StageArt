# StageArt Blueprint
# Chapter 23 : 公演・公演回 定員仕様

Version : 1.1
Status : Confirmed business specification

---

## 1. Purpose

Production（公演）とPerformance（公演回）の定員管理ルールを定義する。

定員はProductionとPerformanceの両方に保持する。

Productionの定員を基本値として設定し、Performance作成時にはその値を引き継ぐ。Performance側では作成後も個別に変更できる。

Productionの定員が再設定されて保存された場合は、**既存の全Performanceの定員をProductionの新しい定員で一括上書きする**。

---

## 2. Production（公演）の定員

Production ＞ 公演情報の「会場」セクションに以下を配置する。

```text
■ 会場

会場名
[ 会場名 ]

定員（非公開）
[ 300 ] 人

情報公開日時
[ YYYY/MM/DD HH:MM ]
```

### 2.1 性質

- Production全体の標準定員として保持する。
- 公開ページには表示しない内部管理情報である。
- チケット管理の価格・販売設定とは別責務とする。
- Performance作成時の初期定員として使用する。
- 正の整数のみ入力可能とする。
- 0以下および小数は不可とする。

会場名は公開対象だが、定員は公開対象外である。

---

## 3. Performance（公演回）の定員

Performanceにも定員を保持する。

Performance作成時には、Productionに設定されている定員を初期値として引き継ぐ。

```text
公演日時
[ YYYY/MM/DD ] [ HH:MM ]

定員
[ 300 ] 人

備考
[                    ]

記号
[     ]
```

Performance作成後も、公演回管理画面の一覧上で公演日時・定員・備考・記号を編集し、［更新］で保存できる。

---

## 4. Production定員の再設定時の一括上書き

Production ＞ 公演情報で定員を変更して保存した場合、既存のPerformanceを含む**全公演回の定員を新しいProduction定員で一括上書きする**。

例：

```text
変更前

Production定員：300人

10/10 14:00　300人
10/10 19:00　250人
10/11 14:00　280人

        ↓

Production定員を350人に変更して保存

        ↓

10/10 14:00　350人
10/10 19:00　350人
10/11 14:00　350人
```

Production定員の変更は、既存Performanceの個別定員より優先される一括更新操作である。

その後は各Performanceで再度個別変更可能とする。

---

## 5. 定員の適用単位

実際の予約・販売・受付など、特定の公演回を対象とする処理では、対象Performanceに保持された定員を使用する。

Production定員は標準値および一括更新の基準値であり、日常的な販売可否判定はPerformance側の定員を参照する。

---

## 6. Business Rules

1. Productionは定員を保持する。
2. Productionの定員は公開ページには表示しない。
3. Performanceも定員を保持する。
4. Performance作成時、Productionの定員を初期値として引き継ぐ。
5. Performanceの定員は作成後に個別変更できる。
6. Productionの定員を変更して保存した場合、既存を含む全Performanceの定員を新しい値で一括上書きする。
7. 一括上書き後は、各Performanceで再度個別変更できる。
8. 特定Performanceに対する予約・販売・受付では、Performance側の定員を使用する。

---

## 7. Status

This chapter is a **Confirmed business specification** for Production and Performance capacity management.

Implementation must follow this specification unless a later Blueprint explicitly supersedes it.
