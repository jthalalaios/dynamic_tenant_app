FROM node:22.23.4-alpine3.24

WORKDIR /app

COPY vue3-frontend/package*.json ./
RUN npm install

COPY vue3-frontend/. .

EXPOSE 5173

CMD ["npm", "run", "dev"]
