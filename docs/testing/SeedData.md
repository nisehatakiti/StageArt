# Web β版 Seed / Demo Data

Scripts: `plugin/scripts/seed_web_beta.php` / `plugin/scripts/seed_web_beta_cleanup.php`

dev/demo environment only (ConoHa `dev` install). Both scripts run through
WP-CLI's `eval-file` against a real WordPress bootstrap, and the seed
script creates every row through the actual REST API (`rest_do_request()`),
so seeded data passes through the same Application layer and Business
Rules real usage does — it is not raw SQL bypassing validation.

## What gets created

- 7 demo WP users, `stageart_demo_*` logins (`stageart_demo_general`,
  `stageart_demo_org_owner`, `stageart_demo_org_member`,
  `stageart_demo_production_manager`, `stageart_demo_production_member`,
  `stageart_demo_applicant`, `stageart_demo_rejected_applicant`),
  `@stageart.invalid` emails, random passwords (nobody is meant to log in
  as these via password - see "Logging in as a demo user" below).
- 2 demo Organizations, both named with a `[Sample]` prefix and an
  explicit "実在の団体ではありません" description, both published.
- 3 demo Productions under the first Organization: one published+ACTIVE
  (with a Join Key, an ACTIVE Participant, and 3 Rehearsals covering
  confirmed/unconfirmed/past-completed states), one unpublished/DRAFT,
  one published then lifecycle-completed to ARCHIVED.
- Membership rows in all three states this phase's flow needs: ACTIVE,
  REQUESTED (pending), REJECTED.
- One Follow (general user → Org B) and two Favorites (general user →
  Org A, general user → the published Production).

Re-running `seed_web_beta.php` without cleaning up first is intentionally
**not** idempotent - it will fail on unique-slug/Join-Key conflicts and
stop early rather than silently duplicating or overwriting demo data.

## Running it

```
wp eval-file plugin/scripts/seed_web_beta.php --path=<wordpress-root>
```

On the ConoHa dev box this is `--path=/home/c0948353/stageart/dev`. The
script's own `require '.../wp-load.php'` line is hardcoded to that same
path (in addition to whatever `--path` WP-CLI itself bootstraps with) -
edit that line first if pointing this at a different WordPress install.

## Resetting

```
wp eval-file plugin/scripts/seed_web_beta_cleanup.php --path=<wordpress-root>
```

Cleanup matches only two things - WP users whose login starts with
`stageart_demo_`, and Organizations/Productions whose name starts with
`[Sample]` - then cascades through every dependent `stageart_*` table
(Memberships, Participants, Rehearsals + their Attendances/Timetables,
JoinKeys, Favorites, Follows, Projects, UserAccounts, auth token tables)
before deleting the matched rows themselves and the WP user accounts.
It never touches any row outside those two match patterns, so it is
safe to run against a database that also has real, non-demo data - and
it is a no-op (all `DELETE`s affect zero rows) when no demo data exists.

To fully reset: run the cleanup script, then re-run the seed script.

## Logging in as a demo user

The seed script does not print usable passwords (it generates a random
one via `wp_generate_password()`, matching how a real account would
never have a script-known password). To actually log into the Web app
as one of the demo identities during manual browser verification, set a
known password first:

```
wp user update stageart_demo_org_owner --user_pass=<your-temp-password> --path=<wordpress-root>
```

Do this per demo login as needed. This only ever touches the
`stageart_demo_*` accounts the seed script itself created - never a real
user's credentials.
