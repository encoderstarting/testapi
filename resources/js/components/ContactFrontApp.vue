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
const healthError = ref('');
const metricsError = ref('');
const loadingHealth = ref(true);
const loadingMetrics = ref(true);
const activeSystemView = ref('health');

const apiLinks = computed(() => [
    { label: 'Health API', href: '/api/health' },
    { label: 'Metrics API', href: '/api/metrics' },
    { label: 'Swagger UI', href: '/api/documentation' },
    { label: 'OpenAPI JSON', href: '/api/openapi.json' },
]);

const systemViews = computed(() => [
    { key: 'health', label: 'Health' },
    { key: 'metrics', label: 'Metrics' },
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

const summaryCards = computed(() => [
    {
        label: 'Health',
        value: loadingHealth.value ? 'Загрузка...' : health.value?.status ?? 'Нет данных',
        meta: `AI: ${health.value?.services?.ai ?? 'unknown'} · Mail: ${health.value?.services?.mail ?? 'unknown'}`,
    },
    {
        label: 'Запросы',
        value: loadingMetrics.value ? 'Загрузка...' : String(metrics.value?.total_requests ?? 0),
        meta: `success: ${metrics.value?.successful_requests ?? 0} · failed: ${metrics.value?.failed_requests ?? 0}`,
    },
    {
        label: 'Режим AI',
        value: result.value ? (result.value.processed_by_ai ? 'AI' : 'fallback') : 'Ожидание',
        meta: result.value ? `priority: ${result.value.priority} · sentiment: ${result.value.sentiment}` : 'Появится после отправки формы',
    },
]);

const healthServices = computed(() => {
    if (!health.value?.services) {
        return [];
    }

    return Object.entries(health.value.services).map(([name, status]) => ({
        name,
        status,
        tone: resolveServiceTone(status),
    }));
});

const metricCards = computed(() => [
    {
        label: 'Всего запросов',
        value: metrics.value?.total_requests ?? 0,
        accent: 'accent',
    },
    {
        label: 'Успешных',
        value: metrics.value?.successful_requests ?? 0,
        accent: 'success',
    },
    {
        label: 'Ошибок',
        value: metrics.value?.failed_requests ?? 0,
        accent: 'danger',
    },
    {
        label: 'AI fallback',
        value: metrics.value?.ai_fallbacks ?? 0,
        accent: 'warning',
    },
]);

const successRate = computed(() => {
    const total = Number(metrics.value?.total_requests ?? 0);
    const successful = Number(metrics.value?.successful_requests ?? 0);

    if (total === 0) {
        return '0%';
    }

    return `${Math.round((successful / total) * 100)}%`;
});

const failureRate = computed(() => {
    const total = Number(metrics.value?.total_requests ?? 0);
    const failed = Number(metrics.value?.failed_requests ?? 0);

    if (total === 0) {
        return '0%';
    }

    return `${Math.round((failed / total) * 100)}%`;
});

const fallbackRate = computed(() => {
    const total = Number(metrics.value?.total_requests ?? 0);
    const fallback = Number(metrics.value?.ai_fallbacks ?? 0);

    if (total === 0) {
        return '0%';
    }

    return `${Math.round((fallback / total) * 100)}%`;
});

const metricsHighlights = computed(() => [
    {
        label: 'Success rate',
        value: successRate.value,
        hint: 'Доля успешно обработанных обращений',
    },
    {
        label: 'Failure rate',
        value: failureRate.value,
        hint: 'Доля обращений с критической ошибкой',
    },
    {
        label: 'Fallback rate',
        value: fallbackRate.value,
        hint: 'Как часто AI заменяется локальным fallback',
    },
]);

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

async function refreshSystemView() {
    if (activeSystemView.value === 'health') {
        await fetchHealth();

        return;
    }

    await fetchMetrics();
}

async function fetchHealth() {
    loadingHealth.value = true;
    healthError.value = '';

    try {
        const response = await fetch('/api/health', {
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            healthError.value = 'Health endpoint временно недоступен.';

            return;
        }

        health.value = await response.json();
    } catch {
        healthError.value = 'Не удалось получить health-статус.';
    } finally {
        loadingHealth.value = false;
    }
}

async function fetchMetrics() {
    loadingMetrics.value = true;
    metricsError.value = '';

    try {
        const response = await fetch('/api/metrics', {
            headers: {
                Accept: 'application/json',
            },
        });

        if (!response.ok) {
            metricsError.value = 'Metrics endpoint временно недоступен.';

            return;
        }

        metrics.value = await response.json();
    } catch {
        metricsError.value = 'Не удалось получить текущие метрики.';
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

function resolveServiceTone(status) {
    if (status === 'available' || status === 'configured') {
        return 'success';
    }

    if (status === 'not_configured') {
        return 'warning';
    }

    return 'neutral';
}

function formatTimestamp(timestamp) {
    if (!timestamp) {
        return 'Нет данных';
    }

    return new Date(timestamp).toLocaleString('ru-RU');
}
</script>

<template>
    <div class="contact-shell">
        <div class="contact-shell__orb contact-shell__orb--amber"></div>
        <div class="contact-shell__orb contact-shell__orb--cyan"></div>

        <main class="contact-layout">
            <section class="contact-hero">
                <p class="eyebrow">Contact AI API</p>
                <h1>Vue frontend для формы, health и metrics.</h1>
                <p class="lead">
                    Корневая страница теперь не только отправляет обращение в `POST /api/contact`,
                    но и даёт отдельный monitoring-экран для `GET /api/health` и `GET /api/metrics`.
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
                    <article v-for="card in summaryCards" :key="card.label" class="status-card">
                        <p class="status-card__label">{{ card.label }}</p>
                        <p class="status-card__value">{{ card.value }}</p>
                        <p class="status-card__meta">{{ card.meta }}</p>
                    </article>
                </div>
            </section>

            <div class="contact-stack">
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
                                Endpoint защищён `throttle:5,1`, а fallback и почтовые ошибки обрабатываются backend.
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

                <section class="system-panel">
                    <div class="panel-top">
                        <div>
                            <p class="panel-top__label">System Frontend</p>
                            <h2>Health и metrics</h2>
                        </div>

                        <button class="ghost-button" type="button" @click="refreshSystemView">
                            Обновить {{ activeSystemView === 'health' ? 'health' : 'metrics' }}
                        </button>
                    </div>

                    <div class="system-tabs" role="tablist" aria-label="System views">
                        <button
                            v-for="view in systemViews"
                            :key="view.key"
                            type="button"
                            :class="['system-tab', { 'system-tab--active': activeSystemView === view.key }]"
                            @click="activeSystemView = view.key"
                        >
                            {{ view.label }}
                        </button>
                    </div>

                    <div v-if="activeSystemView === 'health'" class="system-view">
                        <div class="system-view__intro">
                            <div>
                                <p class="system-view__label">Service status</p>
                                <h3>Детализация `GET /api/health`</h3>
                            </div>

                            <span :class="['tone-badge', `tone-badge--${resolveServiceTone(health?.status)}`]">
                                {{ loadingHealth ? 'Загрузка...' : health?.status ?? 'Нет данных' }}
                            </span>
                        </div>

                        <p class="system-view__meta">
                            Timestamp: {{ loadingHealth ? 'обновляем...' : formatTimestamp(health?.timestamp) }}
                        </p>

                        <div v-if="healthError" class="response-banner response-banner--error">
                            {{ healthError }}
                        </div>

                        <div class="service-grid">
                            <article v-for="service in healthServices" :key="service.name" class="service-card">
                                <div class="service-card__top">
                                    <p class="service-card__name">{{ service.name }}</p>
                                    <span :class="['service-pill', `service-pill--${service.tone}`]">
                                        {{ service.status }}
                                    </span>
                                </div>
                                <p class="service-card__hint">
                                    {{ service.name === 'application' ? 'Laravel runtime' : `${service.name} integration` }}
                                </p>
                            </article>
                        </div>
                    </div>

                    <div v-else class="system-view">
                        <div class="system-view__intro">
                            <div>
                                <p class="system-view__label">Operational metrics</p>
                                <h3>Детализация `GET /api/metrics`</h3>
                            </div>

                            <span class="tone-badge tone-badge--neutral">
                                total {{ loadingMetrics ? '...' : metrics?.total_requests ?? 0 }}
                            </span>
                        </div>

                        <p class="system-view__meta">
                            Метрики считаются backend-сервисом и обновляются после обработки обращения.
                        </p>

                        <div v-if="metricsError" class="response-banner response-banner--error">
                            {{ metricsError }}
                        </div>

                        <div class="metrics-grid">
                            <article
                                v-for="metric in metricCards"
                                :key="metric.label"
                                :class="['metric-card', `metric-card--${metric.accent}`]"
                            >
                                <p class="metric-card__label">{{ metric.label }}</p>
                                <p class="metric-card__value">{{ loadingMetrics ? '...' : metric.value }}</p>
                            </article>
                        </div>

                        <div class="highlights-grid">
                            <article v-for="item in metricsHighlights" :key="item.label" class="highlight-card">
                                <p class="highlight-card__label">{{ item.label }}</p>
                                <p class="highlight-card__value">{{ loadingMetrics ? '...' : item.value }}</p>
                                <p class="highlight-card__hint">{{ item.hint }}</p>
                            </article>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>
</template>
