# Block validity check

Reports blocks the WordPress editor would show as
*"This block contains unexpected or invalid content"*.

## Why this exists rather than a PHP test

A block is valid when the markup stored in the post matches what that block's
`save()` would write. `save()` is JavaScript, so PHP cannot answer the question.
Asserting `serialize_blocks( parse_blocks( $content ) ) === $content` only proves
the *parser* round-trips the markup; it never runs `save()`, so a converted page
can pass that check and still be unopenable in the editor.

This tool loads the script bundles WordPress itself ships
(`wp-includes/js/dist`) into jsdom, registers every core block plus the plugin's
own built blocks, and runs `wp.blocks.parse()` — the same code path the editor
uses.

## Setup

```bash
cd tools/block-validity && npm install
```

## Usage

```bash
node tools/block-validity/validate.js path/to/post-content.html
```

Point it at a directory to check every `.html` file inside. Each file holds one
post's `post_content`, which `wp post get <id> --field=post_content` will dump.

| Option | Meaning |
| --- | --- |
| `--wp <path>` | WordPress install to take the editor scripts from. Defaults to the install this plugin sits in. Use the version the site being checked runs. |
| `--blocks <path>` | The plugin's built blocks. Defaults to `build/blocks`. |
| `--json` | Emit the report as JSON. |
| `--quiet` | List invalid blocks only. |
| `--verbose` | Report each editor script as it loads. |

It exits non-zero when any block is invalid, so it can gate a build.

## Reading the output

```
  core/group (at 0 > 2 > 0)
    ! Expected attribute `style` of value `padding-top:0`, saw `gap:20px;padding-top:0`.
    saved:    <div class="wp-block-group" style="gap:20px;padding-top:0">…
    expected: <div class="wp-block-group" style="padding-top:0">…
```

`saved` is what the converter wrote, `expected` is what `save()` produces from
the attributes in the block comment. Validation compares `class` as an unordered
set of names and `style` as an unordered set of declarations, so only a missing
or extra class or declaration matters — not the order of either.

Validation stops at the first element that differs, so one run can hide a
mismatch further down the same block. Re-run after every fix until it reports
zero.
