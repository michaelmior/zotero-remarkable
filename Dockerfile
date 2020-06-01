FROM alpine:latest

RUN apk add --no-cache \
    ca-certificates \
    tzdata \
    php-cli \
    php-iconv \
    php-json \
    php-openssl \
    php-zip \
    gnu-libiconv

WORKDIR /app

COPY composer.* *.php /app/

RUN set -eux ; \
    apk add --no-cache --virtual .build-deps \
        composer git ; \
    composer install --no-dev ; \
    cd /app/vendor/splitbrain/remarkable-api ; composer install ; \
    rm -rf ~/.composer ; \
    apk del .build-deps ; \
    sed -i -e 's|CP437|ASCII|' /app/vendor/splitbrain/php-archive/src/Zip.php
# gnu-libiconv on alpine is built without the --enable-extra-encodings, meaning that CP437 is missing

ENV LD_PRELOAD /usr/lib/preloadable_libiconv.so php
ENV ZOTERO_REMARKABLE_SCHEDULE "*/15 * * * *"
COPY entrypoint.sh /

ENTRYPOINT ["/entrypoint.sh"]
CMD ["cron"]
