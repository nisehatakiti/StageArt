# StageArt Delivery Model: Hosted Service + WordPress Plugin Products

Status: Confirmed Blueprint (Phase 4 §4). Formalizes the two-pronged
delivery model `03-ModularArchitecture.md` §7 already gestured at
("将来的な販売構想として...個別製品化を想定する") but never named or
distinguished explicitly. This document names the two models, states
what they share, what differs, and what in the current codebase already
supports each - not a plan to build a second deployment today.

---

# 1. The two models

**StageArt Hosted** - StageArt operates the WordPress installation
itself (today: the ConoHa dev/production environment behind
`stageart.top`), multi-Organization, multi-Production, as a service.
An Organization signs up and uses StageArt; they never install
anything. All Modules ship together as one bundled Plugin - exactly
today's actual deployment shape.

**WordPress Plugin Products** - A third-party WordPress site owner
installs **StageArt Core** plus whichever Module Plugin(s) they want
(`StageArt Rehearsal Management`, `StageArt Accounting Management`, a
future `StageArt Performance & Ticket Management`) on their own
self-hosted WordPress site, independent of StageArt's own service. Each
product is sold/distributed separately; a buyer of the Rehearsal
product does not receive Accounting or Ticket.

Both models run **the same codebase** - not a fork, not a
parallel implementation. The entire point of Phases 1-4's Module
Boundary work (Core Contracts, `*ModuleBootstrap`/`*Installer`/
`*ModuleDescriptor`, the reverse-direction Provider pattern) is that
this sentence can be true without maintaining two codebases.

---

# 2. What the two models share

- **Core**: Identity, Organization, Production Context, Membership,
  generic Capability-based Authorization (Production-Scope and
  Organization-Scope), Notification dispatch - `plugin/src/Core/`,
  `plugin/src/Application/{Organization,Production,Participant,...}`.
  Every WordPress Plugin Product still needs *a* Core - either
  StageArt's own (Hosted) or a copy of the Core Plugin the buyer also
  installs (WordPress Plugin Products).
- **Module Boundary mechanism**: `Core\Module\ModuleDescriptor`/
  `ModuleRegistry`, and each Module's own `*ModuleBootstrap`/
  `*Installer` - proven for Rehearsal and Accounting
  (`docs/architecture/WordPressPluginModuleBoundary.md`). A Module's
  entire wiring is already consolidated behind one class whose
  constructor takes only Core Contracts + the Module's own Repository
  interfaces - the same construction call works whether the caller is
  today's single `Presentation\Plugin::boot()` or a future separate
  Plugin's own activation entry point.
- **Domain Model, Business Rules, API Contract shape, Validation
  Rules, Test Cases** - `03-ModularArchitecture.md` §7's original list,
  unchanged by this document.

---

# 3. What differs

| | StageArt Hosted | WordPress Plugin Products |
|---|---|---|
| Who installs the Plugin(s) | StageArt operations only | Each buyer, on their own site |
| Tenancy | Multi-Organization, multi-Production, one shared database | Single-site, whatever Organizations/Productions that one site's owner creates |
| Which Modules are present | All of them, bundled | Only what the buyer purchased/installed |
| Core Adapter in use | `Core*Adapter` (`plugin/src/Core/Adapter/`), StageArt-hosted | Either the same `Core*Adapter` (if StageArt Core Plugin is also installed) or a future `WordPressAdapter` implementing the same Contracts against a different host's Identity/Production model (`CoreModuleArchitecture.md` §12) |
| Public pages / branding | `stageart.top`-branded (per the Web-First public Organization/Production pages work) | Whatever branding/theme the buyer's own WordPress site already has |
| Billing | StageArt's own subscription/usage model (not yet specified - out of scope here) | Per-Plugin purchase (WordPress.org / a commercial marketplace / direct sale - not yet specified) |

---

# 4. Current state, honestly

