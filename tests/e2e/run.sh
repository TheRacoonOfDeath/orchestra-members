#!/bin/bash
# End-to-end test: installs WordPress (SQLite, PHP built-in server), the latest orchestra-core
# release and a fresh orchestra-members build, then drives both through HTTP like a browser (e2e.py).
#
# Usage: tests/e2e/run.sh
#   E2E_DIR          working directory (default: a new temporary directory, deleted afterwards)
#   E2E_PORT         port for the web server (default: 8765)
#   E2E_CORE_ZIP     orchestra-core zip (default: the latest GitHub release)
#
# Requirements: php 8.3+ with pdo_sqlite, zip and fileinfo; python3; curl; unzip; composer.

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
PORT="${E2E_PORT:-8765}"
BASE_URL="http://localhost:${PORT}"
if [ -n "${E2E_DIR:-}" ]; then
    WORK_DIR="$E2E_DIR"
    mkdir -p "$WORK_DIR"
else
    WORK_DIR="$(mktemp -d)"
    trap 'rm -rf "$WORK_DIR"' EXIT
fi
WP_PATH="$WORK_DIR/wordpress"
PHP=(php -d error_reporting=24575 -d display_errors=stderr -d memory_limit=512M)

echo "Working directory: $WORK_DIR"

# WP-CLI, with PHP deprecation notices of newer PHP versions silenced
if [ ! -f "$WORK_DIR/wp-cli.phar" ]; then
    curl -sSLo "$WORK_DIR/wp-cli.phar" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
fi
cat > "$WORK_DIR/wp" <<WRAPPER
#!/bin/bash
${PHP[*]} "$WORK_DIR/wp-cli.phar" --path="$WP_PATH" "\$@" 2> >(grep -v -E 'Deprecated|^\s*$' >&2)
WRAPPER
chmod +x "$WORK_DIR/wp"
WP="$WORK_DIR/wp"

# Fresh WordPress with SQLite
rm -rf "$WP_PATH"
mkdir -p "$WP_PATH"
"$WP" core download --quiet
curl -sSLo "$WORK_DIR/sqlite.zip" https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip
unzip -q -o "$WORK_DIR/sqlite.zip" -d "$WP_PATH/wp-content/plugins"
sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$WP_PATH/wp-content/plugins/sqlite-database-integration#" \
    -e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" \
    "$WP_PATH/wp-content/plugins/sqlite-database-integration/db.copy" > "$WP_PATH/wp-content/db.php"
"$WP" config create --dbname=wp --dbuser=wp --dbpass=wp --skip-check --quiet --extra-php <<'CONFIG'
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
define('WP_ENVIRONMENT_TYPE', 'local');
CONFIG
"$WP" core install --url="$BASE_URL" --title=Orchestra --admin_user=admin --admin_password=admin \
    --admin_email=admin@example.com --skip-email
"$WP" option update siteurl "$BASE_URL" > /dev/null
"$WP" option update home "$BASE_URL" > /dev/null

# Plugins: orchestra-core release and a fresh orchestra-members build
CORE_ZIP="${E2E_CORE_ZIP:-}"
if [ -z "$CORE_ZIP" ]; then
    CORE_ZIP="$WORK_DIR/orchestra-core.zip"
    CORE_URL=$(curl -sS https://api.github.com/repos/TheRacoonOfDeath/orchestra-core/releases/latest \
        | grep -o '"browser_download_url": *"[^"]*\.zip"' | head -n 1 | sed 's/.*"\(http[^"]*\)"/\1/')
    curl -sSLo "$CORE_ZIP" "$CORE_URL"
fi
unzip -q -o "$CORE_ZIP" -d "$WP_PATH/wp-content/plugins"

"$ROOT/build-plugin.sh" > /dev/null
MEMBERS_ZIP=$(ls -t "$ROOT"/build/orchestra-members-*.zip | head -n 1)
unzip -q -o "$MEMBERS_ZIP" -d "$WP_PATH/wp-content/plugins"

# Records the actions of orchestra-members, for the checks of the public API
mkdir -p "$WP_PATH/wp-content/mu-plugins"
cat > "$WP_PATH/wp-content/mu-plugins/e2e-actions.php" <<'MU'
<?php
foreach (['orchestra_members_member_activated', 'orchestra_members_member_deactivated'] as $hook) {
    add_action($hook, static function (int $userId) use ($hook): void {
        $log = get_option('e2e_actions', []);
        $log[] = [$hook, $userId];
        update_option('e2e_actions', $log);
    });
}
MU

"$WP" plugin activate orchestra-core orchestra-members
"$WP" user create organizer organizer@example.com --role=orchestra_organizer --user_pass=pass --display_name=Olga --quiet
"$WP" user create conductor conductor@example.com --role=orchestra_conductor --user_pass=pass --display_name=Carl --quiet
"$WP" user create member member@example.com --role=orchestra_member --user_pass=pass --display_name=Mia --quiet
"$WP" user create member2 member2@example.com --role=orchestra_member --user_pass=pass --display_name=Max --quiet
"$WP" user create sub sub@example.com --role=subscriber --user_pass=pass --display_name=Sam --quiet

# Web server
"${PHP[@]}" -S "localhost:$PORT" -t "$WP_PATH" > "$WORK_DIR/server.log" 2>&1 &
SERVER_PID=$!
cleanup() {
    kill "$SERVER_PID" 2>/dev/null || true
    wait "$SERVER_PID" 2>/dev/null || true
    if [ -z "${E2E_DIR:-}" ]; then
        rm -rf "$WORK_DIR"
    fi
}
trap cleanup EXIT
for _ in $(seq 1 50); do
    curl -s -o /dev/null "$BASE_URL/" && break
    sleep 0.2
done

status=0
E2E_BASE_URL="$BASE_URL" E2E_WP_CLI="$WP" E2E_WP_PATH="$WP_PATH" \
    python3 "$ROOT/tests/e2e/e2e.py" || status=$?

# Fail on PHP warnings, notices or errors (but not on deprecations of WordPress itself,
# nor failed update checks of WordPress itself when WordPress.org is unreachable)
LOG_FILTER='deprecat|could not establish a secure connection to WordPress.org'
if [ -f "$WP_PATH/wp-content/debug.log" ] \
    && grep -v -i -E "$LOG_FILTER" "$WP_PATH/wp-content/debug.log" | grep -qiE 'PHP (Warning|Notice|Fatal)|database error'; then
    echo "FAIL PHP log contains warnings or errors:"
    grep -v -i -E "$LOG_FILTER" "$WP_PATH/wp-content/debug.log" | grep -iE 'PHP (Warning|Notice|Fatal)|database error' | head -20
    status=1
fi

exit $status
