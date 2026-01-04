<p align="center">
  <picture>
    <source media="(prefers-color-scheme: dark)" srcset="docs/images/logo-dark.svg">
    <source media="(prefers-color-scheme: light)" srcset="docs/images/logo-light.svg">
    <img src="docs/images/logo-light.svg" alt="Savvy" width="120">
  </picture>
</p>

<h1 align="center">Savvy</h1>

<p align="center">
  Selfhosted expense tracker with full multi-currency support. One container — done.
</p>

<p align="center">
<img src="https://img.shields.io/badge/DOCKER-savvy-2496ED?style=for-the-badge&logo=docker&logoColor=white" alt="Docker">
<img src="https://img.shields.io/badge/LICENSE-MIT-green?style=for-the-badge" alt="License">
</p>

## ⚡ Quick Start
```bash
docker run -d -p 3000:80 -v savvy-data:/data savvy/savvy
```

Open `localhost:3000`.

## ✨ Features

- **Multi-currency** — any fiat or crypto, transfers between them
- **Base currency** — choose your own for reports
- **Categories & tags** — flexible organization
- **Budgets** — set and track limits
- **Analytics** — clear visual charts

## 🔒 Privacy

Your data stays with you. SQLite stored in `/data`.

## 🛠 Stack

Laravel • SQLite • Docker

## 📄 License

MIT
