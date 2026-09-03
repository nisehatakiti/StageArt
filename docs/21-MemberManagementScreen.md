# StageArt Blueprint
# Chapter 21 : Member Management Screen Specification

Version : 1.2
Status : Confirmed business specification

---

## 1. Purpose

This document defines the screen behavior for managing members/participants of a Production.

Production member management is performed in the context of a Production and represents Production participation. It does not modify or delete the underlying Person account/profile.

Production member management also includes the member-level information required for ticket sales management, specifically **ノルマ** and **チケットバック**.

---

## 2. Member Management Main Screen

The Production Member Management screen provides the following operations.

### 2.1 Member Add

Display a **「メンバー追加」** link/button.

Selecting it opens the Member Add screen.

### 2.2 Member List

Display the existing Production members as a list.

Each list record represents a **Person + Role** assignment.

The member record also has the Production-specific **ノルマ** and **チケットバック** information used for ticket sales management.

Each record has a checkbox so that multiple records can be selected.

Example:

```text
メンバー管理

[ メンバー追加 ]

☑ 山田 太郎　演出
☐ 山田 太郎　脚本
☐ 佐藤 花子　出演
☐ 鈴木 一郎　照明

[ 削除 ]
```

The exact presentation and editing controls for ノルマ and チケットバック are UX details unless separately confirmed.

### 2.3 Member Delete

The **「削除」** button deletes the selected member/role records from the Production.

Deletion removes the Production participation/role assignment represented by the selected record. It does **not** delete the underlying Person or StageArt account.

Because the list is record-based, when the same Person has multiple roles, an individual role record can be deleted without deleting the Person's other role records.

---

## 3. Member Add Screen

The Member Add screen provides the following fields and operations.

```text
メンバー追加

姓　：[　　　　　　]
名　：[　　　　　　]
役割：[ 演出　　 ▼ ]

[ メンバー検索 ]

ID　：[　　　　　　]

[ 保存 ] [ キャンセル ]
```

The Production member record may additionally contain the Production-specific **ノルマ** and **チケットバック** information. The exact input UI and calculation details are not fixed by this chapter unless separately confirmed.

### 3.1 Name

Enter the person's:

- 姓
- 名

These values are used as search conditions for finding an existing registered StageArt user.

Entering a name alone does not establish the Person relationship.

### 3.2 Role

A **役割** field is displayed to the right of the name fields.

The role is selected from a dropdown list of available Production roles.

The selected role is part of the Production member record.

### 3.3 Member Search

Pressing **「メンバー検索」** searches registered StageArt users when a surname or given name has been entered.

Search results are displayed in a popup.

When multiple people match the search conditions, radio buttons are displayed so that the administrator can select one person.

Pressing **OK** on the popup sets the selected person's ID on the Member Add screen.

The Person ID is therefore established by selecting a search result, not merely by typing a name.

### 3.4 Save

Pressing **「保存」** creates the Production member record using the selected Person ID and selected Role, then returns to the Member Management screen.

### 3.5 Cancel

Pressing **「キャンセル」** returns to the Member Management screen without adding a member record.

---

## 4. Multiple Roles for the Same Person

A single Person may have multiple roles in the same Production.

When the same Person is assigned multiple roles, each role is registered as a **separate Production member/participant record**, even though the Person ID is identical.

Example:

```text
Person ID: 12345

12345　山田 太郎　演出
12345　山田 太郎　脚本
12345　山田 太郎　出演
```

These are three separate records.

The system must not collapse these assignments into one record merely because the Person ID is the same.

This also means that the Member Management list and delete operation operate at the **Person + Role record level**.

---

## 5. Relationship to Person and Production Participant

The Member Management screen operates on Production participation, not on the Person entity itself.

Conceptually:

```text
Person
  ↓
Production Participant / Member Record
  ├─ Person ID
  ├─ Role
  ├─ ノルマ
  └─ チケットバック
```

The Person remains an independent StageArt entity.

Adding or deleting a Production member record must not create, modify, or delete the person's general StageArt profile except for the Production participation relationship explicitly represented by the operation.

A Production member may be an existing Organization member or an external/guest participant, according to the Production participation rules.

---

## 6. Ticket Sales Management Purpose

The **ノルマ** and **チケットバック** information associated with a Production member is used in conjunction with the reservation's **扱い** information.

When a reservation is recorded with a particular member as its **扱い**, the reserved ticket quantity can be counted against that member's ticket sales performance.

The member's ticket sales count is used for the Production's ticket sales management, including the counting required for **ノルマ** and **チケットバック**.

The detailed calculation method, thresholds, rates, and settlement rules for ノルマ and チケットバック are not defined by this screen specification unless separately confirmed.

---

## 7. Confirmed UX Rules

- Member Management is a Production-level operation.
- Existing members are displayed as selectable records using checkboxes.
- Multiple member records can be selected for deletion.
- 「メンバー追加」 opens the Member Add screen.
- Member Add requires surname/given name information for search and a role selected from a dropdown.
- Member Search searches registered StageArt users by surname and/or given name.
- Search results are presented in a popup.
- Multiple search results are selected using radio buttons.
- OK on the search popup writes the selected Person ID to the Member Add screen.
- Save creates the Production member/participant record and returns to Member Management.
- Cancel returns without creating a record.
- The same Person may have multiple roles in the same Production.
- Each Person + Role assignment is stored as a separate record, even when the Person ID is identical.
- Deleting a member record removes that Production participation/role assignment only; it does not delete the Person.
- Production member records include the Production-specific concepts of **ノルマ** and **チケットバック**.
- Ticket quantities reserved with a member as **扱い** are used as that member's ticket sales count for the Production's ticket sales management, including ノルマ and チケットバック counting.

---

## 8. Scope

This chapter specifies the confirmed screen behavior and business rules for Production Member Management.

Detailed API paths, persistence field names, authorization implementation, search algorithm, popup wording, visual styling, and the detailed calculation/settlement rules for ノルマ and チケットバック are implementation/UX or separate business-rule details unless separately confirmed.
