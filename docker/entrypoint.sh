#!/bin/sh
# Note: no `set -e` here on purpose — a hiccup talking to MySQL should not
# prevent Apache from starting at all; it should just retry on next boot.

# --- Bind Apache to the port Railway assigns via $PORT -----------------
PORT="${PORT:-8080}"
sed -ri "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Force mpm_prefork as the only enabled MPM every boot, regardless of
# whatever the image's mods-enabled state happens to be — a build-time fix
# for this kept getting silently undone, so do it fresh here instead.
rm -f /etc/apache2/mods-enabled/mpm_event.conf /etc/apache2/mods-enabled/mpm_event.load \
      /etc/apache2/mods-enabled/mpm_worker.conf /etc/apache2/mods-enabled/mpm_worker.load
ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf
ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load

# --- Wait for the MySQL service to accept connections -------------------
# Railway's MySQL template uses a self-signed cert. The client installed
# here is MariaDB's (Debian's default-mysql-client), whose flag for
# "encrypt, don't verify the certificate chain" is --ssl-verify-server-cert=0
# — NOT --ssl-mode, which this client doesn't recognize at all.
MYSQL_SSL_OPT="--ssl-verify-server-cert=0"

# Password via MYSQL_PWD (an env var only this process and its children can
# read) rather than -p on the command line, which would be visible to
# anyone who can list processes in this container (`ps aux`) while it runs.
export MYSQL_PWD="$MYSQLPASSWORD"

if [ -n "$MYSQLHOST" ]; then
  echo "Waiting for MySQL at ${MYSQLHOST}:${MYSQLPORT:-3306}..."
  MYSQL_UP=0
  LAST_ERR=""
  for i in $(seq 1 30); do
    LAST_ERR=$(mysql -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" -u "$MYSQLUSER" $MYSQL_SSL_OPT -e "SELECT 1;" 2>&1 >/dev/null)
    if [ -z "$LAST_ERR" ]; then
      echo "MySQL is up."
      MYSQL_UP=1
      break
    fi
    sleep 2
  done

  if [ "$MYSQL_UP" = "1" ]; then
    # --- First boot only: the schema hasn't been imported yet -----------
    TABLE_COUNT=$(mysql -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" -u "$MYSQLUSER" $MYSQL_SSL_OPT \
      -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${MYSQLDATABASE}' AND table_name = 'users';" 2>&1)
    echo "Existing 'users' table check: ${TABLE_COUNT}"

    if [ "$TABLE_COUNT" = "0" ]; then
      echo "No schema found — importing database/schema.sql..."
      IMPORT_ERR=$(mysql -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" -u "$MYSQLUSER" $MYSQL_SSL_OPT "$MYSQLDATABASE" < /var/www/html/database/schema.sql 2>&1 >/dev/null)
      if [ -z "$IMPORT_ERR" ]; then
        echo "Schema imported successfully."
      else
        echo "Schema import failed: ${IMPORT_ERR}"
        echo "The app will show a DB error until this is fixed; it will retry on next restart."
      fi
    else
      echo "Schema already present — skipping full import."
    fi

    # Always apply every "idempotent-safe" migration too, even on an
    # existing database — each is written to be safe to re-run (IF NOT
    # EXISTS / information_schema guards throughout), so this is how an
    # existing production database picks up newer tables/columns/indexes
    # without a separate manual migration step on every deploy. Only
    # migrations confirmed safe to repeat are listed here — older ones
    # include one-time data transforms that were never audited for that.
    # Add each new migration to the END of this list as it's created.
    for IDEMPOTENT_MIGRATION in migrate_v7_to_v8.sql migrate_v8_to_v9.sql; do
      MIGRATION_PATH="/var/www/html/database/${IDEMPOTENT_MIGRATION}"
      if [ -f "$MIGRATION_PATH" ]; then
        MIGRATION_ERR=$(mysql -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" -u "$MYSQLUSER" $MYSQL_SSL_OPT "$MYSQLDATABASE" < "$MIGRATION_PATH" 2>&1 >/dev/null)
        if [ -z "$MIGRATION_ERR" ]; then
          echo "Applied migration (${IDEMPOTENT_MIGRATION})."
        else
          echo "Migration ${IDEMPOTENT_MIGRATION} had an issue: ${MIGRATION_ERR}"
        fi
      fi
    done
  else
    echo "Could not reach MySQL after 60s. Last error: ${LAST_ERR}"
    echo "Starting Apache anyway; it will show a DB connection error until MySQL is reachable."
  fi
fi

exec "$@"
