#!/bin/bash
set -euo pipefail

until ping -c1 deb.debian.org >/dev/null 2>&1; do
  echo "Waiting for network..."
  sleep 2
done
