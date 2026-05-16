// Mock for $app/paths module in test environment
// Teszt környezet mock a $app/paths modulhoz
export const base = '/quiz';
export const assets = '';
export function resolveRoute(route: string, params: Record<string, string> = {}): string {
    return `${base}${route}`;
}
