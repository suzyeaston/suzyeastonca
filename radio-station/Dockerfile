FROM node:22-alpine
WORKDIR /app
COPY --chown=node:node server.mjs listen.html ./
USER node
ENV BIND_HOST=0.0.0.0 PORT=8788
EXPOSE 8788
CMD ["node", "server.mjs"]
