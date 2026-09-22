#!/bin/sh
set -e

# --- Bind Apache to the port Railway assigns via $PORT -----------------
PORT="${PORT:-8080}"
sed -ri "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# --- Wait for the MySQL service to accept connections -------------------
if [ -n "$MYSQLHOST" ]; then
  echo "Waiting for MySQL at ${MYSQLHOST}:${MYSQLPORT:-3306}..."
  for i in $(seq 1 30); do
    if mysqladmin ping -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" -u "$MYSQLUSER" -p"$MYSQLPASSWORD" --silent 2>/dev/null; then
      echo "MySQL is up."
      break
    fi
    sleep 2
  done

  # --- First boot only: the schema hasn't been imported yet -------------
  TABLE_COUNT=$(mysql -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" -u "$MYSQLUSER" -p"$MYSQLPASSWORD" \
    -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${MYSQLDATABASE}' AND table_name = 'users';" 2>/dev/null || echo "0")

  if [ "$TABLE_COUNT" = "0" ]; then
    echo "No schema found — importing database/schema.sql..."
    mysql -h "$MYSQLHOST" -P "${MYSQLPORT:-3306}" -u "$MYSQLUSER" -p"$MYSQLPASSWORD" "$MYSQLDATABASE" < /var/www/html/database/schema.sql
    echo "Schema imported."
  else
    echo "Schema already present — skipping import."
  fi
fi

exec "$@"
