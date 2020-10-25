FROM node:12

RUN mkdir /work
ADD . /work

WORKDIR /work
RUN npm install && npm run dist
