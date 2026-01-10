# Einkaufszettel aka Shoppinglist

A shared, offline-first family shopping list with list archives, product reuse, and store-specific category ordering. The app runs as a PWA, keeps working without a network connection, and synchronizes with a server-side SQLite store when online.

## Features

- **List archive & deep links**: Create multiple shopping lists and access each one via a dedicated URL (`/list/:id`). Older lists stay available for reference.
- **Two list modes**: Switch between *Create* and *Shop* modes. Create mode focuses on adding items and organizing them; Shop mode is a clean checklist optimized for in-store use.
- **Reusable product catalog**: Add products once and quickly re-use them via search and suggestions. The catalog grows over time through sync.
- **Structured entry**: Split item input into name, amount, and unit. Common quantities are stored as combined strings (e.g., `small tomatoes (500g)`) for fast selection.
- **Category clustering**: Assign products to categories (e.g., Produce, Meat, Non-food). Lists are grouped by category for fast aisle navigation.
- **Store-specific ordering**: Configure stores and drag categories into the preferred order. Select a store on a list to re-sort clusters.
- **Quantity per list item**: Adjust `2x`, `3x`, etc. directly on the list without changing the product catalog.
- **Admin dashboard**:
  - Manage stores and category ordering
  - Manage units (CRUD)
  - Review and remove products from the full inventory
  - Change UI language and theme
- **Offline-first + sync**:
  - Local persistence in `localStorage` for full offline usage
  - Event queue sync to `/api/sync.php`
  - Snapshot pull from `/api/state.php`
  - Automatic sync when online and on focus/visibility
- **PWA**: Installable app with precached assets and standalone feel.

## Tech Stack

- **Frontend**: Vue 3 + TypeScript, Vite
- **State**: Pinia
- **Routing**: Vue Router
- **Styling**: Tailwind CSS + tailwindcss-animate
- **UI**: shadcn-vue patterns + Radix Vue + @radix-icons/vue
- **PWA**: vite-plugin-pwa (injectManifest)
- **Tests/Linting**: Vitest + ESLint (Vue + TS)
- **Backend**: PHP + SQLite (simple sync endpoints)

## Installation (quick start)

1. Install dependencies
   ```bash
   npm install
   ```
2. Run the dev server
   ```bash
   npm run dev
   ```
3. (Optional) Configure API auth
   - Server: set `API_KEY` or `BASIC_AUTH_USER` / `BASIC_AUTH_PASS`
   - Client: set `VITE_API_KEY` for Bearer auth

## Deployment

The repository ships with a GitHub Actions workflow that builds the frontend and deploys both the `dist/` output and the PHP API directory via SSH + rsync.

Workflow file:
- `.github/workflows/deploy_live.yaml`

Required GitHub Secrets:
- `SSH_KEY`: private deploy key (PEM/OpenSSH format)
- `SSH_HOST`: target server hostname or IP
- `SSH_PORT`: SSH port (e.g., `22`)
- `SSH_USER`: SSH username
- `DEPLOY_PATH_SHOPPINGLIST`: absolute path on the server where the app is deployed
- `VITE_API_KEY`: optional client Bearer token (must match server `API_KEY`)
- `VITE_API_BASE`: optional API base URL if the frontend is configured to use it

Server environment (optional auth):
- `API_KEY`: Bearer token for `/api/*`
- `BASIC_AUTH_USER`: Basic auth username
- `BASIC_AUTH_PASS`: Basic auth password
