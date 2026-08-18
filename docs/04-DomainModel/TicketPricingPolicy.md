# StageArt Blueprint

# Domain Model : Ticket Pricing Policy

Version : 1.0

---

# Purpose

StageArtのTicketは、劇団が無理なく料金体系を設定できることを重視する。

Ticket料金は、必要に応じて二つの軸を組み合わせたMatrixとして管理できる。

---

# Pricing Axes

Ticketには、以下の二つの任意軸を持たせる。

## Axis 1 : Ticket Category

料金区分を表す。

標準候補：

- 一般
- 学生
- その他

この軸を使用しないこともできる。

## Axis 2 : Sales Timing

販売時期・販売区分を表す。

標準候補：

- 前売
- 当日
- その他

この軸を使用しないこともできる。

---

# Matrix

両方の軸を使用する場合、StageArtは二軸の組み合わせを料金Matrixとして扱う。

例：

|  | 前売 | 当日 |
|---|---:|---:|
| 一般 | ¥3,000 | ¥3,500 |
| 学生 | ¥2,000 | ¥2,500 |

利用者は必要な組み合わせだけを使用できる。

すべての組み合わせを必須としない。

---

# Single Axis

Axis 1だけを使用する場合：

| 区分 | 料金 |
|---|---:|
| 一般 | ¥3,000 |
| 学生 | ¥2,000 |

Axis 2だけを使用する場合：

| 区分 | 料金 |
|---|---:|
| 前売 | ¥3,000 |
| 当日 | ¥3,500 |

---

# No Axis

両方の軸を使用しない場合、Productionは一律料金のTicketを設定できる。

例：

一律 ¥3,000

---

# Standard Values

Version 1.0では、UI上で以下を標準候補として提示する。

Axis 1：
- 一般
- 学生
- その他

Axis 2：
- 前売
- 当日
- その他

利用者は不要な候補を使用しなくてよい。

必要に応じて独自の区分を追加できる。

---

# Display

観客向けには、二軸の内部構造をそのまま表示する必要はない。

必要に応じてDisplay Nameを設定できる。

例：

- 一般前売
- 学生前売
- 一般当日券
- 学生当日券

---

# Reservation Relationship

Reservationは、成立時点で選択されたTicketおよびPrice Snapshotを保持する。

Ticket料金の変更によって、既存Reservationの価格を変更してはならない。

---

# Budget Relationship

Ticket Pricing MatrixはTicket販売条件の正本であり、Budgetの入力を複雑化させることを目的としない。

Budgetでは、既存の「想定集客数 × チケット単価」による簡易予算入力を引き続き利用できる。

将来、必要に応じてTicket区分ごとの集客予測をBudgetへ連携できる構造を妨げない。

---

# Business Rules

1. Ticket料金は二軸で管理できる。
2. Axis 1は料金区分を表す。
3. Axis 2は販売時期・販売区分を表す。
4. Axis 1は使用しなくてもよい。
5. Axis 2は使用しなくてもよい。
6. 両方を使用しない一律料金も許可する。
7. 両方を使用する場合はMatrixとして表示できる。
8. Matrixの全組み合わせを必須としない。
9. 標準候補は「一般／学生／その他」および「前売／当日／その他」とする。
10. 独自の区分を追加できる。
11. Ticketの正本はProduction側にある。
12. Reservationは予約成立時点のPrice Snapshotを保持する。
