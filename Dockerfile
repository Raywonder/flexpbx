FROM node:18-alpine

WORKDIR /app

RUN apk add --no-cache \
    python3 \
    make \
    g++ \
    sox \
    ffmpeg

COPY package*.json ./
RUN npm ci --only=production

COPY . .

RUN mkdir -p logs recordings voicemail \
    && chmod -R 755 /app

EXPOSE 3000 3443 8088 8089

CMD ["node", "src/index.js"]