FROM node:24-alpine AS build

WORKDIR /app
COPY web/package*.json ./
RUN npm ci
COPY web/ .

ARG VITE_API_URL=/api
RUN npm run build

FROM nginxinc/nginx-unprivileged:1.29-alpine

COPY web/nginx.demo.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html

EXPOSE 8080
