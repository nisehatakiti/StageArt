# StageArt Blueprint
# Chapter 24 : Ticket Reservation Flow

Version : 1.3
Status : Confirmed business specification

---

## 1. Purpose

This document defines the common ticket reservation flow for StageArt.

The ticket reservation screen is shared by all reservation entry points. The entry point only determines the initial state of certain fields, especially the "扱い" (handling person).

The reservation business operation itself must not be duplicated for each entry point.

---

## 2. Reservation Entry Points

StageArt supports the following reservation entry points:

### 2.1 StageArt user searches for a Production

A user who is logged in to StageArt:

StageArt Home
↓
公演を探す
↓
Production public page
↓
Ticket reservation

The user proceeds to the common reservation screen.

---

### 2.2 General user reserves from the public Production page

A general user who is not required to be a StageArt account user may access the public Production page and proceed to ticket reservation.

Production public page
↓
Ticket reservation

The user proceeds to the common reservation screen.

---

### 2.3 General user reserves from a Production participant's handling page

A Production participant may introduce a reservation page to a general user.

The reservation page is associated with the introducing/handling Production participant.

Conceptually:

Production participant
↓
Personal handling reservation page
↓
General user
↓
Common reservation screen

In this case, the "扱い" is fixed to the associated Production participant and cannot be changed by the reservation user.

---

### 2.4 Production participant reserves using their own handling

A Production participant may make a reservation using their own handling.

The common reservation screen is opened with the participant's own "扱い" fixed.

The exact navigation used by the participant to reach this screen is an implementation/UX detail; the reservation operation itself is the same as the other entry points.

---

## 3. Common Reservation Screen

All four entry points use the same reservation screen.

The conceptual fields are:

```text
予約画面

扱い              [選択 ▼]
公演回            [選択 ▼]
チケット枚数
  チケット種別    [枚数 ▼]
  チケット種別    [枚数 ▼]

連絡先メールアドレス  [                    ]
予約者名
  姓                [                    ]
  名                [                    ]

フリガナ
  セイ              [                    ]
  メイ              [                    ]

[予約]
```

### 3.1 扱い

"扱い" identifies the Production participant responsible for the handling of the reservation.

The primary business purpose of "扱い" is to associate the reserved ticket quantity with the responsible Production participant's ticket sales record.

The ticket quantities associated with each participant's "扱い" are used as the basis for counting that participant's ticket sales, including the calculation/counting of ticket-back and ticket sales quota (ノルマ), according to the Production's applicable rules.

In a normal reservation entry point, the user can select the handling person from the available Production participants for the Production.

For a participant-specific reservation page, the handling person is fixed by the entry point and is not selectable/replaceable by the reservation user.

The reservationer and the handling person are separate concepts. A general user can therefore make a reservation while a Production participant is recorded as the handling person.

A reservation may also have no specific "扱い" when it is made without a responsible Production participant. Such a reservation is not attributed to an individual participant's handling sales count.

---

### 3.2 公演回

The user selects the Performance for which the reservation is being made.

The selectable Performances are the Performances of the target Production.

---

### 3.3 チケット枚数

The user specifies the quantity for each ticket type defined for the Production.

Ticket types and prices are managed at Production level and are common to the Production's Performances.

Reserved quantities are **aggregated separately for each ticket type**. Different ticket types are not combined into one quantity for ticket-back calculation.

The reserved quantities are also the basis for aggregating ticket sales by "扱い".

---

### 3.4 連絡先メールアドレス

The reservation user provides the email address to which the reservation confirmation email is sent.

For a reservation made by a logged-in StageArt user, the user's registered account email address may be used without requiring the user to enter it again.

---

### 3.5 予約者名

The reservation user provides surname and given name.

A separate furigana field is provided so that the reservation user does not have to enter the person's name itself in katakana.

---

## 4. Reservation Notice

The reservation screen displays a notice explaining the email-delivery rule.

The following wording is the intended level of detail:

> **【重要】予約について**  
> 予約完了後、入力されたメールアドレス宛に予約確認メールをお送りします。  
> 予約確認メールが届かない場合は、まず迷惑メールフォルダをご確認ください。  
> なお、入力されたメールアドレスが存在しない等の理由でメールが送信できなかった場合は、予約はキャンセルされます。  
> あらかじめ「○○@○○」からのメールを受信できるよう設定してください。

The wording may be adjusted for the final UI, but the above business meaning must be preserved.

---

## 5. Reservation Confirmation Flow

Pressing the reservation button does not immediately finalize the reservation from the user's perspective.

The flow is:

```text
Reservation information entered
↓
Press 「予約」
↓
Check ticket sales/reservation acceptance start date/time
↓
If reservation acceptance has not started
    → Display a popup that the reservation cannot be made

If reservation acceptance has started
    ↓
Check ticket sales/reservation acceptance end date/time
↓
If ticket sales have ended
    → Display a popup that the reservation cannot be made

If ticket sales have not ended
    ↓
Check Performance capacity and requested ticket quantity
↓
If insufficient capacity
    → Display a popup that the reservation cannot be made

If sufficient capacity
    ↓
Display entered reservation details
↓
「予約してよろしいですか？」
↓
Confirmation button
↓
Reservation confirmed
↓
Send reservation confirmation email
```

The ticket reservation acceptance start date/time is checked when the user presses the reservation button. The system checks the current date/time against the Production's configured **チケット販売開始日時**.

