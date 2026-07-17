FROM node:22-alpine

WORKDIR /app
COPY apps/web/package.json apps/web/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY apps/web ./
COPY infrastructure/docker/web-entrypoint.sh /usr/local/bin/web-entrypoint
RUN chmod +x /usr/local/bin/web-entrypoint

EXPOSE 3000
ENTRYPOINT ["/usr/local/bin/web-entrypoint"]
CMD ["npm", "run", "dev", "--", "--hostname", "0.0.0.0"]
