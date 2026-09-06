# StageArt Blueprint
# Chapter 20 : Rehearsal Management Screen Specification

Version : 1.0
Status : Confirmed business specification

---

## 1. Purpose

This document defines the screen structure and user operations for Production-level rehearsal management.

Rehearsal Management belongs directly to Production. It is a sibling of Member Management and Performance Management.

The user experience should be expressed as managing rehearsals for a specific Production.

---

## 2. Rehearsal Management Main Screen

The main screen is entered from a Production context.

The screen provides:

- A **「稽古を作成する」** operation.
- A list of existing rehearsal schedules.

Each existing rehearsal is selectable and opens its Rehearsal Detail screen.

### 2.1 Rehearsal List

Each row displays:

- Status: **確定 / 調整中 / 中止**
- Date
- Time: From - To
- Location

The list is a list of rehearsal schedules belonging to the current Production.

---

## 3. Rehearsal Creation Screen

Selecting **「稽古を作成する」** opens the Rehearsal Creation screen.

### 3.1 Input Items

The screen provides:

- Status
- Date
- Start time
- End time
- Location
- Participating members

### 3.2 Status

The status is selected from a dropdown:

- **確定**
- **調整**

The default value is **調整**.

The main list displays the corresponding adjustment state as **調整中**.

The UI labels are presentation terms; they must not be interpreted as requiring a separate Domain status merely because the displayed wording differs.

### 3.3 Participating Members

Participating members are selected from the members of the current Production.

The member selection area provides:

- Checkbox for each Production member
- **全選択** button
- **全選択解除** button

Only members selected for the rehearsal are assigned as participating members.

### 3.4 Save

Selecting **保存** creates the rehearsal with the entered information and selected members.

After saving, StageArt sends an attendance confirmation request to the participating members.

The attendance confirmation is associated with the created rehearsal.

---

## 4. Rehearsal Detail Screen

Selecting a rehearsal from the Rehearsal Management main screen opens its detail screen.

The detail screen displays and manages:

- Status
- Date
- Start time
- End time
- Location
- Participating members
- Attendance confirmation status

### 4.1 Status

The detail screen displays the rehearsal status as:

- **確定**
- **調整中**

When the rehearsal is in adjustment status, additional operations are available as defined below.

### 4.2 Participating Members

The member area distinguishes selected and unselected Production members.

- Members currently selected for the rehearsal are displayed above.
- Members not currently selected are displayed below.
- An unselected member can be added to the rehearsal from this screen.

The member list is therefore managed in the context of the current Production and the current rehearsal.

### 4.3 Attendance Confirmation Summary

The detail screen displays the current attendance confirmation status as a summary:

- **出席 N 名**
- **欠席 N 名**
- **未定 N 名**
- **未回答 N 名**

The values represent the current attendance confirmation responses for the rehearsal participants.

### 4.4 Save

Selecting **保存** updates the rehearsal information.

When new members have been added to the rehearsal, attendance confirmation is sent **only to the newly added members**.

Existing participants who have already received attendance confirmation are not sent a duplicate confirmation merely because the rehearsal was saved.

---

## 5. Rehearsal Confirmation / Cancellation

When the rehearsal is in **調整中** status, the detail screen provides the following operations:

- **稽古日程を確定する**
- **中止する**

### 5.1 稽古日程を確定する

Selecting **稽古日程を確定する** changes the rehearsal status to **確定**.

When the rehearsal is confirmed, attendance confirmation is sent to the members associated with the rehearsal.

### 5.2 中止する

Selecting **中止する** changes the rehearsal status to **中止**.

A cancelled rehearsal is displayed as **中止** in the Rehearsal Management main list.

---

## 6. Relationship to Rehearsal Domain

A Rehearsal belongs to a Production.

The screen terminology **調整 / 調整中** corresponds to the business state in which the rehearsal schedule is being coordinated and attendance confirmation is relevant. The exact Domain status names and persistence rules remain governed by the Rehearsal Domain specification.

Rehearsal attendance is managed per rehearsal and per participating Person.

The screen specification does not redefine the underlying Rehearsal Domain model; it defines the confirmed user-facing operations and information display.

---

## 7. Confirmed User Flow

Production
↓
Rehearsal Management
↓
稽古を作成する
↓
Rehearsal Creation
↓
参加メンバー選択
↓
保存
↓
出席確認送信

Existing rehearsal:

Production
↓
Rehearsal Management
↓
Rehearsal List
↓
Rehearsal Detail
↓
編集 / メンバー追加 / 保存

When adjusting:

Rehearsal Detail
↓
稽古日程を確定する
↓
確定

or:

Rehearsal Detail
↓
中止する
↓
中止

---

## 8. Business Rules

1. Rehearsal Management is a **Production-level** function.
2. Rehearsal Management is a sibling of Production Member Management and Performance Management.
3. A rehearsal is created for a specific Production.
4. Participating members are selected from the current Production members.
5. Rehearsal creation defaults to **調整**.
6. Saving a newly created rehearsal sends attendance confirmation to its participating members.
7. Adding members to an existing rehearsal and saving sends attendance confirmation only to the newly added members.
8. An adjustment-status rehearsal can be changed to **確定** or **中止** from its detail screen.
9. Confirming the rehearsal sends attendance confirmation to the rehearsal members.
10. The rehearsal list displays **確定 / 調整中 / 中止** as the user-facing status labels.
11. Cancelling a rehearsal does not delete the rehearsal record; it changes its state to **中止**.

---

## 9. Out of Scope

The following are not fixed by this screen specification unless separately confirmed:

- Detailed notification delivery implementation
- Exact notification message wording
- Exact attendance-response UI
- API endpoint structure
- Persistence implementation
- Authorization implementation details
- Additional rehearsal fields not listed above

---

## 10. Status

This chapter is a **Confirmed business specification** for the Rehearsal Management screens.

Implementation must follow this specification unless a later Blueprint or Domain specification explicitly supersedes it.
