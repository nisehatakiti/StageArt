# StageArt Blueprint
# Chapter 20 : Rehearsal Management Screen Specification

Version : 1.1
Status : Confirmed business specification

---

## 1. Purpose

This document defines the confirmed screen structure and user operations for Production-level rehearsal management.

Rehearsal Management belongs directly to Production and manages rehearsals for a specific Production.

---

## 2. Related Screen Structure

Production ＞ 稽古管理 consists of:

- 稽古一覧
- 稽古作成
- 稽古詳細

Notifications are delivered through 個人Home. When Push notifications are enabled for the recipient, Push notification is also sent.

There is no separate attendance-response screen. The notification navigates the participant to the relevant 稽古詳細 screen, where the participant registers attendance.

---

## 3. Rehearsal List

The main screen provides:

- 「稽古を作成する」
- list of existing rehearsals

Each row displays:

- Status: 確定 / 調整中 / 中止
- Date
- Start time
- End time
- Location

Selecting a rehearsal opens its detail screen.

---

## 4. Rehearsal Creation

### 4.1 Input Items

The screen provides:

- Status
- Date
- Start time
- End time
- Location
- 連絡事項
- 回答期限（調整中の場合）
- Participating members

### 4.2 Status

Status options:

- 確定
- 調整

Default: 調整

The main list displays this state as 調整中.

### 4.3 Contact Information

連絡事項 is an optional free-text field.

It may contain rehearsal content, target scenes, meeting instructions, items to bring, warnings, and other information.

### 4.4 Response Deadline

When a rehearsal is created with status 調整, 回答期限 is required.

A planning-stage rehearsal cannot be saved without a response deadline.

### 4.5 Participating Members

Participating members are selected from the current Production members.

The member selection area provides:

- checkbox for each Production member
- 全選択
- 全選択解除

Only selected members are assigned as participants.

### 4.6 Save

Saving creates the rehearsal and sends the applicable attendance confirmation request to participating members.

---

## 5. Two-Stage Attendance Confirmation

### 5.1 調整中

Available responses:

- 出席
- 欠席
- 未定

An optional 備考 field is available.

The system also displays 未回答 for participants who have not submitted a response. 未回答 is not selectable by the participant.

### 5.2 確定

Available responses:

- 出席
- 欠席
- 早退
- 遅刻
- 未定

An optional 備考 field is available.

The confirmed-stage response is a new confirmation stage. A response given during 調整中 does not substitute for the response after confirmation.

---

## 6. Attendance Note

Attendance registration includes an optional free-text 備考 field.

Examples:

- 直前までバイトのため14時から稽古参加になります
- 17時まで別件があるため途中で退席します
- 仕事の都合で参加できません

Administrators and rehearsal management delegates can view the attendance status and note together.

---

## 7. Planning-Stage Response Deadline

### 7.1 Before Deadline

Before the response deadline, participants may freely:

- submit a response
- change the response
- edit the attendance note

### 7.2 After Deadline

After the response deadline:

- new responses are not allowed
- response changes are not allowed
- attendance note changes are not allowed

Participants who did not answer remain 未回答.

---

## 8. Planning-Stage Notifications

### 8.1 Initial Notification

Destinations:

1. 個人Home
2. Push notification when enabled

Message:

> 公演○○の稽古がYYYY/MM/DDに計画されています。詳細を確認して出欠登録をお願いします。

The Production name and rehearsal date are inserted.

### 8.2 Reminder

A reminder is sent exactly 24 hours before the response deadline.

Only participants who are still 未回答 receive it.

Participants who have answered 出席、欠席、or 未定 do not receive the reminder.

Reminder message:

> 【Remind】公演○○の稽古がYYYY/MM/DDに計画されています。詳細を確認して出欠登録をお願いします。

The reminder is the same as the original message with 【Remind】 prepended.

### 8.3 Notification History

No notification-history UI is required.

The system does not need to display notification timestamps, counts, or history in rehearsal management.

---

## 9. Response Deadline Changes

### 9.1 Extension

When the response deadline is extended:

- no immediate notification is sent solely because the deadline changed
- the reminder schedule is recalculated from the new deadline
- the normal reminder is sent 24 hours before the new deadline to participants still 未回答

### 9.2 Deadline Brought Forward

When the deadline is moved earlier:

- the reminder schedule is recalculated from the new deadline
- if the new reminder time is still in the future, the reminder is sent at that time
- if the new reminder time has already passed when the deadline is changed, a reminder is sent immediately to participants still 未回答

---

## 10. Rehearsal Detail

The detail screen displays and manages:

- Status
- Date
- Start time
- End time
- Location
- 連絡事項
- Participating members
- Attendance status
- Attendance notes

Members currently participating are displayed separately from Production members who are not participating.

An unselected Production member can be added from this screen.

When members are newly added and saved, attendance confirmation is sent only to newly added members. Existing participants are not sent duplicate confirmation merely because the rehearsal is saved.

---

## 11. Attendance Summary

For 調整中:

- 出席 N名
- 欠席 N名
- 未定 N名
- 未回答 N名

For 確定:

- 出席 N名
- 欠席 N名
- 早退 N名
- 遅刻 N名
- 未定 N名
- 未回答 N名

---

## 12. Confirmation / Cancellation

When the rehearsal is 調整中, authorized users can:

- 稽古日程を確定する
- 中止する

### 12.1 Confirmation

Confirmation changes the status to 確定 and begins the confirmed-stage attendance confirmation.

Destinations:

1. 個人Home
2. Push notification when enabled

Message:

> ○○の稽古日程がYY/MM/DDで確定しました。詳細を確認して出欠登録をお願いします。

The Production name and rehearsal date are inserted.

### 12.2 Cancellation

Cancellation changes the status to 中止.

The rehearsal record is not deleted and remains visible in the list.

---

## 13. Permissions

The following roles can:

- create rehearsals
- edit rehearsals
- confirm rehearsals
- cancel rehearsals
- add participants

Authorized roles:

- 管理者
- 稽古管理代理人

General participants cannot perform these management operations.

All participants may register and update their own attendance while the relevant attendance stage is open.

---

## 14. Business Rules

1. Rehearsal Management is a Production-level function.
2. Participating members are selected from current Production members.
3. 調整中 attendance responses are 出席 / 欠席 / 未定.
4. 確定 attendance responses are 出席 / 欠席 / 早退 / 遅刻 / 未定.
5. Attendance registration always supports an optional 備考.
6. 調整中 rehearsals require a response deadline.
7. Before the planning-stage deadline, responses and notes may be freely changed.
8. After the planning-stage deadline, attendance registration and changes are not allowed.
9. A reminder is sent 24 hours before the deadline to 未回答 participants only.
10. Deadline extension causes no immediate notification.
11. If a deadline is brought forward and the recalculated reminder time has passed, 未回答 participants receive an immediate reminder.
12. Confirmation triggers a new confirmed-stage attendance request.
13. Notification history is not managed in the rehearsal UI.
14. Only 管理者 and 稽古管理代理人 can perform rehearsal management operations.
15. Cancellation does not delete the rehearsal record.

---

## 15. Status

This chapter is a Confirmed business specification.

Implementation must follow this specification unless a later Blueprint or Domain specification explicitly supersedes it.
