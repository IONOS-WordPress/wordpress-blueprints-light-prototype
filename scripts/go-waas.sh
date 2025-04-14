#!/usr/bin/env bash

# example usage:
#   pnpm go-waas wpscantest-g3bj3z5gzj.live-website.com --tenant=ionos
#

readonly GO_WAAS='./bin/go-waas'

if [[ ! -f "$GO_WAAS" ]]; then
  if ! ping -c 1 gitlab.git-wp.server.lan &> /dev/null; then
  cat <<'EOF'
Error: gitlab.git-wp.server.lan is not reachable.

Check if your VPN is connected and you can reach the gitlab.git-wp.server.lan server.
EOF
    exit 1
  fi

  mkdir -p ./bin

  # see https://gitlab.git-wp.server.lan/whappdev/gowaas/-/releases
  readonly ASSET_URL="https://gitlab.git-wp.server.lan/whappdev/gowaas/-/releases/permalink/latest/downloads/go-waas_$(uname -s)_$(uname -m)"
  echo "Downloading go-waas from ${ASSET_URL}"

  if ! curl -Ls --fail -o "$GO_WAAS" "$ASSET_URL"; then
    echo "Error: Failed to download go-waas from $ASSET_URL"
    exit 1
  fi
  chmod +x "$GO_WAAS"

  echo "Installed go-waas successfully in "$GO_WAAS""
fi

exec "$GO_WAAS" "$@"