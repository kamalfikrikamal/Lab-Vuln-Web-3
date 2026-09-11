#!/bin/bash
set -euo pipefail

# machine-id: regenerate per clone
truncate -s0 /etc/machine-id
rm -f /var/lib/dbus/machine-id
ln -s /etc/machine-id /var/lib/dbus/machine-id

# apt cache / logs / history
apt-get clean
rm -rf /var/lib/apt/lists/*
find /var/log -type f -exec truncate -s0 {} \;
journalctl --rotate 2>/dev/null || true
rm -rf /var/log/journal/*
rm -f /root/.bash_history /home/*/.bash_history
history -c || true

# free-space zero-fill for a smaller compressed OVA (the exported VMDK is
# stream-optimized/compressed, so zeroed blocks shrink away almost entirely)
dd if=/dev/zero of=/EMPTY bs=1M || true
rm -f /EMPTY
sync

# The lab's intended attack surface is the web app (8084) and the MySQL
# port (3306, published deliberately by docker-compose.yml as part of the
# scenario - see its comment) inside the `web`/`db` containers - there is
# no legitimate reason for the appliance to accept a remote shell on future
# boots. `disable` (without --now) only removes the boot-time enable
# symlinks - it does NOT stop the currently running sshd, so this
# provisioner's own SSH session (and the builder's shutdown_command that
# runs right after it) stay alive. Local console login remains available
# via the VirtualBox VM window for the `klaimku` account if maintainers
# need shell access.
systemctl disable ssh
