# Website specification — base/tenant

The public site for the package. A sibling specification exists in the starter
kit repository for its own site; the two share a design system and cross-link,
but they sell different things and must not be built as one site with two
skins.

**Status:** specification, nothing built.
**Last reviewed:** 2026-08-30.

---

## 1. Who arrives, and what they are deciding

One reader, in one situation: a senior Laravel developer or a technical founder
who is about to build a B2B SaaS and has just realised how much of it is not
the product. They are three days into writing tenant scoping and have started
to suspect it will take three months.

They are deciding **build it or buy it**, and the honest competition is not
another package — it is their own weekend. They will not be persuaded by a
feature list, because they can imagine writing every item on it. They are
persuaded by the parts they have not thought of yet, and by evidence that
somebody else already hit them.

Two smaller audiences, served without reshaping the site for them:

- A developer already using the package, looking something up. They need the
  documentation to be fast and linkable, not to be sold to again.
- Someone evaluating on behalf of a team, who needs a licence and a price
  without talking to anyone.

## 2. The one job

**Make a competent developer believe the hard parts are genuinely solved, then
get out of the way.**

Everything else — pricing, the changelog, the newsletter — is secondary to that
sentence and gets cut first when a page is too long.

## 3. What actually differentiates it

The site should be built around these, in this order. They are the things a
weekend of work does not produce.

1. **Isolation that holds where it usually breaks.** Not "multi-tenant" as a
   bullet, but: queued jobs, cache keys, broadcast channels, console commands.
   The specific failure — a job that runs as the wrong customer — is the thing
   to name, because everyone who has built this has met it.
2. **Permissions that are per account.** The same person is an administrator in
   one account and a viewer in another. This is the requirement that quietly
   rewrites an authorisation layer six months in.
3. **Plan limits that are actually enforced.** Counters that survive two
   simultaneous requests, and a 402 with somewhere to go. Most homegrown
   versions check and then act, and both requests pass.
4. **The whole product surface, not just tenancy.** Files, imports, exports,
   metering, connections, webhooks, GDPR, onboarding. This is the difference
   from every tenancy library, and it is the argument that survives contact
   with a roadmap.
5. **Guards, not just tests.** 743 tests, and more to the point: the security
   guards were verified by removing them and confirming the suite went red. A
   page that explains this honestly will do more than any number.

## 4. The thing that must not be lost

**The product's credibility comes from its honesty, so the site cannot
overclaim.** The documentation says which things have never been exercised
against a real service. If the marketing pages contradict that, the first
developer to notice will discount everything else on the site.

So: a visible, linked page listing what is not verified, what is not built, and
what is deliberately out of scope. Not buried in a FAQ. This is unusual and it
is the strongest possible signal to the exact reader described in §1.

## 5. Structure

```
/                     Landing
/features             What it does, by capability
/why                  Build vs buy, worked honestly
/docs                 The manual (generated from the repo)
/docs/agents          The AI-agent documentation, as a first-class section
/pricing              Licence and price
/limits               What is not verified and not built
/changelog            Generated from docs/CHANGELOG.md
/legal/licence
```

### Landing

Above the fold: what it is in one sentence, who it is for in one more, and a
single primary action. No carousel, no logo wall the product has not earned.

Then, in order:

1. **The problem, named precisely.** Three or four sentences about the
   difference between "scope the query" and "the scope survives a queued job".
   A reader who has felt this recognises it immediately; one who has not is not
   the buyer yet.
2. **A real code sample.** `BelongsToAccount` on a model, then the same model
   read inside a job with no extra work. Short enough to read without scrolling.
3. **The capability grid.** Core and the twelve modules, one line each, linking
   into the docs. This is where the surface area does its work.
4. **Evidence.** The test count, the guard approach, and a link to `/limits`.
   Placing the limits link on the landing page is the point.
5. **The kit**, as the way to start a new project, linking to the other site.

### /features

One page, not one page per feature: the reader is scanning to find whether
their specific problem is covered, and a hub of twelve pages makes that slower.
Anchored sections, each with the API in three or four lines. Every section
links to its documentation page for depth.

### /why

The build-vs-buy page, written as an argument that concedes things. It should
say plainly when *not* to buy this: a single-tenant application, a product with
no accounts, a team that needs to own every line for compliance reasons. A page
that cannot say who should not buy reads as a page that will say anything.

### /docs

Generated from the repository so it cannot drift: `docs/USAGE.md` is the manual
and `docs/agents/` is the agent documentation. Both are already structured for
this — one heading level per capability, anchors that resolve.

The agent documentation gets its own visible section rather than an appendix.
A team evaluating this in 2026 will be building with AI assistance, and "the
package ships documentation written for your agent, and a test that fails when
any of it goes stale" is a differentiator no competitor currently has.

### /pricing

Whatever the licence turns out to be, the page must answer without a form: what
it costs, what a licence covers, whether updates are included, and what happens
when a licence lapses. A developer evaluating a foundation will not fill in a
form to find out the price; they will close the tab.

### /limits

The page described in §4. Sourced from the same statements already in the
repository so it cannot quietly diverge:

- Vapor uploads, Stripe Billing Meters, the three OAuth providers and LangSyncer
  are covered against doubles, not against the real services.
- The LangSyncer HTTP contract is inferred from a specification.
- The pre-sale Stripe Checkout is not built.
- The generator does not implement `--settings` or `--api`.
- `config('base-tenant.layouts')` is read by nothing.

Each entry says what it means for the buyer, not just what is missing.

## 6. Build

- **Static.** The content changes when the package changes, which is on a
  release cadence, not per request. A static site removes a running service
  from the things that can break.
- **Documentation generated from the repository**, in the release pipeline. A
  documentation site that is edited separately from the code is a documentation
  site that is wrong within two releases. The repository is the source; the
  site is a rendering.
- **Search over the docs** is the one piece of interactivity worth having.
  Client-side index; the corpus is small.
- **No analytics that require a cookie banner.** The audience notices, and the
  banner costs more attention than the data is worth at this stage.
- Dark mode, because the audience will read the code samples at night.

## 7. Design

Shared with the kit's site: same type scale, same palette, same components,
different accent so the two are told apart at a glance. Zinc plus one accent,
matching the product's own interface — a site that looks like the thing you are
buying is doing part of the selling.

Code samples are the primary visual element. They get the care usually spent on
illustration: real code from the repository, syntax highlighted, short enough
to read in place. No screenshots of an IDE, no stock photography, no
illustrations of abstract clouds.

## 8. What not to build

- A demo application behind a signup. The screens are worth showing, but a
  hosted demo of a *foundation* invites judgement of example data rather than
  of the foundation.
- A comparison table against named competitors. The honest comparison is
  against building it, and that is `/why`.
- Testimonials before there are customers to quote.
- A blog, until there is something to say that is not marketing.

## 9. Done when

1. A developer who has built multi-tenancy before reads the landing page and
   recognises at least one problem they hit personally.
2. The price is discoverable without contacting anyone.
3. The documentation is reachable in two clicks from anywhere and searchable.
4. `/limits` is linked from the landing page, and everything on it matches the
   repository.
5. Publishing a package release republishes the documentation, with no manual
   step that can be skipped.
