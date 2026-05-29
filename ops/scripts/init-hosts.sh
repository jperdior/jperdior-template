#!/bin/sh
# Adds project .localhost domains to /etc/hosts for local dev.
# On macOS 12+, *.localhost resolves automatically via mDNSResponder;
# this script is primarily needed on Linux.
# Safe to run multiple times (idempotent — checks for the marker line).

set -e

HOSTS_FILE=/etc/hosts
MARKER="# jperdior-template local dev"
DOMAINS="api.localhost web.localhost admin.localhost"

if grep -qF "$MARKER" "$HOSTS_FILE" 2>/dev/null; then
  echo "Hosts entries already present — nothing to do."
  exit 0
fi

echo "Adding $DOMAINS to $HOSTS_FILE (requires sudo)..."
printf '\n%s\n127.0.0.1 %s\n' "$MARKER" "$DOMAINS" \
  | sudo tee -a "$HOSTS_FILE" > /dev/null

echo "Done. Entries added:"
grep -A1 "$MARKER" "$HOSTS_FILE"
