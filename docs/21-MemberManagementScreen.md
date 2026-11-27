# StageArt Blueprint
# Chapter 21 : Member Management Screen Specification

Version : 1.5
Status : Confirmed business specification

- 1.5 (2026-11-27): Rewritten for the invitation and enrollment policy as implemented on `feature/invitation-simplify` (`docs/03-InvitationAndEnrollmentPolicy.md`). The invited person no longer accepts or rejects: a verified account is added at invitation time, an unregistered address is added once it is registered and verified (or used for Google sign-in). The invitee's invitation list, 「参加する」 / 「参加しない」, the Home card, 「同意の確認を依頼」 and the consent labels were removed; the invitation e-mail opens `/participant-invitations/{id}`.
- 1.4 (2026-11-27): Added notes for the invitation and enrollment policy decided by the product owner.
- 1.3 (2026-11-19): Revised to match PR #7 R14-B (participation based on the person's own consent), as implemented on `feature/r14b-participant-consent` at `e645b46`. Kept as history in `docs/handoffs/PR7-R14B-ParticipantInvitationDesign.md` §10 (第9版).
- 1.2: Added ノルマ / チケットバック.

---

## 1. Purpose

This document defines the screen behavior for managing members/participants of a Production, and the screen an invited person opens from the invitation e-mail.

Production member management is performed in the context of a Production and represents Production participation. It does not modify or delete the underlying Person account/profile.

**The administrator confirms the person's intention to take part outside StageArt before inviting them** (`03-InvitationAndEnrollmentPolicy.md`). The invited person does not accept or reject the invitation and is not asked to confirm again: when the authenticated account's verified e-mail address matches the invited address, the person is registered as a member automatically. Organizations follow the same principle. A person can also become a member by having their own participation request approved (a separate flow). The screens never look a person up by name, and there is no Person ID input.

Production member management also includes the member-level information required for ticket sales management, specifically **ノルマ** and **チケットバック**.

---

## 2. Member Management Main Screen

Screen: Production → メンバー管理 (`/productions/{id}/participants`).

The screen is available to the Production's PrimaryManager and to an effective delegate with the PARTICIPANT_MANAGER role. Others see a message that they cannot manage members.

The screen has, from top to bottom:

1. the member list (§2.1), whose last row is the invite form with the 「＋ メンバーを招待」 button (§3),
2. messages (the result of an invitation, or why the typed address cannot be invited now),
3. the invitation list 「招待中のメンバー」 (§4), shown when there is at least one listed invitation,
4. the member information publication date/time and the [ 更新 ] button (§2.2).

```text
メンバー管理

削除  名前            役割        備考           メールアドレス      代理人 会計担当 受付担当
□     山田 太郎       [出演者▼]   [チームA  ]    yamada@…             ☑      □        □
□     佐藤 花子       [スタッフ▼] [         ]    sato@…               □      □        □
□     あなた          [出演者▼]   [         ]    …
      [ 氏名 ]        [出演者▼]   [ 備考    ]    [ メールアドレス ]  [＋ メンバーを招待]

招待中のメンバー
氏名  メールアドレス  役割  状態  送信の状態  残りの送信回数  有効期限  操作
…                                                                    [再送] [取消]

メンバー情報公開日時 [ YYYY/MM/DD ] [ HH:MM ] [クリア]
                                                                      [ 更新 ]
```

### 2.1 Member List

The list shows the Production's **ACTIVE** members. Each record represents one Participant record (a Person, or a name-only member, with one role).

| Column | Content |
|---|---|
| 削除 | Checkbox to select the record for deletion (applied by [ 更新 ]) |
| 名前 | The member's display name; 「あなた」 for the viewer's own record |
| 役割 | 出演者 (CAST) / スタッフ (STAFF), changeable by dropdown (applied by [ 更新 ]) |
| 備考 | Production-specific remarks, editable in the list (applied by [ 更新 ]) |
| メールアドレス | Shown according to the disclosure rules (§6) |
| 代理人 / 会計担当 / 受付担当 | Delegate role checkboxes, shown to the PrimaryManager and to the delegate who may assign them |

No consent state is shown, and there is no 「同意の確認を依頼」 (removed 2026-11-27): an ACTIVE member is enough for a delegate role.

### 2.2 Update

