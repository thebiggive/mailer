FROM thebiggive/php:8.0

# Artifacts are immutable so *never* bother re-checking files - this makes opcache.revalidate_freq irrelevant
# See https://www.scalingphpbook.com/blog/2014/02/14/best-zend-opcache-settings.html
RUN echo 'opcache.validate_timestamps = 0' >> /usr/local/etc/php/conf.d/opcache-ecs.ini

# Install the AWS CLI - needed to load in secrets safely from S3. See https://aws.amazon.com/blogs/security/how-to-manage-secrets-for-amazon-ec2-container-service-based-applications-by-using-amazon-s3-and-docker/
RUN apt-get update -qq && apt-get install -y awscli && \
    rm -rf /var/lib/apt/lists/* /var/cache/apk/*

ADD . /var/www/html

# Ensure Apache can run as www-data and still write to these when the Docker build creates them as root.
RUN chmod 777 /var/www/html/var/cache /var/www/html/var/twig

RUN composer install --no-interaction --quiet --optimize-autoloader --no-dev

EXPOSE 80
