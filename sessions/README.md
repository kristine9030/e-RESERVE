# /sessions — temporary storage

This folder is the prototype's stand-in for a database.

| File | Purpose | MySQL equivalent |
|------|---------|------------------|
| `counters.json` | Auto-incrementing sequences for reservation codes, receipt numbers and ticket numbers. Created automatically on the first reservation. | `AUTO_INCREMENT` columns |

Everything else (the signed-in user, the active reservation, the payment and the
issued ticket) lives in the PHP session under the keys:

```
$_SESSION['user']         → tbl_students row
$_SESSION['reservation']  → tbl_reservations row
$_SESSION['payment']      → tbl_payments row
$_SESSION['ticket']       → tbl_tickets row
$_SESSION['gate_log']     → tbl_checkins rows
```

## When MySQL is added

Delete `counters.json`, drop this folder from the deployment, and replace the
bodies of `next_sequence()`, `create_reservation()`, `complete_payment()` and
the `get_*()` accessors in `includes/functions.php` with prepared statements.
No page markup needs to change.

> The folder must be writable by PHP. If it is not, `next_sequence()` silently
> falls back to session-based counters, so the prototype still runs.
