#!/usr/bin/env pwsh
Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

Write-Host "=== Setting up Cambodia Game Top-Up Platform ===" -ForegroundColor Cyan

if (!(Test-Path backend/.env)) {
  Copy-Item backend/.env.development.example backend/.env
}
if (!(Test-Path backend/database/database.sqlite)) {
  New-Item -ItemType File -Path backend/database/database.sqlite | Out-Null
}

Push-Location backend
composer install --no-interaction
php artisan key:generate --force
php artisan migrate:fresh --seed --force
php artisan test
Pop-Location

pnpm install
pnpm test:frontend
Write-Host "Setup complete! Start backend with 'cd backend && php artisan serve', storefront with 'pnpm dev:storefront', admin with 'pnpm dev:admin'." -ForegroundColor Green
