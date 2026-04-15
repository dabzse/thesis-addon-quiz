import { writable } from 'svelte/store';

interface UserData {
    ticket: string;
    name: string;
    email: string;
}

export const userData = writable<UserData>({
    ticket: '',
    name: '',
    email: '',
});
