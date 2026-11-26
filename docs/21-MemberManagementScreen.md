# StageArt Blueprint
# Chapter 21 : Member Management Screen Specification

Version : 1.3
Status : Confirmed business specification

- 1.3 (2026-11-19): Revised to match PR #7 R14-B (participation based on the person's own consent). This version describes the screens as implemented on `feature/r14b-participant-consent` at `e645b46` (checked against the mobile-rn screens; not yet verified on Dev or in a browser). Members are no longer added by searching for a registered user and selecting a Person ID; a person is invited by 氏名＋メールアドレス and becomes a member only by accepting. The invitee-side screens, the person's own withdrawal, old invitation links and the post-login `return_to` are added. The source of truth for the design is `docs/handoffs/PR7-R14B-ParticipantInvitationDesign.md` §10 (design branch `docs/r14b-participant-invitation-design`, 第9版 `9b42eb5`); the APIs are in `docs/08-API/ParticipantAPI.md`「実装済みエンドポイント（PR #7 R14-B）」.
- 1.2: Added ノルマ / チケットバック.

---

## 1. Purpose

This document defines the screen behavior for managing members/participants of a Production, and the screens on which an invited person answers an invitation.

Production member management is performed in the context of a Production and represents Production participation. It does not modify or delete the underlying Person account/profile.

**A person becomes a Production member only with their own consent**: by accepting an invitation, or by having their own participation request approved. An administrator cannot add a registered StageArt user directly, and the screens never look a person up by name or email address.

Production member management also includes the member-level information required for ticket sales management, specifically **ノルマ** and **チケットバック**.

---

## 2. Member Management Main Screen

Screen: Production → メンバー管理 (`/productions/{id}/participants`).

The screen is available to the Production's PrimaryManager and to an effective delegate with the PARTICIPANT_MANAGER role. Others see a message that they cannot manage members.

The screen has, from top to bottom:

1. the member list (§2.1), whose last row is the invite form with the 「＋ メンバーを招待」 button (§3),
2. messages (the result of an invitation, or why the typed address cannot be invited now),
3. the invitation list 「招待中のメンバー」 (§4), shown when there is at least one listed invitation,
4. the member information publication date/time and the [ 更新 ] button (§2.3).

