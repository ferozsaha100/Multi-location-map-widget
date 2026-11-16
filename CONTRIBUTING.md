# Contributing

Thanks for helping improve AVE Multi-Location Map. Bug reports, ideas and pull requests are all welcome.

## Reporting a bug

[Open a bug report](https://github.com/ferozsaha100/multi-location-map-widget/issues/new?template=bug_report.yml) and include:

- Plugin, WordPress, Elementor and PHP versions
- Map engine (Leaflet + CARTO or Google Maps) and layout
- What you expected, what happened, and the steps to reproduce
- Any errors from the browser console (look for lines starting with `AVE Office Map:`)

## Development setup

The plugin needs no build step: edit the files in `ave-multi-location-map/` directly.

The tests stub WordPress and Elementor, so they run without a WordPress install:

```bash
bash tests/run.sh --no-browser                    # PHP 7.4+ and Node 18+
npm install --prefix tests && npx --prefix tests playwright install chromium
bash tests/run.sh                                 # adds the real-browser suites
```

## Pull requests

1. Fork the repository and create a branch from `main`.
2. Keep changes focused. Add or update a test for any behaviour you change.
3. Follow the existing code style ([WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)): tabs, Yoda conditions, escaped output, translatable strings in the `ave-mlm` text domain.
4. Keep PHP 7.4 compatible (no `match`, named arguments or `fn` arrow functions in shipped code).
5. Never add network calls to front-end render paths; geocoding happens only in the editor.
6. Run `bash tests/run.sh` and make sure it passes.
7. Describe the change and how you tested it in the pull request.

## Releasing (maintainers)

1. Bump the version in `ave-multi-location-map/ave-multi-location-map.php` (plugin header and `AVE_MLM_VERSION`) and `Stable tag` in `ave-multi-location-map/readme.txt`.
2. Add the entry to `CHANGELOG.md` and the plugin `readme.txt`.
3. Commit, then tag and push: `git tag v2.4.2 && git push origin v2.4.2`.
4. The *Release* workflow checks that the tag matches the plugin version, runs the tests, builds `ave-multi-location-map-2.4.2.zip` and publishes the GitHub release with the changelog entry as its notes.

## License

By contributing you agree that your contributions are licensed under the [GPL-2.0-or-later](LICENSE).
