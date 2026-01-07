<p align="center">
  <img src="docs/images/logo-dark.svg#gh-light-mode-only" alt="Savvy" width="120">
  <img src="docs/images/logo-light.svg#gh-dark-mode-only" alt="Savvy" width="120">
</p>

<h1 align="center">Savvy</h1>

<p align="center">
  Selfhosted expense tracker with full multi-currency support. One container — done.
</p>

<p align="center">
<a href="https://hub.docker.com/r/truenormis/savvy"><img src="https://img.shields.io/badge/DOCKER-truenormis/savvy-2496ED?style=for-the-badge&logo=docker&logoColor=white" alt="Docker"></a>
<img src="https://img.shields.io/github/v/tag/truenormis/savvy?style=for-the-badge&color=orange" alt="Version">
<img src="https://img.shields.io/badge/LICENSE-MIT-green?style=for-the-badge" alt="License">
</p>

<p align="center">
  <img src="docs/images/screenshot.png" alt="Savvy Screenshot" width="800">
</p>

## ⚡ Quick Start
```bash
docker run -d -p 3000:80 -v savvy-data:/data truenormis/savvy:latest
```

Open `localhost:3000`.

## ✨ Features

- **Multi-currency** — any fiat or crypto, transfers between them
- **Auto exchange rates** — currency rates updated automatically via API
- **Recurring transactions** — scheduled payments (daily, weekly, monthly, yearly)
- **Automation rules** — auto-categorize transactions based on conditions
- **Debts** — track loans and borrowings with payment history
- **Budgets** — set limits and track progress
- **Categories & tags** — flexible organization
- **Multi-user** — share with family or team, role-based access (admin/user)
- **Rich analytics** — Sankey diagrams, heatmaps, net worth tracking, expense pace
- **CSV import** — import transactions from bank exports with duplicate detection
- **Backups** — create, restore and download database backups
- **2FA** — two-factor authentication via TOTP (Google Authenticator, etc.)

<p align="center">
  <img src="docs/images/report.png" alt="Savvy Reports" width="800">
</p>

## 🔒 Privacy

Your data stays with you. SQLite stored in `/data`.

## 🛠 Stack

Laravel • SQLite • Docker

## 📄 License

MIT
