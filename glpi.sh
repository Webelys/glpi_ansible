#!/bin/sh

. $(dirname $0)/glpi.ini

php5 ./glpi_export_hosts.php \
    --glpi "${GLPI_SERVER}" \
    --username "${GLPI_ACCOUNT}" \
    --password "${GLPI_PASSWORD}" \
    --ssl \
    $@
