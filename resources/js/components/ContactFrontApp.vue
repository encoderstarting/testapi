<script setup>
import { computed, onMounted, reactive, ref } from 'vue';

const form = reactive({
    name: '',
    phone: '',
    email: '',
    comment: '',
});

const fieldErrors = reactive({
    name: [],
    phone: [],
    email: [],
    comment: [],
});

const submitState = ref('idle');
const submitMessage = ref('');
const result = ref(null);
const health = ref(null);
const metrics = ref(null);
const loadingHealth = ref(true);
const loadingMetrics = ref(true);

const apiLinks = computed(() => [
    { label: 'Health', href: '/api/health' },
    { label: 'Metrics', href: '/api/metrics' },
    { label: 'Swagger', href: '/api/documentation' },
    { label: 'OpenAPI JSON', href: '/api/openapi.json' },
]);

const statusTone = computed(() => {
    if (submitState.value === 'success') return 'success';
    if (submitState.value === 'error') return 'error';
    if (submitState.value === 'submitting') return 'pending';

    return 'neutral';
});

const aiModeLabel = computed(() => {
    if (!result.value) return 'Ожидается отправка';

    return result.value.processed_by_ai ? 'Проанализировано AI' : 'Использован fallback';
});

onMounted(async () => {
    await Promise.all([
        fetchHealth(),
        fetchMetrics(),
    ]);
});

async function submitForm() {
    clearErrors();
    submitState.value = 'submitting';
    submitMessage.value = 'Отправляем обращение и ждём ответ API.';
    result.value = null;

    try {
        const response = await fetch('/api/contact', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            body: JSON.stringify(form),
        });

        const payload = await response.json();

        if (response.status === 422) {
            applyErrors(payload.errors ?? {});
            submitState.value = 'error';
            submitMessage.value = payload.message ?? 'Проверьте корректность полей.';

            return;
        }

        if (!response.ok) {
            submitState.value = 'error';
            submitMessage.value = payload.message ?? 'Сервис временно недоступен.';

            await fetchMetrics();

            return;
        }

        result.value = payload.data;
        submitState.value = 'success';
        submitMessage.value = payload.message ?? 'Обращение отправлено.';
        resetForm();

        await Promise.all([
            fetchHealth(),
            fetchMetrics(),
        ]);
    } catch {
        submitState.value = 'error';
        submitMessage.value = 'Не удалось связаться с API. Проверьте, что приложение запущено.';
    }
}

