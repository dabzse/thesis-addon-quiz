import { describe, expect, it, vi } from 'vitest';
import { fetchQuestionTypes, fetchQuestionsByCategory, fetchRandomQuestions, fetchSettings } from './api';
import { Config } from './config';

describe('api helpers', () => {
    it('fetchQuestionsByCategory uses current year when year is 0', async () => {
        const payload = { questions: [] };
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: vi.fn().mockResolvedValue(payload),
        });
        vi.stubGlobal('fetch', fetchMock);

        await fetchQuestionsByCategory('linux', 5, 0);

        const expectedYear = new Date().getFullYear();
        expect(fetchMock).toHaveBeenCalledWith(
            `${Config.API_URL}/categories/linux/questions?limit=5&year=${expectedYear}`,
            { cache: 'no-store' }
        );
    });

    it('fetchSettings throws when backend responds with error', async () => {
        const fetchMock = vi.fn().mockResolvedValue({ ok: false });
        vi.stubGlobal('fetch', fetchMock);

        await expect(fetchSettings()).rejects.toThrow('Failed to fetch settings');
    });

    it('fetchRandomQuestions uses current year when year is 0', async () => {
        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: vi.fn().mockResolvedValue({ questions: [] }),
        });
        vi.stubGlobal('fetch', fetchMock);

        await fetchRandomQuestions(3, 0);

        expect(fetchMock).toHaveBeenCalledWith(
            `${Config.API_URL}/questions/random?limit=3&year=${new Date().getFullYear()}`,
            { cache: 'no-store' }
        );
    });

    it('fetchQuestionTypes throws when backend responds with error', async () => {
        const fetchMock = vi.fn().mockResolvedValue({ ok: false });
        vi.stubGlobal('fetch', fetchMock);

        await expect(fetchQuestionTypes()).rejects.toThrow('Failed to fetch question types');
    });
});
