# StageArt Blueprint
# Chapter 24 : Organization Member Management Screen Specification

Version : 1.0
Status : Confirmed business specification

---

## 1. Purpose

Organization Member Management manages the people who belong to an Organization.

The screen structure and member registration UX are the same as Production Member Management defined in Chapter 21, with the exception that the available **Role** choices are Organization roles rather than Production roles.

Organization membership and Production participation remain separate relationships.

---

## 2. Member Management Main Screen

The Organization Member Management screen provides:

- **「メンバー追加」** link/button
- Existing member records displayed as a list
- Checkbox for each member record
- **「削除」** button

Example:

```text
メンバー管理

[ メンバー追加 ]

☑ 山田 太郎　代表
☐ 山田 太郎　会計
☐ 佐藤 花子　制作
☐ 鈴木 一郎　その他

[ 削除 ]
```

Each list record represents a **Person + Organization Role** assignment.

Multiple records can be selected and deleted.

Deleting a record removes that Organization membership/role assignment only. It does not delete the underlying Person or StageArt account.

---

## 3. Member Add Screen

The Member Add screen uses the same structure as Production Member Add.

```text
メンバー追加

姓　：[　　　　　　]
名　：[　　　　　　]
役割：[ 代表　　 ▼ ]

[ メンバー検索 ]

ID　：[　　　　　　]

[ 保存 ] [ キャンセル ]
```

### 3.1 Name

Enter the person's surname and given name for member search.

Typing a name does not itself establish the Person relationship.

### 3.2 Role

The role is selected from a dropdown.

The dropdown contains the roles available for the Organization.

The available role choices are managed independently from Production roles and may increase or decrease according to the Organization role definition.

### 3.3 Member Search

Pressing **「メンバー検索」** searches registered StageArt users by the entered surname and/or given name.

Search results are displayed in a popup.

When multiple people match, radio buttons allow the administrator to select one person.

Pressing **OK** sets the selected Person ID on the Member Add screen.

### 3.4 Save

Pressing **「保存」** creates the Organization member record using the selected Person ID and Organization Role, then returns to Organization Member Management.

### 3.5 Cancel

Pressing **「キャンセル」** returns to Organization Member Management without creating a record.

---

## 4. Multiple Roles for the Same Person

A single Person may have multiple roles in the same Organization.

Each Person + Organization Role assignment is stored as a separate membership/role record, even when the Person ID is identical.

Example:

```text
Person ID: 12345

12345　山田 太郎　代表
12345　山田 太郎　会計
12345　山田 太郎　制作
```

These are three separate records.

Selecting and deleting one role record does not delete the other role records.

---

## 5. Relationship to Production Membership

Organization membership and Production participation are independent.

An Organization member does not automatically become a Production participant.

Likewise, a Production participant does not automatically become an Organization member.

The Organization Member Management screen therefore manages Organization Membership, while the Production Member Management screen manages Production participation.

---

## 6. Confirmed UX Rules

- Organization Member Management uses the same basic screen structure as Production Member Management.
- Existing members are displayed as selectable records using checkboxes.
- Multiple member records can be selected for deletion.
- 「メンバー追加」 opens the Member Add screen.
- Member Add requires surname/given name information for search and a role selected from a dropdown.
- Member Search searches registered StageArt users by surname and/or given name.
- Search results are presented in a popup.
- Multiple search results are selected using radio buttons.
- OK on the search popup writes the selected Person ID to the Member Add screen.
- Save creates the Organization Membership/role record and returns to Organization Member Management.
- Cancel returns without creating a record.
- The same Person may have multiple roles in the same Organization.
- Each Person + Organization Role assignment is stored as a separate record, even when the Person ID is identical.
- Deleting a member record removes that Organization membership/role assignment only; it does not delete the Person.
- Organization Role choices are independent of Production Role choices.

---

## 7. Difference from Production Member Management

The screen layout and operations are intentionally shared between Organization Member Management and Production Member Management.

The only confirmed business difference in the member-registration UI is the source of the Role dropdown:

```text
Organization Member Management
    ↓
Organization Role dropdown

Production Member Management
    ↓
Production Role dropdown
```

The two relationships remain different in the domain model even though the user-facing member-management workflow is intentionally similar.

---

## 8. Out of Scope

The following are not fixed by this chapter:

- Exact Organization Role master values.
- Detailed invitation screen behavior beyond the existing invitation specification.
- API paths and persistence field names.
- Authorization implementation details.
- Search algorithm details.
- Visual styling.

---

## 9. Status

This chapter is a **Confirmed business specification** for Organization Member Management.

Implementation must follow this specification unless a later Blueprint or Domain specification explicitly supersedes it.
