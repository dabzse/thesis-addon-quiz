export interface Category {
    id: number;
    name: string;
    slug: string;
}

export interface Answer {
    id: number;
    answer: string;
    is_correct: number;
    correct_position?: number | null;
    match_answer?: string | null;
}

export interface Question {
    id: number;
    question: string;
    type: 'single' | 'multiple' | 'truefalse' | 'ordering' | 'matching';
    answers: Answer[];
    category?: string;
}

export interface QuizData {
    category: Category;
    questions: Question[];
}

export interface RandomQuizData {
    questions: Question[];
}

export interface QuestionType {
    id: number;
    name: string;
    label: string;
}
