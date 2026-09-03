# StageArt Blueprint
# Chapter 30 : Ticket Reservation Change / Cancellation Screen Specification

Version : 1.0
Status : Confirmed business specification

---

## 1. Purpose

This document defines the dedicated screen used by a customer to change or cancel an existing ticket reservation.

The screen is accessed from a link included in the reservation confirmation email.

The purpose is to allow the reservation holder to change the reserved ticket quantity or cancel the reservation without requiring the user to search for the reservation manually.

---

## 2. Entry Point

The reservation confirmation email contains a link for **チケット変更・キャンセル**.

Conceptually:

```text
予約確認メール
↓
チケット変更・キャンセルリンク
↓
チケット変更・キャンセル画面
```

The reservation number is supplied by the link and is used to identify the reservation.

The user does not manually enter the reservation number in the normal flow.

---

## 3. Screen

The dedicated screen provides the following fields and operations.

```text
チケット変更・キャンセル

予約番号　XXXXXXXXXX
           ※表示のみ

チケット枚数　[ 2枚 ▼ ]

[ 保存 ]
```

### 3.1 予約番号

- The reservation number is received from the email link.
- The reservation number is displayed on the screen.
- The reservation number is read-only and cannot be edited by the user.

### 3.2 チケット枚数

- The current reserved ticket quantity is displayed as the initial selected value.
- The quantity can be changed using a pull-down list.
- Setting the quantity to **0枚** means cancellation of the reservation.

The screen does not require a separate cancellation button.

Cancellation is represented by changing the ticket quantity to 0 and saving.

### 3.3 保存

The user selects **保存** to submit the requested change.

---

## 4. Change Processing

When the user selects **保存**, the system compares the requested quantity with the quantity currently recorded in the reservation.

```text
現在の予約枚数
↓
変更後の予約枚数
↓
差分を算出
```

### 4.1 Quantity Increased

When the requested ticket quantity is greater than the current reservation quantity:

1. Calculate the additional ticket quantity.
2. Check the selected Performance's capacity.
3. If the requested additional quantity would exceed the Performance capacity, the change is not permitted.
4. If capacity is sufficient, display a confirmation popup.
5. When the user confirms, save the new reservation quantity.
6. Adjust the relevant reservation count by the difference from the previous quantity.
7. Send an email notifying the user of the change result.

Conceptually:

```text
変更後枚数 > 現在枚数
        ↓
   定員チェック
        ↓
定員超過 → 変更不可
        ↓
  定員問題なし
        ↓
  確認ポップアップ
        ↓
      OK
        ↓
   予約枚数を更新
        ↓
   差分を予約枚数に反映
        ↓
    変更結果をメール
```

### 4.2 Quantity Decreased

When the requested ticket quantity is less than the current reservation quantity:

1. Display a confirmation popup.
2. When the user confirms, save the new reservation quantity.
3. Reduce the relevant reservation count by the difference from the previous quantity.
4. Send an email notifying the user of the change result.

No additional capacity check is required when reducing the quantity.

### 4.3 Cancellation

When the requested ticket quantity is **0枚**:

1. Treat the operation as cancellation of the reservation.
2. Display a confirmation popup.
3. When the user confirms, save the reservation with 0 tickets / cancel the reservation according to the reservation data model.
4. Reduce the relevant reservation count by the previously reserved quantity.
5. Send an email notifying the user of the cancellation result.

The user performs cancellation by selecting **0枚** and pressing **保存**.

---

## 5. Reservation Count Adjustment

The reservation count must be adjusted according to the change from the previous quantity to the new quantity.

Example:

```text
現在 2枚 → 4枚
差分 +2枚
→ 予約枚数を2枚増加
```

```text
現在 4枚 → 2枚
差分 -2枚
→ 予約枚数を2枚減少
```

```text
現在 3枚 → 0枚
差分 -3枚
→ 予約枚数を3枚減少
```

The reservation count used for relevant Production member / 扱い aggregation must therefore always reflect the current reservation quantity after the change is confirmed.

This also means that Home's reservation-based quota and ticket-back estimate use the updated reservation quantity.

---

## 6. Capacity Check

A capacity check is required **only when the requested ticket quantity increases**.

The capacity used for the check is the capacity of the selected Performance.

The change is rejected when the new requested quantity would exceed the remaining available capacity.

When the capacity check fails, the reservation is not changed.

The exact wording of the error popup is a UX detail unless separately confirmed.

---

## 7. Confirmation Popup

A confirmation popup is displayed before a quantity change or cancellation is finalized.

The popup allows the user to confirm or cancel the operation.

Conceptually:

```text
予約内容を変更します。
よろしいですか？

[キャンセル] [OK]
```

For a cancellation, the message should clearly indicate that the ticket quantity will become 0 / the reservation will be cancelled.

Exact wording is a UX detail.

---

## 8. Email After Change

After the change is successfully confirmed, StageArt sends an email to the reservation email address.

The email communicates the resulting reservation state.

For a quantity change, it communicates the updated ticket quantity.

For cancellation, it communicates that the reservation has been cancelled.

The email sending occurs after the reservation change has been successfully saved.

---

## 9. Business Rules

- The change/cancellation operation is accessed through a link in the reservation confirmation email.
- The reservation number is supplied by the link and displayed read-only.
- The current ticket quantity is the initial value on the screen.
- Ticket quantity is changed using a pull-down list.
- **0枚 means cancellation.**
- There is one **保存** operation; no separate cancellation operation is required.
- When increasing quantity, the selected Performance capacity must be checked.
- A capacity shortage prevents the change from being saved.
- When decreasing quantity, no capacity check is required.
- A confirmation popup is displayed before a successful change/cancellation is finalized.
- After confirmation, the reservation quantity is updated.
- The relevant reservation count is adjusted by the difference between the previous and new quantities.
- After a successful change or cancellation, a result email is sent.
- Home's reservation-based quota and ticket-back estimate use the updated reservation quantity.

---

## 10. Relationship to Existing Reservation Flow

This chapter supplements Chapter 24 : Ticket Reservation Flow.

Chapter 24 defines the initial reservation process, including capacity checking at reservation time and the relationship between reservations and **扱い**.

This chapter defines the subsequent self-service operation for changing or cancelling an existing reservation.

The same reservation record is updated rather than creating a separate replacement reservation for the quantity change.

---

## 11. Scope

This chapter defines the confirmed business behavior and primary UX for ticket reservation changes and cancellations.

The exact URL format, link token structure, authentication/security mechanism for the link, pull-down maximum quantity, email template, popup wording, reservation status persistence details, and API/database implementation are implementation details unless separately confirmed.
