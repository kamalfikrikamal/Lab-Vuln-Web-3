#!/bin/bash
set -euo pipefail

# The Debian installer only auto-configures the interface it used during
# install (the first/NAT NIC). Configure any other physical interface
# dynamically instead of hardcoding a name, since it can vary
# (enp0s3/enp0s8/etc.) depending on VirtualBox chipset/version.
for IF in $(ls /sys/class/net | grep -Ev '^(lo|docker0|veth|br-)'); do
  if grep -rq "iface $IF" /etc/network/interfaces /etc/network/interfaces.d/ 2>/dev/null; then
    continue
  fi
  cat >> /etc/network/interfaces <<EOF

allow-hotplug $IF
iface $IF inet dhcp
EOF
done

systemctl restart networking || true
