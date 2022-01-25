![.github/workflows/release.yml](https://github.com/mziech/nextcloud-majordomo/workflows/.github/workflows/release.yml/badge.svg)

# Nextcloud Majordomo App

**NextCloud app to synchronize user and group information with the Majordomo mailing list manager.**

The app is only meant to be used by a group of trusted users, because the imap and mailinglist configurations are shared between all users with access to the app.

Using this app you can automatically synchronize your user's to a
[Majordomo mailing list manager](https://en.wikipedia.org/wiki/Majordomo_(software)).
It is possible to configure multiple mailing lists at any list server to include or exclude selected groups, users and other email addresses.

Import of existing mailing list memberships is supported, as well as reviewing changes before you apply them.
At your option, this app will keep your mailing list memberships up-to-date on a daily basis.

This app requires the PHP `imap` module and a dedicated IMAP mailbox to function!

To configure the IMAP server, use the format described in the php manual for [imap_open](https://www.php.net/manual/en/function.imap-open.php). For example to use a secure IMAP server attach `/ssl` to the servername: `imap.example.com/ssl`.

## Building the app
Using NodeJS & NPM:
```shell
npm install
npm run build
```

Using Docker:
```shell
docker run -it -u $(id -u) --rm -v $HOME/.npm:/.npm -v $(pwd):/work -w /work node:14 sh -c "npm install && npm run build"
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
docker run -it -u $(id -u) --rm -v $HOME/.npm:/.npm -v $(pwd):/work -w /work node:14 sh -c "npm install && npm run watch"
```

## Install

Place this app in `nextcloud/apps/majordomo`, enable it and limit access to a trusted group.
