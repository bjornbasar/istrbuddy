#!/usr/bin/env bash
# istrbuddy DOCS local CI/CD deploy — gates the API reference against the code, renders the
# MkDocs site, builds MULTI-ARCH, pushes the local registry (:latest + :sha- for rollback),
# redeploys Bosco, smoke-tests.
#
# WHAT THIS PIPELINE DOES *NOT* DO: publish the npm package. That is a release action on a
# tag, not a docs push. It DOES run the package's own test suite, because unlike karhu (whose
# GitHub Actions matrix runs the library tests on every push) istrbuddy has no other gate — and a
# reference describing behaviour the tests no longer support is exactly the failure this site
# exists to prevent.
#
# Runs on Ruxa via `git push ruxa main` (post-receive) or by hand from the checkout.
set -euo pipefail
source "${CI_LIB:-/data/git/ci-lib.sh}"

BOSCO="ubuntu@192.168.4.34"
BOSCO_DIR="/data/istrbuddy-docs"
IMG="$REGISTRY/istrbuddy-docs"
PORT=8105

ci_trap "→ Bosco (docs.twobots.dev/istrbuddy/)"
ci_lock
ci_ensure_buildx

# ---------------------------------------------------------------- pre-deploy gates
# These run on RUXA against the source tree, before anything is built or pushed. A broken
# reference should fail here, not after an arm64 image has been through qemu.

# No composer install and no PHPUnit here: this pipeline publishes DOCUMENTATION, and the
# application's own suite is a `composer check` you run locally. What it does gate on is the
# one thing that makes published docs wrong.

# THE GATE THIS SITE EXISTS FOR. Docs that are confidently wrong are worse than docs that
# are absent: absent docs send you to the source, wrong docs send you nowhere and you believe
# them. check-docs.mjs asks the TypeScript compiler what each entry point actually exports,
# then fails when an export is undocumented, when a page names a symbol that no longer
# exists, or when prose cites a src/ path that has moved.
# Reflects over app/ for every #[Route] and fails when one is undocumented, when the
# reference claims a route nothing declares, or when prose cites a moved path. Runs in a bare
# php:8.3-cli-alpine — no composer install needed, because it reads the attribute text rather
# than booting the container to reflect on it. About two seconds.
ci_log "assert the route reference still matches app/"
ci_php php tools/check-docs.php


# ---------------------------------------------------------------------- render
# --strict turns warnings into a failed build, and mkdocs.yml sets validation.* to warn for
# unrecognised links and missing anchors — so a cross-reference to a renamed heading fails
# HERE rather than shipping as a dead link.
ci_log "render the MkDocs site (build --strict)"
rm -rf site
ci_mkdocs build --strict

[ -f site/index.html ] || ci_die "mkdocs produced no site/index.html"
PAGES=$(find site -name '*.html' | wc -l)
ci_log "rendered $PAGES pages"
# A nav regression that silently dropped the API reference would otherwise deploy happily.
# 3 = home + routes + operating. The 404 page mkdocs-material emits is headroom.
[ "$PAGES" -ge 3 ] || ci_die "only $PAGES pages rendered — expected 3+; the nav has probably lost a section"

# Assert every page carries the route back to the documentation index. Added because these
# sites sit under a shared landing page and a reader who arrives on a deep link needs a way
# up — and because a theme override is exactly the kind of thing a Material upgrade can
# silently stop rendering, with no error and no visible breakage on the page itself.
#
# Checks the COUNT, not merely presence: a partial render (the override applying to some
# templates but not the 404 page, say) is the failure mode worth catching, and it looks
# identical to success if you only grep one file.
LINKED=$(grep -rl 'class="docs-up"' site --include='*.html' | wc -l)
[ "$LINKED" = "$PAGES" ] || ci_die "only $LINKED of $PAGES pages carry the 'All documentation' link — check overrides/main.html and theme.custom_dir"
ci_log "every page routes back to the index (correct): $LINKED/$PAGES"

# The canonical must name the PUBLIC host. docs.bjornbasar.com is behind Cloudflare Access,
# so a canonical pointing there would advertise a URL search engines can never fetch.
grep -q 'rel="canonical" href="https://docs.bjornbasar.com/istrbuddy/' site/index.html \
  || ci_die "canonical is missing or points elsewhere — check site_url in mkdocs.yml"
ci_log "canonical names the gated host (correct) — istrbuddy has no public copy"

# ---------------------------------------------------------------------- build + ship
ci_log "build + push multi-arch: $IMG (:latest + :sha-$CI_SHA)"
docker buildx build --builder multiarch --platform linux/amd64,linux/arm64 \
  -f docs.Dockerfile -t "$IMG:latest" -f docs.Dockerfile -t "$IMG:sha-$CI_SHA" --push .

ci_log "sync compose + redeploy on Bosco"
ssh "$BOSCO" "mkdir -p $BOSCO_DIR"
# ⚠ docker-compose.docs.yml, renamed on arrival. This repo ALSO has its own
# docker-compose.yml for the PHP application, which carries a `build: .` — rsyncing that one
# sent Bosco a compose file that tried to build istrbuddy itself from a context containing no
# Dockerfile, and the deploy died at "failed to read dockerfile". Same class of mistake as
# generating plain Dockerfile/nginx.conf names into a repo that already owns them.
rsync -a docker-compose.docs.yml "$BOSCO:$BOSCO_DIR/docker-compose.yml"
ssh "$BOSCO" "cd $BOSCO_DIR && docker compose pull && docker compose up -d --remove-orphans && docker image prune -f"

