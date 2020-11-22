FROM node:12

RUN mkdir /work
WORKDIR /work

ADD package.json /work
ADD package-lock.json /work
RUN npm install

ADD . /work

RUN npm run dist
