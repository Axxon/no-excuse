# Third-party notices

No excuse is MIT licensed, but its dependencies remain the property of their respective authors and keep their own licenses. This file is informational and does not replace the license files distributed with each package.

The exact package names, versions, source URLs and declared licenses are recorded in:

- `api/composer.lock` for PHP runtime and development packages;
- `web/package-lock.json` for JavaScript build and runtime packages.
- `pseudonymizer/requirements.txt` and the pseudonymizer image inventory for Python packages.

The dependency inventory currently includes MIT, Apache-2.0, BSD, ISC and 0BSD packages, plus the following file-level or weak-copyleft components:

| Component | Version | License | Distribution note |
| --- | --- | --- | --- |
| `dompdf/dompdf` | 3.1.5 | LGPL-2.1 | PHP dependency distributed separately under its own license |
| `dompdf/php-font-lib` | 1.0.2 | LGPL-2.1-or-later | PHP dependency distributed separately under its own license |
| `dompdf/php-svg-lib` | 1.0.2 | LGPL-3.0-or-later | PHP dependency distributed separately under its own license |
| `smalot/pdfparser` | 2.12.5 | LGPL-3.0 | PHP dependency distributed separately under its own license |
| `lightningcss` and platform packages | 1.32.0 | MPL-2.0 | Build-time packages; MPL applies at file level |

The local CV pseudonymizer uses Presidio (MIT), spaCy (MIT), FastAPI (MIT), Uvicorn (BSD-3-Clause), and the `xx_ent_wiki_sm` spaCy model (MIT). Their transitive Python dependencies retain their own permissive licenses. The model is downloaded and embedded while building the image; it performs no runtime model download.

Production container builders must preserve license files supplied by runtime dependencies. The frontend production image contains compiled assets rather than `node_modules`; build-time packages are not copied into that image.

Run `composer licenses` inside the API container, inspect `web/package-lock.json`, and inventory the installed Python image before publishing a binary distribution after dependency upgrades. This notice must be reviewed whenever a PHP, JavaScript, Python or model dependency changes.
