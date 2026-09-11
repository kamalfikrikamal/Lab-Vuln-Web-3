#!/bin/bash
set -euo pipefail

cat > /etc/systemd/system/klaimku.service <<'EOF'
[Unit]
Description=KlaimKu lab (docker compose)
Requires=docker.service
After=docker.service network-online.target
Wants=network-online.target

[Service]
Type=oneshot
RemainAfterExit=yes
WorkingDirectory=/opt/klaimku
ExecStart=/usr/bin/docker compose up -d
ExecStop=/usr/bin/docker compose down
TimeoutStartSec=0

[Install]
WantedBy=multi-user.target
EOF

systemctl enable klaimku.service
