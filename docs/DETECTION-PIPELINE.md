# Detection pipeline contract

How kart-number detections get from a machine that can run inference into this
gallery.

## The boundary

Detection runs **off-site**, on the photographer's own hardware where a GPU and
a YOLO/OCR pipeline can live. This application never runs inference. It runs on
shared hosting with a five-minute cron, no GPU, and a PHP memory limit, and it
would be the wrong place even if it could.

What crosses the boundary is a JSON sidecar file. That file is the whole
contract. Keeping it explicit is what lets either side be rewritten without
breaking the other: retrain the model, swap the OCR engine, rewrite the pipeline
in something else entirely, and as long as it emits this file the gallery does
not care.

```
  homelab                                 shared hosting
  ┌──────────────────────────┐            ┌────────────────────────────┐
  │ detect karts             │            │ /admin/detections          │
  │ read numbers (OCR)       │  sidecar   │   parse + validate         │
  │ propagate across bursts  │ ─────────► │   resolve to entrants      │
  │ emit JSON                │   .json    │   write photo_entrants     │
  └──────────────────────────┘            │ /admin/review (the rest)   │
                                          └────────────────────────────┘
```

## The sidecar format

```json
{
  "batch_id": "buckmore-2026-08-01",
  "generated_at": "2026-08-01T21:14:00Z",
  "detections": [
    {"filename": "IMG_4821.jpg", "number": "7",  "confidence": 0.94, "method": "ocr"},
    {"filename": "IMG_4822.jpg", "number": "7",  "confidence": 0.61, "method": "propagated"},
    {"filename": "IMG_4823.jpg", "number": "12", "confidence": 0.88, "method": "livery"}
  ]
}
```

| Field | Required | Notes |
|---|---|---|
| `batch_id` | no | Shown back on the import screen so you can tell runs apart |
| `generated_at` | no | Informational |
| `filename` | **yes** | Matched against `photos.original_filename`, case-insensitively |
| `number` | **yes** | Text, not an integer. `7`, `07` and `7a` are all different karts |
| `confidence` | **yes** | 0 to 1. Never omit it, see below |
| `method` | **yes** | `ocr`, `propagated`, `livery` or `manual` |

`bbox` and any other extra keys are ignored. They are fine to include.

### Why confidence is required

A missing confidence is rejected rather than defaulted. Defaulting high pushes
unchecked guesses straight into public galleries; defaulting low buries good
detections in a review queue nobody asked for. Neither is a decision the gallery
should make on the pipeline's behalf, so it refuses the row and tells you.

### What the methods mean

- **`ocr`** — a number was read off the kart.
- **`propagated`** — inferred from a neighbouring frame in the same burst. This
  is how a photo where the number is hidden still gets attributed: identify #51
  in one frame and carry it across adjacent frames shot milliseconds apart. It
  is worth implementing on the pipeline side; it is often the single biggest
  win in coverage.
- **`livery`** — matched on helmet design or kart livery rather than a number.
  Useful when the number faces away or is obscured. Not biometric: it identifies
  a vehicle and its paint, not a person's face.
- **`manual`** — a human said so.

## What happens on import

1. **Choose a session.** The session is what makes a number resolvable, because
   it names the class, and a driver's real identity is (event, class, number).
2. **Filenames resolve to photos** within that session only.
3. **Numbers resolve to entrants.** With a class on the session this is
   unambiguous. Without one, the number is looked up across the event and
   accepted only if exactly one entrant carries it.
4. **Attributions are written** to `photo_entrants` with their source and
   confidence.

Anything at or above the confidence threshold (0.75) shows in galleries
immediately. Anything below waits in **Review detections**.

## What it will not do

**Guess.** A detection that cannot be resolved to exactly one entrant is
reported and no row is written. The tempting shortcut is to pick the first
matching class when a number exists in several, and in this sport that silently
attributes one child's photos to a different child.

Three things are reported rather than resolved:

- filenames not present in the chosen session
- numbers not in the entry list (import the entry list first)
- numbers used by more than one class, where the session has no class set

**Overwrite a human.** A detection never replaces an attribution somebody has
already ruled on, and never lowers an existing confidence. Re-importing a batch
is safe, and a later low-confidence run cannot undo someone pressing "That's me"
on their own photos.

## Accuracy notes for the pipeline side

Two techniques are worth the effort and belong on the detection side, not here:

- **Temporal clustering.** Photos in a burst are milliseconds apart and almost
  always show the same kart. Propagating an identification across the burst
  picks up frames where the number is not legible at all.
- **A confusion matrix for the classic misreads.** 6 and 8, 1 and 7, 46 and 48.
  Checking a read number against the actual entry list and correcting toward a
  number that exists removes a whole category of error before it reaches the
  gallery.

## Working the review queue

**Review detections** groups everything uncertain by driver rather than listing
it flat. Four hundred uncertain photos as a list is four hundred decisions;
grouped by number it is usually a dozen groups, and a group is normally right or
wrong as a whole. Both bulk actions apply to every pending photo for that
driver, not just the thumbnails on screen.

Rejecting keeps the row with its confidence zeroed rather than deleting it, so
the next pipeline run does not re-propose the same rejected guess.

## Privacy

Driver names appear on the review queue, which is behind admin authentication.
They must never reach a public surface. See
[PRIVACY-DESIGN.md](PRIVACY-DESIGN.md), which also records why face recognition
is permanently out of scope for this project.
