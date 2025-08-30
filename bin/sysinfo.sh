#!/usr/bin/env bash
set -euo pipefail

# --- Température CPU ---
cpu="n/a"
if command -v vcgencmd >/dev/null 2>&1; then
  t=$(vcgencmd measure_temp 2>/dev/null | awk -F= '{print $2}' | tr -d "'")
  [ -n "$t" ] && cpu="$t"
elif [ -f /sys/class/thermal/thermal_zone0/temp ]; then
  t=$(cat /sys/class/thermal/thermal_zone0/temp 2>/dev/null)
  [[ "$t" =~ ^[0-9]+$ ]] && cpu="$(awk -v v="$t" 'BEGIN{printf "%.1f°C", v/1000}')"
fi

# --- RAM ---
ram="n/a"
if command -v free >/dev/null 2>&1; then
  read -r total used < <(free -m | awk 'NR==2{print $2, $3}')
  if [[ -n "$total" && -n "$used" ]]; then
    ram="$(awk -v u="$used" -v t="$total" 'BEGIN{printf "%dMB / %dMB (%.0f%%)", u, t, (u/t)*100}')"
  fi
fi

# --- Disque (/) ---
read -r used total pcent < <(df -hP / | awk 'NR==2{print $3, $2, $5}')
disk="${used} / ${total} (${pcent})"

# --- Uptime ---
uptime="n/a"
if command -v uptime >/dev/null 2>&1; then
  uptime=$(uptime -p 2>/dev/null)
fi

# --- Load average ---
load="n/a"
if [ -r /proc/loadavg ]; then
  load=$(cut -d ' ' -f1-3 /proc/loadavg)
fi

# --- CPU cores ---
cpu_cores="n/a"
if command -v nproc >/dev/null 2>&1; then
  cpu_cores=$(nproc)
fi

# --- IP locale ---
ip_local="n/a"
if command -v hostname >/dev/null 2>&1; then
  ip_local=$(hostname -I | awk '{print $1}')
fi

# --- Services ---
nginx="n/a"
if command -v systemctl >/dev/null 2>&1; then
  nginx=$(systemctl is-active nginx 2>/dev/null || echo "inactive")
fi

# --- OS & Kernel ---
os="n/a"; kernel="n/a"
if [ -r /etc/os-release ]; then
  . /etc/os-release
  os="${PRETTY_NAME:-${NAME:-n/a}}"
fi
if command -v uname >/dev/null 2>&1; then
  kernel="$(uname -r)"
fi

# --- Nombre de processus ---
procs="n/a"
if command -v ps >/dev/null 2>&1; then
  procs=$(ps -e 2>/dev/null | wc -l | awk '{print $1}')
fi

# --- Top CPU ---
top_cpu="n/a"
if command -v ps >/dev/null 2>&1; then
  top_cpu=$(ps -eo pid,comm,%cpu --sort=-%cpu 2>/dev/null | awk 'NR==2{printf "%s:%s:%s%%",$1,$2,$3}')
fi

# --- Top MEM ---
top_mem="n/a"
if command -v ps >/dev/null 2>&1; then
  top_mem=$(ps -eo pid,comm,%mem --sort=-%mem 2>/dev/null | awk 'NR==2{printf "%s:%s:%s%%",$1,$2,$3}')
fi

# --- Disque /var/www ---
disk_www="n/a"
if [ -d /var/www ]; then
  read -r u t p < <(df -hP /var/www | awk 'NR==2{print $3, $2, $5}')
  disk_www="${u} / ${t} (${p})"
fi

# --- PHP CLI ---
php_cli="n/a"
if command -v php >/dev/null 2>&1; then
  php_cli=$(php -r 'echo PHP_VERSION;' 2>/dev/null)
fi

# --- Sockets PHP-FPM disponibles ---
php_fpm_sockets="none"
for s in /run/php/php*-fpm.sock; do
  if [ -S "$s" ]; then
    base="$(basename "$s")"
    if [ "$php_fpm_sockets" = "none" ]; then
      php_fpm_sockets="$base"
    else
      php_fpm_sockets="$php_fpm_sockets, $base"
    fi
  fi
done

# --- Version Nginx ---
nginx_ver="n/a"
if command -v nginx >/dev/null 2>&1; then
  nginx_ver=$(nginx -v 2>&1 | awk -F/ '{print $NF}')
fi

# --- PHP-FPM (états sockets + services pour versions connues) ---
php_fpm_status=""
for v in 8.2 8.3 8.4; do
  sock="/run/php/php${v}-fpm.sock"
  socket_status="non"; service_status="inactif"
  [[ -S "$sock" ]] && socket_status="oui"
  if command -v systemctl >/dev/null 2>&1; then
    systemctl is-active --quiet "php${v}-fpm" && service_status="actif"
  fi
  php_fpm_status+="- PHP ${v} → socket:${socket_status}, service:${service_status}; "
done
# Nettoie le dernier "; "
php_fpm_status="$(printf "%s" "$php_fpm_status" | sed 's/; $//')"

# --- Output ---
echo "cpu_temp=${cpu}"
echo "ram=${ram}"
echo "disk=${disk}"
echo "uptime=${uptime}"
echo "load=${load}"
echo "cpu_cores=${cpu_cores}"
echo "ip_local=${ip_local}"
echo "nginx=${nginx}"
echo "php_fpm_status=${php_fpm_status}"
echo "os=${os}"
echo "kernel=${kernel}"
echo "procs=${procs}"
echo "top_cpu=${top_cpu}"
echo "top_mem=${top_mem}"
echo "disk_www=${disk_www}"
echo "php_cli=${php_cli}"
echo "php_fpm_sockets=${php_fpm_sockets}"
echo "nginx_ver=${nginx_ver}"
echo "boot_id=$(cat /proc/sys/kernel/random/boot_id)"