async function fetchHealth() {
    loadingHealth.value = true;

    try {
        const response = await fetch('/api/health', {
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            return;
        }

        health.value = await response.json();
    } finally {
        loadingHealth.value = false;
    }
}

async function fetchMetrics() {
    loadingMetrics.value = true;

    try {
        const response = await fetch('/api/metrics', {
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            return;
        }

        metrics.value = await response.json();
    } finally {
        loadingMetrics.value = false;
    }
}

function resetForm() {
    form.name = '';
    form.phone = '';
    form.email = '';
    form.comment = '';
}

function clearErrors() {
    fieldErrors.name = [];
    fieldErrors.phone = [];
    fieldErrors.email = [];
    fieldErrors.comment = [];
}

function applyErrors(errors) {
    fieldErrors.name = errors.name ?? [];
    fieldErrors.phone = errors.phone ?? [];
    fieldErrors.email = errors.email ?? [];
    fieldErrors.comment = errors.comment ?? [];
}
</script>

<template>
    <div class="contact-shell">
        <div class="contact-shell__orb contact-shell__orb--amber"></div>
        <div class="contact-shell__orb contact-shell__orb--cyan"></div>

        <main class="contact-layout">
            <section class="contact-hero">
                <p class="eyebrow">Contact AI API</p>
                <h1>Фронт на Vue для готового backend формы обратной связи.</h1>
                <p class="lead">
                    Интерфейс работает с уже реализованными endpoint, показывает статус AI-обработки,
                    ошибки валидации, состояние сервиса и текущие метрики.
                </p>

                <div class="quick-links">
                    <a
                        v-for="link in apiLinks"
                        :key="link.href"
                        :href="link.href"
                        class="quick-link"
                        target="_blank"
                        rel="noreferrer"
                    >
                        {{ link.label }}
                    </a>
                </div>

                <div class="status-grid">
                    <article class="status-card">
                        <p class="status-card__label">Health</p>
                        <p class="status-card__value">
                            {{ loadingHealth ? 'Загрузка...' : health?.status ?? 'Нет данных' }}
                        </p>
                        <p class="status-card__meta">
                            AI: {{ health?.services?.ai ?? 'unknown' }} · Mail: {{ health?.services?.mail ?? 'unknown' }}
                        </p>
                    </article>

                    <article class="status-card">
                        <p class="status-card__label">Метрики</p>
                        <p class="status-card__value">
                            {{ loadingMetrics ? 'Загрузка...' : metrics?.total_requests ?? 0 }}
                        </p>
                        <p class="status-card__meta">
                            success: {{ metrics?.successful_requests ?? 0 }} · failed: {{ metrics?.failed_requests ?? 0 }}
                        </p>
                    </article>
                </div>
            </section>

            <section class="contact-panel">
                <div class="panel-top">
                    <div>
                        <p class="panel-top__label">Форма обращения</p>
                        <h2>Отправить запрос в API</h2>
                    </div>

                    <span :class="['tone-badge', `tone-badge--${statusTone}`]">
                        {{ aiModeLabel }}
                    </span>
                </div>

                <form class="contact-form" @submit.prevent="submitForm">
                    <label class="field">
                        <span>Имя</span>
                        <input v-model.trim="form.name" type="text" name="name" placeholder="Никита" />
                        <small v-if="fieldErrors.name.length" class="field__error">{{ fieldErrors.name[0] }}</small>
                    </label>

                    <div class="field-row">
                        <label class="field">
                            <span>Телефон</span>
                            <input v-model.trim="form.phone" type="text" name="phone" placeholder="+79999999999" />
                            <small v-if="fieldErrors.phone.length" class="field__error">{{ fieldErrors.phone[0] }}</small>
                        </label>

                        <label class="field">
                            <span>Email</span>
                            <input v-model.trim="form.email" type="email" name="email" placeholder="nikita@example.com" />
                            <small v-if="fieldErrors.email.length" class="field__error">{{ fieldErrors.email[0] }}</small>
                        </label>
                    </div>

                    <label class="field">
                        <span>Комментарий</span>
                        <textarea
                            v-model.trim="form.comment"
                            name="comment"
                            rows="6"
                            placeholder="Хочу обсудить разработку интернет-магазина"
                        ></textarea>
                        <small v-if="fieldErrors.comment.length" class="field__error">{{ fieldErrors.comment[0] }}</small>
                    </label>

                    <div class="form-footer">
                        <button class="submit-button" type="submit" :disabled="submitState === 'submitting'">
                            {{ submitState === 'submitting' ? 'Отправка...' : 'Отправить обращение' }}
                        </button>

                        <p class="submit-hint">
                            Endpoint защищён `throttle:5,1`, ошибки и fallback уже обрабатываются backend.
                        </p>
                    </div>
                </form>

                <div v-if="submitMessage" :class="['response-banner', `response-banner--${statusTone}`]">
                    {{ submitMessage }}
                </div>

                <div v-if="result" class="result-card">
                    <div class="result-card__header">
                        <h3>Ответ backend</h3>
                        <span>{{ result.processed_by_ai ? 'AI' : 'fallback' }}</span>
                    </div>

                    <dl class="result-grid">
                        <div>
                            <dt>Категория</dt>
                            <dd>{{ result.category }}</dd>
                        </div>
                        <div>
                            <dt>Тональность</dt>
                            <dd>{{ result.sentiment }}</dd>
                        </div>
                        <div>
                            <dt>Приоритет</dt>
                            <dd>{{ result.priority }}</dd>
                        </div>
                        <div>
                            <dt>Режим</dt>
                            <dd>{{ result.processed_by_ai ? 'processed_by_ai=true' : 'processed_by_ai=false' }}</dd>
                        </div>
                    </dl>
                </div>
            </section>
        </main>
    </div>
</template>
