export const Config = {
    API_URL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api',
    APP_JSON: { 'Content-Type': 'application/json' }
};
