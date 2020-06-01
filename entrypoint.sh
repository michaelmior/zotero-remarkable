#!/bin/sh

set -eu

echo "Zotero user:       ${ZOTERO_USER}"
echo "Zotero collection: ${ZOTERO_COLLECTION}"
echo "WebDAV endpoint:   ${WEBDAV_URL}"
echo "Cron schedule:     ${ZOTERO_REMARKABLE_SCHEDULE}"

echo "${ZOTERO_REMARKABLE_SCHEDULE} php /app/zotero-remarkable.php" | crontab -

if [ "$@" = "register" ] ; then
    echo "Registering at reMarkable with code ${REMARKABLE_CODE}.."
    php vendor/splitbrain/remarkable-api/remarkable.php register ${REMARKABLE_CODE}
    echo "returned token:"
    echo "    REMARKABLE_TOKEN=$(cat vendor/splitbrain/remarkable-api/auth.token)"
elif [ "$@" = "cron" ] ; then
    exec crond -f -l 8
elif [ "$@" = "run" ] ; then
    exec php zotero-remarkable.php
else
    exec "$@"
fi
