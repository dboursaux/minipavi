#!/usr/bin/env bash
# =============================================================================
# start-minipavi.sh — Lance X11, les serveurs PHP MiniPavi et Chromium kiosk
# Exécuté via xinit par minipavi-kiosk.service
# =============================================================================

# Charge la clé Anthropic
[ -f ~/minipavi/.env.minipavi ] && source ~/minipavi/.env.minipavi

export DISPLAY=:0
export XAUTHORITY=/home/damienboursaux/.Xauthority

# Rotation écran (portrait, câble HDMI en bas)
xrandr --output HDMI-1 --rotate left || true
setxkbmap us

# Window manager léger pour la gestion du focus clavier
openbox --sm-disable &

# Lance xbindkeys pour intercepter F10 (toggle écran)
xbindkeys &

# Tue les éventuels résidus des services
pkill -f 'minipavi.php'        || true
pkill -f 'php -S 127.0.0.1:8090' || true
pkill -f 'php -S 127.0.0.1:8091' || true
pkill -f 'chromium'    || true
sleep 1

# Serveur MiniPavi WebSocket (port 8182) + admin (port 8080)
cd ~/minipavi && nohup php ./minipavi.php --cfgfile ./minipavi-base.conf \
    > /tmp/minipavi.log 2>&1 &

# Serveur emulminitel (émulateur HTML Minitel, port 8090)
cd ~/minipavi/emulminitel && nohup php -S 127.0.0.1:8090 \
    > /tmp/minipavi-viewer.log 2>&1 &

# Serveur web MPD (port 8092, accessible Tailscale)
cd ~/minipavi/services && nohup php -S 0.0.0.0:8092 -t . > /tmp/mpd-web.log 2>&1 &

# Serveur services PHP (port 8091)
cd ~/minipavi && nohup php -S 127.0.0.1:8091 -t services \
    > /tmp/minipavi-services.log 2>&1 &

# Attendre que les trois serveurs soient prêts (max 20 s)
for i in {1..40}; do
    ss -lnt | grep -q ':8182' \
    && ss -lnt | grep -q ':8090' \
    && ss -lnt | grep -q ':8091' \
    && break
    sleep 0.5
done

# Profil Chromium jetable pour éviter les popups de session précédente
rm -rf /tmp/chromium-minipavi-profile
mkdir -p /tmp/chromium-minipavi-profile

# Lance Chromium en mode kiosk, pointé sur l'émulateur Minitel
exec unclutter -idle 0 &
    chromium \
    --kiosk --new-window \
    --user-data-dir="/tmp/chromium-minipavi-profile" \
    --no-first-run --no-default-browser-check \
    --disable-extensions --disable-infobars \
    --disable-translate --disable-sync \
    --noerrdialogs \
    'http://127.0.0.1:8090/kiosk.php?gw=ws://127.0.0.1:8182/&url=http%3A%2F%2F127.0.0.1%3A8091%2Fdamien%2F'
