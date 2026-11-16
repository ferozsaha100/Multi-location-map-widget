# Security policy

## Supported versions

Security fixes are released for the latest version only. Please keep the plugin updated.

| Version | Supported |
|---|---|
| 2.4.x | Yes |
| < 2.4 | No |

## Reporting a vulnerability

**Please don't open a public issue for security problems.**

Report privately through GitHub's [private vulnerability reporting](https://github.com/ferozsaha100/Multi-location-map-widget/security/advisories/new) (*Security → Report a vulnerability*). Include the affected version, the steps to reproduce, and the impact.

You'll get an acknowledgement within 5 business days. Once a fix is ready, it is released, credited to you (unless you prefer otherwise), and noted in the changelog.

## Scope notes

- Address lookups (`POST /wp-json/ave-mlm/v1/geocode`) require the `edit_posts` capability and a valid `wp_rest` nonce.
- The front end makes no server requests. Office data is rendered into the page as escaped HTML and JSON.
