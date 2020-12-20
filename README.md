![.github/workflows/release.yml](https://github.com/mziech/nextcloud-majordomo/workflows/.github/workflows/release.yml/badge.svg)

# Nextcloud Majordomo App

NextCloud app to synchronize user and group information with the Majordomo mailing list manager.

## Building the app
Using NodeJS & NPM:
```
npm install
npm run dist
```

Using Docker:
```
docker build .
```

## Install
Place this app in `nextcloud/apps/majordomo`