The **[ 更新 ]** button applies the following together and then refreshes the list:

- deletions of the checked members,
- role and remarks changes,
- the member information publication date/time.

Deletion removes the Production participation of the selected record. The record becomes CANCELLED and is kept, together with its attendance and accounting records. It does **not** delete the underlying Person or StageArt account. The member's delegate roles in this Production are released at the same time.

The list does not change a member's status in any other way. A member who was made INACTIVE cannot be made ACTIVE again from this screen. They return by being invited again (registered from the invitation) or by having a participation request approved.

Inviting (§3) is a separate, immediate action and is not part of [ 更新 ].

---

## 3. Invite Form

The last row of the member list is the invite form:

```text
[ 氏名 ]　[ 出演者 ▼ ]　[ 備考 ]　[ メールアドレス ]　[ ＋ メンバーを招待 ]
```

- 氏名 and メールアドレス are required; the button is disabled until both are entered. 役割 is 出演者 or スタッフ. 備考 is optional.
- When the typed address has reached a sending limit for this Production (§4), the button is disabled and the reason is shown.
- The results:
  - the address belongs to a verified StageArt account: the person is added at once, no e-mail is sent — 「メンバーに追加しました。」;
  - otherwise a new invitation is e-mailed — 「招待メールを送信しました。招待したメールアドレスで登録とメールアドレスの確認が済むと、メンバーに追加されます。」;
  - an invitation to the same address and role is already waiting and is resent — 「登録待ちの招待に再送しました。」.
- Refusals: 「この方は既にこの役割で公演のメンバーです。」 (already an ACTIVE member with that role), 「この方の参加の情報に確認が必要なため、メンバーに追加できません。下書きの登録を確認してください。」 (a DRAFT record), a closed Production, and the sending limits.
- The Production invitation is valid for 7 days from when it is sent; a resend extends it to 7 days from the resend. (Organization invitations are valid for 24 hours.) The e-mail contains no token. It links to the invitation's own screen, `/participant-invitations/{id}` (§7).

The screen has no member search, no search popup and no Person ID field.

A name-only member (NAME_ONLY: a member recorded by name only, with no StageArt Person) can be recorded through the API, but this screen does not provide an input for it.

---

## 4. Invitation List 「招待中のメンバー」

The list shows this Production's invitations except completed (registered) and past accepted ones.

| Column | Content |
|---|---|
| 氏名 / メールアドレス / 役割 | As entered when inviting |
| 状態 | 登録待ち / 期限切れ / 無効（公演終了） / 無効（招待者の権限なし） / 取消 / 辞退（以前の方式） (a past R14-B rejection, kept as history) |
| 送信の状態 | 送信中 / 送信しました（相手に届いたかどうかは確認できません） / 送信に失敗しました。再送してください（再送も送信回数に数えます） / 送信の結果を確認できませんでした。メールが届いていない場合は再送してください. Also shows the last sending time |
| 残りの送信回数 | This invitation's remaining resends (5 in total) and the remaining sends to this address in 30 days (6 in total); 「確認できません」 when it cannot be counted |
| 有効期限 | Expiry of the invitation |
| 操作 | [再送] and [取消] for an invitation whose stored status is still PENDING (waiting to be registered) |

- [再送] is disabled when a limit is reached. The reason is shown:
  - 「このメールアドレスへの送信は30日間で6回までです。」 with the date from which it is possible again;
  - or the 5-resend limit of this invitation.
- There is no 30-day block after a rejection any more (removed 2026-11-27).

---

## 5. Multiple Roles for the Same Person

A single Person may take part in the same Production as both 出演者 and スタッフ. Each role is a **separate Production member/participant record**, even though the Person is the same.

The system must not collapse these records into one merely because the Person is the same. The member list and the delete operation operate at the **record level**.

Changing a record's role to a role for which the same Person already has a record in this Production is refused.

---

## 6. Display of Personal Information

The member data returned to the screen is limited by who is viewing (design doc §7.3). A withheld item is not shown.

- The PrimaryManager and PARTICIPANT_MANAGER delegates:
  - see family/given name and e-mail address of ACTIVE / INACTIVE members;
  - see only the display name and status of ended records (CANCELLED / WITHDRAWN / REJECTED).
