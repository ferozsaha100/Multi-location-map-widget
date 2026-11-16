# Changelog

All notable changes to **AVE Multi-Location Map** are documented here.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [2.4.1] - 2026-11-16

### Fixed
- **Office Map:** on phones and short maps, a tapped or clicked office's popup could be cut off when it was taller than the space above or below its pin (e.g. with several phone numbers). The map now pans just enough to show the whole popup and its pin, clear of the zoom buttons, including after tapping an office in the Layout 2 list. Hover popups on desktop still never move the map.

## [2.4.0] - 2026-11-16

### Added
- **Per-office pins** (*Offices → office → Pin for this office*, just above *Button link*):
  - **Pin colour** overrides *Style → Pins* for that office only; global kit colours work. The pin keeps its colour when highlighted (a shade darker and larger), and its Layout 2 list number matches.
  - **Custom pin icon / logo**: an uploaded PNG/SVG replaces the pin, with its bottom centre on the location. In Layout 2, a number badge sits on its corner. A broken image URL falls back to the standard pin.
- *Style → Pins → Custom icon height* (default 40px).

## [2.3.0] - 2026-11-11

### Changed
- **Layout 2:** the single numbering switch is now two independent switches, **Numbers on pins** (hidden: pins show a dot) and **Numbers in office list** (hidden: no badge, text moves left). Both default to *Show*.

## [2.2.0] - 2026-11-11

### Added
- Phone entries can carry a person's name: `Name | Role | Number` shows the name above the number and the role as the small label. Leave a slot empty for name-only or role-only.
- Style controls for the name (colour, typography).

### Changed
- **Default role label** is now *below the number, small caps text*. Widgets that relied on the 2.1.0 default (pill, right of number) switch to the new look; set *Content → Phone numbers* to *Right of number, top* + *Pill* to keep the old one.
- Entries with names get 12px between them (6px otherwise) unless *Space between numbers* is set.

## [2.1.0] - 2026-11-11

### Added
- Multiple phone numbers per office (up to 10) with optional labels: `Label | Number`, `Label: Number`, `Number | Label` or just the number.
- Tap-to-call links; extensions such as `ext. 12` dial after a pause.
- *Content → Phone numbers*: label position (right of number top/bottom, above, below), label style (pill or small caps), phone icon. *Style → Phone numbers* section.

### Changed
- Default popup width 280px → 300px so a number and its label fit on one line.

## [2.0.0] - 2026-11-06

### Added
- **Office Map widget.** Pins are found once in the editor with OpenStreetMap (*Find on map*, *Find all missing pins*) and saved as latitude/longitude, so the live page never geocodes and there is no pin limit.
- Two layouts: **pins with hover popup**, and **office list + numbered, synced pins** (stacks under the map on phones).
- Map engine per widget: **Leaflet + CARTO** (Light, Voyager, Dark; Leaflet 1.9.4 bundled) or **Google Maps** (Standard, Silver, Dark), with identical HTML pins.
- *Settings → AVE Maps* for the CARTO key, Google key and Nominatim contact email (reuses Agent View Extension's keys when present).
- Lazy loading, keyboard-accessible pins, editor-only status notes, and a no-key fallback that shows no broken map.

### Unchanged
- The original *Multi-Location Map* widget, so existing pages keep working.

## [1.3.0] - 2026-11-05

### Fixed
- Original widget stopped at ~20 pins: 1.2.2 read Google's geocode callback arguments in the wrong order, and browser geocoding is rate-limited by Google per visitor session.

### Added
- Server-side geocoding (Google Geocoding API web service), cached 27–30 days; *Server Geocoding Key* field / `AVE_MLM_GEOCODE_API_KEY` constant; editor-only geocoding status box.

## [1.2.2]

### Fixed
- Rate-limited lookups are retried in later passes with adaptive delays; stalled lookups time out after 10s.

### Added
- `?avemlm_debug=1` overlay listing placed and failed locations.

## [1.2.1]

### Fixed
- Pins dropped on maps with 20+ locations; transient geocoding errors are retried with back-off.

### Added
- Browser cache of resolved coordinates (30 days), progressive auto-fit, and nudging of pins that share coordinates.

## [1.2.0]

### Changed
- Location link renders as a styled *View Details* button, shown only when a link is set.

[2.4.1]: https://github.com/ferozsaha100/multi-location-map-widget/releases/tag/v2.4.1
