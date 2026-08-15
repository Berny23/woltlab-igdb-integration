#!/usr/bin/env bash
# Copies the contents of a downloaded WoltLab Suite package into the running
# php container's document root (woltlab volume).
#
# Usage:
#   ./setup-woltlab.sh /path/to/woltlab-suite-*.zip
#
# Get package from: https://www.woltlab.com/en/woltlab-suite-download/
# Stack must be running first: podman compose up -d
set -euo pipefail

cd "$(dirname "$0")"

if [[ $# -ne 1 || ! -f "$1" ]]; then
    echo "Usage: $0 /path/to/woltlab-suite-*.zip" >&2
    exit 1
fi

CID=$(podman ps -q \
    --filter label=com.docker.compose.project=woltlab-dev \
    --filter label=com.docker.compose.service=php)
if [[ -z "$CID" ]]; then
    echo "The php container is not running. Start the stack first: podman compose up -d" >&2
    exit 1
fi

TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT

unzip -q "$1" -d "$TMP"

# WoltLab packages contain an "upload/" directory whose contents belong in the web root
SRC="$TMP"
[[ -d "$TMP/upload" ]] && SRC="$TMP/upload"

podman cp "$SRC/." "$CID:/var/www/html/"
podman exec "$CID" chown -R www-data:www-data /var/www/html

echo "Done. Open http://localhost/install.php to run the installer."
echo "Database settings: host=db, user=woltlab, password=woltlab, database=woltlab"