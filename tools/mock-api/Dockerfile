FROM node:22-alpine

WORKDIR /app

COPY --chown=node:node server.js ./server.js
COPY --chown=node:node fixtures ./fixtures

USER node

EXPOSE 3000

CMD ["node", "server.js"]