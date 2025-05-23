FROM bref/arm-php-82:2

WORKDIR /var/task

# Load PDO PostgreSQL extension for ARM64 too (seems to be needed)
COPY php/conf.d/pdo_extension.ini.sample /opt/bref/etc/php/conf.d/99-pdo_pgsql.ini

ENTRYPOINT ["/var/task/entrypoint.sh"]