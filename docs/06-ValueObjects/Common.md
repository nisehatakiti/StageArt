# StageArt Blueprint
# Value Object : Common

Version : 1.0

---

# Purpose

CommonはStageArt全体で利用する共通Value Objectを定義する。

特定のドメインへ依存しない。

---

# Name

表示名称。

利用例

- Person名
- Organization名
- Productionタイトル

Nameは識別子ではない。

---

# Description

説明文。

利用例

- プロフィール
- 劇団紹介
- 公演紹介

Markdown対応可否は実装で決定する。

---

# Image

画像を表す。

利用例

- プロフィール画像
- 劇団ロゴ
- フライヤー

Imageは画像そのものではなく画像リソースを参照する。

---

# Url

Webページを表す。

利用例

- 劇団ホームページ
- SNS
- 外部予約サイト

---

# Email

メールアドレス。

EmailはValue Objectとして妥当性を保証する。

---

# PhoneNumber

電話番号。

表示形式と保存形式は分離する。

---

# Memo

自由記述。

内部管理用。

公開・非公開はEntity側が決定する。

---

# Design Principles

- Commonは特定ドメインへ依存しない。
- CommonはImmutableである。
- Validationを内包できる。
- Entity間で共有できる。
