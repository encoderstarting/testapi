<h1>Новое обращение</h1>

<p><strong>Имя:</strong> {{ $contactData->name }}</p>
<p><strong>Телефон:</strong> {{ $contactData->phone }}</p>
<p><strong>Email:</strong> {{ $contactData->email }}</p>
<p><strong>Комментарий:</strong> {{ $contactData->comment }}</p>

<h2>AI-анализ</h2>

<p><strong>Категория:</strong> {{ $analysisResult->category }}</p>
<p><strong>Тональность:</strong> {{ $analysisResult->sentiment }}</p>
<p><strong>Приоритет:</strong> {{ $analysisResult->priority }}</p>
<p><strong>Краткое резюме:</strong> {{ $analysisResult->summary }}</p>
<p><strong>Режим обработки:</strong> {{ $analysisResult->processedByAi ? 'AI' : 'fallback' }}</p>