```text
メンバー管理

削除  名前            役割        備考           メールアドレス      操作                    代理人 会計担当 受付担当
□     山田 太郎       [出演者▼]   [チームA  ]    yamada@…                                     ☑      □        □
□     佐藤 花子       [スタッフ▼] [         ]    sato@…              [同意の確認を依頼]        □      □        □
      同意未確認（旧方式）
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
| 操作 | 「同意の確認を依頼」 for a member whose consent is not confirmed (§2.2) |
| 代理人 / 会計担当 / 受付担当 | Delegate role checkboxes, shown to the PrimaryManager and to the delegate who may assign them |

Below the name, a member whose consent is not confirmed is marked:

- 「同意未確認」, or
- 「同意未確認（旧方式）」 for a member recorded before R14-B.

### 2.2 Consent Confirmation Request

Members recorded before R14-B have no recorded consent. Their delegate roles grant nothing until consent is recorded.

Pressing **「同意の確認を依頼」** sends that member an ordinary invitation to their address, with the same role. When they accept it, their consent is recorded and their record stays as it is. Declining changes nothing.

When the member's address is not available, the button is disabled and 「メールアドレスが不明です」 is shown.

### 2.3 Update

The **[ 更新 ]** button applies the following together and then refreshes the list:

- deletions of the checked members,
- role and remarks changes,
- the member information publication date/time.

Deletion removes the Production participation of the selected record. The record becomes CANCELLED and is kept, together with its attendance and accounting records. It does **not** delete the underlying Person or StageArt account. The member's delegate roles in this Production are released at the same time.

The list does not change a member's status in any other way. A member who was made INACTIVE cannot be made ACTIVE again from this screen. They return only by accepting a new invitation or by having a participation request approved.

Inviting (§3) is a separate, immediate action and is not part of [ 更新 ].

---

## 3. Invite Form

The last row of the member list is the invite form:

```text
[ 氏名 ]　[ 出演者 ▼ ]　[ 備考 ]　[ メールアドレス ]　[ ＋ メンバーを招待 ]
```

- 氏名 and メールアドレス are required; the button is disabled until both are entered. 役割 is 出演者 or スタッフ. 備考 is optional.
- When the typed address has reached a sending limit for this Production (§4), the button is disabled and the reason is shown.
- Pressing the button sends an invitation **whatever the address belongs to**. The screen never shows whether the address belongs to a registered user, and never adds anyone directly.
- There are exactly two results:
  - a new invitation: 「招待メールを送信しました。本人が承諾するとメンバーに追加されます。」;
  - an invitation to the same address and role is already waiting and is resent: 「承諾待ちの招待に再送しました。」.
- The Production invitation is valid for 7 days from when it is sent; a resend extends it to 7 days from the resend. (Organization invitations, a separate feature, are valid for 24 hours.) The e-mail contains no token. It links to the invitee's invitation list (§7).

The screen has no member search, no search popup and no Person ID field.

A name-only member (NAME_ONLY: a member recorded by name only, with no StageArt Person and outside consent) can be recorded through the API, but this screen does not provide an input for it.

---

## 4. Invitation List 「招待中のメンバー」

The list shows this Production's invitations except accepted ones.

| Column | Content |
|---|---|
| 氏名 / メールアドレス / 役割 | As entered when inviting |
| 状態 | 承諾待ち / 期限切れ / 無効（公演終了） / 無効（招待者の権限なし） / 辞退 / 取消 |
| 送信の状態 | 送信中 / 送信しました（相手に届いたかどうかは確認できません） / 送信に失敗しました。再送してください（再送も送信回数に数えます） / 送信の結果を確認できませんでした。メールが届いていない場合は再送してください. Also shows the last sending time |
| 残りの送信回数 | This invitation's remaining resends (5 in total) and the remaining sends to this address in 30 days (6 in total); 「確認できません」 when it cannot be counted |
| 有効期限 | Expiry of the invitation |
| 操作 | [再送] and [取消] for an invitation whose stored status is still PENDING (waiting for an answer) |

- [再送] is disabled when a limit is reached. The reason is shown:
  - 「このメールアドレスへの送信は30日間で6回までです。」 with the date from which it is possible again;
  - or the 5-resend limit of this invitation.
- For a person who declined, a new invitation to the same Production is not possible for 30 days after the rejection.

---

## 5. Multiple Roles for the Same Person

A single Person may take part in the same Production as both 出演者 and スタッフ. Each role is a **separate Production member/participant record**, even though the Person is the same.

The system must not collapse these records into one merely because the Person is the same. The member list and the delete operation operate at the **record level**.

Changing a record's role to a role for which the same Person already has a record in this Production is refused.

---

## 6. Display of Personal Information

The member data returned to the screen is limited by who is viewing (design doc §7.3). A withheld item is not shown.

- The PrimaryManager and PARTICIPANT_MANAGER delegates:
  - see family/given name and e-mail address of ACTIVE / INACTIVE members, and the consent information;
  - see only the display name and status of ended records (CANCELLED / WITHDRAWN / REJECTED).
- A REHEARSAL_MANAGER delegate (read-only, for choosing rehearsal attendees, outside this screen):
  - sees names of ACTIVE / INACTIVE members, no e-mail address and no consent information;
  - does not see ended records at all.
- After the Production ends (COMPLETED / ARCHIVED / CANCELLED):
  - a REHEARSAL_MANAGER also no longer sees names;
  - only the PrimaryManager sees e-mail addresses.
- The viewer's own record is always shown in full, as 「あなた」.

---

## 7. Invitee Screens

### 7.1 Home

Home shows 「公演への招待」 with the number of invitations the person can accept now, and the way to the invitation list. No in-app notification is created when an invitation is made, so the invitation e-mail and this count are how a registered person notices an invitation.

### 7.2 Invitation List (`/participant-invitations`, login required)

The list shows the invitations addressed to the logged-in person's verified e-mail addresses (the login address and a verified notification address).

- An invitation that can no longer be accepted shows the reason:
  - 「公演が終了または中止されたため、承諾できません」;
  - 「招待した管理者の権限がなくなったため、承諾できません」.
- Such an invitation can still be declined.
- Expired, answered and cancelled invitations are not listed.

An invitation sent to a different address than the one the person registered with does not appear.

### 7.3 Invitation Detail (`/participant-invitations/{id}`)

- [ 参加する ] accepts the invitation. The result is 「参加しました。公演のメンバーになりました。」, with a link to the person's participating Productions.
- [ 参加しない ] declines it: 「招待を辞退しました。」. Declining creates no member record.
- An invitation that cannot be shown: 「この招待は表示できません。招待一覧から確認してください。」.

### 7.4 Participating Productions — Withdrawal

On the person's own list of participating Productions (`/participating-productions`), each Production offers 「参加をやめる」 behind a confirmation:

```text
「{公演名}」の参加をやめますか？
担当者の役割も解除されます。
[ 参加をやめる ] [ キャンセル ]
```

- The record becomes WITHDRAWN and is kept, with its attendance and accounting records. The person's delegate roles in that Production are released.
- The PrimaryManager of the Production is not offered the button. Instead: 「公演の管理者（PrimaryManager）のため、参加をやめるには先に管理者を引き継いでください。」.

### 7.5 Own Delegate Role Notice

On a Production's overview, a person whose delegate role is not effective sees why:

- 「あなたの担当者の役割は、参加への同意が確認できていないため無効になっています。…」;
- 「あなたの担当者の役割は、この公演のメンバーではないため無効になっています。」.

On the delegate screen, the PrimaryManager sees each ineffective delegate with its reason and the next step:

- 「同意未確認のため無効です。本人に同意の確認を依頼してください」;
- 「この公演のメンバーではないため無効です。招待して、承諾後に改めて任命してください」.

The delegate screen offers a link to this member management screen. Only consented ACTIVE members are offered as delegate candidates.

---

## 8. Invitation Links, Login and `return_to`

### 8.1 New Invitation E-mail

The Production invitation e-mail links to `/participant-invitations`. It carries no token.

The person signs in with the address that received the e-mail (or registers with it and confirms it) and sees the invitation in the list.

### 8.2 Old Invitation Links (`/register?token=...`)

An invitation e-mail sent before R14-B linked to `/register?token=...`. Opening such a link:

- the token is not read, not looked up, and not carried into the URL or `return_to`;
- no registration form and nothing about any invitation is shown;
- logged out: the person is moved to the login screen with `return_to=/participant-invitations`;
- logged in: the person is moved to `/participant-invitations`.

This is the app's (Frontend's) handling of the URL. It is separate from the backend registration API, which still accepts an organization invitation's `invitation_token` (existing behaviour kept under D1; the app never sends it; to be cleaned up in R14-C).

An organization invitation e-mail sent before the D-1 change (R14-B stage 10, `e645b46`) also used `/register?token=...` and is handled the same way in the app. New organization invitation e-mails link to `/register` without a token. Organization invitations themselves (24-hour expiry, membership resolved by the verified address) are unchanged and are outside this chapter.

### 8.3 `return_to` after Login

- Opening `/participant-invitations` (or an invitation detail) while logged out leads to the login screen with that path as `return_to`.
- After login (e-mail or Google), the person is taken to the `return_to` path.
- When the person has not set a name yet, they go through the name screen (`/set-name`) with the same `return_to`, and are taken there after saving the name.
- `return_to` is followed only when it is a path inside the app:
  - it starts with a single `/`;
  - it has no scheme, host, backslash or control character;
  - it is not an authentication screen (login, register, etc.).
- Otherwise the usual route (Home) is used.
- The registration screen does not receive `return_to`. A newly registered person confirms their e-mail address, then reaches the invitation list from Home.

---

## 9. Relationship to Person and Production Participant

The Member Management screen operates on Production participation, not on the Person entity itself.

Conceptually:

```text
Person
  ↓ (own consent: invitation accepted / participation request approved)
