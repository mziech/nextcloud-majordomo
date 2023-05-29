![.github/workflows/release.yml](https://github.com/mziech/nextcloud-majordomo/workflows/.github/workflows/release.yml/badge.svg)

# Nextcloud Majordomo App

**NextCloud app to synchronize user and group information with the Majordomo mailing list manager.**

Using this app you can automatically synchronize your user's to a
[Majordomo mailing list manager](https://en.wikipedia.org/wiki/Majordomo_(software)).
It is possible to configure multiple mailing lists at any list server to include or exclude selected groups, users and other email addresses.

Import of existing mailing list memberships is supported, as well as reviewing changes before you apply them.
At your option, this app will keep your mailing list memberships up-to-date on a daily basis.

This app requires the PHP `imap` module and a dedicated IMAP mailbox to function!

To configure the IMAP server, use the format described in the php manual for [imap_open](https://www.php.net/manual/en/function.imap-open.php). For example to use a secure IMAP server attach `/ssl` to the servername: `imap.example.com/ssl`.

## Caveat: Using Nextcloud inside Docker

If you are using the [Nextcloud Docker image](https://hub.docker.com/_/nextcloud/), this app will not work out of the box, because the image is lacking IMAP support.
In order to use this app, you need to extend the image with IMAP support, e.g. by using the following `Dockerfile`:

```Dockerfile
FROM nextcloud:26

RUN apt-get update && apt-get install -y libc-client-dev libkrb5-dev && rm -r /var/lib/apt/lists/*
RUN docker-php-ext-configure imap --with-kerberos --with-imap-ssl && docker-php-ext-install imap
```

You can then use the extended Docker image in Docker Compose like:
```yaml
  nextcloud:
    build:
      context: ./build
      dockerfile: Dockerfile.nextcloud
```

## Building the app
Using NodeJS & NPM:
```shell
npm install
npm run build
```

Using Docker:
```shell
docker run -it -u $(id -u) --rm -v $HOME/.npm:/.npm -v $(pwd):/work -w /work node:lts sh -c "npm install && npm run build"
```

The majordomo.tar.gz for the app store release can be built with:
```shell
touch majordomo.tar.gz && tar --exclude-ignore=.appignore --transform 's,^\./,majordomo/,' -cvzf majordomo.tar.gz .
```

## Local development

You can use the `watch` script keep building the JS/CSS bundle for local development. Either use NPM:
```shell
npm run watch
```

Or use Docker:
```shell
docker run -it -u $(id -u) --rm -v $HOME/.npm:/.npm -v $(pwd):/work -w /work node:lts sh -c "npm install && npm run watch"
```

## Install
Place this app in `nextcloud/apps/majordomo`