- A REHEARSAL_MANAGER delegate (read-only, for choosing rehearsal attendees, outside this screen):
  - sees names of ACTIVE / INACTIVE members, no e-mail address;
  - does not see ended records at all.
- After the Production ends (COMPLETED / ARCHIVED / CANCELLED):
  - a REHEARSAL_MANAGER also no longer sees names;
  - only the PrimaryManager sees e-mail addresses.
- The viewer's own record is always shown in full, as 「あなた」.

The API still returns the consent history fields to managers (`08-API/ParticipantAPI.md`); the screen does not show them.

---

## 7. Invitee Screens

### 7.1 Invitation Screen (`/participant-invitations/{id}`, login required)

The invitation e-mail opens this screen. A logged-out visitor logs in first and comes back here (§8.2). Opening it is **not** an acceptance; it only shows the invitation.

- The invitation is shown only when its address is one of the logged-in person's verified addresses (the login address or a verified notification address). Otherwise — another person's invitation, an unknown one — only 「この招待は表示できません。招待メールを受け取ったメールアドレスでログインしているか確認してください。」 and the way Home are shown.
- Shown: the Production name, the role, and the state:
  - 登録済み 「この公演の参加者として登録されています。」, with 「公演を開く」 (to the Production's overview);
  - 登録待ち 「招待されたメールアドレスの確認が完了すると、この公演の参加者として登録されます。」;
  - 期限切れ 「この招待は有効期限が切れています。公演の管理者に再送を依頼してください。」;
  - 取消 「この招待は取り消されました。」;
  - 終了 「この招待は終了しています。」.
- There is no 「参加する」 / 「参加しない」.

A person with a verified account who is invited is added at once and receives no invitation e-mail; this screen is for a person who was not registered (or not verified) when invited.

The organization invitation e-mail opens the same kind of screen, `/organization-invitations/{id}` (「団体への招待」, with 「団体を開く」 when registered).

### 7.2 Home

There is no invitation card on Home any more (removed 2026-11-27): nothing waits for the person's answer. `/participant-invitations` (the link of e-mails sent before) goes to Home.

### 7.3 Participating Productions — Withdrawal

On the person's own list of participating Productions (`/participating-productions`), each Production offers 「参加をやめる」 behind a confirmation:

```text
「{公演名}」の参加をやめますか？
担当者の役割も解除されます。
[ 参加をやめる ] [ キャンセル ]
```

- The record becomes WITHDRAWN and is kept, with its attendance and accounting records. The person's delegate roles in that Production are released.
- The PrimaryManager of the Production is not offered the button. Instead: 「公演の管理者（PrimaryManager）のため、参加をやめるには先に管理者を引き継いでください。」.

### 7.4 Own Delegate Role Notice

On a Production's overview, a person whose delegate role is not effective sees why: 「あなたの担当者の役割は、この公演のメンバーではないため無効になっています。」.

On the delegate screen, the PrimaryManager sees each ineffective delegate with its reason and the next step: 「この公演のメンバーではないため無効です。メンバーとして登録してから、改めて任命してください」 or 「解除済み」.

The delegate screen offers a link to this member management screen. Every ACTIVE PERSON member is offered as a delegate candidate (with or without recorded consent, 2026-11-27).

---

## 8. Invitation Links, Login and `return_to`

### 8.1 Invitation E-mails

- New Production invitation e-mails link to `/participant-invitations/{id}`; new organization invitation e-mails link to `/organization-invitations/{id}`. They carry no token; the id alone shows nothing (§7.1).
- E-mails sent before:
  - `/participant-invitations` (the former invitation list) goes to Home (logged out: login first, then Home);
  - `/register` (organization invitation e-mails of R14-B stage 10) is the registration screen;
  - `/register?token=...` (before R14-B): the token is not read, not looked up, and not carried into the URL or `return_to`; nothing about any invitation is shown; logged out → login with `return_to=/participant-invitations`; logged in → `/participant-invitations` (then Home).

The backend registration API still accepts an organization invitation's `invitation_token` (kept; the app never sends it).

### 8.2 `return_to` after Login

- Opening `/participant-invitations/...` or `/organization-invitations/...` while logged out leads to the login screen with that path as `return_to`.
- After login (e-mail or Google), the person is taken to the `return_to` path. When the person has not set a name yet, they go through the name screen (`/set-name`) with the same `return_to`.
- The login screen's `return_to` is carried through registration and registration-pending back to login. After the e-mail address is confirmed, the confirmation screen leads to the ordinary login (and then Home): the registration's `return_to` is not kept on the device (A1 was removed, policy §2). To continue with an invitation, the person opens the invitation e-mail again after logging in.
- `return_to` is followed only when it is a path inside the app:
  - it starts with a single `/`;
  - it has no scheme, host, backslash or control character;
  - it is not an authentication screen (login, register, etc.).
- Otherwise the usual route (Home) is used.

---

## 9. Relationship to Person and Production Participant

The Member Management screen operates on Production participation, not on the Person entity itself.

Conceptually:

```text
Person
  ↓ (registered from an invitation / participation request approved)
Production Participant / Member Record
  ├─ Person (or name only)
  ├─ Role (出演者 / スタッフ)
  ├─ ノルマ
  └─ チケットバック
```

The Person remains an independent StageArt entity.

Adding, changing or ending a Production member record must not create, modify, or delete the person's general StageArt profile. The only exception is the Production participation relationship explicitly represented by the operation.

A Production member may be an existing Organization member or an external/guest participant, according to the Production participation rules. Organization membership does not make anyone a Production member.

---

## 10. Ticket Sales Management Purpose

The **ノルマ** and **チケットバック** information associated with a Production member is used in conjunction with the reservation's **扱い** information.

When a reservation is recorded with a particular member as its **扱い**, the reserved ticket quantity can be counted against that member's ticket sales performance.

The member's ticket sales count is used for the Production's ticket sales management, including the counting required for **ノルマ** and **チケットバック**.

The detailed calculation method, thresholds, rates, and settlement rules for ノルマ and チケットバック are not defined by this screen specification unless separately confirmed.

---

## 11. Confirmed UX Rules

- Member Management is a Production-level operation, available to the PrimaryManager and effective PARTICIPANT_MANAGER delegates.
- The administrator confirms the person's intention before inviting them; nobody accepts or rejects an invitation in StageArt.
- 「＋ メンバーを招待」 by 氏名＋メールアドレス＋役割＋備考: a verified account is added at once ("added"); otherwise an invitation is e-mailed ("sent") or resent ("resent"), and the person is added once the invited address is registered and verified (or used for Google sign-in).
- There is no member search and no Person ID input.
- The member list shows ACTIVE members, with checkboxes for deletion, role dropdowns and editable remarks. [ 更新 ] applies them together with the publication date/time.
- Deleting a member record ends that Production participation only (CANCELLED, kept); it does not delete the Person. Their delegate roles in the Production are released.
- An INACTIVE member cannot be made ACTIVE again from the screen.
- No consent state is shown; an ACTIVE member can be given delegate roles.
- The invitation list shows status, sending status and remaining sends, with 再送 / 取消. Sending limits:
  - 5 resends per invitation;
  - 6 sends per address per Production in 30 days.
- The same Person may have one record per role (出演者 / スタッフ) in the same Production.
- Personal information is shown according to the viewer's role and the Production's status (§6).
- The invitation e-mail opens the invitation's own screen (login required, verified addresses only, no token); opening it is not an acceptance.
- A member can withdraw from their participating Productions, except the PrimaryManager.
- Old links never use a token; `/participant-invitations` goes to Home.
- Production member records include the Production-specific concepts of **ノルマ** and **チケットバック**.
- Ticket quantities reserved with a member as **扱い** are used as that member's ticket sales count for the Production's ticket sales management, including ノルマ and チケットバック counting.

---

## 12. Scope

This chapter specifies the confirmed screen behavior and business rules for Production Member Management and the invitation screens.

The following are implementation/UX or separate business-rule details unless separately confirmed:

- detailed API paths and fields (see `docs/08-API/ParticipantAPI.md` and `docs/03-InvitationAndEnrollmentPolicy.md` §3.2);
- persistence field names and the authorization implementation;
- wording and visual styling;
- the detailed calculation/settlement rules for ノルマ and チケットバック.

Organization member management is specified in `docs/24-OrganizationMemberManagementScreen.md`. Organization invitations follow the same invitation and enrollment policy (`03-InvitationAndEnrollmentPolicy.md` §3); their screen is `/organization-invitations/{id}` (§7.1).
