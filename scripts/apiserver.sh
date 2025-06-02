#!/usr/bin/env bash

# example usage:
#   pnpm apiserver 

set -eo pipefail

if ! docker ps --format '{{.Names}}' | grep -q "wordpress-1"; then
  echo "Error: wp-env is not up and running - please exec 'pnpm start'"
  exit 1
fi

readonly WP_ENV_HOME=$(pnpm -s wp-env  install-path 2>/dev/null)
readonly WP_ENV_HASH=$(basename $WP_ENV_HOME)

exec docker exec -i "${WP_ENV_HASH}-wordpress-1" /bin/bash <<EOF
  # set -x
  command -v killall &>/dev/null || apt-get install -y psmisc
  # command -v lsof &>/dev/null || apt-get install -y lsof

  # php_pid=$(lsof -t -i:9090)
  # [[ $? -eq 1 ]] && kill -9 $php_pid
  killall php &>/dev/null || true

  cd ./server/public_html/
  # run not as root to prevent plugin deletion issues
  runuser -u $USER -- php -S localhost:9090 index.php
EOF