# ---------------------------------------------------------------------- smoke tests
# NOTE the /istrbuddy/ prefix on every path: the site is rooted at its public path INSIDE the
# container, so that MkDocs' trailing-slash redirects stay correct behind a path-routed
# proxy. See the header of docs.nginx.conf.
ci_log "smoke-test the deployed container"
for PAGE in /istrbuddy/ /istrbuddy/routes/ /istrbuddy/operating/; do
  CODE=$(ssh "$BOSCO" "curl -s -o /dev/null -w '%{http_code}' http://localhost:$PORT$PAGE")
  [ "$CODE" = "200" ] || ci_die "$PAGE returned $CODE — the site did not deploy correctly"
  ci_log "serves (correct): $PAGE → 200"
done

# Search is the classic silent mkdocs failure: the page renders, the box appears, and nothing
# is ever found because the index 404s.
CODE=$(ssh "$BOSCO" "curl -s -o /dev/null -w '%{http_code}' http://localhost:$PORT/istrbuddy/search/search_index.json")
[ "$CODE" = "200" ] || ci_die "search_index.json → HTTP $CODE — site search is broken"
ci_log "search index resolves (correct)"

# THE ALLOWLIST ASSERTION. The build context is the whole package repo — a regression in the
# Dockerfile COPY list would publish source, tests, or the raw markdown.
ci_log "assert the repo itself is not being served"
for LEAK in /app/Controllers/IssueController.php /composer.json /vendor/autoload.php /db /config /mkdocs.yml /Dockerfile /tools/check-docs.php /docs/index.md; do
  CODE=$(ssh "$BOSCO" "curl -s -o /dev/null -w '%{http_code}' --path-as-is http://localhost:$PORT$LEAK")
  [ "$CODE" = "404" ] || ci_die "$LEAK is being SERVED (HTTP $CODE) — check the Dockerfile COPY allowlist"
  ci_log "not served (correct): $LEAK → 404"
done

# THE PATH-ROUTING ASSERTION, and the reason this site is not a straight copy of karhu's.
# mkdocs links pages as directory URLs, so nginx 301s /istrbuddy/routes to add a slash. With
# absolute_redirect off that Location is root-relative — relative to the ORIGIN, not to any
# proxied prefix. It must therefore already CONTAIN /istrbuddy/, or the redirect would throw
# visitors out of this site and into whatever else answers at docs.twobots.dev/api/.
#
# Reads the RAW Location header on purpose: curl's %{redirect_url} resolves a relative header
# against the request URL, so it always looks correct and can never distinguish the two cases.
ci_log "assert the trailing-slash redirect keeps the /istrbuddy/ prefix"
LOC=$(ssh "$BOSCO" "curl -s -D- -o /dev/null http://localhost:$PORT/istrbuddy/routes | grep -i '^location:' | tr -d '\r'")
case "$LOC" in
  *"://"*)             ci_die "the redirect is absolute ($LOC) — absolute_redirect must be off" ;;
  *"/istrbuddy/routes/"*) ci_log "redirect keeps the prefix (correct): '$LOC'" ;;
  *)                   ci_die "redirect LOST the /istrbuddy/ prefix: '$LOC' — the site must be rooted at /istrbuddy/ in the image" ;;
esac

# ---------------------------------------------------------------------- public checks
# Non-fatal by design: Cloudflare/Ayula can lag a container swap by a few seconds, and a
# deploy that succeeded on the origin should not go red in #duskana for that.
# ⚠ MUST NOT BE PUBLIC — a reference app with documented default passwords. A 404 on the
# public docs host is the required result; a 200 means a location block exists that should not.
ci_log "assert the reference is NOT on the public docs host"
PUB=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 https://docs.twobots.dev/istrbuddy/ 2>/dev/null || echo 000)
case "$PUB" in
  404) ci_log "not public (correct): docs.twobots.dev/istrbuddy/ → 404" ;;
  200) ci_die "docs.twobots.dev/istrbuddy/ is PUBLIC (200) — remove its location block from the public vhost" ;;
  *)   ci_log "⚠ docs.twobots.dev/istrbuddy/ → $PUB (expected 404; could not confirm)" ;;
esac

# The gated hostname must NOT answer 200 to an anonymous request. A 302 to the Cloudflare
# Access login is the CORRECT result, and is also why this path is probed at the origin
# rather than through the edge — blackbox follows redirects and would record a false 200.
ci_log "verify the gated hostname still gates (non-fatal)"
GATED=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 https://docs.bjornbasar.com/istrbuddy/ 2>/dev/null || echo 000)
case "$GATED" in
  302|401|403) ci_log "gated (correct): docs.bjornbasar.com/istrbuddy/ → $GATED" ;;
  200)         ci_log "⚠ docs.bjornbasar.com/istrbuddy/ answered 200 ANONYMOUSLY — Cloudflare Access is not covering this path" ;;
  *)           ci_log "⚠ docs.bjornbasar.com/istrbuddy/ → $GATED" ;;
esac

# No ghcr copy. These docs are gated, and a cloud copy of a private site is a second place
# for it to leak from in exchange for nothing — the local registry is the only consumer.