**Today, only StageArt Hosted exists as a real deployment** - one
WordPress installation, one bundled Plugin, every Module present. No
Module has ever been installed standalone on a different WordPress
site. **WordPress Plugin Products are not built** - they are the
target the Module Boundary mechanism (§2) was built *toward*, proven
extractable by Bootstrap Isolation Tests
(`RehearsalModuleBootstrapIsolationTest`/
`AccountingModuleBootstrapIsolationTest`), not yet actually split into
separate `.zip` files, separate `stageart.php`-equivalent entry points,
or a real `WordPressAdapter`.

Concretely, turning a Module into a real, separately-installable
WordPress Plugin Product still requires (per
`docs/architecture/WordPressPluginModuleBoundary.md` §5's own disclosed
gap):

1. A physical second Plugin directory/zip with its own `<Module>.php`
   entry point (mirroring `plugin/stageart.php`'s
   `register_activation_hook`/`plugins_loaded` shape), calling that
   Module's `*ModuleBootstrap`/`*Installer` from its *own* activation
   hook instead of `Presentation\Plugin::boot()`.
2. A per-Module schema version (`SchemaUpgrader::CURRENT_VERSION` is
   still one shared constant across every Module today - disclosed as
   open in `WordPressPluginModuleBoundary.md` §14 item 3).
3. A real dependency check at the new Plugin's activation time
   ("does a compatible StageArt Core Plugin exist on this site" -
   `ModuleDescriptor::requiredCoreVersion()`/`requiredContracts()` are
   declared today but not yet enforced by any runtime check - see
   `ModuleDescriptor`'s own docblock).
4. Either StageArt's Core Plugin installed alongside (WordPress Plugin
   Products running "in StageArt mode", per `Ticket.md`'s own "WordPress
   Plugin Reuse Goal" section), or a genuinely new `WordPressAdapter`
   implementing the 5 Core Contracts against a different host's
   Identity/Production model (WordPress Plugin Products running fully
   standalone).

None of this is built, and none of it is attempted by this document -
this is a naming/formalization pass, not an implementation phase.

---

# 5. Why this matters for day-to-day decisions

Going forward, when adding a feature or a new Contract, ask: *does this
assume StageArt Hosted's multi-tenant shape, or does it stay valid for
a single-site WordPress Plugin Product too?* Concrete examples already
decided correctly by earlier phases:

- `AuthorizationContract`/`MembershipContract`/etc. never assume
  StageArt-specific tenancy - they take a `ProductionId`/`OrganizationId`
  and answer a yes/no question, valid in either model.
- `PersonId` resolution goes through `IdentityContract::resolveCurrentPersonId(int
  $wordPressUserId)` - always WordPress-user-scoped, never
  StageArt-account-scoped, so a single-site WordPress Plugin Product
  needs no StageArt-specific identity concept at all.
- The public Organization/Production pages (`stageart.top/{slug}`) are
  a StageArt Hosted-specific product decision (branding, routing) - not
  something any Module's own Contract or Bootstrap depends on structurally.

A feature that only makes sense assuming "the one shared StageArt
database" (e.g. cross-Organization search, StageArt-wide admin
dashboards per `docs/architecture/StageArtAdminConsole.md`'s Platform
Administration scope) is a StageArt Hosted-specific concern and should
be built as such explicitly, not silently assumed to be universal.

---

# 6. Relationship to other Architecture docs

- `docs/03-ModularArchitecture.md` §7 - the original policy naming
  this direction; this document is the concrete elaboration.
- `docs/architecture/CoreModuleArchitecture.md` - the Contract boundary
  both delivery models depend on identically.
- `docs/architecture/WordPressPluginModuleBoundary.md` - the concrete
  Package Boundary mechanism (`*ModuleBootstrap`/`*Installer`/
  `*ModuleDescriptor`) §4 above builds on; §5 of that document lists
  the same "what a physical split still requires" gap in more
  implementation-level detail.
- `docs/architecture/StageArtAdminConsole.md` - a StageArt Hosted
  (and, per its own §"WordPress Plugin Productization", WordPress
  Plugin Product) UI-surface decision, not a delivery-model decision -
  cross-referenced, not duplicated, here.
