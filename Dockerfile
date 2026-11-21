FROM php:8.3-cli

WORKDIR /app

COPY . /app

RUN mkdir -p /app/database /app/config && chmod -R 777 /app/database /app/config

EXPOSE 8000

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8000} -t /app"]
