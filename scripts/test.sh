#!/usr/bin/env bash

# example usage:
#   pnpm test 
#   or pnpm test <phpunit arguments...> like pnpm test -- '--filter test_complex_example'

exec pnpm run wp-env run tests-cli \
  --env-cwd=wp-content/plugins/ionos-blueprints-light \
  bash -c \
    "phpunit --debug --no-interaction --do-not-cache-result --prepend ~/.composer/vendor/autoload.php --bootstrap \$WP_TESTS_DIR/includes/bootstrap.php ${@} ./tests"