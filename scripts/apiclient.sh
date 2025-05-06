#!/usr/bin/env bash

# example usage:
#   pnpm test 
#   or pnpm test <phpunit arguments...> like pnpm test -- '--filter test_complex_example'

set -eo pipefail

if ! docker ps --format '{{.Names}}' | grep -q "wordpress-1"; then
  echo "Error: wp-env is not up and running - please exec 'pnpm start'"
  exit 1
fi

readonly WP_ENV_HOME=$(pnpm -s wp-env  install-path 2>/dev/null)
readonly WP_ENV_HASH=$(basename $WP_ENV_HOME)

docker exec -i "${WP_ENV_HASH}-wordpress-1" /bin/bash <<EOF
  command -v jq &>/dev/null || apt install -y jq
EOF

echo -e "\033[1;33m
You can access the API server at http://localhost:9090
Example usage:

  curl -v \\
    http://localhost:9090/jobs \\
    -H 'Accept: application/json' \\
    -H 'Content-Type: application/json' \\
    -d '{
      \"foo\": \"bar\",
      \"lorem\": \"ipsum\"
    }' | jq .
\033[0m"

pnpm -s run wp-env run wordpress bash
