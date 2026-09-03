# StageArt Blueprint
# Chapter 23 : 公演・公演回 定員仕様

Version : 1.0
Status : Confirmed business specification

---

## 1. Purpose

Production（公演）とPerformance（公演回）の定員管理ルールを定義する。

定員はProductionとPerformanceの両方に保持する。

Productionの定員を基本値として設定し、Performance作成時にはその値をデフォルト値として引き継ぐ。Performance側では、引き継いだ定員を個別に変更できる。

---

## 2. Production（公演）の定員

Production（公演）情報に**定員**を入力する。

この定員は、そのProductionにおける基本となる定員値である。

Production
├─ 公演名
├─ 公演情報
└─ 定員：100名

Productionの定員は、Performance（公演回）を作成する際のデフォルト値として使用する。

---

## 3. Performance（公演回）の定員

Performance（公演回）にも**定員**を保持する。

Performance作成時には、Productionに設定されている定員をデフォルト値として設定する。

例えば、Productionの定員が100名の場合：

```text
Production（公演）
定員：100名

        ↓ 公演回作成

Performance（公演回）
定員：100名 ← デフォルト値
```

Performanceの定員は、作成後に個別変更できる。

例えば：

```text
Performance
├─ 10/10 14:00　定員：100名
├─ 10/10 19:00　定員：100名
└─ 10/11 14:00　定員：80名
```

---

## 4. デフォルト値の扱い

Productionの定員は、Performance作成時にPerformanceの定員のデフォルト値として引き継ぐ。

これは単なる参照値ではなく、Performance側の定員として設定される値である。

したがって、Performance作成後にProductionの定員を変更しても、既に作成済みのPerformanceの定員は自動的には変更されない。

既存Performanceの定員を変更する場合は、Performance側で個別に変更する。

---

## 5. 定員の適用単位

実際のチケット販売・受付等、特定の公演回を対象とする処理では、対象Performanceに設定された定員を使用する。

Productionの定員は基本値であり、各Performanceの最終的な運用定員はPerformance側の定員によって決まる。

---

## 6. User Flow

### Production作成・編集

Production情報
↓
定員を入力
↓
保存

### Performance作成

Production
↓
公演回管理
↓
公演回を追加
↓
Productionの定員をデフォルト値として表示
↓
必要に応じて定員を変更
↓
保存

---

## 7. Screen Specification Impact

### Production Information Screen

Production情報の入力項目として**定員**を追加する。

### Performance Management Screen

Performanceの入力項目として**定員**を追加する。

Performance作成時はProductionの定員をデフォルト値として設定する。

Performanceごとに定員を変更可能とする。

---

## 8. Business Rules

1. Production（公演）は定員を保持する。
2. Performance（公演回）も定員を保持する。
3. Performance作成時、Productionの定員をPerformanceのデフォルト値として引き継ぐ。
4. Performanceの定員は、作成時に変更可能である。
5. Productionの定員変更は、既存Performanceの定員を自動変更しない。
6. Performanceごとに異なる定員を設定できる。
7. 特定Performanceに対するチケット販売・受付等では、Performance側の定員を使用する。

---

## 9. Out of Scope

以下は本章では固定しない。

- 定員超過時の具体的なチケット販売制御
- キャンセルによる定員計算への影響
- 席番号・座席表・指定席管理
- APIエンドポイント仕様
- データベース実装

---

## 10. Status

This chapter is a **Confirmed business specification** for Production and Performance capacity management.

Implementation must follow this specification unless a later Blueprint or Domain specification explicitly supersedes it.
