# Infrastructure for provisioning a WordPress instance from a separate PHP Process

The goal of this prototype is finding a cheap way to provision 

## Current state

**Experimental 🥸**

## Features

- WordPress plugin : jobs can be injected via WordPress options

- a standalone mini service (see ./standalone) can be runned using PHP built-in Webserver to provision the WordPress instance : `pnpm apiserver`

  - you can inject jobs to execute by a simple `curl` statement against the standalone service

- jobs will be validated against JSON Schema for validity when enqueued

- a job implementation will consist of minimal footprint

## Demo

![Video](blueprints-demo.webm)

![Video](./blueprints-demo.webm)


## Prerequisities

- `Docker`
- `pnpm`

