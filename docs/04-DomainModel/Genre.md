# StageArt Blueprint
# Domain Model : Genre

Version : 1.0

---

# Purpose

Genreは作品ジャンルを表す参照ドメインである。

作品の世界観や内容を分類する。

---

# Concept

Genreは公演形態ではない。

作品内容を表現する。

例）

- コメディ
- ミステリー
- ホラー
- SF
- 恋愛
- 青春
- サスペンス
- ファンタジー
- アクション
- その他

---

# Identity

GenreはGenreIDによって一意に識別する。

---

# Relationship

GenreはProductionへ紐付く。

```
Genre

↓

Production
```

一つのProductionは複数Genreを持てる。

例）

コメディ

+

ミステリー

---

# Management

GenreはStageArt運営が管理する。

利用者は編集できない。

---

# Future

将来的に

- 人気ジャンル
- 関連ジャンル

などへ対応する。

---

# Design Principles

- Genreは作品内容を表す。
- Productionは複数Genreを持てる。
- 利用者は編集できない。
