#!/bin/bash
set -e

# Detect local IP
IP=$(hostname -I | awk '{print $1}')
if [ -z "$IP" ]; then
    IP="192.168.1.117"
fi

echo "=========================================================="
echo " Generating SSL Certificate for IP: $IP"
echo "=========================================================="

mkdir -p ssl

openssl req -x509 -nodes -days 3650 -newkey rsa:2048 \
  -keyout ssl/key.pem \
  -out ssl/cert.pem \
  -subj "/CN=$IP" \
  -addext "subjectAltName=IP:$IP,DNS:localhost,IP:127.0.0.1"

chmod 600 ssl/key.pem
chmod 644 ssl/cert.pem

echo "SSL Certificate generated successfully in ./ssl/ (cert.pem, key.pem)"
