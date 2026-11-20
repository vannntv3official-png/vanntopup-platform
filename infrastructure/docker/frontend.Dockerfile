FROM node:22-alpine AS builder

ARG APP_FILTER
ARG APP_DIR

RUN corepack enable && corepack prepare pnpm@12.15.0 --activate

WORKDIR /app
COPY package.json pnpm-workspace.yaml pnpm-lock.yaml ./
COPY packages ./packages
COPY ${APP_DIR} ./${APP_DIR}

RUN pnpm install --frozen-lockfile --filter "${APP_FILTER}..."
RUN pnpm --filter "${APP_FILTER}" build

FROM node:22-alpine AS runner
ARG APP_DIR
WORKDIR /app
ENV NODE_ENV=production

COPY --from=builder /app/${APP_DIR}/.output ./.output

EXPOSE 3000 3001
CMD ["node", ".output/server/index.mjs"]
