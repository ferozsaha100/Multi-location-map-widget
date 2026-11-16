=== AVE Multi-Location Map ===
Contributors: agentviewdigital
Requires at least: 6.0
Tested up to: 6.6
Requires PHP: 7.4
Requires Elementor: 3.5.0+
Stable tag: 2.4.1
License: GPLv2 or later

Two Elementor widgets for multi-office maps.

OFFICE MAP (new in 2.0, recommended)
Unlimited office pins with name, address and a button. Each address is looked
up ONCE in the Elementor editor ("Find on map", OpenStreetMap) and saved as
latitude/longitude, so visitors never trigger a lookup and there is no pin
limit. Two layouts:
  - Layout 1: full-width map, popup with name, address and button on hover
    (tap on phones).
  - Layout 2: office list on the left, numbered pins on the map; hovering or
    clicking either side highlights the other. The list stacks under the map
    on phones.
Map engine per widget: Leaflet + CARTO tiles (free, Leaflet 1.9.4 bundled) or
Google Maps.

MULTI-LOCATION MAP (original widget, kept so existing pages keep working)
A single Google Map with unlimited office/address locations,
added via a repeater control. Type an address and the pin location is
auto-detected — same behavior as Elementor's native Google Maps widget — no
need to look up coordinates. Auto-fits the viewport to show every pin,
supports custom marker icons per location, and opens a styled info window
(name, address, phone, website link) on click.

== Installation ==

1. Upload the `ave-multi-location-map` folder to `/wp-content/plugins/`.
2. Activate the plugin through the "Plugins" menu in WordPress.
3. Requires Elementor (free or Pro) to be installed and active.
4. In the Elementor editor, search for "Office Map" (new) or "Multi-Location
   Map" (original) in the widget panel, under the "Agent View" category.
5. Updating from 1.x: upload the zip and choose "Replace current with
   uploaded". Existing Multi-Location Map widgets keep working unchanged.

== Office Map setup ==

1. Settings > AVE Maps:
   - CARTO basemaps key (for the Leaflet + CARTO engine). Free, no CARTO
     account: https://carto.com/basemaps/apikey — one key per client site.
     CARTO stopped serving tiles without a key on 25 September 2026.
   - Google Maps API key (only if a widget uses the Google engine). Enable
     "Maps JavaScript API" and restrict the key to the site's domain. The
     Geocoding API is NOT needed.
   - Contact email for OpenStreetMap lookups (defaults to the admin email).
   If Agent View Extension is installed and already has these keys, they are
   used automatically.
2. In Elementor, add "Office Map" (Agent View category).
3. Under Offices, add a row per office: name, address, phone numbers (one
   per line, "Jonathon Kyes | Office Manager | (727) 555-0101" — name and
   role optional), button link/text.
4. Click "Find all missing pins" (about one office per second; 45 offices take
   under a minute, once), or "Find on map" inside a single row. Latitude and
   longitude are filled in and stay editable for fine-tuning.
5. Pick the layout and engine, then style pins, popup, button and list in the
   Style tab.
6. Update / publish the page.

Notes:
- An office without latitude/longitude is left off the map. The editor shows
  a yellow box naming any such offices (visitors never see it).
- OpenStreetMap may not know very new streets or some suite addresses. The
  lookup retries without "Suite/Ste/Unit/#"; if it still fails, enter the
  coordinates yourself (right-click the spot in Google Maps to copy them).
- Mouse-wheel zoom is off by default so the page scrolls past the map.

== Multi-Location Map setup (original widget) ==

1. Get a Google Maps API key from Google Cloud Console. Enable BOTH:
   - "Maps JavaScript API" (renders the map)
   - "Geocoding API" (turns a typed address into a pin location)
   Set up billing on the project — both APIs have a free monthly quota, but
   billing must be enabled for the key to work at all.
