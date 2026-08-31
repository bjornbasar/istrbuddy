# The istrbuddy documentation site — static nginx.
#
# ⚠ NOT istrbuddy's own image. The application is built from ./Dockerfile (PHP 8.3) and this
# is an unrelated container serving rendered HTML. Hence the `docs.` prefix on every file
# here: deriving these from a package repo's plain names once overwrote a service's own
# Dockerfile, compose and deploy script.
#
# Built multi-arch on Ruxa (amd64 + arm64), pushed to 192.168.4.9:5000, pulled on Bosco.
# Fronted by Ayula at docs.bjornbasar.com/istrbuddy/ ONLY, behind Cloudflare Access.
#
# ⚠ PRIVATE-ONLY. istrbuddy is a reference application seeded with three accounts whose
# default password is `changeme`, and its docs say so. That is correct for a local dogfood
# and is not something to publish — it is also why the app itself has never had a public
# hostname.
#
# amd64 would be enough for Bosco today. arm64 is built anyway so the site can move to
# Hurska without a rebuild — the same reason sleuth does it.
#
# `site/` is produced by ci/deploy.sh BEFORE the build (ci_mkdocs in ../ci/lib.sh):
# mkdocs-material is Python, and running pip under arm64 emulation to produce static HTML
# would be minutes of waste per deploy.
#
# COPY is an explicit ALLOWLIST. The build context is a PHP application including vendor/,
# db/ (the SQLite database, with seeded password hashes) and config/. None of it may be
# served by a static nginx that happens to share the repo. An allowlist fails closed. An allowlist
# fails closed — a new directory in the repo cannot leak by default. ci/deploy.sh asserts the
# important ones 404 after deploy.
FROM nginx:alpine

LABEL org.opencontainers.image.source=https://github.com/bjornbasar/istrbuddy
LABEL org.opencontainers.image.description="istrbuddy — reference (docs.bjornbasar.com/istrbuddy/)"

COPY docs.nginx.conf /etc/nginx/conf.d/default.conf

# ⚠ THE /istrbuddy/ SUBDIRECTORY IS REQUIRED, NOT COSMETIC. The site is served at its real
# public path so MkDocs' trailing-slash redirects stay correct behind a path-routed proxy —
# see the header of docs.nginx.conf. Changing this to `html/` breaks every directory URL.
COPY site/ /usr/share/nginx/html/istrbuddy/

EXPOSE 80
