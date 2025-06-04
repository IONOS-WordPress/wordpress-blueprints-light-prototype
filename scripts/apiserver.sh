#!/usr/bin/env bash

#
# starts the API server in the wp-env wordpress container
#
# example usage:
#   pnpm apiserver 

set -eo pipefail

if ! docker ps --format '{{.Names}}' | grep -q "wordpress-1"; then
  echo "Error: wp-env is not up and running - please exec 'pnpm start'"
  exit 1
fi

MODE="source"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --help)
      # print everything in this script file after the '###help-message' marker
      printf "$(sed -e '1,/^###help-message/d' "$0")\n"
      exit
      ;;
    --mode)
      MODE="${2}"
      shift 2
      ;;
    *)
      echo "Unknown option: $1"
      exit 1
      ;;
  esac
done

readonly WP_ENV_HOME=$(pnpm -s wp-env  install-path 2>/dev/null)
readonly WP_ENV_HASH=$(basename $WP_ENV_HOME)

exec docker exec -i "${WP_ENV_HASH}-wordpress-1" /bin/bash <<EOF
  # set -x
  command -v killall &>/dev/null || apt-get install -y psmisc
  # command -v lsof &>/dev/null || apt-get install -y lsof

  # php_pid=$(lsof -t -i:9090)
  # [[ $? -eq 1 ]] && kill -9 $php_pid
  killall php &>/dev/null || true

  # run not as root to prevent plugin deletion issues
  if [[ "$MODE" == 'phar' ]]; then
    echo "run apiserver from build/server.phar"
    runuser -u $USER -- php -S localhost:9090 build/server.phar
  elif [[ "$MODE" == 'phar.gz' ]]; then
    printf "\033[0;31m\nusing phar.gz mode doesnt work yet\n\033[0m"
    exit 1
    
    echo "run apiserver from build/server.phar.gz"
    runuser -u $USER -- php -S localhost:9090 build/server.phar.gz
  else
    echo "run apiserver from ./server/public_html/index.php"
    cd ./server/public_html/ && runuser -u $USER -- php -S localhost:9090 index.php
  fi
EOF

exit

###help-message
Syntax: 'pnpm apiserver [options]'

Options:
  --help        Show this help message and exit
  --mode [mode] Run apiserver using the phar file ('--mode phar'), compress phar mode ('--mode phar.gz') or by using the source code ('--mode source').
                The default mode is 'source'. 

This script wil start the API server in the wp-env wordpress container.