# Docker and Database Commands

Run these commands from the project root.

The examples use the local development defaults from `compose.yaml`:

- Database: `accountant_crm`
- User: `accountant`
- Password: `accountant_dev`

If a `.env` file overrides these values, use the values in that file instead.

## Container status

```bash
docker compose ps
```

Shows the application and database containers, their status, and exposed ports.

## Open a database prompt

```bash
docker compose exec db mariadb -uaccountant -p accountant_crm
```

When prompted, enter `accountant_dev` (or the configured database password). Exit the prompt with:

```sql
exit;
```

## List current tables

```bash
docker compose exec -T db mariadb -uaccountant -paccountant_dev accountant_crm -e 'SHOW TABLES;'
```

Current tables:

```text
active_professionals
clients
diary_events
diary_notes
messages
partners
tasks
users
```

## Inspect a table's columns

```bash
docker compose exec -T db mariadb -uaccountant -paccountant_dev accountant_crm -e 'DESCRIBE clients;'
```

## Show all clients

To return every column and row from `clients`:

```sql
SELECT * FROM clients;
```

Run it directly through Docker:

```bash
docker compose exec -T db mariadb -uaccountant -paccountant_dev accountant_crm -e 'SELECT * FROM clients;'
```

For a more readable result ordered by client name, use:

```bash
docker compose exec -T db mariadb -uaccountant -paccountant_dev accountant_crm -e 'SELECT id, name, afm, category, phone, email, created_at FROM clients ORDER BY name;'
```

## Count clients

```bash
docker compose exec -T db mariadb -uaccountant -paccountant_dev accountant_crm -e 'SELECT COUNT(*) AS client_count FROM clients;'
```

## Find clients by name or tax ID (AFM)

Replace `search text` with a name, part of a name, or an AFM.

```bash
docker compose exec -T db mariadb -uaccountant -paccountant_dev accountant_crm -e "SELECT id, name, afm, phone, email FROM clients WHERE name LIKE '%search text%' OR afm LIKE '%search text%' ORDER BY name;"
```

## Run a query from the database prompt

After opening the prompt, run SQL such as:

```sql
SELECT id, name, afm, category, phone, email
FROM clients
ORDER BY name;
```

> The password is included in the one-line examples only for convenient local development. Do not place production passwords in shell history; use the interactive prompt or secure environment variables instead.
