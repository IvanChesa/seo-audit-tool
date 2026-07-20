#!/usr/bin/env bash
# Script temporal para probar la API desde WSL (evita problemas de comillas de PowerShell).
# Uso: bash test-audit.sh create   -> crea una auditoría contra ivanchesa.es y devuelve el JSON
#      bash test-audit.sh show ID  -> muestra la auditoría ID
set -e

case "$1" in
  create)
    curl -s -X POST http://localhost/api/audits \
      -H 'Content-Type: application/json' \
      -H 'Accept: application/json' \
      -d '{"url":"https://ivanchesa.es"}'
    ;;
  show)
    curl -s "http://localhost/api/audits/$2" -H 'Accept: application/json'
    ;;
  *)
    echo "uso: $0 create | show ID" >&2
    exit 1
    ;;
esac
