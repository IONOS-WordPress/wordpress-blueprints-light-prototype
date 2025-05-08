# Infrastructure for provisioning a WordPress instance from a separate PHP Process

The goal of this prototype is finding a cheap way to provision a WordPress instance.

The provisioning data will be provided in JSON. 

The JSON maps to jobs/tasks (see ionos-blueprints-light/inc/blueprints/jobs/set_option.php as an example job).

Provided Job JSON is valided against job specific JSON Schema (ionos-blueprints-light/inc/blueprints/jobs/set_option.schema.json) to verify the integrity of the job description.

All code is self contained and has only WordPress as dependency.

## Current state

**Experimental 🥸**

## Features

- WordPress plugin : jobs can be injected via WordPress options

- a standalone mini service (see ./standalone) can be runned using PHP built-in Webserver to provision the WordPress instance : `pnpm apiserver`

  - you can inject jobs to execute by a simple `curl` statement against the standalone service

- jobs will be validated against JSON Schema for validity when enqueued

- a job implementation will consist of minimal footprint

## Demo

[![Video](https://img.youtube.com/vi/UIRmfr2kZuY/maxresdefault.jpg)](https://www.youtube.com/watch?v=UIRmfr2kZuY)

The Video shows how provision a wordpress instance using a ultra lightweight PHP service.

## Prerequisities

- `Docker`
- `pnpm`

