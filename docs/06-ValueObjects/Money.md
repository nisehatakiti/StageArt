# StageArt Blueprint
# Value Object : Money

Version : 1.0

---

# Purpose

MoneyはStageArt全体で利用する金額を表すValue Objectである。

金額に関するBusiness Ruleを共通化する。

Moneyはチケット価格だけではなく、

予算、

売上、

支出、

出演料、

助成金など、

StageArtで扱うすべての金額に利用する。

---

# Concept

Moneyは金額を表す。

Moneyは数値ではない。

通貨を含めて一つのValue Objectとする。

---

# Design Principles

- Immutableである。
- 通貨を保持する。
- 小数計算を避ける。
- 金額比較ができる。
- 四則演算ができる。

---

# Amount

金額を表す。

負数も許可する。

利用例

- 支出

- 値引き

---

# Currency

通貨を表す。

Version 1.0では

JPY

のみ対応する。

将来的に

USD

EUR

などへ対応する。

---

# Price

Priceは販売価格を表す。

Moneyを利用して表現する。

利用例

- 一般
- 学生
- 当日
- 前売

Priceは負数を許可しない。

---

# Tax

Version 1.0では税込価格として扱う。

消費税計算は実装側へ委ねる。

将来的に

- 税抜
- 内税
- 外税

へ対応する。

---

# Calculation

Moneyは以下をサポートする。

- 加算
- 減算
- 比較

乗除算はBusiness Ruleに従う。

---

# Validation

以下は不正とする。

- 通貨未設定
- Priceが負数
- 不正な金額

---

# Future

将来的に以下へ対応する。

- 外貨
- 為替
- 割引
- クーポン
- ポイント
- 手数料

---

# Design Principles

- Moneyは金額を表す。
- Priceは販売価格を表す。
- MoneyはImmutableである。
- Moneyは通貨を保持する。
- Priceは負数を許可しない。
- 実装方式には依存しない。
