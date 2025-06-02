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
  command -v jq &>/dev/null || apt-get install -y jq
  command -v nano &>/dev/null || apt-get install -y nano
EOF

# echo -e "\033[1;33m"
cat <<'EOF'
You can access the API server at http://localhost:9090

Example usages:

# call echo endpoint 
curl -v \
  http://localhost:9090/echo \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '
  {
    "foo": "bar",
    "lorem": "ipsum"
  }
  ' | jq .

# enqeue tasks to execute 
curl -v \
  http://localhost:9090/enqueue \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '
  [
    {
      "id": "4ac68232-bc87-4dec-b935-8c7fdca9d370",
      "type": "install_plugin",
      "args":
      {
        "url": "https://downloads.wordpress.org/plugin/hello-world.zip",
        "slug": "hello-world/hello-world.php",
        "force": true
      }
    },
    {
      "id": "c87572d9-73ec-460a-a885-9dca940db5ce",
      "type": "activate_plugin",
      "args":
      {
        "slug": "hello-world/hello-world.php",
        "force": true
      }
    },
    {
      "id": "c8d4e19a-0f69-492f-8f71-379787e5f462",
      "type": "set_option",
      "args":
      {
        "name": "hello_world",
        "value": "whoooo!"
      }
    }
  ]
  ' | jq .

# get option 
curl -v \
  http://localhost:9090/enqueue \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '
  [
    {
      "id": "4ac68232-bc87-4dec-b935-8c7fdca9d371",
      "type": "get_option",
      "args":
      {
        "name": "hello_world"
      }
    }
  ]
  ' | jq .


EOF
# echo -e "\033[0m"

exec pnpm -s run wp-env run wordpress bash

