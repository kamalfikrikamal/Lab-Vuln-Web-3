#!/bin/bash
set -euo pipefail

# /etc/issue is shown on the console before login, unlike /etc/motd which
# only appears after an interactive login - and SSH is disabled at the end
# of this build, so the console banner is the only place a participant will
# ever see the lab's IP/URL.
cat > /usr/local/sbin/klaimku-issue.sh <<'EOF'
#!/bin/sh
IP=$(ip -4 -o addr show scope global \
      | awk '{print $2, $4}' \
      | grep -Ev '^(docker0|veth|br-)' \
      | grep -v '10\.0\.2\.15' \
      | awk -F/ '{print $1}' | tail -n1)
{
  echo "Debian GNU/Linux 12 \n \l"
  echo ""
  echo "=== KlaimKu Lab ==="
  if [ -n "$IP" ]; then
    echo "Lab URL: http://$IP:8084"
  else
    echo "No IP yet on the lab network adapter - check VirtualBox network settings."
  fi
  echo "===================="
  echo ""
} > /etc/issue
EOF
chmod +x /usr/local/sbin/klaimku-issue.sh

cat > /etc/systemd/system/klaimku-issue.service <<'EOF'
[Unit]
Description=Refresh /etc/issue with KlaimKu lab IP
After=network-online.target
Wants=network-online.target

[Service]
Type=oneshot
ExecStart=/usr/local/sbin/klaimku-issue.sh

[Install]
WantedBy=multi-user.target
EOF

systemctl enable klaimku-issue.service
