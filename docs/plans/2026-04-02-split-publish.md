# XRR — Split-Publish from Polyglot Monorepo

**Author:** $USER
**Date:** 2026-04-02
**Status:** Draft

## Problem

`xrr` is a polyglot monorepo (Go, TS, Python, PHP, Rust). Each language's
SDK lives in a subdir (`go/`, `ts/`, `py/`, `php/`, `rs/`). This breaks
Go's vanity URL resolution — `go get hop.top/xrr` clones the repo root
but finds no `go.mod` there (it's in `go/`). Workaround: `replace`
directives pointing to `github.com/hop-top/xrr/go`, which requires
private repo auth in CI.

Same problem applies to other ecosystems: `pip install` from git,
`cargo add` from git, `composer require` from git — each expects the
manifest at repo root.

## Solution

Rename `hop-top/xrr` → `hop-top/xrr-poly` (the monorepo, where all
development happens). Create per-language child repos that receive
automated publishes:

```
xrr-poly (monorepo — all development, issues, PRs)
  ├── go/   → publishes to hop-top/xrr     (Go SDK)
  ├── ts/   → publishes to hop-top/xrr-ts  (TypeScript SDK)
  ├── py/   → publishes to hop-top/xrr-py  (Python SDK)
  ├── php/  → publishes to hop-top/xrr-php (PHP SDK)
  └── rs/   → publishes to hop-top/xrr-rs  (Rust SDK)
```

### Child repo rules

- Issues + PRs disabled (point to xrr-poly)
- README explains: "this repo is auto-published from xrr-poly"
- No direct commits; only CI pushes
- Each has its manifest at root (`go.mod`, `package.json`, etc.)

### Vanity URLs

| Module path | GitHub repo | Notes |
|-------------|-------------|-------|
| `hop.top/xrr` | `hop-top/xrr` | Go — vanity resolves directly |
| `@hop-top/xrr` | `hop-top/xrr-ts` | npm publish from xrr-ts |
| `xrr` (PyPI) | `hop-top/xrr-py` | pip install from xrr-py |
| `hop-top/xrr` (Packagist) | `hop-top/xrr-php` | composer from xrr-php |
| `xrr` (crates.io) | `hop-top/xrr-rs` | cargo add from xrr-rs |

## Architecture

```
xrr-poly/docs/plans/2026-04-02-split-publish-v1.mmd
```

## Publish triggers

Two modes:

1. **Tag-based** — push `v*` tag on xrr-poly → CI splits + tags each
   child repo with same version
2. **Nightly** — cron job checks if any subdir changed since last
   publish → push to child repos with pseudo-version

### Tag flow

```
developer pushes v0.2.0 tag to xrr-poly
  → CI workflow: .github/workflows/split-publish.yml
    → for each lang in [go, ts, py, php, rs]:
      1. git subtree split --prefix=$lang -b split-$lang
      2. git push git@github.com:hop-top/xrr-$suffix.git split-$lang:main
      3. git tag v0.2.0 on child repo
      4. (optional) publish to registry (npm, PyPI, crates.io, Packagist)
```

### Nightly flow

```
cron 0 4 * * * UTC
  → CI workflow: .github/workflows/nightly-publish.yml
    → for each lang in [go, ts, py, php, rs]:
      1. check if $lang/ changed since last publish marker
      2. if changed: subtree split + push
      3. update publish marker
```

## Tasks

### T1: Rename xrr → xrr-poly

1. `gh repo rename xrr xrr-poly` (in hop-top org)
2. Update vanity URL: remove `xrr` entry from worker repos.ts
3. Update all local worktree remotes
4. Update references in cxr go.mod replace directive

### T2: Create child repos

For each suffix in `["", "-ts", "-py", "-php", "-rs"]`:

1. `gh repo create hop-top/xrr$suffix --private --description "..."`
2. Disable issues + PRs via API
3. Push initial content via subtree split from xrr-poly
4. Add README explaining the split-publish setup

For Go child (`hop-top/xrr`):
- `go.mod` at root (subtree split of `go/` puts it there)
- vanity URL `hop.top/xrr` already registered

### T3: Split-publish CI workflow

Create `.github/workflows/split-publish.yml` in xrr-poly:

```yaml
name: Split Publish
on:
  push:
    tags: ["v*"]

jobs:
  split:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        include:
          - dir: go
            repo: hop-top/xrr
          - dir: ts
            repo: hop-top/xrr-ts
          - dir: py
            repo: hop-top/xrr-py
          - dir: php
            repo: hop-top/xrr-php
          - dir: rs
            repo: hop-top/xrr-rs
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0
      - name: Split + push
        run: |
          git subtree split --prefix=${{ matrix.dir }} -b split
          git push "https://x-access-token:${{ secrets.SPLIT_TOKEN }}@github.com/${{ matrix.repo }}.git" split:main --force
          git tag ${{ github.ref_name }} split
          git push "https://x-access-token:${{ secrets.SPLIT_TOKEN }}@github.com/${{ matrix.repo }}.git" ${{ github.ref_name }}
```

Requires: `SPLIT_TOKEN` org secret with push access to all child repos.

### T4: Nightly publish workflow

Create `.github/workflows/nightly-publish.yml` in xrr-poly:

```yaml
name: Nightly Publish
on:
  schedule:
    - cron: "0 4 * * *"
  workflow_dispatch:

jobs:
  publish:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        include:
          - dir: go
            repo: hop-top/xrr
          - dir: ts
            repo: hop-top/xrr-ts
          - dir: py
            repo: hop-top/xrr-py
          - dir: php
            repo: hop-top/xrr-php
          - dir: rs
            repo: hop-top/xrr-rs
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0
      - name: Check for changes
        id: check
        run: |
          LAST=$(git log -1 --format=%H -- ${{ matrix.dir }}/)
          MARKER=$(gh variable get LAST_PUBLISH_${{ matrix.dir }} 2>/dev/null || echo "none")
          echo "changed=$([[ "$LAST" != "$MARKER" ]] && echo true || echo false)" >> "$GITHUB_OUTPUT"
          echo "sha=$LAST" >> "$GITHUB_OUTPUT"
      - name: Split + push
        if: steps.check.outputs.changed == 'true'
        run: |
          git subtree split --prefix=${{ matrix.dir }} -b split
          git push "https://x-access-token:${{ secrets.SPLIT_TOKEN }}@github.com/${{ matrix.repo }}.git" split:main --force
      - name: Update marker
        if: steps.check.outputs.changed == 'true'
        run: gh variable set LAST_PUBLISH_${{ matrix.dir }} --body "${{ steps.check.outputs.sha }}"
```

### T5: Child repo READMEs

Template for each child repo README:

```markdown
# xrr — [Language] SDK

> This repo is auto-published from
> [xrr-poly](https://github.com/hop-top/xrr-poly). Do not open issues
> or PRs here — contribute to xrr-poly instead.

## Install

[language-specific install instructions]

## Usage

[language-specific quick example]

## License

MIT — see [LICENSE](LICENSE)
```

### T6: Update downstream consumers

After split-publish is live:

1. cxr: remove `replace hop.top/xrr` → use `go get hop.top/xrr@v...`
2. aps: remove xrr replace from go.mod (cxr's transitive dep resolves)
3. Remove private module auth from CI workflows
4. Verify `go get hop.top/xrr` works without GOPRIVATE

### T7: hop-vanity script update

Update `~/.w/ideacrafterslabs/kit/hops/main/scripts/hop-vanity` to
handle the poly→child mapping. When run from xrr-poly, detect that it's
a polyglot repo and register vanity URLs for all child repos.

## Sequencing

```
T1 (rename) → T2 (create children) → T3 (tag publish) + T4 (nightly)
                                    → T5 (READMEs)
                                    → T6 (update consumers)
                                    → T7 (hop-vanity)
```

T3, T4, T5 are independent after T2.
T6 depends on T3 (need at least one tagged publish).
T7 can happen anytime after T2.

## Rollback

If split-publish causes issues:

1. Child repos are expendable — delete + recreate anytime
2. Consumers can revert to `replace` directives
3. xrr-poly rename is reversible: `gh repo rename xrr-poly xrr`
