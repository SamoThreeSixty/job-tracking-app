# Job Tracker

Background Symfony app for tracking ticket-based work blocks through the day, now set up to run on FrankenPHP.

## What it does

- Start a running block with a ticket, job number, and description
- Stop the running block and save it with quarter-hour timing
- Manually adjust saved start and end times
- Store data in SQLite
- Run on FrankenPHP locally or in Docker

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
npm run build
SERVER_NAME=:8000 frankenphp run --config docker/php/Caddyfile
```

## Run with Docker

```bash
npm run build
docker compose up --build app
```

The app is served on [http://localhost:8000](http://localhost:8000).
SQLite data is stored at `var/data/app.db`.

## Linux services

Copy the unit files from `deploy/systemd/` to `/etc/systemd/system/`, then:

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now job-tracker-app.service
```

Update the `WorkingDirectory` in the unit file if your deploy path is not `/opt/job-tracker`.
