# StageArt Blueprint
# Domain Model : Category

Version : 1.0

---

# Purpose

CategoryはProductionの公演形態を表す参照ドメインである。

CategoryはProductionを大きな分類で整理するために利用する。

---

# Concept

Categoryは作品のジャンルではない。

「どのような公演であるか」を表現する。

例）

- 演劇
- ミュージカル
- 朗読劇
- ダンス
- ライブ
- コンサート
- 映像作品
- 配信
- ワークショップ
- その他

---

# Identity

CategoryはCategoryIDによって一意に識別する。

表示名は変更できる。

---

# Relationship

CategoryはProductionへ紐付く。

```
Category

↓

Production
```

一つのProductionは一つのCategoryを持つ。

---

# Management

CategoryはStageArt運営が管理する。

利用者は追加・削除できない。

---

# Future

将来的に

- 表示順
- アイコン
- カラー

を保持できる。

---

# Design Principles

- Categoryは公演形態を表す。
- CategoryはProductionへ所属する。
- 利用者は編集できない。
