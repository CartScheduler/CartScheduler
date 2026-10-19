---
paths:
  - 'docker/mariadb/**'
---

# Mariadb

## The testing database is created by an init script that runs once
`docker/mariadb/create-testing-database.sh` creates the `testing` database and grants `sail` access. The MariaDB entrypoint only runs `/docker-entrypoint-initdb.d/*` when the `carts-mariadb` volume is first created, so a bad script fails silently and stays broken.

It must call `/usr/bin/mariadb` — the `mariadb:11.4` image ships no `mysql` binary. `docker/mysql/create-testing-database.sh` is the older copy that calls `mysql` and does not work against this image; docker-compose points at the `mariadb/` one.

Symptom when the grant is missing: the whole suite fails with `SQLSTATE[HY000] [1044] Access denied for user 'sail'@'%' to database 'testing'`. Repair an existing volume without destroying it:

    docker exec cartapp-mysql-1 mariadb -uroot -ppassword \
      -e "CREATE DATABASE IF NOT EXISTS testing; GRANT ALL PRIVILEGES ON \`testing%\`.* TO 'sail'@'%'; FLUSH PRIVILEGES;"
