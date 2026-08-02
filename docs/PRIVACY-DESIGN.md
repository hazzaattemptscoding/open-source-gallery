# Privacy design: why discovery is by number, not by face or name

This document records two deliberate decisions and the reasoning behind them, so
that a future contributor does not helpfully undo either one. Both look like
missing features. Neither is.

**Decision 1: this project will not implement facial recognition or selfie search.**

**Decision 2: driver names are never shown on, or accepted by, a public page.**

---

## The context that drives both decisions

A large share of competitors in grassroots karting are under 16. That single fact
changes the legal position of anything that identifies a person from a photograph,
and it is why this project deliberately diverges from what most event-photography
platforms did during 2025 and 2026.

## Decision 1: no facial recognition, no selfie search

Selfie search became close to standard across event photography. For this sport it
is the wrong answer, on three independent grounds.

**It does not work well here.** Drivers wear full-face helmets for the entire
session. The face is not visible in the photographs being searched.

**It is legally hazardous.** Under UK GDPR Article 4(14), processing images
specifically to uniquely identify a person is *biometric data*. Article 9 presumes
that processing is prohibited unless a narrow exception applies, which in practice
means explicit consent. For under-16s that consent must come from a parent or
guardian. ICO guidance expects a Data Protection Impact Assessment for facial
recognition, and expects the operator to demonstrate that a less intrusive method
was not available.

A less intrusive method **is** available, which is the point: the kart number.

**Kart-number identification is not biometric.** It identifies a vehicle, then
joins that to an entry list the organiser has already published. It sits entirely
outside Article 9. For a small self-hosted operator this is the difference between
a normal privacy notice and a special-category compliance programme.

So the choice here is not "accuracy versus privacy". Number identification is both
more accurate for helmeted drivers and legally cleaner. That is a genuinely
defensible position rather than a compromise, and it is worth stating publicly:
*we find you by your kart number, not by your face.*

### If this is ever revisited

Do not add it casually. The only defensible design would be: strictly opt-in, the
selfie never stored, used only for the duration of a single search, with a human
confirmation step before any photo is attributed, and disabled entirely for any
class containing minors. Even then it needs a DPIA first. If you are reading this
because you were about to add a face-search feature, the DPIA is the first
deliverable, not the last.

## Decision 2: driver names never appear on a public surface

Names are imported and stored. They are used by the admin tagging screen, the
detection review queue, and reporting. They are never rendered on, or accepted by,
an unauthenticated page.

This was not always true. Names were once exposed in five separate places, one of
which nobody had catalogued: image alt text, which search engines index. That was
removed wholesale in commit `d750edf`.

**Both rendering and filtering were removed, deliberately.** It is not enough to
stop printing a name. Accepting `?driver=Some+Name` as a filter would let anyone
confirm whether a named child appears in a gallery, which is the same disclosure
in a quieter form. So the public surfaces do not accept a driver parameter at all,
and free-text search does not match against `driver_name`.

### What the public identity is instead

A driver is publicly identified as **number plus class**, for example `#7 — Junior
X30`. That is:

- sufficient for the person searching, who knows their own number
- meaningful to the parent sharing the link, who knows the class
- not personally identifying to a stranger who has neither

### Share links use an opaque token, not a name

Personal pages are shareable and durable by design, because a parent posting the
link into a WhatsApp group is the main organic discovery route in this sport. The
share link therefore must not leak anything.

A name-derived slug such as `/driver/jack-smith` would publish the name into every
shared URL and make entrants trivially enumerable. The share token is instead an
opaque random value. It is equally durable and equally shareable, and it has the
additional property that only someone who has been given the link can use it.

This mirrors the strongest privacy design in the sector, where search is by bib
number and never by name, so that only the person pictured can reach their photos.

## Practical consequences for contributors

- Do not add a driver-name search box, autocomplete, or facet.
- Do not accept a name as a query parameter on any public route.
- Do not put a name in alt text, a meta tag, a JSON-LD block, a filename, or a
  `data-` attribute rendered to a public page. Alt text and structured data are
  crawled and indexed; they are public surfaces even though they are not visible.
- Do not derive a public slug or token from a name.
- Admin pages may show names freely. They are behind authentication.

If a change touches driver data, re-run the leak sweep before merging: grep the
public views, controllers and generated markup for `driver_name` and confirm every
hit is admin-only.
