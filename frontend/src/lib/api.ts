const BASE_URL = 'http://localhost:8000/api';

export async function fetchCategories() {
    const res = await fetch(`${BASE_URL}/categories`);
    if (!res.ok) throw new Error('Failed to fetch categories');
    return res.json();
}

export async function fetchQuestion(id: number) {
    const res = await fetch(`${BASE_URL}/questions/${id}`);
    if (!res.ok) throw new Error('Failed to fetch question');
    return res.json();
}

export async function fetchQuestionsByCategory(categorySlug: string, limit = 10, year = 0) {
    const y = year || new Date().getFullYear();
    const res = await fetch(
        `${BASE_URL}/categories/${categorySlug}/questions?limit=${limit}&year=${y}`,
        { cache: 'no-store' }
    );
    if (!res.ok) throw new Error('Failed to fetch questions');
    return res.json();
}

export async function fetchRandomQuestions(limit = 10, year = 0) {
    const y = year || new Date().getFullYear();
    const res = await fetch(
        `${BASE_URL}/questions/random?limit=${limit}&year=${y}`,
        { cache: 'no-store' }
    );
    if (!res.ok) throw new Error('Failed to fetch questions');
    return res.json();
}

export async function fetchQuestionTypes() {
    const res = await fetch(`${BASE_URL}/question-types`);
    if (!res.ok) throw new Error('Failed to fetch question types');
    return res.json();
}

export async function fetchSettings() {
    const res = await fetch(`${BASE_URL}/settings`, { cache: 'no-store' });
    if (!res.ok) throw new Error('Failed to fetch settings');
    return res.json();
}
