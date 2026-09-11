#!/bin/bash
set -euo pipefail

mkdir -p /opt/klaimku
tar xzf /tmp/klaimku-src.tar.gz -C /opt/klaimku
rm -f /tmp/klaimku-src.tar.gz

chown -R root:root /opt/klaimku