The ticket reservation acceptance end date/time is also checked when the user presses the reservation button. The system calculates the end date/time for the selected Performance from the Production's **販売終了** setting:

```text
公演回の [日数]日前の [時]時まで
```

For example, if the Production is configured as:

```text
公演回の [1]日前の [23]時まで
```

ticket reservations for a Performance end at 23:00 on the day before that Performance.

If the current date/time is past the calculated sales end date/time, the reservation is not accepted.

The sales start/end checks are performed at reservation submission time, not merely when the reservation screen is displayed.

The capacity check must use the capacity of the selected Performance.

The ticket sales/reservation acceptance start and end date/times are business conditions for accepting the reservation; they are separate from the publication date/time of ticket information or other Production information.

---

## 6. Email Delivery and Reservation Status

The reservation is confirmed before the reservation confirmation email is sent.

After confirmation, StageArt sends the reservation confirmation email to the specified address.

The important distinction is between **successful email sending** and **the recipient actually seeing/receiving the email**.

### 6.1 Successful sending

If the email can be sent normally to the specified address, the reservation remains established.

This includes cases where:

- The email is delivered to the recipient's normal inbox.
- The email is classified as spam by the recipient's mail service/client.
- The recipient does not notice or read the email.
- The recipient-side mail client otherwise prevents the user from noticing the message, while the sender side has successfully sent the message.

The recipient actually reading or seeing the email is not a condition for reservation establishment.

### 6.2 Unknown/non-existent destination

If the specified email address does not exist or the destination is rejected as an unknown/non-existent recipient, the reservation is cancelled.

Conceptually:

```text
Reservation confirmed
↓
Send reservation confirmation email
↓
Email delivery result
├─ Normal sending / accepted by mail system
│    ↓
│  Reservation remains established
│
└─ Destination unknown / recipient does not exist
     ↓
   Reservation cancelled
```

The purpose of this rule is to prevent a user from reserving tickets with an arbitrary or non-existent email address and thereby occupying capacity without a reachable reservation contact.

A spam-folder classification is not treated as a failed reservation.

---

## 7. Common Processing Principle

The four reservation entry points must converge on the same reservation processing flow.

```text
                 ┌─ StageArt user → 公演を探す
                 │
                 ├─ Public Production page
                 │
Entry points ────┼─ Participant handling page
                 │
                 └─ Participant's own handling
                         ↓
                 Common reservation screen
                         ↓
              Check reservation acceptance
                (開始日時・販売終了日時)
                         ↓
                   Capacity check
                         ↓
                Reservation confirmation
                         ↓
                 Reservation confirmed
                         ↓
            Reservation confirmation email
                         ↓
          ┌──────────────┴──────────────┐
          ↓                             ↓
   Normal sending                Unknown destination
          ↓                             ↓
    Reservation remains             Reservation
       established                  cancelled
```

The entry point is therefore not a separate reservation business function. It only determines the context and initial/fixed values presented by the common reservation screen.

---

## 8. Business Rules Summary

1. All reservation entry points use one common reservation screen.
2. "扱い" and "予約者" are separate concepts.
3. The primary purpose of "扱い" is to associate ticket quantities with the responsible Production participant for ticket sales counting, including ticket-back and ticket sales quota (ノルマ) counting according to the Production's applicable rules.
4. A normal reservation entry point allows the user to select "扱い" from the available Production participants.
5. A participant-specific reservation page fixes "扱い" to the associated participant.
6. A reservation may have no specific "扱い"; such a reservation is not attributed to an individual participant's handling sales count.
7. The user selects the target Performance (公演回).
8. Ticket quantities are entered by ticket type and are **aggregated separately per ticket type**.
9. Different ticket types are not combined into one quantity for ticket-back calculation.
10. A logged-in StageArt user can use the account's registered email without re-entering it.
11. Reservationer name is entered as surname/given name, with separate furigana fields.
12. When the user presses the reservation button, the current date/time is checked against the Production's チケット販売開始日時.
13. If ticket sales/reservation acceptance has not started, the reservation is not made and an error popup is displayed.
14. The current date/time is also checked against the Performance's calculated ticket sales end date/time.
15. The sales end date/time is calculated from the Production-level setting **公演回の［日数］日前の［時］時まで**.
16. If ticket sales/reservation acceptance has ended, the reservation is not made and an error popup is displayed.
17. The start/end acceptance checks are performed when the user presses the reservation button.
18. If ticket sales/reservation acceptance has started and has not ended, the capacity of the selected Performance is checked.
19. If the requested quantity cannot be accommodated, the reservation is not made and an error popup is displayed.
20. If capacity is available, the user confirms the displayed reservation details.
21. Reservation is confirmed before the confirmation email is sent.
22. Normal email sending establishes/retains the reservation even if the recipient does not see the message or it is classified as spam.
23. If the email destination is unknown/non-existent and the email is rejected for that reason, the reservation is cancelled.
24. The recipient actually receiving or reading the email is not itself a business condition for reservation establishment.

---

## 9. Scope Not Yet Defined

This specification does not define the following items unless separately confirmed:

- Online payment
- Seat assignment
- Reservation expiration
- Additional reservation modification rules
- Detailed email retry policy
- Detailed mail-server error classification and technical implementation
- Attendance/completion processing after reception
- Detailed ticket-back calculation rules
- Detailed ticket sales quota (ノルマ) rules

Reservation change/cancellation is specified separately in **Chapter 30 : Ticket Reservation Change / Cancellation Screen**.

These items must not be inferred from this reservation flow specification.
