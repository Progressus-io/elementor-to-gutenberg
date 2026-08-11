Fonts bundled with Migrate Off Elementor
========================================

These files are used by the plugin's own admin screens (assets/css/batch-wizard.css).
They were previously loaded from fonts.googleapis.com with an @import, which sent
every administrator's IP address, user agent and referrer to Google each time one of
those screens was opened. They are now served from this directory, so the admin UI
looks the same and no request leaves the site.

Families and licences
---------------------

All three families are licensed under the SIL Open Font License, Version 1.1, which
is compatible with the GPL. The full licence text of each family is included here.

  Schibsted Grotesk  Copyright 2023 The Schibsted-Grotesk Project Authors
                     https://github.com/schibsted/schibsted-grotesk
                     Licence: OFL-Schibsted-Grotesk.txt

  Hanken Grotesk     Copyright 2021 The Hanken Grotesk Project Authors
                     https://github.com/marcologous/hanken-grotesk
                     Licence: OFL-Hanken-Grotesk.txt

  JetBrains Mono     Copyright 2020 The JetBrains Mono Project Authors
                     https://github.com/JetBrains/JetBrainsMono
                     Licence: OFL-JetBrains-Mono.txt

The files
---------

Each .woff2 is a variable font covering the weight range the admin UI uses
(400-800 for the two Grotesks, 400-700 for JetBrains Mono), split by unicode
subset exactly as Google Fonts serves them, so a browser downloads only the
subsets it needs. The matching @font-face rules, including each subset's
unicode-range, are at the top of assets/css/batch-wizard.css.

  schibsted-grotesk-{latin,latin-ext}.woff2
  hanken-grotesk-{latin,latin-ext,vietnamese,cyrillic-ext}.woff2
  jetbrains-mono-{latin,latin-ext,vietnamese,greek,cyrillic,cyrillic-ext}.woff2

Sources: the upstream projects linked above. The .woff2 builds are the ones
published by the Google Fonts API for those projects.
