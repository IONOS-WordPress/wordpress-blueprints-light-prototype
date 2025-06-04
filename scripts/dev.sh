#!/usr/bin/env bash

# 
# starts the apiserver 
# watch changes in filesystem and rebuild and restart the server
#

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

echo "Running in mode: $MODE"

while 
  # kill previously running API server if it exists
  if [[ -n "$API_SERVER_PID" ]]; then
    kill "$API_SERVER_PID"
  fi

  pnpm -s build
  pnpm -s apiserver --mode "$MODE" &
  API_SERVER_PID=$!
do inotifywait -e modify,create,delete,move -r ./server; done

exit

###help-message
Syntax: 'pnpm dev [options]'

Options:
  --help        Show this help message and exit
  --mode [mode] Run restart apiserver using the phar file ('--mode phar'), compress phar mode ('--mode phar.gz') or by using the source code ('--mode source').
                The default mode is 'source'. 

This script wil rebuild and restart the API server whenever changes are detected in the './server' directory.
