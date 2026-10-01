FROM node:22 AS sms-gateway
WORKDIR /var/www/socket-server

# 1. Зеркало npm (NPM_REGISTRY из .env). Пусто — registry.npmjs.org.
ARG NPM_REGISTRY=
RUN if [ -n "$NPM_REGISTRY" ]; then npm config set registry "$NPM_REGISTRY"; fi

COPY package*.json ./
# 3. Устанавливаем только продакшен-зависимости с очисткой npm-кэша
RUN npm install  && npm cache clean --force
COPY . .

# 4. Запускаем от непривилегированного пользователя node для безопасности
USER node

CMD ["node", "index.js"]
