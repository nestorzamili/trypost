#!/usr/bin/env bash
#
# TryPost — deploy the production stack for access by raw IP + port.
#
# This is a SEPARATE workflow from the domain/TLS production deploy. It does not
# modify compose.prod.yaml or the existing GitHub deploy pipeline: it layers
# compose.ip.yaml on top of compose.prod.yaml (same image, same services, same
# containers) and only changes the app port binding to 0.0.0.0 so the app is
# reachable at http://<server-ip>:<port>.
#
# Run it ON THE SERVER, from the deploy directory (default /opt/trypost) that
# holds compose.prod.yaml, compose.ip.yaml and .env.
#
#   TRYPOST_IMAGE=ghcr.io/OWNER/REPO:SHA APP_PORT=8000 ./scripts/deploy-ip.sh
#
# Required environment:
#   TRYPOST_IMAGE   Published image reference to run (no local build).
# Optional environment:
#   APP_PORT        Host port to publish (default 8000).
#   APP_BIND        Host interface to bind (default 0.0.0.0 = all interfaces).
#   DEPLOY_DIR      Directory holding the compose files + .env (default /opt/trypost).
#   ENV_FILE        Env file name inside DEPLOY_DIR (default .env).
#
# SECURITY: http://IP:port is UNENCRYPTED. Suitable for internal / VPN / staging
# hosts. For a public deployment, terminate TLS in front (a reverse proxy) and
# use the domain-based compose.prod.yaml caddy profile instead.

set -euo pipefail

DEPLOY_DIR="${DEPLOY_DIR:-/opt/trypost}"
ENV_FILE="${ENV_FILE:-.env}"
APP_PORT="${APP_PORT:-8000}"
APP_BIND="${APP_BIND:-0.0.0.0}"

: "${TRYPOST_IMAGE:?TRYPOST_IMAGE is required (e.g. ghcr.io/owner/repo:sha)}"

cd "$DEPLOY_DIR"

for f in compose.prod.yaml compose.ip.yaml "$ENV_FILE"; do
    test -f "$f" || {
        echo "[deploy-ip] $DEPLOY_DIR/$f is required" >&2
        exit 1
    }
done

# Fail loud on the env keys the stack cannot boot without.
require_env() {
    if ! grep -Eq "^${1}=.+" "$ENV_FILE"; then
        echo "[deploy-ip] $1 must be set in $ENV_FILE" >&2
        exit 1
    fi
}
for key in APP_ENV APP_KEY APP_URL \
    DB_CONNECTION DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD \
    REDIS_HOST REDIS_PORT QUEUE_CONNECTION CACHE_STORE SESSION_DRIVER \
    BROADCAST_CONNECTION REVERB_APP_ID REVERB_APP_KEY REVERB_APP_SECRET \
    REVERB_HOST REVERB_PORT REVERB_SCHEME \
    PASSPORT_PRIVATE_KEY PASSPORT_PUBLIC_KEY; do
    require_env "$key"
done

# Warn (do not block) when APP_URL is not an http://IP:port form — the operator
# may have a good reason, but a mismatch is the usual cause of broken cookies.
if ! grep -Eq '^APP_URL=http://' "$ENV_FILE"; then
    echo "[deploy-ip] warning: APP_URL is not http://... — over plain IP:port it usually should be" >&2
fi

export APP_PORT APP_BIND TRYPOST_IMAGE

compose() {
    docker compose \
        --env-file "$ENV_FILE" \
        -p trypost \
        -f compose.prod.yaml \
        -f compose.ip.yaml \
        "$@"
}

echo "[deploy-ip] validating merged compose config"
compose config --quiet

echo "[deploy-ip] pulling $TRYPOST_IMAGE"
compose pull app

echo "[deploy-ip] starting datastores"
compose up -d --wait --remove-orphans pgsql redis

echo "[deploy-ip] draining the queue before swap"
compose exec -T app php artisan horizon:terminate || true

echo "[deploy-ip] starting app on ${APP_BIND}:${APP_PORT}"
compose up -d --wait --no-deps --remove-orphans app

echo "[deploy-ip] waiting for health check on 127.0.0.1:${APP_PORT}/up"
ATTEMPT=0
until curl --fail --silent --show-error "http://127.0.0.1:${APP_PORT}/up" >/dev/null; do
    ATTEMPT=$((ATTEMPT + 1))
    if [ "$ATTEMPT" -ge 30 ]; then
        echo "[deploy-ip] health check failed after 60s" >&2
        compose ps
        exit 1
    fi
    sleep 2
done

echo "[deploy-ip] done — app is up at http://<server-ip>:${APP_PORT}"
compose ps