Production Participant / Member Record
  ├─ Person (or name only)
  ├─ Role (出演者 / スタッフ)
  ├─ Consent (confirmed / unconfirmed)
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
- A person becomes a member only by their own consent (accepting an invitation, or an approved participation request). The screen never adds a registered user directly.
- 「＋ メンバーを招待」 sends an invitation by 氏名＋メールアドレス＋役割＋備考, whatever the address belongs to. The result is either "sent" or "resent".
- There is no member search and no Person ID input.
- The member list shows ACTIVE members, with checkboxes for deletion, role dropdowns and editable remarks. [ 更新 ] applies them together with the publication date/time.
- Deleting a member record ends that Production participation only (CANCELLED, kept); it does not delete the Person. Their delegate roles in the Production are released.
- An INACTIVE member cannot be made ACTIVE again from the screen.
- Members whose consent is not confirmed are marked. 「同意の確認を依頼」 sends them an invitation whose acceptance records consent.
- The invitation list shows status, sending status and remaining sends, with 再送 / 取消. Sending limits:
  - 5 resends per invitation;
  - 6 sends per address per Production in 30 days;
  - no new invitation for 30 days after a rejection.
- The same Person may have one record per role (出演者 / スタッフ) in the same Production.
- Personal information is shown according to the viewer's role and the Production's status (§6).
- The invitee answers on the invitation list / detail (login required, verified addresses only, no token).
- A member can withdraw from their participating Productions, except the PrimaryManager.
- Old `/register?token=...` links never use the token and lead to login (with `return_to=/participant-invitations`) or to the invitation list.
- Production member records include the Production-specific concepts of **ノルマ** and **チケットバック**.
- Ticket quantities reserved with a member as **扱い** are used as that member's ticket sales count for the Production's ticket sales management, including ノルマ and チケットバック counting.

---

## 12. Scope

This chapter specifies the confirmed screen behavior and business rules for Production Member Management and the invitee screens of PR #7 R14-B.

The following are implementation/UX or separate business-rule details unless separately confirmed:

- detailed API paths and fields (see `docs/08-API/ParticipantAPI.md`);
- persistence field names and the authorization implementation;
- wording and visual styling;
- the detailed calculation/settlement rules for ノルマ and チケットバック.

Organization member management and organization invitations are specified in `docs/24-OrganizationMemberManagementScreen.md` and are not changed by R14-B. The organization invitation flow is to be reviewed in R14-C.
