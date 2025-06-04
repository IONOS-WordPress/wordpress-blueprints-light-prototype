# Infrastructure for provisioning a WordPress instance from a separate PHP Process

The goal of this prototype is finding a cheap way to provision a WordPress instance.

The provisioning data will be provided in JSON. 

The JSON maps to lightweight tasks (see ionos-blueprints-light/inc/blueprints/tasks/set_option.php as an example task implementation).

The provisioning JSON is valided against task specific JSON Schema (ionos-blueprints-light/inc/blueprints/tasks/set_option.schema.json) to verify the integrity of the task description before it gets enqueued for execution.

All code is self contained and has only WordPress as dependency.

## Current state

**Experimental 🥸**

## Features

- a very lightweight mini standalone service (see `./server`) can be runned using PHP built-in Webserver to provision the WordPress instance 

  - you can inject tasks to execute by a simple `curl` statement against the standalone service

    ```bash
    # enqeue tasks for installing and configuring a plugin 
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
            "name": "hello_world_lyrics",
            "value": "whoooo!"
          }
        }
      ]
      ' | jq .
    ```

    The service returns the executed task ids, their execution exit code and returned data : 

    ```json
    {
      "success": "Tasks processed successfully",
      "payload": [
        {
          "success": true,
          "id": "4ac68232-bc87-4dec-b935-8c7fdca9d370"
        },
        {
          "success": true,
          "id": "c87572d9-73ec-460a-a885-9dca940db5ce"
        },
        {
          "success": true,
          "id": "c8d4e19a-0f69-492f-8f71-379787e5f462"
        }
      ]
    }
    ```

- tasks will be validated against JSON Schema for validity when enqueued

- a task implementation will consist of minimal footprint

## Demo

[![Video](https://img.youtube.com/vi/UIRmfr2kZuY/maxresdefault.jpg)](https://www.youtube.com/watch?v=UIRmfr2kZuY)

The Video shows how provision a wordpress instance using a ultra lightweight PHP service.

## Prerequisities

- `Docker`
- `pnpm`

## Setup

- startup wp-env (a conterized wordpress installation) : `pnpm start`

- build the php PHAR archive containing the api server : `pnpm build`

  This command build the a PHP PHAR archive containing the whole apiserver in a single PHAR file.

- start the blueprints api server : `pnpm apiserver --phar`

  This command start the api server inside the wordpress container of wp-env. 

  Inside the wp-env wordpress container the api server is started using the PHP built-in webserver on port 9090.

  The api server is only available within the wordpress container of wp-env.

- open another terminal and execute `pnpm apiclient`. 

  This will open a shell in the wordpress container of wp-env. 

  Here you can execute curl commands against the api server. 

  Example curl statements : 

  ```shell
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

  # install and activate a plugin. set an option
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

  ```

# Development

- start wp-env : `pnpm start`

- start watching changes in files of directory `./server` and rebuild/restart api server : `pnpm dev`

  The `pnpm start` command generated a vscode launch configuration. You can debug into the api server code when processing requests.

  To start debugging you simply execute a curl statement in the `pnpm apiclient` shell against the server.

  _Alternatively you can start the dev command using `--mode phar` option to develop against the phar archive._
