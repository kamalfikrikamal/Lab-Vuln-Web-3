packer {
  required_version = ">= 1.9.0"
  required_plugins {
    virtualbox = {
      version = ">= 1.0.0"
      source  = "github.com/hashicorp/virtualbox"
    }
  }
}

variable "ssh_username" {
  type    = string
  default = "klaimku"
}

variable "ssh_password" {
  type      = string
  default   = "ChangeMe123!"
  sensitive = true
}

# "bridged" (default, direkomendasikan - lihat docs/OVA_BUILD.md) atau "hostonly".
variable "lab_adapter_type" {
  type    = string
  default = "bridged"
}

# Interface fisik host yang dipakai untuk NIC bridged (hanya dipakai saat build;
# saat OVA di-import di mesin lain, VirtualBox akan minta pilih interface lagi).
variable "bridge_interface" {
  type    = string
  default = "" # kosong = biarkan Packer/VirtualBox pilih default
}

locals {
  lab_nic_vboxmanage = var.lab_adapter_type == "hostonly" ? [
    ["modifyvm", "{{.Name}}", "--nic2", "hostonly"],
    ["modifyvm", "{{.Name}}", "--hostonlyadapter2", "vboxnet0"],
    ["modifyvm", "{{.Name}}", "--nictype2", "82540EM"],
    ] : [
    ["modifyvm", "{{.Name}}", "--nic2", "bridged"],
    ["modifyvm", "{{.Name}}", "--bridgeadapter2", var.bridge_interface],
    ["modifyvm", "{{.Name}}", "--nictype2", "82540EM"],
  ]
}

source "virtualbox-iso" "klaimku" {
  guest_os_type = "Debian_64"
  vm_name       = "klaimku"

  iso_url      = "https://cdimage.debian.org/cdimage/archive/12.8.0/amd64/iso-cd/debian-12.8.0-amd64-netinst.iso"
  iso_checksum = "sha256:04396d12b0f377958a070c38a923c227832fa3b3e18ddc013936ecf492e9fbb3"

  http_directory = "packer/http"

  cpus      = 2
  memory    = 4096
  disk_size = 20480

  boot_wait = "5s"
  # netcfg/choose_interface must be set here on the kernel command line, not
  # just in preseed.cfg: the installer needs to pick + bring up an interface
  # BEFORE it can fetch preseed.cfg over the network (network preseeding's
  # chicken-and-egg problem), so preseed.cfg's own copy of this answer never
  # gets read in time. enp0s3 is the first/NAT NIC (nic1) - the one with
  # internet access for the install itself; enp0s8 (bridged, nic2) is the
  # lab-access adapter configured later by 30-configure-second-nic.sh.
  boot_command = [
    "<esc><wait>",
    "install <wait>",
    " auto=true priority=critical",
    " netcfg/choose_interface=enp0s3",
    " preseed/url=http://{{ .HTTPIP }}:{{ .HTTPPort }}/preseed.cfg",
    " hostname=klaimku domain=local",
    "<enter>"
  ]

  ssh_username = var.ssh_username
  ssh_password = var.ssh_password
  ssh_timeout  = "30m"

  shutdown_command = "echo '${var.ssh_password}' | sudo -S shutdown -P now"

  guest_additions_mode = "disable"

  vboxmanage = concat([
    ["modifyvm", "{{.Name}}", "--memory", "4096"],
    ["modifyvm", "{{.Name}}", "--cpus", "2"],
    ["modifyvm", "{{.Name}}", "--nic1", "nat"],
    ], local.lab_nic_vboxmanage
  )

  format           = "ova"
  output_directory = "packer/output/klaimku"
}

build {
  sources = ["source.virtualbox-iso.klaimku"]

  provisioner "shell" {
    execute_command = "echo '${var.ssh_password}' | sudo -S -E bash '{{ .Path }}'"
    script          = "packer/scripts/00-wait-for-network.sh"
  }

  # A single tarball, not per-directory uploads: Packer's file provisioner
  # was observed silently dropping one level of nesting on multi-level
  # directory uploads against this VirtualBox/SSH communicator combo
  # (docker/web/Dockerfile -> docker/Dockerfile, app/www + app/sql merged
  # flat into app/) - a single-file transfer sidesteps that entirely.
  provisioner "file" {
    source      = "packer/build-context/klaimku-src.tar.gz"
    destination = "/tmp/klaimku-src.tar.gz"
  }

  provisioner "shell" {
    execute_command = "echo '${var.ssh_password}' | sudo -S -E bash '{{ .Path }}'"
    scripts = [
      "packer/scripts/10-layout-app.sh",
      "packer/scripts/15-app-service.sh",
      "packer/scripts/20-install-docker.sh",
      "packer/scripts/30-configure-second-nic.sh",
      "packer/scripts/40-build-and-preseed-app.sh",
      "packer/scripts/50-motd-ip-banner.sh",
      "packer/scripts/90-cleanup-and-zero.sh",
    ]
  }
}
