FROM node:24-alpine

ARG USER_ID=1000
ARG GROUP_ID=1000

WORKDIR /app
RUN if [ "${USER_ID}:${GROUP_ID}" != "1000:1000" ]; then \
      deluser node && delgroup node && \
      addgroup -g "${GROUP_ID}" node && \
      adduser -D -u "${USER_ID}" -G node node; \
    fi \
    && chown node:node /app
USER node

COPY --chown=node:node package*.json ./
RUN npm install

EXPOSE 5173

CMD ["npm", "run", "dev", "--", "--host", "0.0.0.0"]
