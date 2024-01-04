FROM php:8.3-cli-alpine3.19

RUN apk add --no-cache --update \
      bash \
      git \
    ; \
    addgroup -g 1000 -S noroot; \
    adduser -u 1000 -S noroot -G noroot --home /home/noroot --shell /bin/bash; \
    # composer
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer; \
    chown -R noroot:noroot /usr/local/bin/composer; \
    # cleanup
    rm -rf /tmp/*; \
    mkdir -p /app /home/noroot/.composer; \
    chown -R noroot:noroot /app /home/noroot;

WORKDIR /app