2. Paste the key into the widget's "Google Maps API" section.
2a. For maps with 20+ locations, also fill in "Server Geocoding Key". This
   key is used only by your server (never shown to visitors) to turn
   addresses into pins, so there is no pin limit. Create it in Google Cloud
   Console with:
   - API restriction: "Geocoding API" only
   - Application restriction: None, or "IP addresses" (your server's IP)
   Do NOT give it a "Websites" (HTTP referrer) restriction — Google rejects
   referrer-restricted keys for server requests. Alternatively define it in
   wp-config.php: define( 'AVE_MLM_GEOCODE_API_KEY', 'your-key' );
   If left blank, the plugin tries the main API key; the editor shows a
   status box above the map telling you whether that worked.
3. Add one repeater row per office under "Locations" — name and full address
   is all that's required. The pin drops automatically.
4. Optional: fill in Latitude/Longitude on a location only if the
   auto-detected pin needs correcting (e.g. new construction not yet indexed
   by Google, PO box vs. physical address mismatch, etc). When lat/lng are
   filled in, geocoding is skipped for that location and the exact
   coordinates are used instead.
5. Optional: upload a custom marker icon per location, add phone/website,
   toggle auto-fit vs. fixed zoom, paste a Snazzy Maps style JSON for custom
   map styling.

== Notes ==

- If you already enqueue the Google Maps JS API elsewhere on the site, leave
  the API Key field blank on this widget to avoid loading the script twice —
  just make sure Geocoding API is enabled on whichever key IS loaded.
- Address geocoding happens on the server via the Geocoding API web service.
  Each address is looked up once and the coordinates are cached for 27-30
  days (Google's terms allow caching lat/lng for up to 30 days), so visitors
  never wait on geocoding. Any address the server can't resolve falls back to
  google.maps.Geocoder in the visitor's browser, which Google rate limits to
  roughly 20 lookups per visit.
- Developer hooks: ave_mlm_geocode_api_key (filter the server key),
  ave_mlm_geocode_time_budget (seconds per render for uncached lookups,
  default 10), ave_mlm_geocode_request_params (extra Geocoding API params,
  e.g. [ 'region' => 'us' ]).
- If a specific address fails to geocode, check the browser console — the
  widget logs the failed address and Google's status code (e.g.
  ZERO_RESULTS for a bad/incomplete address, OVER_QUERY_LIMIT for rate
  limiting) rather than failing silently.

== Changelog ==

= 2.4.1 =
* Fix (Office Map): on phones and short maps, a tapped or clicked office's
  popup could be cut off when it was taller than the space above or below
  the pin (e.g. with several phone numbers). The map now pans just enough to
  show the whole popup, clear of the zoom buttons, including after tapping an
  office in the Layout 2 list. Hover popups on desktop still never move the
  map.

= 2.4.0 =
* Office Map: per-office pin settings under Offices > (office) > "Pin for
  this office", just above the button link:
  - Pin colour: overrides Style > Pins for that office only (global kit
    colours work). The pin keeps its colour when highlighted (a shade
    darker and larger), and its Layout 2 list number matches.
  - Custom pin icon / logo: an uploaded image (PNG/SVG) replaces the pin;
    its bottom centre sits on the location. In Layout 2 with pin numbers on,
    a small number badge sits on its corner. A broken image URL falls back
    to the standard pin.
* Style > Pins: "Custom icon height" (default 40px) for all custom icons.
* Offices without their own colour or icon look exactly as before.

= 2.3.0 =
* Office Map, Layout 2: separate Show/Hide switches for "Numbers on pins"
  (hidden → pins show a dot) and "Numbers in office list" (hidden → no
  number badge; the office text moves left). Both default to Show.
  Previously one switch controlled both; a widget that had it turned off
  keeps pin numbers hidden, and its list numbers can now be hidden with the
  new switch.

= 2.2.0 =
* Office Map: phone entries can carry a person's name. Enter
  "Name | Role | Number" (e.g. "Jonathon Kyes | Office Manager |
  (727) 555-0101"): the name shows above the number and the role as the
  small label. "Role | Number" and plain numbers still work; leave a slot
  empty for name-only ("Ana Ruiz |  | 555-0100") or role-only.
* New defaults: role label below the number in small caps text (all four
  positions and the pill style remain in Content > Phone numbers).
* Style tab: name colour and typography. Entries with names get 12px
  between them (6px otherwise) unless "Space between numbers" is set.

= 2.1.0 =
* Office Map: multiple phone numbers per office, each with an optional label
  (e.g. "Office Manager"). Enter one per line in Offices > Phone numbers as
  "Label | Number" ("Label: Number" and "Number | Label" also work; up to 10).
* Numbers are tap-to-call links (extensions like "ext. 12" dial after a
  pause) and show in the popup and in the Layout 2 office list.
* New "Phone numbers" settings: label position (right of number top /
  bottom, above, below), label style (pill or small caps text), phone icon
  on/off. New Style section: number, label, pill, icon colours and
  typography, spacing.
* Popup default width 280px → 300px so a number and its label fit on one
  line; long labels wrap neatly under the number.

= 2.0.0 =
* New widget: Office Map. Pins are found once in the editor with OpenStreetMap
  and saved as lat/lng, so the live page never geocodes and there is no pin
  limit (tested with 45 and 120 offices).
* Two layouts: hover popup (name, address, button), and office list on the
  left with numbered, synced pins. List stacks under the map on phones.
* Engines per widget: Leaflet + CARTO (Light, Voyager, Dark; Leaflet 1.9.4
  bundled) or Google Maps (Standard, Silver, Dark). Same HTML pins on both.
* "Find on map" per office and "Find all missing pins" buttons; lookups are
  server-side, editor-only (edit_posts + REST nonce), cached, and spaced
  1.1 s apart per the Nominatim usage policy.
* Settings > AVE Maps page for the CARTO key, Google key and contact email
  (reuses Agent View Extension's keys when present).
* Map scripts load only when the map scrolls near the viewport. Keyboard
  accessible pins (Enter / Esc). Missing key → no broken map; the list still
  shows.
* The original Multi-Location Map widget is unchanged from 1.3.0.

= 1.3.0 =
* Fix: maps stopped at ~20 pins. Two causes:
  1. 1.2.2 read Google's geocode callback arguments in the wrong order
     ( status, results instead of results, status ), so every browser lookup
     was treated as a failure — only pins with manual lat/lng or coordinates
     cached from 1.2.1 appeared.
  2. Browser geocoding (google.maps.Geocoder) is rate limited by Google per
     visitor session, about 20 lookups, and retrying doesn't get past it.
* New: addresses are geocoded server-side via the Geocoding API web service
  and cached 27-30 days, so the map shows every pin (tested with 45 and 120
  locations) and visitors' browsers send no geocoding requests.
* New: "Server Geocoding Key" field (or AVE_MLM_GEOCODE_API_KEY constant) for
  a key without a website restriction.
* New: editor-only status box above the map — how many addresses resolved,
  which failed and why, and what to change if Google rejects the key.
* Browser geocoding is kept as a fallback for anything the server couldn't
  resolve.

= 1.2.2 =
* Fix: maps with 40+ locations now place every pin that Google can resolve.
  Addresses that hit Google's rate limit are deferred and retried in later
  passes (up to 6) instead of being dropped, the delay between lookups adapts
  automatically while Google is throttling, and a lookup that never answers
  times out after 10s instead of stalling the queue.
* New diagnostic: add ?avemlm_debug=1 to the page URL to show an overlay on the
  map listing how many pins were placed and exactly which locations failed and
  why (e.g. ZERO_RESULTS = Google can't find that address).
* Locations with no address and no lat/lng are now reported in the console
  instead of disappearing silently.

= 1.2.1 =
* Fix: maps with many locations (20+) no longer drop pins. Geocoding requests
  that hit Google's rate limit (OVER_QUERY_LIMIT) or return a transient error
  are now retried with back-off instead of being silently skipped.
* Resolved coordinates are cached in the visitor's browser (30 days), so repeat
  views place every pin instantly with almost no geocoding requests.
* The map now zooms to fit pins progressively as they appear, instead of
  waiting for every lookup to finish.
* One failing location can no longer stall the rest of the queue.
* Pins with identical coordinates (e.g. several offices in one building) are
  nudged slightly apart so none is hidden under another.
* Console now logs a summary of any pins that could not be placed.

= 1.2.0 =
* Website link now renders as a styled "View Details" button instead of a
  plain text link, only shown when a link (URL or #anchor) is set on that
  location.
* Added per-location "Button Text" field to customize the label.
* Added Style tab section "View Details Button" (colors, hover state,
  typography, padding, border radius).

= 1.1.1 =
* Website link now respects the URL field's "Open in new window" checkbox
  instead of always forcing a new tab. Unchecked = opens in the same page
  (works correctly for same-page anchor/hash links like #section-id).
* Added nofollow support (respects the URL field's "Add nofollow" checkbox).

= 1.1.0 =
* Address field now auto-geocodes to a pin (matches Elementor's native map
  widget behavior). Lat/Lng are now optional manual overrides instead of
  required fields.

= 1.0.0 =
* Initial release.
