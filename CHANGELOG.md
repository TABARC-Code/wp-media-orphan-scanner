## `CHANGELOG.md`

```markdown
# Changelog

## 1.0.0.8

- First public release.
- Added Tools  
  Media Orphans screen.
- Scan behaviour:
  - Counts total attachments.
  - Scans a limited batch of recent attachments per run.
  - Detects attachments whose file is missing on disk.
  - Detects unattached attachments.
  - Marks attachments as likely unused when:
    - They are unattached.
    - The file exists.
    - Their URL or base filename is not found in post_content.
    - Their URL or base filename is not found in postmeta.
- Summary table for quick health snapshot.
- Filter `wpmos_scan_limit` to control scan size.
- No deletion or modification of data.
- Licensed under GPL-3.0-or-later.
