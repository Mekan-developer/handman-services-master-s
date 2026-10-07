# OTP gateway: Express + Socket.IO bridge between Laravel and the SMS phone.
FROM node:24-alpine

ENV NODE_ENV=production

WORKDIR /var/www/socket-server

# npm registry mirror (NPM_REGISTRY from the root .env). Empty — registry.npmjs.org.
ARG NPM_REGISTRY=""

COPY package.json package-lock.json ./
RUN if [ -n "${NPM_REGISTRY}" ]; then npm config set registry "${NPM_REGISTRY}"; fi \
 && npm ci --omit=dev --no-audit --no-fund \
 && npm cache clean --force

COPY . .

# Fail the build, not the container start, when a dependency is missing.
RUN node -e "require('dotenv'); require('express'); require('socket.io'); console.log('[gateway] dependencies ok')"

USER node

EXPOSE 3000
CMD ["node", "index.js"]
