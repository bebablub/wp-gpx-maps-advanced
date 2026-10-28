---
description: Merge upstream changes (devfarm-it/wp-gpx-maps) into main with minimal, verified conflict resolution
argument-hint: Upstream branch, tag or release version, e.g. 1.3.18
agent: agent
---

Merge upstream into `main`: ${input:release}

Follow `.github/copilot-instructions.md`. Work on a disposable branch. Ask before merging into `main` or pushing.

1. **Prepare**
   - Make sure the working tree is clean (or stash/commit unrelated work after asking).
   - Ensure an `upstream` remote exists (`https://github.com/devfarm-it/wp-gpx-maps.git`; add it if missing), then `git fetch upstream`.
   - Record a baseline: `git diff --stat upstream/<ref> main | tail -1`.
2. **Dry run**
   - List conflicts with `git merge-tree --write-tree --name-only main upstream/<ref>`.
   - Run `git diff <merge-base>..upstream/<ref> --stat` to see what upstream changed.
   - Collect our additions in shared files (privacy mode, grade coloring, direction arrows, distance type, tile proxy, `AdvancedMarkerElement`, Highcharts v11, resource hints) so they can be checked after the merge.
3. **Merge** on `tmp-merge-upstream` (branched from `main`), and read every conflict hunk with its context before resolving it:
   - If our side only differs in formatting, take upstream.
   - If our side contains functional code, take upstream, then re-apply only our functional lines in upstream's style.
   - If upstream changed code we call or hook into (renamed functions, changed signatures, new checks), adapt our code. Never drop upstream security fixes.
   - Do not bulk auto-resolve before every hunk has been read.
4. **Verify**
   - No conflict markers: `git grep -nE '^(<{7}|>{7})'`.
   - `php -l` passes on all PHP files, and `node --check` passes on changed JS.
   - Every addition collected in step 2 is still present.
   - Constraints still hold: PHP 5.6, ES5, procedural style, conditional asset loading, `LIBXML_NONET`, tile proxy SSRF checks.
   - `git diff --stat upstream/<ref>` is not larger than the baseline without a reason.
5. **Report**
   - Each conflict and how it was resolved.
   - Upstream security or behaviour changes that affect our code.
   - A manual test list: shortcode with a GPX track, charts (altitude, speed, heart rate), privacy mode, images from NextGEN, tile proxy, settings save, several shortcodes on one page.
   - Ask before merging into `main`.

Keep a todo list with one item per step. Keep answers concise.
