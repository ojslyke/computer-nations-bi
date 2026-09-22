#!/bin/sh
# Note: no `set -e` here on purpose — a hiccup talking to MySQL should not
# prevent Apache from starting at all; it should just retry on next boot.

# --- Bind Apache to the port Railway assigns via $PORT -----------------
PORT="${PORT:-8080}"
sed -ri "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# --- Wait for the MySQL service to accept connections -------------------
# Railway's MySQL template uses a self-signed cert. The client installed
# here is MariaDB's (Debian's default-mysql-client), whose flag for
# "encrypt, don't verify the certificate chain" is --ssl-verify-server-cert=0
# — NOT --ssl-mode, which this client doesn't recognize at all.
MYSQL_SSL_OPT="--ssl-verify-server-cert=0"
echo "Client: $(mysql --version)"

if [ -n "$MYSQLHOST" ]; then
  echo "Waiting for MySQL at ${MYSQLHOST}:${MYSQLPORT:-3306}..."
  MYSQL_UP=0
  LAST_ERR=""
  for i in $(seq 1 30); do
    LAST_ERR=$(mysql -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" -u "$MYSQLUSER" -p"$MYSQLPASSWORD" $MYSQL_SSL_OPT -e "SELECT 1;" 2>&1 >/dev/null)
    if [ -z "$LAST_ERR" ]; then
      echo "MySQL is up."
      MYSQL_UP=1
      break
    fi
    sleep 2
  done

  if [ "$MYSQL_UP" = "1" ]; then
    # --- First boot only: the schema hasn't been imported yet -----------
    TABLE_COUNT=$(mysql -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" -u "$MYSQLUSER" -p"$MYSQLPASSWORD" $MYSQL_SSL_OPT \
      -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${MYSQLDATABASE}' AND table_name = 'users';" 2>&1)
    echo "Existing 'users' table check: ${TABLE_COUNT}"

    if [ "$TABLE_COUNT" = "0" ]; then
      echo "No schema found — importing database/schema.sql..."
      IMPORT_ERR=$(mysql -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" -u "$MYSQLUSER" -p"$MYSQLPASSWORD" $MYSQL_SSL_OPT "$MYSQLDATABASE" < /var/www/html/database/schema.sql 2>&1 >/dev/null)
      if [ -z "$IMPORT_ERR" ]; then
        echo "Schema imported successfully."
      else
        echo "Schema import failed: ${IMPORT_ERR}"
        echo "The app will show a DB error until this is fixed; it will retry on next restart."
      fi
    else
      echo "Schema already present — skipping import."
    fi
  else
    echo "Could not reach MySQL after 60s. Last error: ${LAST_ERR}"
    echo "Starting Apache anyway; it will show a DB connection error until MySQL is reachable."
  fi
fi

exec "$@"
