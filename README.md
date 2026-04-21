# Job Tracker

Background Symfony app for tracking ticket-based work blocks through the day, now set up to run on FrankenPHP.

## What it does

- Start a running block with a ticket, job number, and description
- Stop the running block and save it with quarter-hour timing
- Manually adjust saved start and end times
- Store data in SQLite
- Run on FrankenPHP locally or in Docker
- Keep a separate frontend watcher service for asset development

## Run locally with FrankenPHP

Install FrankenPHP first. The official docs currently list Homebrew support for macOS and Linux:

```bash
brew install dunglas/frankenphp/frankenphp
```

Then run:

```bash
composer install
npm install
php bin/console doctrine:migrations:migrate --no-interaction
npm run dev
SERVER_NAME=:8000 frankenphp run --config docker/php/Caddyfile
```

## Run with Docker

```bash
docker compose up --build app
docker compose up --build node
```

The app is served on [http://localhost:8000](http://localhost:8000).
SQLite data is stored at `var/data/app.db`.

## Why FrankenPHP here

- It runs Symfony directly instead of using PHP's built-in web server
- It uses a Caddy/FrankenPHP config that matches Symfony's current guidance
- It keeps the project closer to a future self-contained local app/binary path

## Linux services

Copy the unit files from `deploy/systemd/` to `/etc/systemd/system/`, then:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now job-tracker-app.service
sudo systemctl enable --now job-tracker-frontend.service
```

Update the `WorkingDirectory` in each unit file if your deploy path is not `/opt/job-tracker`.
