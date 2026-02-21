# Cinghy

A lightweight, self-hosted personal finance tracker built in PHP, designed for people who want full control over their financial data without relying on third-party services or subscriptions.

Cinghy uses plain-text **hledger-compatible journal files** as its data format — human-readable, portable, and version-controllable. The web interface provides a clean dashboard on top of them, while keeping the underlying files as the source of truth.

---

## Features

- **Plain-text journals** — data stored in `.journal` files compatible with [hledger](https://hledger.org/) and Ledger CLI
- **Multi-account support** — track bank accounts, cash, investments, credit, PayPal, and more
- **Dashboard widgets** — asset allocation chart, income statement, account tree with rollup balances
- **Recurring transactions** — define rules for automatic monthly/weekly entries with placeholder support (`{{month_name}}`, `{{year}}`, etc.)
- **Built-in file editor** — edit journal files directly from the browser
- **GitHub sync** — optionally back up all journal files to a private GitHub repository after each save, running in background without blocking the UI
- **Multi-user** — per-user data isolation with role-based access (user / superadmin)
- **Auto-detection of journal settings** — currency symbol, decimal separator, indentation width detected automatically via heuristics
- **Multi-language UI** — English, Italian, French, German, Spanish, Portuguese, Dutch, Swedish, Finnish, Polish, Ukrainian

---

## Data Format

Cinghy reads standard hledger journal syntax:

```
2026-01-29 Boss | Salary
  Assets:Bank:Unicredit     1700,00€
  Revenues:Work:Salary     -1700,00€

2026-02-01 Agency | Rent
  Expenses:Basic:Rent        700,00€
  Assets:Bank:Unicredit     -700,00€
```

Postings without an explicit amount are auto-balanced, mirroring hledger behaviour.

---

## Tech Stack

- **PHP 8.1+** — no framework, plain PHP with a lightweight custom router
- **File-based storage** — no database required; data lives in `.journal` files, settings and cache in JSON files
- **Vanilla JS + CSS** — no frontend framework
- **Optional GitHub API** — for automatic journal backup via the GitHub Contents API

---

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/fedeis/cinghy.git
   cd cinghy
   ```

2. Make sure the `users/` and `cache/` directories are writable by the web server:
   ```bash
   mkdir -p users
   chmod -R 755 users
   ```

3. Point your web server document root to the `public/` directory.

4. Open the app in your browser — on first run you'll be prompted to create the first superadmin account.

5. *(Optional)* Upload or create your first `.journal` file from the Files section.

### Requirements

- PHP 8.1 or higher
- `curl` extension enabled
- A web server with FastCGI (nginx + php-fpm recommended) or Apache mod_php

---

## GitHub Sync Setup

To enable automatic backup of your journal files to GitHub:

1. Create a **private GitHub repository**
2. Generate a **Personal Access Token** with `repo` scope
3. In Cinghy settings, enable GitHub sync and enter your token, repo name (`user/repo`), and branch

Sync runs in background after each save — it does not block the UI.

---

## Security Notes

- All journal files are stored outside the web root under `users/{username}/data/`
- Passwords are hashed with bcrypt
- Login is protected against brute force (10 attempts, 15-minute lockout)
- All POST routes are CSRF-protected
- File operations are path-traversal safe
- Journal cache is stored as JSON (not executable PHP)
- GitHub token is never passed through the shell — all API calls use PHP's native curl

---

## License

MIT
