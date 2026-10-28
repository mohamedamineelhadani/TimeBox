export class ApiError extends Error {
  constructor(message, status) {
    super(message);
    this.status = status;
  }
}

/** Small JSON client for /api/tasks. Sends the CSRF token with every request. */
export function createApi({ base, csrf }) {
  async function request(method, path, body) {
    let response;
    try {
      response = await fetch(base + path, {
        method,
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrf,
        },
        body: body === undefined ? undefined : JSON.stringify(body),
      });
    } catch {
      throw new ApiError('Can’t reach the server. Check your connection.', 0);
    }

    const data = await response.json().catch(() => null);

    if (response.status === 401) {
      window.location.assign(`${base}/login`);
      throw new ApiError('Please log in again.', 401);
    }
    if (!response.ok) {
      throw new ApiError(data?.error ?? 'Something went wrong.', response.status);
    }

    return data;
  }

  return {
    create: (payload) => request('POST', '/api/tasks', payload),
    update: (id, payload) => request('PUT', `/api/tasks/${id}`, payload),
    remove: (id) => request('DELETE', `/api/tasks/${id}`),
  };
}
