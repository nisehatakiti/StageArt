# StageArt Blueprint
# Chapter 25 : Production Ticket Sales Rules — Quota and Ticket Back

Version : 1.0
Status : Confirmed business specification

---

## 1. Purpose

This document records the confirmed relationship between Production information, Production member management, and ticket sales management concerning **ノルマ** and **チケットバック**.

The detailed calculation and settlement rules are intentionally not defined here unless separately confirmed.

---

## 2. Production-level Management

The Production information/management area must include the concepts of:

- **ノルマ**
- **チケットバック**

These are Production-level ticket sales management information and are used together with the member-level settings and ticket reservation results.

The exact input method, units, calculation formula, thresholds, rates, and settlement procedure are not fixed by this chapter unless separately confirmed.

---

## 3. Member-level Management

Production Member Management must include the concepts of:

- **ノルマ**
- **チケットバック**

These values/information are associated with the Production member record and are used for that member's ticket sales management.

A Production member record is associated with a Person and Role. The member-level ticket sales management information is therefore managed in the Production context and does not modify the Person's general profile.

---

## 4. Relationship with Reservation Handling

A ticket reservation may have a Production member recorded as its **扱い**.

The ticket quantity of reservations associated with a member as **扱い** is used as that member's ticket sales count.

This ticket sales count is used for the counting required for:

- **ノルマ**
- **チケットバック**

A reservation without a specified **扱い** is not attributed to an individual member for this purpose.

---

## 5. Business Relationship

The confirmed relationship is:

```text
Production
├─ ノルマ
├─ チケットバック
│
└─ Production Member
     ├─ ノルマ
     ├─ チケットバック
     └─ 扱いとして紐づく予約
            ↓
        チケット販売枚数
            ↓
      ノルマ・チケットバックのカウント
```

The purpose of **扱い** is therefore not merely to identify the person who introduced a reservation. It provides the link required to count ticket sales by Production member for the Production's ノルマ and チケットバック management.

---

## 6. Scope Not Yet Defined

This specification does not define:

- The exact value/type of ノルマ.
- Whether ノルマ is expressed only as a ticket quantity or through another representation.
- The exact チケットバック rate or amount.
- Whether ticket-back calculation differs by ticket type.
- The exact calculation formula.
- The timing or method of settlement/payment.
- Treatment of cancelled reservations in the final sales count.
- Other accounting rules related to ticket sales.

These items must be confirmed separately before implementation.
