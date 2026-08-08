# StageArt Blueprint
# Value Object : DateTime

Version : 1.0

---

# Purpose

DateTimeはStageArt全体で利用する日時に関するValue Objectを定義する。

日時・期間・スケジュールを共通の概念として表現する。

---

# Design Principles

- DateとTimeは異なる概念である。
- DateTimeはDateとTimeを組み合わせた概念である。
- Periodは開始と終了を表現する。
- Value ObjectはImmutableである。
- タイムゾーンを考慮する。
- 実装ライブラリには依存しない。

---

# Date

Dateは日付を表す。

例

- 2026-10-01
- 2026-10-15

利用例

- 生年月日
- 公演日
- 予約日

Dateは時刻を持たない。

---

# Time

Timeは時刻を表す。

例

- 13:00
- 18:30

利用例

- 開場時間
- 開演時間

Timeは日付を持たない。

---

# DateTime

DateTimeは日時を表す。

利用例

- 公演開始日時
- 公演終了日時
- 予約日時
- チェックイン日時

DateTimeはDateとTimeを組み合わせた概念である。

---

# Period

Periodは開始日時と終了日時を表す。

Periodは以下を保持する。

- StartDateTime
- EndDateTime

利用例

- 公演期間
- 稽古期間
- チケット販売期間
- 募集期間

---

# Duration

Durationは時間の長さを表す。

利用例

- 上演時間
- 稽古時間
- 作業時間

例

- 90分
- 2時間30分

---

# TimeZone

StageArtはタイムゾーンを考慮する。

Version 1.0では日本時間(JST)を標準とする。

将来的に海外公演へ対応できる設計とする。

---

# Validation

以下は不正とする。

- EndDateTime < StartDateTime
- 存在しない日時
- 不正な日付

ValidationはValue Object内で保証する。

---

# Future

将来的に以下へ対応する。

- サマータイム
- 海外タイムゾーン
- 繰り返しイベント
- 営業日計算
- カレンダー連携

---

# Design Principles

- Dateは日付のみを表す。
- Timeは時刻のみを表す。
- DateTimeは日時を表す。
- Periodは期間を表す。
- Durationは時間の長さを表す。
- DateTimeはImmutableである。
- 実装ライブラリに依存しない。
