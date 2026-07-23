<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\DTO\ContactData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Services\Contact\ContactService;
use Illuminate\Http\JsonResponse;

final class ContactController extends Controller
{
    public function __invoke(
        StoreContactRequest $request,
        ContactService $contactService,
    ): JsonResponse {
        $contactData = ContactData::fromArray($request->validated());
        $result = $contactService->handle($contactData);

        return response()->json([
            'success' => true,
            'message' => 'Обращение успешно отправлено.',
            'data' => $result->toResponseArray(),
        ], 201);
    }
}
