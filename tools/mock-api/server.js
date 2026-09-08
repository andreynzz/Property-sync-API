import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';

const port = 3000;
const token = 'demo-token';
const fixtureUrl = new URL('./fixtures/properties.json', import.meta.url);

const sendJson = (response, status, body) => {
  response.writeHead(status, { 'Content-Type': 'application/json; charset=utf-8' });
  response.end(JSON.stringify(body));
};

const server = createServer(async (request, response) => {
  const url = new URL(request.url ?? '/', `http://${request.headers.host}`);

  if (request.method === 'GET' && url.pathname === '/health') {
    sendJson(response, 200, { status: 'ok' });
    return;
  }

  if (request.method !== 'GET' || url.pathname !== '/properties') {
    sendJson(response, 404, { error: 'not_found' });
    return;
  }

  if (request.headers.authorization !== `Bearer ${token}`) {
    sendJson(response, 401, { error: 'unauthorized' });
    return;
  }

  try {
    const properties = JSON.parse(await readFile(fixtureUrl, 'utf8'));
    const requestedPage = Number.parseInt(url.searchParams.get('page') ?? '1', 10);
    const requestedPerPage = Number.parseInt(url.searchParams.get('per_page') ?? '50', 10);
    const page = Number.isFinite(requestedPage) && requestedPage > 0 ? requestedPage : 1;
    const perPage = Number.isFinite(requestedPerPage)
      ? Math.min(Math.max(requestedPerPage, 1), 100)
      : 50;
    const start = (page - 1) * perPage;
    const data = properties.slice(start, start + perPage);
    const totalPages = Math.max(Math.ceil(properties.length / perPage), 1);

    sendJson(response, 200, {
      data,
      meta: {
        page,
        per_page: perPage,
        total: properties.length,
        total_pages: totalPages,
        next_page: page < totalPages ? page + 1 : null,
      },
    });
  } catch {
    sendJson(response, 500, { error: 'fixture_unavailable' });
  }
});

server.listen(port, '0.0.0.0', () => {
  console.log(`Mock property API listening on port ${port}`);
});