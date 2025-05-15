FROM bref/arm-php-82:2

# Install aws-lambda-rie to allow local testing
RUN curl -Lo /usr/local/bin/aws-lambda-rie https://github.com/aws/aws-lambda-runtime-interface-emulator/releases/latest/download/aws-lambda-rie && \
    chmod +x /usr/local/bin/aws-lambda-rie

WORKDIR /var/task

# Copy ARM64-specific PHP configuration
COPY php/conf.d/php.ini.arm64 /var/task/php/conf.d/php.ini

ENTRYPOINT ["/var/task/entrypoint.sh"]